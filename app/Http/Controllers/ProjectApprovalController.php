<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectApprovalController extends Controller
{
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
                'project_code' =>
                    'This project already has an approval record.',
            ]);
        }

        $validated = $request->validate([
            'approval_date' => [
                'required',
                'date',
            ],

            'project_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'project_approvals',
                    'project_code'
                ),
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
                    'project_code' =>
                        'This project is no longer available for approval.',
                ]);
            }

            $approval = $lockedProject
                ->approval()
                ->create([
                    'approval_date' =>
                        $validated['approval_date'],

                    'project_code' =>
                        strtoupper(
                            trim(
                                $validated['project_code']
                            )
                        ),

                    'remarks' =>
                        $validated['remarks'] ?? null,

                    'approved_by' =>
                        $request->user()->id,

                    'approved_at' =>
                        now(),
                ]);

            $lockedProject->update([
                'status' =>
                    ProjectStatus::APPROVED,

                'updated_by' =>
                    $request->user()->id,
            ]);

            return redirect()
                ->route(
                    'projects.show',
                    $lockedProject
                )
                ->with(
                    'success',
                    "Project approved successfully. Project Code: {$approval->project_code}"
                );
        });
    }
}