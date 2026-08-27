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
    ];

    public function __construct(
        private readonly ImplementationStageService $implementationStageService,
        private readonly ProjectPaymentService $paymentService,
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
                $this->isDirectAdministration($project)
                && $this->preImplementationRequirementsComplete($project)
                    ? ProjectStatus::FOR_IMPLEMENTATION
                    : null,

            ProjectStatus::FOR_IMPLEMENTATION =>
                $this->nextImplementationStatus(
                    $project,
                    $today,
                    [
                        ProjectStatus::ONGOING_IMPLEMENTATION,
                        ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    ],
                ),

            ProjectStatus::ONGOING_IMPLEMENTATION =>
                $this->nextImplementationStatus(
                    $project,
                    $today,
                    [ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS],
                ),

            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS =>
                $this->postDocumentsComplete($project)
                    ? ProjectStatus::FOR_PAYMENT
                    : null,

            ProjectStatus::FOR_PAYMENT =>
                $this->paymentService->summary($project)['is_fully_paid']
                    ? ProjectStatus::COMPLETED
                    : null,

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
                'insuranceEnrollment',
                'ppeDelivery',
                'noticeToProceed',
            ],

            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION => [
                'insuranceEnrollment',
                'ppeDelivery',
                'noticeToProceed',
                'orientation',
                'implementation',
            ],

            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS => [
                'postDocuments',
            ],

            ProjectStatus::FOR_PAYMENT => [
                'obligations.disbursements',
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
            [ProjectStatus::APPROVED, ProjectStatus::FOR_IMPLEMENTATION] =>
                'Automatic status engine: Insurance, PPE delivery, and Notice to Proceed are complete.',

            [ProjectStatus::FOR_IMPLEMENTATION, ProjectStatus::ONGOING_IMPLEMENTATION] =>
                'Automatic status engine: The implementation start date has been reached (Asia/Manila).',

            [ProjectStatus::FOR_IMPLEMENTATION, ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS],
            [ProjectStatus::ONGOING_IMPLEMENTATION, ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS] =>
                'Automatic status engine: The implementation end date has been reached (Asia/Manila).',

            [ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS, ProjectStatus::FOR_PAYMENT] =>
                'Automatic status engine: Complete post-documentary requirements were forwarded to IMSD.',

            [ProjectStatus::FOR_PAYMENT, ProjectStatus::COMPLETED] =>
                'Automatic status engine: The full payable wage amount is obligated and disbursed.',

            default => 'Automatic status engine transition.',
        };
    }
}
