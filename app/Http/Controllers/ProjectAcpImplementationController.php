<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProjectAcpImplementationController extends Controller
{
    public function __construct(
        private readonly ProjectStatusEngine $statusEngine,
    ) {
    }

    public function show(Project $project): View
    {
        $this->ensureAcp($project);

        $project->load([
            'allocation.adl',
            'approval',
            'acpPayment',
            'acpCheckRelease.attachments',
            'implementation.recorder',
        ]);

        return view('acp-implementation.show', compact('project'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureAcp($project);

        if ($project->status !== ProjectStatus::FOR_IMPLEMENTATION) {
            abort(
                403,
                'The Through ACP implementation period can only be recorded while the project is For Implementation.'
            );
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $project, $validated): void {
            $locked = Project::query()
                ->with(['acpCheckRelease', 'implementation'])
                ->lockForUpdate()
                ->findOrFail($project->id);

            if (
                $locked->implementation_mode !== ImplementationMode::THROUGH_ACP
                || $locked->status !== ProjectStatus::FOR_IMPLEMENTATION
            ) {
                throw ValidationException::withMessages([
                    'start_date' => 'This Through ACP project is no longer available for implementation scheduling.',
                ]);
            }

            if (! $locked->acpCheckRelease) {
                throw ValidationException::withMessages([
                    'start_date' => 'A recorded check release is required before Through ACP implementation can be scheduled.',
                ]);
            }

            $startDate = Carbon::parse($validated['start_date'])->startOfDay();

            if ($startDate->lt($locked->acpCheckRelease->released_date->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'start_date' => 'The implementation start date cannot be earlier than the date the check was released to the proponent.',
                ]);
            }

            $durationDays = max(1, (int) $locked->number_of_days);
            $endDate = $startDate->copy()->addDays($durationDays);

            $locked->implementation()->updateOrCreate(
                ['project_id' => $locked->id],
                [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'remarks' => $validated['remarks'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]
            );
        });

        $this->statusEngine->synchronize(
            $project,
            actorId: (int) $request->user()->id,
        );

        $project->refresh();

        return redirect()
            ->route('acp-implementation.show', $project)
            ->with(
                'success',
                sprintf(
                    'Through ACP implementation period saved. The end date was calculated from the approved %d-day project duration.',
                    max(1, (int) $project->number_of_days)
                )
            );
    }

    private function ensureAcp(Project $project): void
    {
        if ($project->implementation_mode !== ImplementationMode::THROUGH_ACP) {
            abort(403, 'This interface applies only to Through ACP projects.');
        }
    }
}
