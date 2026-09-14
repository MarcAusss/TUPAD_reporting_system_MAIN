<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Services\Finance\FinancialCeilingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectDetailController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Always-Editable Descriptive Fields
    |--------------------------------------------------------------------------
    |
    | None of these affect a financial calculation, so they stay editable for
    | as long as the project is not Completed, regardless of whether
    | implementation has already started.
    |
    */

    private const DESCRIPTIVE_FIELDS = [
        'date_received',
        'project_title',
        'nature_of_work',
        'fund_sponsor',
        'partner',
        'project_series',
        'project_series_remarks',
        'tevs_date_verified',
        'tevs_remarks',
        'remarks',
    ];

    /*
    |--------------------------------------------------------------------------
    | Pre-Implementation-Only Fields
    |--------------------------------------------------------------------------
    |
    | These drive the Wages / Insurance / Total Project Cost formula. Once an
    | implementation work period exists, obligations, payments, or ACP
    | liquidations may already be measured against the existing figures, so
    | these stay editable only before implementation has started. Afterward,
    | adjustments go through the guarded Beneficiary Replacement flow, which
    | recomputes and re-validates safely instead.
    |
    */

    private const FINANCIAL_FIELDS = [
        'number_of_days',
        'wage_rate',
        'beneficiaries_total',
        'beneficiaries_female',
        'insurance_rate',
        'insurance_beneficiaries',
    ];

    public function __construct(
        private readonly FinancialCeilingService $ceilings,
    ) {
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        if ($project->status === ProjectStatus::COMPLETED) {
            abort(403, 'A Completed project can no longer be edited.');
        }

        $implementationStarted = $this->implementationStarted($project);

        $validated = $request->validate(
            $this->rulesFor($implementationStarted)
        );

        return DB::transaction(function () use ($request, $project, $validated, $implementationStarted) {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            if ($lockedProject->status === ProjectStatus::COMPLETED) {
                return back()->withErrors([
                    'project_title' => 'This project was completed while your edit was in progress.',
                ]);
            }

            $attributes = Arr::only($validated, self::DESCRIPTIVE_FIELDS);

            $attributes['project_title'] = trim($attributes['project_title']);
            $attributes['nature_of_work'] = trim($attributes['nature_of_work']);
            $attributes['fund_sponsor'] = trim($attributes['fund_sponsor']);
            $attributes['partner'] = trim($attributes['partner']);
            $attributes['project_series'] = trim($attributes['project_series']);
            $attributes['project_series_remarks'] = filled($attributes['project_series_remarks'] ?? null)
                ? trim($attributes['project_series_remarks'])
                : null;
            $attributes['tevs_remarks'] = filled($attributes['tevs_remarks'] ?? null)
                ? trim($attributes['tevs_remarks'])
                : null;
            $attributes['remarks'] = filled($attributes['remarks'] ?? null)
                ? trim($attributes['remarks'])
                : null;

            if (! $implementationStarted) {
                $attributes = array_merge(
                    $attributes,
                    $this->recomputeFinancials($lockedProject, Arr::only($validated, self::FINANCIAL_FIELDS)),
                );
            }

            $attributes['updated_by'] = $request->user()->id;

            $lockedProject->update($attributes);

            $changedFields = array_keys(
                Arr::except($lockedProject->getChanges(), ['updated_at', 'updated_by'])
            );

            if ($changedFields !== []) {
                ProjectStatusHistory::create([
                    'project_id' => $lockedProject->id,
                    'from_status' => $lockedProject->status,
                    'to_status' => $lockedProject->status,
                    'changed_by' => $request->user()->id,
                    'remarks' => 'Project details revised: '.implode(', ', $changedFields).'.',
                    'changed_at' => now(),
                ]);
            }

            return redirect()
                ->route('projects.show', $lockedProject)
                ->with(
                    'success',
                    $changedFields !== []
                        ? 'Project details updated.'
                        : 'No changes were submitted.'
                );
        });
    }

    /**
     * Whether this project already has a recorded implementation work
     * period, under either implementation mode.
     */
    private function implementationStarted(Project $project): bool
    {
        return $project->implementation()->exists()
            || $project->acpCheckRelease()->exists();
    }

    private function rulesFor(bool $implementationStarted): array
    {
        $rules = [
            'date_received' => ['required', 'date'],
            'project_title' => ['required', 'string', 'max:255'],
            'nature_of_work' => ['required', 'string', 'max:3000'],
            'fund_sponsor' => ['required', 'string', 'max:255'],
            'partner' => ['required', 'string', 'max:255'],
            'project_series' => ['required', 'string', 'max:100'],
            'project_series_remarks' => ['nullable', 'string', 'max:3000'],
            'tevs_date_verified' => ['required', 'date'],
            'tevs_remarks' => ['nullable', 'string', 'max:3000'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ];

        if ($implementationStarted) {
            return $rules;
        }

        return $rules + [
            'number_of_days' => ['required', 'integer', 'min:10', 'max:90'],
            'wage_rate' => ['required', 'numeric', 'gt:0'],
            'beneficiaries_total' => ['required', 'integer', 'min:1'],
            'beneficiaries_female' => ['required', 'integer', 'min:0', 'lte:beneficiaries_total'],
            'insurance_rate' => ['required', 'numeric', 'min:0'],
            'insurance_beneficiaries' => ['nullable', 'integer', 'min:0', 'lte:beneficiaries_total'],
        ];
    }

    /**
     * Recompute Wages/Insurance/Total Project Cost from the revised inputs
     * and re-validate against the allocation's remaining budget, excluding
     * this project's own existing cost from what's already "utilized".
     *
     * @param  array<string, mixed>  $financial
     * @return array<string, mixed>
     */
    private function recomputeFinancials(Project $project, array $financial): array
    {
        $numberOfDays = (int) $financial['number_of_days'];
        $wageRate = round((float) $financial['wage_rate'], 2);
        $beneficiariesTotal = (int) $financial['beneficiaries_total'];
        $beneficiariesFemale = (int) $financial['beneficiaries_female'];
        $insuranceRate = round((float) $financial['insurance_rate'], 2);

        $insuranceBeneficiaries = filled($financial['insurance_beneficiaries'] ?? null)
            ? (int) $financial['insurance_beneficiaries']
            : $beneficiariesTotal;

        /*
        |--------------------------------------------------------------------------
        | PPE Items Must Still Fit
        |--------------------------------------------------------------------------
        |
        | Existing PPE line items were validated against the OLD Total
        | Beneficiaries at creation time. Shrinking the total below any
        | recorded PPE beneficiary count would silently invalidate that
        | earlier validation, so block the edit instead.
        |
        */

        $oversizedPpeItem = $project->ppeItems()
            ->where('beneficiary_count', '>', $beneficiariesTotal)
            ->exists();

        if ($oversizedPpeItem) {
            throw ValidationException::withMessages([
                'beneficiaries_total' => 'Total Beneficiaries cannot be reduced below an existing PPE item\'s beneficiary count. Adjust the PPE Requirements first.',
            ]);
        }

        $wagesTotal = round($wageRate * $beneficiariesTotal * $numberOfDays, 2);
        $insuranceTotal = round($insuranceRate * $insuranceBeneficiaries, 2);
        $totalProjectCost = round($wagesTotal + (float) $project->ppe_total + $insuranceTotal, 2);

        if ($project->allocation) {
            $summary = $this->ceilings->allocationProjectSummaryExcludingProject(
                $project->allocation,
                $project,
            );

            $projectCostCents = $this->ceilings->amountToCents($totalProjectCost);
            $availableCents = max(0, $summary['remaining_cents']);

            if ($projectCostCents > $availableCents) {
                throw ValidationException::withMessages([
                    'wage_rate' => sprintf(
                        'Revised Total Project Cost of ₱%s exceeds the allocation remaining balance of ₱%s.',
                        number_format($projectCostCents / 100, 2),
                        number_format($availableCents / 100, 2),
                    ),
                ]);
            }
        }

        return [
            'number_of_days' => $numberOfDays,
            'term' => ProjectTerm::fromDays($numberOfDays),
            'wage_rate' => $wageRate,
            'wages_total' => $wagesTotal,
            'beneficiaries_total' => $beneficiariesTotal,
            'beneficiaries_female' => $beneficiariesFemale,
            'insurance_rate' => $insuranceRate,
            'insurance_beneficiaries' => $insuranceBeneficiaries,
            'insurance_total' => $insuranceTotal,
            'total_project_cost' => $totalProjectCost,
        ];
    }
}
