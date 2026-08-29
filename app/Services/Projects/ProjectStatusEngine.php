<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Payments\ProjectPaymentService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProjectStatusEngine
{
    private const ELIGIBLE_STATUSES = [
        ProjectStatus::APPROVED,
        ProjectStatus::FOR_IMPLEMENTATION,
        ProjectStatus::ONGOING_IMPLEMENTATION,
        ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        ProjectStatus::FOR_PAYMENT,
        ProjectStatus::FOR_LIQUIDATION,
        ProjectStatus::PARTIALLY_LIQUIDATED,
    ];

    public function __construct(
        private readonly ImplementationStageService $implementationStageService,
        private readonly ProjectPaymentService $paymentService,
        private readonly ProjectAcpLiquidationService $acpLiquidationService,
    ) {
    }

    /**
     * Synchronize one project using only authoritative related records.
     *
     * Manual stages (profiling, evaluation, compliance, and approval) are
     * deliberately excluded. Automatic transitions are forward-only.
     */
    public function synchronize(
        Project $project,
        ?int $actorId = null,
        ?CarbonInterface $today = null,
    ): ProjectStatus {
        $effectiveDate = $this->effectiveDate($today);

        DB::transaction(function () use ($project, $actorId, $effectiveDate): void {
            $lockedProject = Project::query()
                ->lockForUpdate()
                ->findOrFail($project->getKey());

            $this->loadRelationsForCurrentStatus($lockedProject);

            // A stale imported record can require more than one deterministic
            // transition. The guard prevents an accidental endless loop.
            for ($step = 0; $step < 5; $step++) {
                $nextStatus = $this->nextAutomaticStatus(
                    $lockedProject,
                    $effectiveDate,
                );

                if ($nextStatus === null || $nextStatus === $lockedProject->status) {
                    break;
                }

                $lockedProject->setStatusTransitionContext(
                    actorId: $actorId,
                    remarks: $this->transitionRemarks(
                        $lockedProject->status,
                        $nextStatus,
                    ),
                );

                $attributes = ['status' => $nextStatus];

                if ($actorId !== null) {
                    $attributes['updated_by'] = $actorId;
                }

                $lockedProject->update($attributes);
                $lockedProject->clearStatusTransitionContext();
                $this->loadRelationsForCurrentStatus($lockedProject);
            }
        });

        $project->refresh();

        return $project->status;
    }

    /**
     * Synchronize all eligible projects, or a selected list of project IDs.
     *
     * @param  array<int, int>|null  $projectIds
     * @return array{scanned: int, updated: int}
     */
    public function synchronizeEligible(
        ?CarbonInterface $today = null,
        ?array $projectIds = null,
    ): array {
        $scanned = 0;
        $updated = 0;

        Project::query()
            ->whereIn(
                'status',
                array_map(
                    static fn (ProjectStatus $status): string => $status->value,
                    self::ELIGIBLE_STATUSES,
                ),
            )
            ->when(
                $projectIds !== null,
                fn (Builder $query): Builder => $query->whereIn('id', $projectIds),
            )
            ->select('id', 'status')
            ->orderBy('id')
            ->chunkById(100, function ($projects) use (
                &$scanned,
                &$updated,
                $today,
            ): void {
                foreach ($projects as $project) {
                    $scanned++;
                    $before = $project->status;

                    $after = $this->synchronize(
                        $project,
                        actorId: null,
                        today: $today,
                    );

                    if ($after !== $before) {
                        $updated++;
                    }
                }
            });

        return [
            'scanned' => $scanned,
            'updated' => $updated,
        ];
    }

    private function nextAutomaticStatus(
        Project $project,
        CarbonImmutable $today,
    ): ?ProjectStatus {
        return match ($project->status) {
            ProjectStatus::APPROVED =>
                $project->implementation_mode === ImplementationMode::THROUGH_ACP
                    ? ($project->approval ? ProjectStatus::FOR_PAYMENT : null)
                    : ($this->preImplementationRequirementsComplete($project)
                        ? ProjectStatus::FOR_IMPLEMENTATION
                        : null),

            ProjectStatus::FOR_IMPLEMENTATION =>
                $this->isThroughAcp($project)
                    ? $this->nextAcpImplementationStatus(
                        $project,
                        $today,
                        [
                            ProjectStatus::ONGOING_IMPLEMENTATION,
                            ProjectStatus::FOR_LIQUIDATION,
                        ],
                    )
                    : $this->nextImplementationStatus(
                        $project,
                        $today,
                        [
                            ProjectStatus::ONGOING_IMPLEMENTATION,
                            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                        ],
                    ),

            ProjectStatus::ONGOING_IMPLEMENTATION =>
                $this->isThroughAcp($project)
                    ? $this->nextAcpImplementationStatus(
                        $project,
                        $today,
                        [ProjectStatus::FOR_LIQUIDATION],
                    )
                    : $this->nextImplementationStatus(
                        $project,
                        $today,
                        [ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS],
                    ),

            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS =>
                $this->isDirectAdministration($project)
                && $this->postDocumentsComplete($project)
                    ? ProjectStatus::FOR_PAYMENT
                    : null,

            ProjectStatus::FOR_PAYMENT =>
                $this->isDirectAdministration($project)
                && $this->paymentService->summary($project)['is_fully_paid']
                    ? ProjectStatus::COMPLETED
                    : null,

            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED =>
                $this->nextAcpLiquidationStatus($project),

            default => null,
        };
    }

    /**
     * @param  array<int, ProjectStatus>  $allowedForwardStatuses
     */
    private function nextImplementationStatus(
        Project $project,
        CarbonImmutable $today,
        array $allowedForwardStatuses,
    ): ?ProjectStatus {
        if (! $this->isDirectAdministration($project)) {
            return null;
        }

        $stage = $this->implementationStageService->stageFor(
            $project,
            $today,
        );

        return in_array($stage, $allowedForwardStatuses, true)
            ? $stage
            : null;
    }

    /**
     * @param  array<int, ProjectStatus>  $allowedForwardStatuses
     */
    private function nextAcpImplementationStatus(
        Project $project,
        CarbonImmutable $today,
        array $allowedForwardStatuses,
    ): ?ProjectStatus {
        if (
            ! $this->isThroughAcp($project)
            || $project->acpCheckRelease === null
            || $project->implementation === null
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Date-Only Workflow in Asia/Manila
        |--------------------------------------------------------------------------
        |
        | ProjectImplementation start_date/end_date are Eloquent date casts.
        | Depending on the application/database timezone they may be Carbon
        | instances representing midnight UTC. Comparing those instants directly
        | against a Manila-local workflow date can delay a same-calendar-day
        | transition by eight hours.
        |
        | The ACP implementation workflow is calendar-date based, so rebuild the
        | stored Y-m-d values explicitly at midnight in Asia/Manila. This mirrors
        | the existing Direct Administration ImplementationStageService behavior.
        |
        */
        $startDate = CarbonImmutable::parse(
            $project->implementation->start_date->format('Y-m-d'),
            'Asia/Manila',
        )->startOfDay();

        $endDate = CarbonImmutable::parse(
            $project->implementation->end_date->format('Y-m-d'),
            'Asia/Manila',
        )->startOfDay();

        $stage = $today->gte($endDate)
            ? ProjectStatus::FOR_LIQUIDATION
            : ($today->gte($startDate)
                ? ProjectStatus::ONGOING_IMPLEMENTATION
                : ProjectStatus::FOR_IMPLEMENTATION);

        return in_array($stage, $allowedForwardStatuses, true)
            ? $stage
            : null;
    }

    private function nextAcpLiquidationStatus(
        Project $project
    ): ?ProjectStatus {
        if (! $this->isThroughAcp($project)) {
            return null;
        }

        $summary = $this->acpLiquidationService->summary($project);

        if ($summary['is_fully_liquidated']) {
            return ProjectStatus::COMPLETED;
        }

        if (
            $project->status === ProjectStatus::FOR_LIQUIDATION
            && $summary['has_liquidation']
        ) {
            return ProjectStatus::PARTIALLY_LIQUIDATED;
        }

        return null;
    }

    private function preImplementationRequirementsComplete(Project $project): bool
    {
        return $project->insuranceEnrollment !== null
            && $project->ppeDelivery !== null
            && $project->noticeToProceed !== null;
    }

    private function postDocumentsComplete(Project $project): bool
    {
        return $project->postDocuments->contains(
            static fn ($document): bool =>
                $document->date_forwarded_to_imsd !== null,
        );
    }

    private function isDirectAdministration(Project $project): bool
    {
        return $project->implementation_mode
            === ImplementationMode::DIRECT_ADMINISTRATION;
    }

    private function isThroughAcp(Project $project): bool
    {
        return $project->implementation_mode
            === ImplementationMode::THROUGH_ACP;
    }

    private function effectiveDate(?CarbonInterface $today): CarbonImmutable
    {
        return $today
            ? CarbonImmutable::instance($today)
                ->setTimezone('Asia/Manila')
                ->startOfDay()
            : CarbonImmutable::now('Asia/Manila')->startOfDay();
    }

    private function loadRelationsForCurrentStatus(Project $project): void
    {
        $relations = match ($project->status) {
            ProjectStatus::APPROVED => [
                'approval',
                'insuranceEnrollment',
                'ppeDelivery',
                'noticeToProceed',
            ],

            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION =>
                $this->isThroughAcp($project)
                    ? [
                        'acpCheckRelease',
                        'implementation',
                    ]
                    : [
                        'insuranceEnrollment',
                        'ppeDelivery',
                        'noticeToProceed',
                        'orientation',
                        'implementation',
                    ],

            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => [
                'postDocuments',
            ],

            ProjectStatus::FOR_PAYMENT =>
                $this->isThroughAcp($project)
                    ? ['acpPayment']
                    : ['obligations.disbursements'],

            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED => [
                'acpCheckRelease',
                'acpLiquidations',
            ],

            default => [],
        };

        if ($relations !== []) {
            $project->load($relations);
        }
    }

    private function transitionRemarks(
        ProjectStatus $from,
        ProjectStatus $to,
    ): string {
        return match ([$from, $to]) {
            [ProjectStatus::APPROVED, ProjectStatus::FOR_PAYMENT] =>
                'Automatic status engine: Through ACP approval is complete and the project is ready for payment processing.',

            [ProjectStatus::APPROVED, ProjectStatus::FOR_IMPLEMENTATION] =>
                'Automatic status engine: Insurance, PPE delivery, and Notice to Proceed are complete.',

            [ProjectStatus::FOR_IMPLEMENTATION, ProjectStatus::ONGOING_IMPLEMENTATION] =>
                'Automatic status engine: The implementation start date has been reached (Asia/Manila).',

            [ProjectStatus::FOR_IMPLEMENTATION, ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS],
            [ProjectStatus::ONGOING_IMPLEMENTATION, ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS] =>
                'Automatic status engine: The Direct Administration implementation end date has been reached (Asia/Manila).',

            [ProjectStatus::FOR_IMPLEMENTATION, ProjectStatus::FOR_LIQUIDATION],
            [ProjectStatus::ONGOING_IMPLEMENTATION, ProjectStatus::FOR_LIQUIDATION] =>
                'Automatic status engine: The Through ACP implementation end date has been reached and liquidation is now required.',

            [ProjectStatus::FOR_LIQUIDATION, ProjectStatus::PARTIALLY_LIQUIDATED] =>
                'Automatic status engine: A partial Through ACP liquidation was recorded.',

            [ProjectStatus::FOR_LIQUIDATION, ProjectStatus::COMPLETED],
            [ProjectStatus::PARTIALLY_LIQUIDATED, ProjectStatus::COMPLETED] =>
                'Automatic status engine: The full released Through ACP amount has been liquidated.',

            [ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS, ProjectStatus::FOR_PAYMENT] =>
                'Automatic status engine: Complete post-documentary requirements were forwarded to IMSD.',

            [ProjectStatus::FOR_PAYMENT, ProjectStatus::COMPLETED] =>
                'Automatic status engine: The full payable wage amount is obligated and disbursed.',

            default => 'Automatic status engine transition.',
        };
    }
}
