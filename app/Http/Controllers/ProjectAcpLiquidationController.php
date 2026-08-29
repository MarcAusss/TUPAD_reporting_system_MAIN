<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectAcpLiquidationAttachment;
use App\Services\Projects\ProjectAcpLiquidationService;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProjectAcpLiquidationController extends Controller
{
    public function __construct(
        private readonly ProjectAcpLiquidationService $liquidationService,
        private readonly ProjectStatusEngine $statusEngine,
    ) {
    }

    public function show(Project $project): View
    {
        $this->ensureAcp($project);

        $project->load([
            'allocation.adl',
            'approval',
            'acpCheckRelease',
            'implementation',
            'acpLiquidations.recorder',
            'acpLiquidations.attachments',
        ]);

        $summary = $this->liquidationService->summary($project);

        return view(
            'acp-liquidations.show',
            compact('project', 'summary')
        );
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureAcp($project);

        if (! in_array(
            $project->status,
            [
                ProjectStatus::FOR_LIQUIDATION,
                ProjectStatus::PARTIALLY_LIQUIDATED,
            ],
            true
        )) {
            abort(
                403,
                'Liquidation can only be recorded for Through ACP projects that are For Liquidation or Partially Liquidated.'
            );
        }

        $validated = $request->validate([
            'liquidation_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'liquidation_reference' => ['nullable', 'string', 'max:150'],
            'attachments' => ['required', 'array', 'min:1'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            ],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $project,
                $validated,
                &$storedPaths,
            ): void {
                $locked = Project::query()
                    ->with([
                        'acpCheckRelease',
                        'implementation',
                        'acpLiquidations',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($project->id);

                if (
                    $locked->implementation_mode !== ImplementationMode::THROUGH_ACP
                    || ! in_array(
                        $locked->status,
                        [
                            ProjectStatus::FOR_LIQUIDATION,
                            ProjectStatus::PARTIALLY_LIQUIDATED,
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'amount' => 'This Through ACP project is no longer available for liquidation.',
                    ]);
                }

                if (! $locked->acpCheckRelease || ! $locked->implementation) {
                    throw ValidationException::withMessages([
                        'amount' => 'Check-release and implementation records are required before liquidation.',
                    ]);
                }

                $liquidationDate = Carbon::parse($validated['liquidation_date'])->startOfDay();

                if ($liquidationDate->lt($locked->implementation->end_date->copy()->startOfDay())) {
                    throw ValidationException::withMessages([
                        'liquidation_date' => 'The liquidation date cannot be earlier than the Through ACP implementation end date.',
                    ]);
                }

                $summary = $this->liquidationService->summary($locked);
                $amountCents = $this->liquidationService->amountToCents($validated['amount']);

                if ($amountCents <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'The liquidation amount must be greater than zero.',
                    ]);
                }

                if ($amountCents > $summary['remaining_cents']) {
                    throw ValidationException::withMessages([
                        'amount' => sprintf(
                            'The liquidation amount cannot exceed the remaining liquidatable balance of ₱%s.',
                            number_format($summary['remaining_cents'] / 100, 2)
                        ),
                    ]);
                }

                $liquidation = $locked->acpLiquidations()->create([
                    'liquidation_date' => $liquidationDate->toDateString(),
                    'amount' => $this->liquidationService->centsToDecimal($amountCents),
                    'liquidation_reference' => blank($validated['liquidation_reference'] ?? null)
                        ? null
                        : trim($validated['liquidation_reference']),
                    'remarks' => $validated['remarks'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]);

                foreach ($request->file('attachments') as $attachment) {
                    $path = $attachment->store(
                        "projects/{$locked->id}/acp-liquidations/{$liquidation->id}",
                        'local'
                    );
                    $storedPaths[] = $path;

                    $liquidation->attachments()->create([
                        'original_name' => $attachment->getClientOriginalName(),
                        'attachment_path' => $path,
                        'mime_type' => $attachment->getClientMimeType(),
                        'file_size' => $attachment->getSize(),
                    ]);
                }

                $this->statusEngine->synchronize(
                    $locked,
                    actorId: (int) $request->user()->id,
                );
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return redirect()
            ->route('acp-liquidations.show', $project)
            ->with('success', 'Through ACP liquidation record saved successfully.');
    }

    public function download(
        Project $project,
        ProjectAcpLiquidationAttachment $attachment,
    ): StreamedResponse {
        $this->ensureAcp($project);

        $liquidation = $attachment->liquidation;

        if (! $liquidation || $liquidation->project_id !== $project->id) {
            abort(404);
        }

        if (
            blank($attachment->attachment_path)
            || ! Storage::disk('local')->exists($attachment->attachment_path)
        ) {
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
