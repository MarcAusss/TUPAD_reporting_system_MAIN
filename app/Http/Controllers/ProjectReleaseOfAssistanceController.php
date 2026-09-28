<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectReleaseOfAssistanceController extends Controller
{
    public const PAYOUT_MODES = [
        'Cash',
        'Check',
        'Bank Transfer',
        'E-wallet / Remittance',
    ];

    /**
     * Record (or correct) the Release of Assistance after the Focal/Admin
     * completed the obligation tranches. The project completes automatically
     * once the payout date is reached and the full cost is disbursed.
     */
    public function store(
        Request $request,
        Project $project,
        ProjectStatusEngine $statusEngine,
    ): RedirectResponse {
        if (
            $project->implementation_mode
                !== ImplementationMode::DIRECT_ADMINISTRATION
            || $project->status !== ProjectStatus::FOR_PAYMENT
        ) {
            abort(
                403,
                'Release of Assistance applies only to Direct Administration projects with For Payment status.'
            );
        }

        if ($project->obligations_completed_at === null) {
            abort(
                403,
                'Release of Assistance is available after the obligation tranches are completed.'
            );
        }

        $validated = $request->validate([
            'payout_mode' => ['required', Rule::in(self::PAYOUT_MODES)],
            'payout_date' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'payout_mode' => 'mode of payment',
            'payout_date' => 'date of payout',
        ]);

        $project->payout()->updateOrCreate(
            ['project_id' => $project->id],
            [
                'payout_mode' => $validated['payout_mode'],
                'payout_date' => $validated['payout_date'],
                'venue' => trim($validated['venue']),
                'remarks' => $validated['remarks'] ?? null,
                'recorded_by' => $request->user()->id,
            ],
        );

        $status = $statusEngine->synchronize(
            $project,
            actorId: (int) $request->user()->id,
        );

        return redirect()
            ->route('projects.show', [
                'project' => $project,
                'workspace' => 'workflow',
            ])
            ->withFragment('release-of-assistance')
            ->with(
                'success',
                $status === ProjectStatus::COMPLETED
                    ? 'Release of Assistance saved. All requirements are met and the project is now Completed.'
                    : 'Release of Assistance saved. The project completes once the payout date is reached and the full project cost is disbursed.'
            );
    }
}
