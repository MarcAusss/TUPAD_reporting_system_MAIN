<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ProjectCodeGenerator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectApprovalController extends Controller
{
    public function __construct(
        private readonly ProjectCodeGenerator $projectCodeGenerator,
    ) {
    }

    public function store(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::FOR_APPROVAL
        ) {
            abort(
                403,
                'Only projects with For Approval status may be approved.'
            );
        }

        if ($project->approval()->exists()) {
            return back()->withErrors([
                'approval' =>
                    'This project already has an approval record.',
            ]);
        }

        $validated = $request->validate([
            'approval_date' => [
                'required',
                'date',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        return DB::transaction(function () use ($request, $project, $validated) {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->id);

            if (
                $lockedProject->status
                !== ProjectStatus::FOR_APPROVAL
            ) {
                return back()->withErrors([
                    'approval' =>
                        'This project is no longer available for approval.',
                ]);
            }

            // The Project Code is entirely system-generated on approval —
            // the coordinator never types, selects, or edits it.
            try {
                $projectCode = $this->projectCodeGenerator->generate(
                    $lockedProject,
                    Carbon::parse($validated['approval_date']),
                );
            } catch (RuntimeException $e) {
                return back()->withErrors([
                    'approval' => $e->getMessage(),
                ]);
            }

            $approval = $lockedProject
                ->approval()
                ->create([
                    'approval_date' =>
                        $validated['approval_date'],

                    'project_code' =>
                        $projectCode,

                    'remarks' =>
                        $validated['remarks'] ?? null,

                    'approved_by' =>
                        $request->user()->id,

                    'approved_at' =>
                        now(),
                ]);

            $lockedProject->setStatusTransitionContext(
                actorId: (int) $request->user()->id,
                remarks: sprintf(
                    'Project approved on %s with official Project Code %s.',
                    $approval->approval_date->toDateString(),
                    $approval->project_code,
                ),
            )->update([
                'status' =>
                    ProjectStatus::APPROVED,

                'updated_by' =>
                    $request->user()->id,
            ]);

            $lockedProject->clearStatusTransitionContext();

            if ($lockedProject->implementation_mode === ImplementationMode::THROUGH_ACP) {
                $lockedProject->setStatusTransitionContext(
                    actorId: (int) $request->user()->id,
                    remarks: 'Through ACP project approval completed. Project moved to For Payment.',
                )->update([
                    'status' => ProjectStatus::FOR_PAYMENT,
                    'updated_by' => $request->user()->id,
                ]);

                $lockedProject->clearStatusTransitionContext();
            }

            return redirect()
                ->route(
                    'projects.show',
                    $lockedProject
                )
                ->with(
                    'success',
                    $lockedProject->implementation_mode === ImplementationMode::THROUGH_ACP
                        ? "Project approved successfully. Project Code: {$approval->project_code}. Through ACP project moved to For Payment."
                        : "Project approved successfully. Project Code: {$approval->project_code}"
                );
        });
    }
}
