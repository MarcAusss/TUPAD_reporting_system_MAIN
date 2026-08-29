<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;

final class ProjectWorkflowDefinition
{
    /**
     * Return the authoritative straight-through workflow for an implementation mode.
     *
     * For Compliance is intentionally excluded because it remains an optional
     * TSSD evaluation exception branch before For Approval.
     *
     * @return array<int, ProjectStatus>
     */
    public function happyPathFor(ImplementationMode $mode): array
    {
        $shared = [
            ProjectStatus::ONGOING_PROFILING,
            ProjectStatus::TSSD_EVALUATION,
            ProjectStatus::FOR_APPROVAL,
            ProjectStatus::APPROVED,
        ];

        return match ($mode) {
            ImplementationMode::DIRECT_ADMINISTRATION => [
                ...$shared,
                ProjectStatus::FOR_IMPLEMENTATION,
                ProjectStatus::ONGOING_IMPLEMENTATION,
                ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                ProjectStatus::FOR_PAYMENT,
                ProjectStatus::COMPLETED,
            ],

            ImplementationMode::THROUGH_ACP => [
                ...$shared,
                ProjectStatus::FOR_PAYMENT,
                ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                ProjectStatus::FOR_IMPLEMENTATION,
                ProjectStatus::ONGOING_IMPLEMENTATION,
                ProjectStatus::FOR_LIQUIDATION,
                ProjectStatus::PARTIALLY_LIQUIDATED,
                ProjectStatus::COMPLETED,
            ],
        };
    }

    public function nextHappyPathStatus(
        ImplementationMode $mode,
        ProjectStatus $currentStatus,
    ): ?ProjectStatus {
        $path = $this->happyPathFor($mode);
        $index = array_search($currentStatus, $path, true);

        if ($index === false) {
            return null;
        }

        return $path[$index + 1] ?? null;
    }

    public function contains(
        ImplementationMode $mode,
        ProjectStatus $status,
    ): bool {
        return in_array($status, $this->happyPathFor($mode), true);
    }
}
