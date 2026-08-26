<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectPostDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectPostDocumentController extends Controller
{
    public function store(
        Request $request,
        Project $project
    ): RedirectResponse {
        if (
            $project->status
            !== ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        ) {
            abort(
                403,
                'Post-documentary requirements can only be recorded while the project is awaiting post-doc submission.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Revised Post-Document Input
        |--------------------------------------------------------------------------
        |
        | New UI:
        | - Date Received
        | - Attachments Received (multiple files)
        | - Date Forwarded to IMSD
        |
        | The old single-file `attachment` + `document_type` payload remains
        | accepted for backwards compatibility with older integrations/tests.
        |
        */

        $validated = $request->validate([
            'date_received' => [
                'required',
                'date',
            ],

            'attachments' => [
                'nullable',
                'array',
                'min:1',
                'max:20',
                'required_without:attachment',
            ],

            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],

            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
                'required_without:attachments',
            ],

            'document_type' => [
                'nullable',
                'string',
                'max:255',
            ],

            'date_forwarded_to_imsd' => [
                'required',
                'date',
                'after_or_equal:date_received',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $files = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                if ($file) {
                    $files[] = $file;
                }
            }
        }

        if (
            empty($files)
            && $request->hasFile('attachment')
        ) {
            $files[] = $request->file('attachment');
        }

        if (empty($files)) {
            throw ValidationException::withMessages([
                'attachments' =>
                    'At least one post-documentary attachment is required.',
            ]);
        }

        $storedPaths = [];

        try {
            DB::transaction(
                function () use (
                    $request,
                    $project,
                    $validated,
                    $files,
                    &$storedPaths
                ): void {
                    foreach ($files as $file) {
                        $path = $file->store(
                            "projects/{$project->id}/post-docs",
                            'local'
                        );

                        $storedPaths[] = $path;

                        $originalName = trim(
                            (string) $file->getClientOriginalName()
                        );

                        $legacyDocumentType = trim(
                            (string) (
                                $validated['document_type']
                                ?? ''
                            )
                        );

                        $documentType =
                            count($files) === 1
                            && $legacyDocumentType !== ''
                                ? $legacyDocumentType
                                : $originalName;

                        $project->postDocuments()->create([
                            'date_received' =>
                                $validated['date_received'],

                            'document_type' =>
                                mb_substr(
                                    $documentType !== ''
                                        ? $documentType
                                        : 'Post-Documentary Requirement',
                                    0,
                                    255
                                ),

                            'attachment_path' =>
                                $path,

                            'date_forwarded_to_imsd' =>
                                $validated[
                                    'date_forwarded_to_imsd'
                                ],

                            'remarks' =>
                                $validated['remarks']
                                ?? null,

                            'recorded_by' =>
                                $request->user()->id,
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Revised Automatic Transition
                    |--------------------------------------------------------------------------
                    |
                    | The revised document says Save automatically updates the
                    | project to For Payment. Because the form requires all
                    | three official inputs, a successful save is complete.
                    |
                    */

                    $project->update([
                        'status' =>
                            ProjectStatus::FOR_PAYMENT,

                        'updated_by' =>
                            $request->user()->id,
                    ]);
                }
            );
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return back()->with(
            'success',
            count($files) === 1
                ? 'Post-documentary requirements saved. Project automatically moved to For Payment.'
                : count($files)
                    . ' post-documentary attachments saved. Project automatically moved to For Payment.'
        );
    }

    public function download(
        Request $request,
        Project $project,
        ProjectPostDocument $projectPostDocument
    ): StreamedResponse {
        if (
            $projectPostDocument->project_id
            !== $project->id
        ) {
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
                [
                    ProjectStatus::FOR_PAYMENT,
                    ProjectStatus::COMPLETED,
                ],
                true
            )
        ) {
            abort(403);
        }

        if (
            blank($projectPostDocument->attachment_path)
            || ! Storage::disk('local')->exists(
                $projectPostDocument->attachment_path
            )
        ) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $projectPostDocument->attachment_path
        );
    }
}
