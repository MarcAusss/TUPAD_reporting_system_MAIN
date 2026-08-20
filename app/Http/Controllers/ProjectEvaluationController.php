<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'Only projects under Ongoing Profiling may be submitted for TSSD Evaluation.'
            );
        }

        $project->update([
            'status' => ProjectStatus::TSSD_EVALUATION,
            'updated_by' => $request->user()->id,
        ]);

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
            !in_array(
                $project->status,
                [
                    ProjectStatus::TSSD_EVALUATION,
                    ProjectStatus::FOR_COMPLIANCE,
                ],
                true
            )
        ) {
            abort(
                403,
                'This project cannot currently be evaluated.'
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
                'nullable',
                'string',
                'max:5000',
            ],

            'required_documents' => [
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

        if (
            $validated['result'] === 'for_compliance'
            && blank($validated['findings'] ?? null)
            && blank($validated['required_documents'] ?? null)
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'findings' =>
                        'Provide findings or required documents when returning the project for compliance.',
                ]);
        }

        $project->evaluations()->create([
            'findings' =>
                $validated['findings'] ?? null,

            'required_documents' =>
                $validated['required_documents'] ?? null,

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
            $validated['result'] === 'for_compliance'
            ? ProjectStatus::FOR_COMPLIANCE
            : ProjectStatus::FOR_APPROVAL;

        $project->update([
            'status' => $newStatus,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with(
                'success',
                $newStatus === ProjectStatus::FOR_COMPLIANCE
                ? 'Project returned for compliance.'
                : 'Project moved to For Approval.'
            );
    }

    public function resubmit(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::FOR_COMPLIANCE
        ) {
            abort(
                403,
                'Only projects under For Compliance may be resubmitted.'
            );
        }

        $project->update([
            'status' => ProjectStatus::TSSD_EVALUATION,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with(
                'success',
                'Project resubmitted for TSSD Evaluation.'
            );
    }
}