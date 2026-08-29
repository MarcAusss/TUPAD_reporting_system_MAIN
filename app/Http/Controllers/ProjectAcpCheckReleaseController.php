<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectAcpCheckReleaseAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectAcpCheckReleaseController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureAcp($project);

        if ($project->status !== ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT) {
            abort(403, 'Check release is only available for Through ACP projects with For Release of Check to Proponent status.');
        }

        $request->merge([
            'check_number' => strtoupper(trim((string) $request->input('check_number'))),
        ]);

        $validated = $request->validate([
            'check_number' => [
                'required', 'string', 'max:150',
                Rule::unique('project_acp_check_releases', 'check_number'),
            ],
            'check_date' => ['required', 'date'],
            'released_date' => ['required', 'date', 'after_or_equal:check_date'],
            'released_to' => ['required', 'string', 'max:255'],
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => [
                'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $project, $validated, &$storedPaths): void {
                $locked = Project::query()
                    ->with('acpPayment')
                    ->lockForUpdate()
                    ->findOrFail($project->id);

                if (
                    $locked->implementation_mode !== ImplementationMode::THROUGH_ACP
                    || $locked->status !== ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT
                ) {
                    throw ValidationException::withMessages([
                        'check_number' => 'This Through ACP project is no longer available for check release.',
                    ]);
                }

                if (! $locked->acpPayment) {
                    throw ValidationException::withMessages([
                        'check_number' => 'A Through ACP payment record is required before a check can be released.',
                    ]);
                }

                if ($locked->acpCheckRelease()->exists()) {
                    throw ValidationException::withMessages([
                        'check_number' => 'This project already has a check release record.',
                    ]);
                }

                if ($locked->acpPayment->payment_date->gt($validated['check_date'])) {
                    throw ValidationException::withMessages([
                        'check_date' => 'The check date cannot be earlier than the recorded ACP payment date.',
                    ]);
                }

                $release = $locked->acpCheckRelease()->create([
                    'check_number' => strtoupper(trim($validated['check_number'])),
                    'check_date' => $validated['check_date'],
                    // Official released amount is copied from the server-side ACP payment record.
                    'amount' => $locked->acpPayment->amount,
                    'released_date' => $validated['released_date'],
                    'released_to' => trim($validated['released_to']),
                    'remarks' => $validated['remarks'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);

                foreach ($request->file('attachments') as $attachment) {
                    $path = $attachment->store("projects/{$locked->id}/acp-check-release", 'local');
                    $storedPaths[] = $path;

                    $release->attachments()->create([
                        'original_name' => $attachment->getClientOriginalName(),
                        'attachment_path' => $path,
                        'mime_type' => $attachment->getClientMimeType(),
                        'file_size' => $attachment->getSize(),
                    ]);
                }

                $locked->setStatusTransitionContext(
                    actorId: (int) $request->user()->id,
                    remarks: sprintf(
                        'Check %s was released to the ACP proponent. Project moved to For Implementation.',
                        strtoupper(trim($validated['check_number']))
                    ),
                )->update([
                    'status' => ProjectStatus::FOR_IMPLEMENTATION,
                    'updated_by' => $request->user()->id,
                ]);

                $locked->clearStatusTransitionContext();
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()
            ->route('acp-payments.show', $project)
            ->with('success', 'Check release recorded. Through ACP project moved to For Implementation.');
    }

    public function download(
        Request $request,
        Project $project,
        ProjectAcpCheckReleaseAttachment $attachment,
    ): StreamedResponse {
        $this->ensureAcp($project);

        $release = $attachment->release;
        if (! $release || $release->project_id !== $project->id) {
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

    private function ensureAcp(Project $project): void
    {
        if ($project->implementation_mode !== ImplementationMode::THROUGH_ACP) {
            abort(403, 'This interface applies only to Through ACP projects.');
        }
    }
}
