<?php

namespace Tests\Concerns;

use App\Enums\ImplementationMode;
use App\Models\Project;

/**
 * Compatibility for report tests written before Project::scopeReportable().
 *
 * Reports now only include Direct Administration projects once the Focal
 * completes the obligations (obligations_completed_at). These tests build
 * projects that are already "in reports", so any Direct Administration project
 * created without that timestamp is marked completed on its date_received,
 * keeping each test's reporting period unchanged. Tests that set
 * obligations_completed_at themselves (including null via forceFill after
 * creation) are left alone. Through ACP projects still need a real ACP payment.
 */
trait TreatsProjectsAsReportable
{
    protected function setUpTreatsProjectsAsReportable(): void
    {
        Project::creating(function (Project $project): void {
            $mode = $project->implementation_mode instanceof ImplementationMode
                ? $project->implementation_mode
                : ImplementationMode::tryFrom((string) $project->implementation_mode);

            if (
                $mode === ImplementationMode::DIRECT_ADMINISTRATION
                && ! array_key_exists('obligations_completed_at', $project->getAttributes())
                && $project->date_received !== null
            ) {
                $project->obligations_completed_at = $project->date_received;
            }
        });
    }
}
