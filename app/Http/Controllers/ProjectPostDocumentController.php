<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectPostDocument;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

        $validator = Validator::make($request->all(), [
            'date_received' => ['required', 'date'],
            'attachments' => ['nullable', 'array', 'min:1'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
            // Backward-compatible payload accepted by earlier clients/tests.
            'document_type' => ['nullable', 'string', 'max:255'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
            'date_forwarded_to_imsd' => [
                'required',
                'date',
                'after_or_equal:date_received',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $hasBatchAttachments = $request->hasFile('attachments');
            $hasLegacyAttachment = $request->hasFile('attachment');

            if (! $hasBatchAttachments && ! $hasLegacyAttachment) {
                $validator->errors()->add(
                    'attachments',
                    'At least one attachment received is required.'
                );
            }

            if (
                $hasLegacyAttachment
                && blank($request->input('document_type'))
            ) {
                $validator->errors()->add(
                    'document_type',
                    'The document type field is required for the legacy single-attachment payload.'
                );
            }
        });

        $validated = $validator->validate();
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $project,
                $statusEngine,
                $validated,
                &$storedPaths,
            ): void {
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $attachment) {
                        $attachmentPath = $attachment->store(
                            "projects/{$project->id}/post-docs",
                            'local'
                        );

                        $storedPaths[] = $attachmentPath;

                        $project->postDocuments()->create([
                            'date_received' => $validated['date_received'],
                            'document_type' => $attachment->getClientOriginalName(),
                            'attachment_path' => $attachmentPath,
                            'date_forwarded_to_imsd' => $validated['date_forwarded_to_imsd'],
                            'remarks' => $validated['remarks'] ?? null,
                            'recorded_by' => $request->user()->id,
                        ]);
                    }
                } else {
                    $attachment = $request->file('attachment');
                    $attachmentPath = $attachment->store(
                        "projects/{$project->id}/post-docs",
                        'local'
                    );

                    $storedPaths[] = $attachmentPath;

                    $project->postDocuments()->create([
                        'date_received' => $validated['date_received'],
                        'document_type' => trim($validated['document_type']),
                        'attachment_path' => $attachmentPath,
                        'date_forwarded_to_imsd' => $validated['date_forwarded_to_imsd'],
                        'remarks' => $validated['remarks'] ?? null,
                        'recorded_by' => $request->user()->id,
                    ]);
                }

                $statusEngine->synchronize(
                    $project,
                    actorId: (int) $request->user()->id,
                );
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        return back()->with(
            'success',
            $project->status === ProjectStatus::FOR_PAYMENT
                ? 'Post-documentary requirements saved. Project automatically moved to For Payment.'
                : 'Post-documentary requirements saved successfully.'
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
