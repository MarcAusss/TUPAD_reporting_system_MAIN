<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectObligation;
use App\Services\Payments\ProjectPaymentService;
use App\Services\Projects\AcpWorkflowService;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectReleaseOfAssistanceController extends Controller
{
    public const PAYOUT_MODES = [
        '(Actual) Cash Payout',
        'Release of Reference Number',
        'Through MRSP (Money Remittance Service Providers)',
        'Awarding of Check',
        self::OTHER_MODE,
    ];

    public const OTHER_MODE = 'Others';

    /** Stored as "Others: <specified mode>". */
    public const OTHER_PREFIX = 'Others: ';

    /**
     * Split a stored payout mode into [selected option, specified text]
     * for re-populating the form.
     *
     * @return array{0:string|null,1:string|null}
     */
    public static function splitPayoutMode(?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [null, null];
        }

        if (str_starts_with($stored, self::OTHER_PREFIX)) {
            return [self::OTHER_MODE, substr($stored, strlen(self::OTHER_PREFIX))];
        }

        // Legacy values not in the current list are treated as "Others".
        return in_array($stored, self::PAYOUT_MODES, true)
            ? [$stored, null]
            : [self::OTHER_MODE, $stored];
    }

    public static function errorBag(ProjectObligation $obligation): string
    {
        return 'release_'.$obligation->id;
    }

    /**
     * Record (or correct) the Release of Assistance for one tranche. It is
     * available once the Focal has fully disbursed that tranche. The project
     * completes automatically once the Focal completed the tranches, every
     * tranche is disbursed and released, and every release date is reached.
     */
    public function store(
        Request $request,
        Project $project,
        ProjectObligation $obligation,
        ProjectStatusEngine $statusEngine,
        ProjectPaymentService $paymentService,
    ): RedirectResponse {
        if ((int) $obligation->project_id !== (int) $project->id) {
            abort(404);
        }

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

        if ($obligation->isReleased()) {
            abort(
                403,
                'This tranche already has a Release of Assistance. Corrections are made from its section in the project overview.'
            );
        }

        if (! $paymentService->trancheFullyDisbursed($obligation)) {
            abort(
                403,
                'Release of Assistance is available after the Focal fully disburses this tranche.'
            );
        }

        [$validated, $payoutMode] = $this->validateRelease($request, self::errorBag($obligation));

        $obligation->update([
            'release_mode' => $payoutMode,
            'release_date' => $validated['payout_date'],
            'release_venue' => trim($validated['venue']),
            'release_remarks' => $validated['remarks'] ?? null,
            'released_by' => $request->user()->id,
            'released_at' => now(),
        ]);

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
                    ? "Release of Assistance saved for Tranche {$obligation->tranche_number}. All requirements are met and the project is now Completed."
                    : "Release of Assistance saved for Tranche {$obligation->tranche_number}. The project completes once every tranche is obligated, disbursed, and released and all payout dates are reached."
            );
    }

    public const ACP_ERROR_BAG = 'acp_release';

    /**
     * Through ACP Release of Assistance (one per project), recorded by the
     * TUPAD Coordinator once the work period has ended. Liquidation opens
     * when its payout date is reached. Corrections go through the overview.
     */
    public function storeAcp(
        Request $request,
        Project $project,
        ProjectStatusEngine $statusEngine,
        AcpWorkflowService $acpWorkflow,
    ): RedirectResponse {
        if (! $acpWorkflow->isAcp($project)) {
            abort(403, 'This Release of Assistance applies only to Through ACP projects.');
        }

        $project->loadMissing('payout');

        if ($project->payout !== null) {
            abort(403, 'The Release of Assistance is already recorded. Corrections are made from its section in the project overview.');
        }

        if (! $acpWorkflow->releaseOpen($project)) {
            abort(403, 'The Through ACP Release of Assistance opens once implementation is ongoing and the work period has ended.');
        }

        [$validated, $payoutMode] = $this->validateRelease($request, self::ACP_ERROR_BAG);

        $project->payout()->create([
            'payout_mode' => $payoutMode,
            'payout_date' => $validated['payout_date'],
            'venue' => trim($validated['venue']),
            'remarks' => $validated['remarks'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $status = $statusEngine->synchronize($project, actorId: (int) $request->user()->id);

        return redirect()
            ->route('projects.show', ['project' => $project, 'workspace' => 'workflow'])
            ->withFragment('acp-release-of-assistance')
            ->with(
                'success',
                $status === ProjectStatus::FOR_LIQUIDATION
                    ? 'Release of Assistance saved. The payout date is reached and the project is now For Liquidation.'
                    : 'Release of Assistance saved. Liquidation opens once the payout date is reached.'
            );
    }

    /** @return array{0: array<string, mixed>, 1: string} validated input and the stored payout mode */
    private function validateRelease(Request $request, string $bag): array
    {
        $validated = $request->validateWithBag($bag, [
            'payout_mode' => ['required', Rule::in(self::PAYOUT_MODES)],
            'payout_mode_other' => [
                'nullable',
                'required_if:payout_mode,'.self::OTHER_MODE,
                'string',
                'max:'.(100 - strlen(self::OTHER_PREFIX)),
            ],
            'payout_date' => ['required', 'date'],
            'venue' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ], [
            'payout_mode_other.required_if' => 'Please specify the other mode of payment.',
        ], [
            'payout_mode' => 'mode of payment',
            'payout_mode_other' => 'other mode of payment',
            'payout_date' => 'date of payout',
        ]);

        $payoutMode = $validated['payout_mode'] === self::OTHER_MODE
            ? self::OTHER_PREFIX.trim($validated['payout_mode_other'])
            : $validated['payout_mode'];

        return [$validated, $payoutMode];
    }
}
