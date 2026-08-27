<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectEvaluationController extends Controller
{
    public function start(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::ONGOING_PROFILING
        ) {
            abort(
                403,
                'Only legacy Ongoing Profiling projects may be submitted for TSSD Evaluation.'
            );
        }

        $project->setStatusTransitionContext(
            actorId: (int) $request->user()->id,
            remarks: 'Legacy profiling record submitted for TSSD Evaluation.',
        )->update([
            'status' => ProjectStatus::TSSD_EVALUATION,
            'updated_by' => $request->user()->id,
        ]);

        $project->clearStatusTransitionContext();

        return redirect()
            ->route('projects.show', $project)
            ->with(
                'success',
                'Project moved to TSSD Evaluation.'
            );
    }

    public function store(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::TSSD_EVALUATION
        ) {
            abort(
                403,
                'Only projects under TSSD Evaluation may be evaluated.'
            );
        }

        $validated = $request->validate([
            'result' => [
                'required',
                Rule::in([
                    'for_compliance',
                    'for_approval',
                ]),
            ],
            'findings' => [
                'required_if:result,for_compliance',
                'nullable',
                'string',
                'max:5000',
            ],
            'required_documents' => [
                'required_if:result,for_compliance',
                'nullable',
                'string',
                'max:5000',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $isForCompliance =
            $validated['result'] === 'for_compliance';

        $project->evaluations()->create([
            'findings' =>
                $isForCompliance
                    ? trim($validated['findings'])
                    : null,
            'required_documents' =>
                $isForCompliance
                    ? trim($validated['required_documents'])
                    : null,
            'remarks' =>
                $validated['remarks'] ?? null,
            'result' =>
                $validated['result'],
            'evaluated_by' =>
                $request->user()->id,
            'evaluated_at' =>
                now(),
        ]);

        $newStatus =
            $isForCompliance
                ? ProjectStatus::FOR_COMPLIANCE
                : ProjectStatus::FOR_APPROVAL;

        $project->setStatusTransitionContext(
            actorId: (int) $request->user()->id,
            remarks: $isForCompliance
                ? 'TSSD evaluation recorded findings and required compliance documents.'
                : 'TSSD evaluation completed and recommended the project for approval.',
        )->update([
            'status' => $newStatus,
            'updated_by' => $request->user()->id,
        ]);

        $project->clearStatusTransitionContext();

        return redirect()
            ->route('projects.show', $project)
            ->with(
                'success',
                $newStatus === ProjectStatus::FOR_COMPLIANCE
                    ? 'Project moved to For Compliance.'
                    : 'Project moved to For Approval.'
            );
    }

    public function compliance(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::FOR_COMPLIANCE
        ) {
            abort(
                403,
                'Only projects under For Compliance may record compliance.'
            );
        }

        $latestCompliance =
            $project
                ->evaluations()
                ->where(
                    'result',
                    'for_compliance'
                )
                ->latest('evaluated_at')
                ->latest('id')
                ->first();

        if (! $latestCompliance) {
            abort(
                422,
                'No TSSD compliance finding is available for this project.'
            );
        }

        $validated = $request->validate([
            'compliance_date' => [
                'required',
                'date',
                'after_or_equal:'
                    . $latestCompliance
                        ->evaluated_at
                        ->toDateString(),
            ],
        ]);

        return DB::transaction(
            function () use (
                $request,
                $project,
                $validated
            ) {
                $lockedProject =
                    Project::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $project->id
                        );

                if (
                    $lockedProject->status
                    !== ProjectStatus::FOR_COMPLIANCE
                ) {
                    return back()->withErrors([
                        'compliance_date' =>
                            'This project is no longer under For Compliance.',
                    ]);
                }

                $evaluation =
                    $lockedProject
                        ->evaluations()
                        ->where(
                            'result',
                            'for_compliance'
                        )
                        ->latest('evaluated_at')
                        ->latest('id')
                        ->first();

                if (! $evaluation) {
                    abort(
                        422,
                        'No TSSD compliance finding is available for this project.'
                    );
                }

                $complianceDate =
                    Carbon::parse(
                        $validated[
                            'compliance_date'
                        ]
                    )->startOfDay();

                $agingDays =
                    (int) $evaluation
                        ->evaluated_at
                        ->copy()
                        ->startOfDay()
                        ->diffInDays(
                            $complianceDate
                        );

                $evaluation->update([
                    'compliance_date' =>
                        $validated[
                            'compliance_date'
                        ],
                    'complied_by' =>
                        $request->user()->id,
                    'complied_at' =>
                        now(),
                ]);

                $lockedProject->setStatusTransitionContext(
                    actorId: (int) $request->user()->id,
                    remarks: sprintf(
                        'Compliance recorded on %s after %d day(s); project forwarded for approval.',
                        $complianceDate->toDateString(),
                        $agingDays,
                    ),
                )->update([
                    'status' =>
                        ProjectStatus::FOR_APPROVAL,
                    'updated_by' =>
                        $request->user()->id,
                ]);

                $lockedProject->clearStatusTransitionContext();

                return redirect()
                    ->route(
                        'projects.show',
                        $lockedProject
                    )
                    ->with(
                        'success',
                        "Compliance recorded. Project moved to For Approval. Aging: {$agingDays} day(s)."
                    );
            }
        );
    }

    public function resubmit(
        Request $request,
        Project $project
    ): RedirectResponse {
        return $this->compliance(
            $request,
            $project
        );
    }
}
