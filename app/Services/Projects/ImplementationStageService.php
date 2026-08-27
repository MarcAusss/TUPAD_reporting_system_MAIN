<?php

namespace App\Services\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ImplementationStageService
{
    /**
     * Return the implementation-board stage for a project.
     *
     * Rules:
     *
     * 1. No work period yet
     *    -> For Implementation
     *
     * 2. Preparation is not complete yet
     *    -> For Implementation
     *
     * 3. Today is before Start Date
     *    -> For Implementation
     *
     * 4. Start Date <= Today < End Date
     *    -> Ongoing Implementation
     *
     * 5. Today >= End Date
     *    -> For Submission of Post Docs
     */
    public function stageFor(
        Project $project,
        ?CarbonInterface $today = null
    ): ProjectStatus {
        $project->loadMissing([
            'insuranceEnrollment',
            'ppeDelivery',
            'noticeToProceed',
            'orientation',
            'implementation',
        ]);

        if (! $project->implementation) {
            return ProjectStatus::FOR_IMPLEMENTATION;
        }

        if (! $this->preparationComplete($project)) {
            return ProjectStatus::FOR_IMPLEMENTATION;
        }

        $today =
            $today
                ? CarbonImmutable::instance(
                    $today
                )->startOfDay()
                : CarbonImmutable::now(
                    'Asia/Manila'
                )->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Date-Only Workflow in Asia/Manila
        |--------------------------------------------------------------------------
        |
        | start_date and end_date are Eloquent "date" casts. Depending on the
        | application/database timezone, those values may arrive as Carbon
        | objects representing midnight in UTC.
        |
        | Passing that Carbon object directly to parse() preserves the instant,
        | which can shift the effective calendar day by eight hours in Manila.
        |
        | The implementation workflow is date-based, not time-based, so first
        | take the stored Y-m-d value and then rebuild midnight in Asia/Manila.
        |
        */

        $startDate =
            CarbonImmutable::parse(
                $project
                    ->implementation
                    ->start_date
                    ->format('Y-m-d'),
                'Asia/Manila'
            )->startOfDay();

        $endDate =
            CarbonImmutable::parse(
                $project
                    ->implementation
                    ->end_date
                    ->format('Y-m-d'),
                'Asia/Manila'
            )->startOfDay();

        if ($today->lt($startDate)) {
            return ProjectStatus::FOR_IMPLEMENTATION;
        }

        if ($today->gte($endDate)) {
            return ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS;
        }

        return ProjectStatus::ONGOING_IMPLEMENTATION;
    }

    /**
     * Keep the stored workflow status aligned with the date-based stage.
     *
     * Approved projects remain Approved until implementation preparation is
     * complete. Once a project has entered implementation workflow, the
     * calendar controls movement between the three implementation stages.
     */
    public function synchronize(
        Project $project,
        int $userId,
        ?CarbonInterface $today = null
    ): ProjectStatus {
        $stage =
            $this->stageFor(
                $project,
                $today
            );

        $project->refresh();

        $canSynchronize = match ($project->status) {
            ProjectStatus::FOR_IMPLEMENTATION => in_array(
                $stage,
                [
                    ProjectStatus::ONGOING_IMPLEMENTATION,
                    ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                ],
                true,
            ),

            ProjectStatus::ONGOING_IMPLEMENTATION =>
                $stage === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,

            default => false,
        };

        if (
            $canSynchronize
            && $project->status !== $stage
        ) {
            $project->setStatusTransitionContext(
                actorId: $userId,
                remarks: $stage === ProjectStatus::ONGOING_IMPLEMENTATION
                    ? 'Automatic workflow: The implementation start date has been reached (Asia/Manila).'
                    : 'Automatic workflow: The implementation end date has been reached (Asia/Manila).',
            );

            $project->update([
                'status' =>
                    $stage,

                'updated_by' =>
                    $userId,
            ]);

            $project->clearStatusTransitionContext();

            $project->refresh();
        }

        return $stage;
    }

    public function preparationComplete(
        Project $project
    ): bool {
        return (bool) $project->insuranceEnrollment
            && (bool) $project->ppeDelivery
            && (bool) $project->noticeToProceed
            && (bool) $project->orientation
            && (bool) $project->implementation;
    }
}
