<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectPostDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectPostDocumentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        if ($project->status !== ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS) {
            abort(
                403,
                'Post-documentary requirements can only be recorded while the project is awaiting post-doc submission.'
            );
        }

        $validated = $request->validate([
            'date_received' => ['required', 'date'],
            'document_type' => ['required', 'string', 'max:255'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
            'date_forwarded_to_imsd' => [
                'nullable',
                'date',
                'after_or_equal:date_received',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                "projects/{$project->id}/post-docs",
                'local'
            );
        }

        $project->postDocuments()->create([
            'date_received' => $validated['date_received'],
            'document_type' => trim($validated['document_type']),
            'attachment_path' => $attachmentPath,
            'date_forwarded_to_imsd' => $validated['date_forwarded_to_imsd'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $project->refresh();

        if ($project->postDocumentsComplete()) {
            $project->update([
                'status' => ProjectStatus::FOR_PAYMENT,
                'updated_by' => $request->user()->id,
            ]);
        }

        return back()->with(
            'success',
            $project->status === ProjectStatus::FOR_PAYMENT
                ? 'Post-documentary requirement saved. Project moved to For Payment.'
                : 'Post-documentary requirement saved successfully.'
        );
    }

    public function download(
        Request $request,
        Project $project,
        ProjectPostDocument $projectPostDocument
    ): StreamedResponse {
        if ($projectPostDocument->project_id !== $project->id) {
            abort(404);
        }

        $user = $request->user();

        if ($user->isGip()) {
            abort(403);
        }

        if (
            $user->isFocal()
            && ! in_array(
                $project->status,
                [ProjectStatus::FOR_PAYMENT, ProjectStatus::COMPLETED],
                true
            )
        ) {
            abort(403);
        }

        if (
            blank($projectPostDocument->attachment_path)
            || ! Storage::disk('local')->exists($projectPostDocument->attachment_path)
        ) {
            abort(404);
        }

        return Storage::disk('local')->download($projectPostDocument->attachment_path);
    }
}
