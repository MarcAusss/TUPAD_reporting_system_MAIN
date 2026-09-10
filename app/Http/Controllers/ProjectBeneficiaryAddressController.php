<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectBeneficiaryAddress;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectBeneficiaryAddressController extends Controller
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function update(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();
        $provinceId = $this->beneficiaryProvinceId($project, $user);

        abort_unless(
            $provinceId !== null,
            422,
            'The project does not have a valid province for beneficiary address encoding.'
        );

        $validated = $request->validate([
            'province_id' => ['required', 'integer', 'exists:provinces,id'],
            'beneficiary_addresses' => ['required', 'array', 'min:1'],
            'beneficiary_addresses.*.municipality_id' => [
                'required',
                'integer',
                'distinct',
                'exists:municipalities,id',
            ],
            'beneficiary_addresses.*.barangays' => ['required', 'array', 'min:1'],
            'beneficiary_addresses.*.barangays.*.barangay_id' => [
                'required',
                'integer',
                'distinct',
                'exists:barangays,id',
            ],
            'beneficiary_addresses.*.barangays.*.beneficiaries_total' => [
                'required',
                'integer',
                'min:0',
            ],
            'beneficiary_addresses.*.barangays.*.beneficiaries_female' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        if ((int) $validated['province_id'] !== $provinceId) {
            throw ValidationException::withMessages([
                'province_id' => 'Beneficiary addresses must remain inside the project/coordinator assigned province.',
            ]);
        }

        $municipalityIds = collect($validated['beneficiary_addresses'])
            ->pluck('municipality_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $municipalities = Municipality::query()
            ->whereIn('id', $municipalityIds)
            ->get(['id', 'province_id'])
            ->keyBy('id');

        if (
            $municipalities->count() !== $municipalityIds->count()
            || $municipalities->contains(
                fn (Municipality $municipality): bool =>
                    (int) $municipality->province_id !== $provinceId
            )
        ) {
            throw ValidationException::withMessages([
                'beneficiary_addresses' => 'Every municipality must belong to the assigned beneficiary province.',
            ]);
        }

        $rows = collect();
        $seenBarangays = [];

        foreach ($validated['beneficiary_addresses'] as $locationIndex => $location) {
            $municipalityId = (int) $location['municipality_id'];

            foreach ($location['barangays'] as $barangayIndex => $barangayInput) {
                $barangayId = (int) $barangayInput['barangay_id'];
                $total = (int) $barangayInput['beneficiaries_total'];
                $female = (int) $barangayInput['beneficiaries_female'];

                if (isset($seenBarangays[$barangayId])) {
                    throw ValidationException::withMessages([
                        "beneficiary_addresses.{$locationIndex}.barangays.{$barangayIndex}.barangay_id" =>
                            'The same barangay cannot be used more than once.',
                    ]);
                }

                $seenBarangays[$barangayId] = true;

                if ($female > $total) {
                    throw ValidationException::withMessages([
                        "beneficiary_addresses.{$locationIndex}.barangays.{$barangayIndex}.beneficiaries_female" =>
                            'Female beneficiaries cannot exceed the total beneficiaries for this barangay.',
                    ]);
                }

                $rows->push([
                    'municipality_id' => $municipalityId,
                    'barangay_id' => $barangayId,
                    'beneficiaries_total' => $total,
                    'beneficiaries_female' => $female,
                ]);
            }
        }

        $barangays = Barangay::query()
            ->whereIn('id', $rows->pluck('barangay_id')->unique())
            ->get(['id', 'municipality_id'])
            ->keyBy('id');

        foreach ($rows as $index => $row) {
            $barangay = $barangays->get($row['barangay_id']);

            if (! $barangay || (int) $barangay->municipality_id !== $row['municipality_id']) {
                throw ValidationException::withMessages([
                    'beneficiary_addresses' => 'A selected barangay does not belong to its selected municipality.',
                ]);
            }
        }

        $allocatedTotal = (int) $rows->sum('beneficiaries_total');
        $allocatedFemale = (int) $rows->sum('beneficiaries_female');

        if ($allocatedTotal !== (int) $project->beneficiaries_total) {
            throw ValidationException::withMessages([
                'beneficiary_addresses' => sprintf(
                    'Beneficiary address allocations must total %s beneficiaries. Current total: %s.',
                    number_format((int) $project->beneficiaries_total),
                    number_format($allocatedTotal),
                ),
            ]);
        }

        if ($allocatedFemale !== (int) $project->beneficiaries_female) {
            throw ValidationException::withMessages([
                'beneficiary_addresses' => sprintf(
                    'Female beneficiary address allocations must total %s. Current total: %s.',
                    number_format((int) $project->beneficiaries_female),
                    number_format($allocatedFemale),
                ),
            ]);
        }

        DB::transaction(function () use ($project, $provinceId, $rows, $user): void {
            ProjectBeneficiaryAddress::query()
                ->where('project_id', $project->id)
                ->delete();

            foreach ($rows as $row) {
                ProjectBeneficiaryAddress::query()->create([
                    'project_id' => $project->id,
                    'province_id' => $provinceId,
                    'municipality_id' => $row['municipality_id'],
                    'barangay_id' => $row['barangay_id'],
                    'beneficiaries_total' => $row['beneficiaries_total'],
                    'beneficiaries_female' => $row['beneficiaries_female'],
                    'encoded_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }
        });

        return redirect(
            route('projects.show', [
                'project' => $project,
                'workspace' => 'beneficiaries',
            ]).'#beneficiary-classification'
        )->with('success', 'Beneficiary address allocation saved successfully.');
    }

    private function beneficiaryProvinceId(Project $project, $user): ?int
    {
        if ($user->isTc()) {
            return $this->provinceAccess->assignedProvinceId($user);
        }

        if ($project->province_id) {
            return (int) $project->province_id;
        }

        $projectProvinceId = $project->projectLocations()
            ->orderBy('sort_order')
            ->value('province_id');

        return $projectProvinceId ? (int) $projectProvinceId : null;
    }
}
