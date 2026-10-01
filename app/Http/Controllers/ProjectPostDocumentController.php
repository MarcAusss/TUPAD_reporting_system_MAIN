<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectPostDocumentController extends Controller
{
    public function store(
        Request $request,
        Project $project,
        ProjectStatusEngine $statusEngine,
    ): RedirectResponse
    {
        if (
            $project->implementation_mode
                !== ImplementationMode::DIRECT_ADMINISTRATION
            || $project->status !== ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        ) {
            abort(
                403,
                'Post-documentary requirements apply only to Direct Administration projects with For Submission of Post-Docs status.'
            );
        }

        $validated = Validator::make($request->all(), [
            'date_received' => ['required', 'date'],
            'document_type' => ['nullable', 'string', 'max:255'],
            'date_forwarded_to_imsd' => [
                'required',
                'date',
                'after_or_equal:date_received',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'document_type' => 'documents received',
        ])->validate();

        DB::transaction(function () use ($request, $project, $statusEngine, $validated): void {
            $project->postDocuments()->create([
                'date_received' => $validated['date_received'],
                'document_type' => filled($validated['document_type'] ?? null)
                    ? trim($validated['document_type'])
                    : 'Post-Documentary Requirements',
                'date_forwarded_to_imsd' => $validated['date_forwarded_to_imsd'],
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            $statusEngine->synchronize(
                $project,
                actorId: (int) $request->user()->id,
            );
        });

        return back()->with(
            'success',
            $project->status === ProjectStatus::FOR_PAYMENT
                ? 'Post-documentary requirements saved. Project automatically moved to For Payment.'
                : 'Post-documentary requirements saved successfully.'
        );
    }
}
