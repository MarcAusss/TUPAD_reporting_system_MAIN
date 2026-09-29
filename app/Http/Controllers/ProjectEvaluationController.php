<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectEvaluation;
use App\Models\ProjectEvaluationAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectEvaluationController extends Controller
{
    private const ATTACHMENT_RULES = [
        'file',
        'max:10240',
        'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
    ];

    private const ATTACHMENT_MESSAGES = [
        'attachments.max' => 'Upload at most 10 attachments at a time.',
        'attachments.*.max' => 'Each attachment must be 10 MB or smaller.',
        'attachments.*.mimes' => 'Attachments must be PDF, image (JPG/PNG), Word, or Excel files.',
    ];

    /**
     * Store the request's uploaded files on the evaluation. Stored paths are
     * collected so the caller can delete them if the transaction fails.
     *
     * @param  list<string>  $storedPaths
     */
    private function storeAttachments(
        Request $request,
        ProjectEvaluation $evaluation,
        string $kind,
        string $directory,
        array &$storedPaths,
    ): void {
        foreach ((array) $request->file('attachments', []) as $file) {
            $path = $file->store($directory, 'local');
            $storedPaths[] = $path;

            $evaluation->attachments()->create([
                'kind' => $kind,
                'original_name' => $file->getClientOriginalName(),
                'attachment_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
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
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => self::ATTACHMENT_RULES,
        ], self::ATTACHMENT_MESSAGES);

        $isForCompliance =
            $validated['result'] === 'for_compliance';

        $storedPaths = [];

        try {
            return DB::transaction(function () use ($request, $project, $validated, $isForCompliance, &$storedPaths): RedirectResponse {
                return $this->recordEvaluation($request, $project, $validated, $isForCompliance, $storedPaths);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $storedPaths
     */
    private function recordEvaluation(
        Request $request,
        Project $project,
        array $validated,
        bool $isForCompliance,
        array &$storedPaths,
    ): RedirectResponse {
        $evaluation = $project->evaluations()->create([
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

        // Attachments support the findings / required documents, so they are
        // only kept for a For Compliance result.
        if ($isForCompliance) {
            $this->storeAttachments(
                $request,
                $evaluation,
                ProjectEvaluationAttachment::KIND_EVALUATION,
                "projects/{$project->id}/evaluation",
                $storedPaths,
            );
        }

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
            'compliance_remarks' => [
                'required',
                'string',
                'max:5000',
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => self::ATTACHMENT_RULES,
        ], self::ATTACHMENT_MESSAGES);

        $storedPaths = [];

        try {
            return $this->recordCompliance($request, $project, $validated, $storedPaths);
        } catch (Throwable $exception) {
            // The database work was rolled back; remove any files already stored.
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    public function downloadAttachment(
        Project $project,
        ProjectEvaluationAttachment $attachment,
    ): StreamedResponse {
        if ((int) $attachment->evaluation?->project_id !== (int) $project->id) {
            abort(404);
        }

        if (blank($attachment->attachment_path) || ! Storage::disk('local')->exists($attachment->attachment_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $attachment->attachment_path,
            $attachment->original_name,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<string>  $storedPaths
     */
    private function recordCompliance(
        Request $request,
        Project $project,
        array $validated,
        array &$storedPaths,
    ): RedirectResponse {
        return DB::transaction(
            function () use (
                $request,
                $project,
                $validated,
                &$storedPaths
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
                    'compliance_remarks' =>
                        trim(
                            $validated['compliance_remarks']
                        ),
                    'complied_by' =>
                        $request->user()->id,
                    'complied_at' =>
                        now(),
                ]);

                $this->storeAttachments(
                    $request,
                    $evaluation,
                    ProjectEvaluationAttachment::KIND_COMPLIANCE,
                    "projects/{$lockedProject->id}/compliance",
                    $storedPaths,
                );

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
