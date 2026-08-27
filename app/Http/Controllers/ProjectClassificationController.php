<?php

namespace App\Http\Controllers;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Models\Project;
use App\Models\ProjectBeneficiarySector;
use App\Models\ProjectLaborMarketReferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectClassificationController extends Controller
{
    public function show(Project $project): View
    {
        $project->load([
            'allocation.adl',
            'approval',
            'beneficiarySectors.recorder',
            'beneficiarySectors.updater',
            'laborMarketReferrals.recorder',
            'laborMarketReferrals.updater',
        ]);

        return view('projects.classifications.show', [
            'project' => $project,
            'priorityCategories' =>
                BeneficiarySectorCategory::priorityVulnerable(),
            'occupationalCategories' =>
                BeneficiarySectorCategory::occupationalLivelihood(),
            'interventionFocuses' => ProjectInterventionFocus::cases(),
            'laborMarketPrograms' => LaborMarketProgram::cases(),
            'sectorRecords' => $project->beneficiarySectors->keyBy(
                static fn (ProjectBeneficiarySector $sector): string =>
                    $sector->sector_key->value,
            ),
        ]);
    }

    public function updateClassification(
        Request $request,
        Project $project,
    ): RedirectResponse {
        $sectorKeys = array_map(
            static fn (BeneficiarySectorCategory $category): string =>
                $category->value,
            BeneficiarySectorCategory::cases(),
        );

        $rules = [
            'intervention_focus' => [
                'required',
                Rule::enum(ProjectInterventionFocus::class),
            ],
            'sectors' => [
                'required',
                'array:'.implode(',', $sectorKeys),
            ],
        ];

        foreach (BeneficiarySectorCategory::cases() as $category) {
            $prefix = "sectors.{$category->value}";

            $rules[$prefix] = ['required', 'array:total,female'];
            $rules["{$prefix}.total"] = [
                'required',
                'integer',
                'min:0',
            ];
            $rules["{$prefix}.female"] = [
                'required',
                'integer',
                'min:0',
                "lte:{$prefix}.total",
            ];
        }

        $validated = $request->validate($rules, [
            'sectors.*.female.lte' =>
                'A sector female count cannot exceed its total beneficiary count.',
        ]);

        DB::transaction(function () use ($request, $project, $validated): void {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            $userId = (int) $request->user()->id;

            $lockedProject->update([
                'intervention_focus' => $validated['intervention_focus'],
                'updated_by' => $userId,
            ]);

            $existing = ProjectBeneficiarySector::query()
                ->where('project_id', $lockedProject->id)
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    static fn (ProjectBeneficiarySector $sector): string =>
                        $sector->sector_key->value,
                );

            foreach (BeneficiarySectorCategory::cases() as $category) {
                $record = $existing->get($category->value)
                    ?? new ProjectBeneficiarySector([
                        'project_id' => $lockedProject->id,
                        'sector_key' => $category->value,
                        'recorded_by' => $userId,
                    ]);

                $record->fill([
                    'sector_group' => $category->group(),
                    'beneficiaries_total' =>
                        (int) $validated['sectors'][$category->value]['total'],
                    'beneficiaries_female' =>
                        (int) $validated['sectors'][$category->value]['female'],
                    'updated_by' => $userId,
                ]);

                $record->save();
            }
        });

        return redirect()
            ->route('projects.classifications.show', $project)
            ->with(
                'success',
                'Beneficiary classifications and intervention focus saved successfully.'
            );
    }

    public function storeLaborMarketReferral(
        Request $request,
        Project $project,
    ): RedirectResponse {
        $validated = $request->validate([
            'reporting_month' => ['required', 'date_format:Y-m'],
            'program' => [
                'required',
                Rule::enum(LaborMarketProgram::class),
            ],
            'interested_referred_total' => [
                'required',
                'integer',
                'min:0',
            ],
            'interested_referred_female' => [
                'required',
                'integer',
                'min:0',
                'lte:interested_referred_total',
            ],
            'provided_intervention_total' => [
                'required',
                'integer',
                'min:0',
                'lte:interested_referred_total',
            ],
            'provided_intervention_female' => [
                'required',
                'integer',
                'min:0',
                'lte:provided_intervention_total',
            ],
            'amount_released' => [
                'required',
                'regex:/^\d{1,13}(?:\.\d{1,2})?$/',
            ],
            'services_availed' => [
                'required',
                'string',
                'max:5000',
            ],
        ], [
            'interested_referred_female.lte' =>
                'Female beneficiaries referred cannot exceed total beneficiaries referred.',
            'provided_intervention_total.lte' =>
                'Beneficiaries provided with intervention cannot exceed total beneficiaries referred.',
            'provided_intervention_female.lte' =>
                'Females provided with intervention cannot exceed total beneficiaries provided with intervention.',
            'amount_released.regex' =>
                'Amount released must be zero or a positive amount with no more than two decimal places.',
        ]);

        $created = DB::transaction(function () use (
            $request,
            $project,
            $validated,
        ): bool {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            $reportingMonth = $validated['reporting_month'].'-01';
            $userId = (int) $request->user()->id;

            $referral = ProjectLaborMarketReferral::query()
                ->where('project_id', $lockedProject->id)
                ->whereDate('reporting_month', $reportingMonth)
                ->where('program', $validated['program'])
                ->lockForUpdate()
                ->first();

            $created = $referral === null;

            $referral ??= new ProjectLaborMarketReferral([
                'project_id' => $lockedProject->id,
                'reporting_month' => $reportingMonth,
                'program' => $validated['program'],
                'recorded_by' => $userId,
            ]);

            $referral->fill([
                'interested_referred_total' =>
                    (int) $validated['interested_referred_total'],
                'interested_referred_female' =>
                    (int) $validated['interested_referred_female'],
                'provided_intervention_total' =>
                    (int) $validated['provided_intervention_total'],
                'provided_intervention_female' =>
                    (int) $validated['provided_intervention_female'],
                'amount_released' => $validated['amount_released'],
                'services_availed' => trim($validated['services_availed']),
                'updated_by' => $userId,
            ]);

            $referral->save();

            return $created;
        });

        return redirect()
            ->route('projects.classifications.show', $project)
            ->with(
                'success',
                $created
                    ? 'Active Labor Market referral record added successfully.'
                    : 'Active Labor Market referral record updated successfully.'
            );
    }
}
