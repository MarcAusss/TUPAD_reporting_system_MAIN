<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Through ACP workflow gates (mirrors the Direct Administration rules):
 *
 *   Evaluation → Approval → ACP Payment → Check Release
 *   → Preparation: GSIS Enrollment (Insurance), PPE Delivery, NAFA, Notice to Proceed
 *   → Implementation: Orientation + Work Period
 *   → Release of Assistance (after the work period ends)
 *   → Liquidation → Completed
 *
 * Projects that were already Ongoing before NAFA existed (no NAFA recorded)
 * keep the earlier rule: they move to liquidation when the work period ends.
 */
class AcpWorkflowService
{
    public function isAcp(Project $project): bool
    {
        return $project->implementation_mode === ImplementationMode::THROUGH_ACP;
    }

    /** Insurance, PPE delivery, NAFA, and NTP are all recorded. */
    public function preparationComplete(Project $project): bool
    {
        $project->loadMissing(['insuranceEnrollment', 'ppeDeliveries', 'nafa', 'noticeToProceed']);

        return $project->insuranceEnrollment !== null
            && $project->ppeDeliveries->isNotEmpty()
            && $project->nafa !== null
            && $project->noticeToProceed !== null;
    }

    /** @return list<string> labels of the preparation steps still missing */
    public function missingPreparation(Project $project): array
    {
        $project->loadMissing(['insuranceEnrollment', 'ppeDeliveries', 'nafa', 'noticeToProceed']);

        return array_values(array_filter([
            $project->insuranceEnrollment ? null : 'GSIS Enrollment (Insurance)',
            $project->ppeDeliveries->isNotEmpty() ? null : 'PPE Delivery',
            $project->nafa ? null : 'NAFA',
            $project->noticeToProceed ? null : 'Notice to Proceed',
        ]));
    }

    /** Preparation, orientation, and the work period are recorded. */
    public function readyToImplement(Project $project): bool
    {
        $project->loadMissing(['orientation', 'implementation']);

        return $this->preparationComplete($project)
            && $project->orientation !== null
            && $project->implementation !== null;
    }

    /** Legacy projects that reached implementation before the NAFA step existed. */
    public function isLegacy(Project $project): bool
    {
        $project->loadMissing('nafa');

        return $project->nafa === null
            && in_array($project->status, [
                ProjectStatus::ONGOING_IMPLEMENTATION,
                ProjectStatus::FOR_LIQUIDATION,
                ProjectStatus::PARTIALLY_LIQUIDATED,
                ProjectStatus::COMPLETED,
            ], true);
    }

    public function workPeriodEnded(Project $project, ?CarbonInterface $today = null): bool
    {
        $project->loadMissing('implementation');

        return $project->implementation !== null
            && $this->dateOnly($project->implementation->end_date)->lte($this->today($today));
    }

    public function workPeriodStarted(Project $project, ?CarbonInterface $today = null): bool
    {
        $project->loadMissing('implementation');

        return $project->implementation !== null
            && $this->dateOnly($project->implementation->start_date)->lte($this->today($today));
    }

    /** The TUPAD Coordinator can record the Release of Assistance. */
    public function releaseOpen(Project $project, ?CarbonInterface $today = null): bool
    {
        return $this->isAcp($project)
            && $project->status === ProjectStatus::ONGOING_IMPLEMENTATION
            && ! $this->isLegacy($project)
            && $this->readyToImplement($project)
            && $this->workPeriodEnded($project, $today);
    }

    /** Release of Assistance recorded and its payout date reached. */
    public function releaseDone(Project $project, ?CarbonInterface $today = null): bool
    {
        $project->loadMissing('payout');

        return $project->payout !== null
            && $this->dateOnly($project->payout->payout_date)->lte($this->today($today));
    }

    /** Liquidation may open (status engine uses this to leave implementation). */
    public function readyForLiquidation(Project $project, ?CarbonInterface $today = null): bool
    {
        if ($this->isLegacy($project)) {
            return $this->workPeriodEnded($project, $today);
        }

        return $this->readyToImplement($project)
            && $this->workPeriodEnded($project, $today)
            && $this->releaseDone($project, $today);
    }

    private function dateOnly(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->format('Y-m-d'), 'Asia/Manila')->startOfDay();
    }

    private function today(?CarbonInterface $today): CarbonImmutable
    {
        return $today
            ? CarbonImmutable::instance($today)->setTimezone('Asia/Manila')->startOfDay()
            : CarbonImmutable::now('Asia/Manila')->startOfDay();
    }
}
