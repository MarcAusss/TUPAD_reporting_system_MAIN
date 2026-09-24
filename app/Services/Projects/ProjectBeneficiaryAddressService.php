<?php

namespace App\Services\Projects;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectBeneficiaryAddress;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and persists a project's beneficiary-address allocation.
 *
 * Extracted from ProjectBeneficiaryAddressController so the same
 * validation/sync logic can also be invoked from the Beneficiary
 * Replacement workflow's optional "Update Beneficiary Address" step,
 * without duplicating the barangay-allocation rules.
 */
class ProjectBeneficiaryAddressService
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
        private readonly ProjectBarangayPpeDistributionService $ppeDistribution,
    ) {
    }

    public function beneficiaryProvinceId(Project $project, User $user): ?int
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

    /**
     * @param  array<int, array{municipality_id: int, barangays: array<int, array{barangay_id: int, beneficiaries_total: int, beneficiaries_female: int}>}>  $addresses
     * @return bool Whether the stored allocation actually changed.
     */
    public function sync(
        Project $project,
        User $user,
        int $provinceId,
        ?int $submittedProvinceId,
        array $addresses,
    ): bool {
        /*
        |--------------------------------------------------------------------------
        | Raw Input, Kept Separately From The Shape Validator
        |--------------------------------------------------------------------------
        |
        | The shape validator below only declares rules for specific leaf
        | fields (municipality_id, barangay_id, beneficiaries_total/female).
        | Laravel's validated() output for wildcard array rules is rebuilt
        | from only those declared leaf paths, so sibling keys with no rule
        | of their own (the optional PPE-distribution fields) would
        | otherwise be silently dropped. Read those from this untouched
        | copy of the original input instead, keyed the same way the
        | validated array is indexed below.
        |
        */
        $rawAddresses = $addresses;

        $shape = Validator::make(
            ['beneficiary_addresses' => $addresses],
            [
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
            ],
        )->validate();

        $addresses = $shape['beneficiary_addresses'];

        if ($submittedProvinceId !== $provinceId) {
            throw ValidationException::withMessages([
                'province_id' => 'Beneficiary addresses must remain inside the project/coordinator assigned province.',
            ]);
        }

        $municipalityIds = collect($addresses)
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

        foreach ($addresses as $locationIndex => $location) {
            $municipalityId = (int) $location['municipality_id'];

            foreach ($location['barangays'] as $barangayIndex => $barangayInput) {
                $barangayId = (int) $barangayInput['barangay_id'];
                $total = (int) $barangayInput['beneficiaries_total'];
                $female = (int) $barangayInput['beneficiaries_female'];
                $rawBarangayInput = $rawAddresses[$locationIndex]['barangays'][$barangayIndex] ?? [];

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
                    'ppe' => $this->extractPpeDistribution($rawBarangayInput),
                ]);
            }
        }

        $barangays = Barangay::query()
            ->whereIn('id', $rows->pluck('barangay_id')->unique())
            ->get(['id', 'municipality_id'])
            ->keyBy('id');

        foreach ($rows as $row) {
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
                'beneficiary_addresses' => $allocatedTotal > (int) $project->beneficiaries_total
                    ? sprintf(
                        "Allocated total of %s exceeds the project's declared Total Beneficiaries of %s by %s.",
                        number_format($allocatedTotal),
                        number_format((int) $project->beneficiaries_total),
                        number_format($allocatedTotal - (int) $project->beneficiaries_total),
                    )
                    : sprintf(
                        'Beneficiary address allocations must total %s beneficiaries. Current total: %s.',
                        number_format((int) $project->beneficiaries_total),
                        number_format($allocatedTotal),
                    ),
            ]);
        }

        if ($allocatedFemale !== (int) $project->beneficiaries_female) {
            throw ValidationException::withMessages([
                'beneficiary_addresses' => $allocatedFemale > (int) $project->beneficiaries_female
                    ? sprintf(
                        "Allocated female count of %s exceeds the project's declared Female Beneficiaries of %s by %s.",
                        number_format($allocatedFemale),
                        number_format((int) $project->beneficiaries_female),
                        number_format($allocatedFemale - (int) $project->beneficiaries_female),
                    )
                    : sprintf(
                        'Female beneficiary address allocations must total %s. Current total: %s.',
                        number_format((int) $project->beneficiaries_female),
                        number_format($allocatedFemale),
                    ),
            ]);
        }

        $normalize = fn ($collection) => $collection
            ->map(fn (array $row): string => implode('|', [
                $row['barangay_id'],
                $row['beneficiaries_total'],
                $row['beneficiaries_female'],
            ]))
            ->sort()
            ->values()
            ->all();

        $before = $normalize(
            $project->beneficiaryAddresses()
                ->get(['barangay_id', 'beneficiaries_total', 'beneficiaries_female'])
                ->map(fn (ProjectBeneficiaryAddress $address): array => [
                    'barangay_id' => $address->barangay_id,
                    'beneficiaries_total' => $address->beneficiaries_total,
                    'beneficiaries_female' => $address->beneficiaries_female,
                ])
        );

        $after = $normalize($rows);
        $changed = $before !== $after;

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

            $this->ppeDistribution->sync($project, $user, $rows);
        });

        return $changed;
    }

    /**
     * Pull the optional PPE-distribution fields out of one barangay row's
     * raw submitted input. Returns null when none of the PPE fields were
     * submitted at all, which tells ProjectBarangayPpeDistributionService
     * to leave the barangay's existing stored distribution untouched
     * (e.g. the Beneficiary Replacement flow's address step never sends
     * these fields).
     *
     * @param  array<string, mixed>  $barangayInput
     * @return array{hazardous_workers: mixed, complete_set_workers: mixed, ppe_items: array<int|string, mixed>}|null
     */
    private function extractPpeDistribution(array $barangayInput): ?array
    {
        $hasHazardous = array_key_exists('hazardous_workers', $barangayInput);
        $hasCompleteSet = array_key_exists('complete_set_workers', $barangayInput);
        $hasItems = array_key_exists('ppe_items', $barangayInput) && is_array($barangayInput['ppe_items']);

        if (! $hasHazardous && ! $hasCompleteSet && ! $hasItems) {
            return null;
        }

        return [
            'hazardous_workers' => $barangayInput['hazardous_workers'] ?? 0,
            'complete_set_workers' => $barangayInput['complete_set_workers'] ?? null,
            'ppe_items' => $hasItems ? $barangayInput['ppe_items'] : [],
        ];
    }
}
