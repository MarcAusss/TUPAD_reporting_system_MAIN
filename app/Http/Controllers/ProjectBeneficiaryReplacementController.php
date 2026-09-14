<?php

namespace App\Http\Controllers;

use App\Enums\BeneficiaryReplacementRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectBeneficiary;
use App\Models\ProjectStatusHistory;
use App\Services\Finance\FinancialCeilingService;
use App\Services\Projects\ProjectBeneficiaryAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectBeneficiaryReplacementController extends Controller
{
    public function __construct(
        private readonly FinancialCeilingService $ceilings,
        private readonly ProjectBeneficiaryAddressService $addressService,
    ) {
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        if ($project->status === ProjectStatus::COMPLETED) {
            abort(403, 'A Completed project can no longer record a beneficiary replacement.');
        }

        $validated = $this->validateRequest($request, $project);

        return DB::transaction(function () use ($request, $project, $validated) {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            if ($lockedProject->status === ProjectStatus::COMPLETED) {
                return back()->withErrors([
                    'reason' => 'This project was completed while your replacement was in progress.',
                ]);
            }

            $user = $request->user();

            /*
            |--------------------------------------------------------------------------
            | Resolve Removed Beneficiaries
            |--------------------------------------------------------------------------
            |
            | Existing roster rows are reused as-is. Beneficiaries who left but were
            | never previously encoded are recorded now, then immediately tagged as
            | removed in the same batch — the roster grows through use rather than a
            | separate management screen.
            |
            */

            $removedBeneficiaries = ProjectBeneficiary::query()
                ->where('project_id', $lockedProject->id)
                ->whereIn('id', $validated['removed_existing'] ?? [])
                ->get();

            foreach ($validated['removed_new'] ?? [] as $entry) {
                $removedBeneficiaries->push(
                    $lockedProject->beneficiaries()->create([
                        ...$this->beneficiaryAttributes($entry),
                        'encoded_by' => $user->id,
                        'remarks' => 'Recorded while processing a beneficiary replacement.',
                    ])
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Create Replacement Beneficiaries
            |--------------------------------------------------------------------------
            */

            $addedBeneficiaries = collect($validated['added'])
                ->map(fn (array $entry) => $lockedProject->beneficiaries()->create([
                    ...$this->beneficiaryAttributes($entry),
                    'encoded_by' => $user->id,
                ]));

            /*
            |--------------------------------------------------------------------------
            | Optional Detail Revisions
            |--------------------------------------------------------------------------
            |
            | Only ever recorded when the underlying value actually changes.
            |
            */

            $insuranceChange = $this->applyInsuranceBeneficiariesChange(
                $lockedProject,
                $validated['insurance_beneficiaries'] ?? null,
                $user->id,
            );

            $addressChanged = false;

            if (! empty($validated['beneficiary_addresses'])) {
                $provinceId = $this->addressService->beneficiaryProvinceId($lockedProject, $user);

                abort_unless(
                    $provinceId !== null,
                    422,
                    'The project does not have a valid province for beneficiary address encoding.'
                );

                $addressChanged = $this->addressService->sync(
                    project: $lockedProject,
                    user: $user,
                    provinceId: $provinceId,
                    submittedProvinceId: $provinceId,
                    addresses: $validated['beneficiary_addresses'],
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Replacement Record
            |--------------------------------------------------------------------------
            */

            $replacement = $lockedProject->beneficiaryReplacements()->create([
                'reason' => trim($validated['reason']),
                'performed_by' => $user->id,
                'performed_at' => now(),

                'insurance_beneficiaries_changed' => $insuranceChange !== null,
                'insurance_beneficiaries_before' => $insuranceChange['before'] ?? null,
                'insurance_beneficiaries_after' => $insuranceChange['after'] ?? null,

                'total_project_cost_changed' => $insuranceChange !== null,
                'total_project_cost_before' => $insuranceChange['cost_before'] ?? null,
                'total_project_cost_after' => $insuranceChange['cost_after'] ?? null,

                'beneficiary_address_changed' => $addressChanged,
            ]);

            foreach ($removedBeneficiaries as $beneficiary) {
                $replacement->members()->create([
                    'beneficiary_id' => $beneficiary->id,
                    'role' => BeneficiaryReplacementRole::REMOVED,
                ]);
            }

            foreach ($addedBeneficiaries as $beneficiary) {
                $replacement->members()->create([
                    'beneficiary_id' => $beneficiary->id,
                    'role' => BeneficiaryReplacementRole::ADDED,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Project Progress History Marker
            |--------------------------------------------------------------------------
            |
            | Keeps the replacement visible inline in the existing Project Status
            | History timeline. The expandable detail is rendered from the
            | replacement record itself.
            |
            */

            $summaryParts = [
                sprintf(
                    'Beneficiary Replacement: %d removed, %d added.',
                    $removedBeneficiaries->count(),
                    $addedBeneficiaries->count(),
                ),
            ];

            if ($insuranceChange !== null) {
                $summaryParts[] = sprintf(
                    'Insurance Beneficiaries %d to %d.',
                    $insuranceChange['before'],
                    $insuranceChange['after'],
                );
            }

            if ($addressChanged) {
                $summaryParts[] = 'Beneficiary address allocation updated.';
            }

            ProjectStatusHistory::create([
                'project_id' => $lockedProject->id,
                'from_status' => $lockedProject->status,
                'to_status' => $lockedProject->status,
                'changed_by' => $user->id,
                'remarks' => implode(' ', $summaryParts),
                'changed_at' => now(),
            ]);

            return redirect()
                ->route('projects.show', [
                    'project' => $lockedProject,
                    'workspace' => 'beneficiaries',
                ])
                ->with('success', 'Beneficiary replacement recorded.');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, Project $project): array
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],

            'removed_existing' => ['nullable', 'array'],
            'removed_existing.*' => [
                'integer',
                'distinct',
                Rule::exists('project_beneficiaries', 'id')
                    ->where('project_id', $project->id),
            ],

            'removed_new' => ['nullable', 'array'],
            'removed_new.*.first_name' => ['required', 'string', 'max:100'],
            'removed_new.*.middle_name' => ['nullable', 'string', 'max:100'],
            'removed_new.*.last_name' => ['required', 'string', 'max:100'],
            'removed_new.*.suffix' => ['nullable', 'string', 'max:30'],
            'removed_new.*.sex' => ['required', Rule::in(['male', 'female'])],

            'added' => ['required', 'array', 'min:1'],
            'added.*.first_name' => ['required', 'string', 'max:100'],
            'added.*.middle_name' => ['nullable', 'string', 'max:100'],
            'added.*.last_name' => ['required', 'string', 'max:100'],
            'added.*.suffix' => ['nullable', 'string', 'max:30'],
            'added.*.sex' => ['required', Rule::in(['male', 'female'])],
            'added.*.is_pwd' => ['nullable', 'boolean'],
            'added.*.is_rebel_returnee' => ['nullable', 'boolean'],

            'insurance_beneficiaries' => [
                'nullable',
                'integer',
                'min:0',
                'max:'.(int) $project->beneficiaries_total,
            ],

            'beneficiary_addresses' => ['nullable', 'array'],
        ]);

        $alreadyReplacedIds = ProjectBeneficiary::query()
            ->whereIn('id', $validated['removed_existing'] ?? [])
            ->whereHas(
                'replacementMemberships',
                fn ($query) => $query->where('role', BeneficiaryReplacementRole::REMOVED),
            )
            ->pluck('id');

        if ($alreadyReplacedIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'removed_existing' => 'One or more selected beneficiaries were already replaced in a previous transaction.',
            ]);
        }

        $removedCount = count($validated['removed_existing'] ?? [])
            + count($validated['removed_new'] ?? []);

        $addedCount = count($validated['added']);

        if ($removedCount === 0) {
            throw ValidationException::withMessages([
                'removed_existing' => 'Select or record at least one beneficiary being replaced.',
            ]);
        }

        if ($removedCount !== $addedCount) {
            throw ValidationException::withMessages([
                'added' => sprintf(
                    'The number of replacement beneficiaries (%d) must match the number being replaced (%d).',
                    $addedCount,
                    $removedCount,
                ),
            ]);
        }

        return $validated;
    }

    /**
     * @param  array{first_name:string,middle_name?:?string,last_name:string,suffix?:?string,sex:string,is_pwd?:?bool,is_rebel_returnee?:?bool}  $entry
     * @return array<string, mixed>
     */
    private function beneficiaryAttributes(array $entry): array
    {
        return [
            'first_name' => trim($entry['first_name']),
            'middle_name' => filled($entry['middle_name'] ?? null) ? trim($entry['middle_name']) : null,
            'last_name' => trim($entry['last_name']),
            'suffix' => filled($entry['suffix'] ?? null) ? trim($entry['suffix']) : null,
            'sex' => $entry['sex'],
            'is_pwd' => (bool) ($entry['is_pwd'] ?? false),
            'is_rebel_returnee' => (bool) ($entry['is_rebel_returnee'] ?? false),
        ];
    }

    /**
     * Recompute Insurance Total / Total Project Cost from a revised
     * Insurance Beneficiaries count, only if it actually differs from the
     * project's current effective value. Returns null when nothing changed.
     *
     * @return array{before:int,after:int,cost_before:float,cost_after:float}|null
     */
    private function applyInsuranceBeneficiariesChange(
        Project $project,
        ?int $newInsuranceBeneficiaries,
        int $userId,
    ): ?array {
        if ($newInsuranceBeneficiaries === null) {
            return null;
        }

        $currentInsuranceBeneficiaries = $project->insurance_beneficiaries
            ?? (int) $project->beneficiaries_total;

        if ($newInsuranceBeneficiaries === $currentInsuranceBeneficiaries) {
            return null;
        }

        $insuranceTotal = round(
            (float) $project->insurance_rate * $newInsuranceBeneficiaries,
            2,
        );

        $costBefore = (float) $project->total_project_cost;

        $costAfter = round(
            (float) $project->wages_total
                + (float) $project->ppe_total
                + $insuranceTotal,
            2,
        );

        if ($project->allocation) {
            $summary = $this->ceilings->allocationProjectSummaryExcludingProject(
                $project->allocation,
                $project,
            );

            $projectCostCents = $this->ceilings->amountToCents($costAfter);
            $availableCents = max(0, $summary['remaining_cents']);

            if ($projectCostCents > $availableCents) {
                throw ValidationException::withMessages([
                    'insurance_beneficiaries' => sprintf(
                        'Revised Total Project Cost of ₱%s exceeds the allocation remaining balance of ₱%s.',
                        number_format($projectCostCents / 100, 2),
                        number_format($availableCents / 100, 2),
                    ),
                ]);
            }
        }

        $project->update([
            'insurance_beneficiaries' => $newInsuranceBeneficiaries,
            'insurance_total' => $insuranceTotal,
            'total_project_cost' => $costAfter,
            'updated_by' => $userId,
        ]);

        return [
            'before' => $currentInsuranceBeneficiaries,
            'after' => $newInsuranceBeneficiaries,
            'cost_before' => $costBefore,
            'cost_after' => $costAfter,
        ];
    }
}
