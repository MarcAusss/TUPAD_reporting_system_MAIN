<?php

namespace App\Services\Payments;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectObligation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ProjectPaymentService
{
    public function amountToCents(string|int|float|null $amount): int
    {
        $normalized = trim((string) ($amount ?? '0'));
        [$whole, $fraction] = array_pad(
            explode('.', $normalized, 2),
            2,
            ''
        );

        return ((int) $whole * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    public function centsToDecimal(int $cents): string
    {
        return sprintf(
            '%d.%02d',
            intdiv($cents, 100),
            abs($cents % 100)
        );
    }

    /**
     * Obligation tranches cover the whole project cost
     * (wages + insurance + PPE), not wages alone.
     */
    public function payableCents(Project $project): int
    {
        return $this->amountToCents($project->total_project_cost);
    }

    public function obligationCents(ProjectObligation $obligation): int
    {
        return $this->amountToCents($obligation->amount);
    }

    public function obligatedCents(Project $project): int
    {
        $project->loadMissing('obligations');

        return $project->obligations->sum(
            fn (ProjectObligation $obligation): int =>
                $this->obligationCents($obligation)
        );
    }

    public function disbursedCents(Project $project): int
    {
        $project->loadMissing('obligations.disbursements');

        return $project->obligations->sum(
            fn (ProjectObligation $obligation): int =>
                $obligation->disbursements->sum(
                    fn ($disbursement): int =>
                        $this->amountToCents($disbursement->amount)
                )
        );
    }

    public function disbursedForObligationCents(
        ProjectObligation $obligation
    ): int {
        $obligation->loadMissing('disbursements');

        return $obligation->disbursements->sum(
            fn ($disbursement): int =>
                $this->amountToCents($disbursement->amount)
        );
    }

    public function summary(Project $project): array
    {
        $payable = $this->payableCents($project);
        $obligated = $this->obligatedCents($project);
        $disbursed = $this->disbursedCents($project);

        return [
            'payable_cents' => $payable,
            'obligated_cents' => $obligated,
            'disbursed_cents' => $disbursed,
            'unobligated_cents' => max(0, $payable - $obligated),
            'remaining_cents' => max(0, $payable - $disbursed),
            'progress_percent' => $payable > 0
                ? min(100, (int) floor(($disbursed * 100) / $payable))
                : 0,
            'is_fully_obligated' =>
                $payable > 0
                && $obligated === $payable,
            'obligations_completed' =>
                $project->obligations_completed_at !== null,
            'is_fully_paid' =>
                $payable > 0
                && $obligated === $payable
                && $disbursed === $payable,
        ];
    }

    /**
     * A Direct Administration project completes only when:
     * - the obligation tranches were marked complete,
     * - the full project cost is obligated and disbursed,
     * - the TUPAD Coordinator recorded the Release of Assistance, and
     * - the payout date has been reached (Asia/Manila calendar date).
     */
    public function completionReady(
        Project $project,
        ?CarbonInterface $today = null
    ): bool {
        $project->loadMissing(['obligations.disbursements', 'payout']);

        if (
            $project->obligations_completed_at === null
            || ! $this->summary($project)['is_fully_paid']
            || $project->payout === null
        ) {
            return false;
        }

        return ! $this->payoutDatePending($project, $today);
    }

    public function payoutDatePending(
        Project $project,
        ?CarbonInterface $today = null
    ): bool {
        $project->loadMissing('payout');

        if ($project->payout === null) {
            return false;
        }

        $effectiveDate = $today
            ? CarbonImmutable::instance($today)->setTimezone('Asia/Manila')->startOfDay()
            : CarbonImmutable::now('Asia/Manila')->startOfDay();

        $payoutDate = CarbonImmutable::parse(
            $project->payout->payout_date->format('Y-m-d'),
            'Asia/Manila',
        )->startOfDay();

        return $payoutDate->gt($effectiveDate);
    }

    public function synchronizeCompletion(
        Project $project,
        int $userId
    ): bool {
        $project->load(['obligations.disbursements', 'payout']);

        if (
            $project->implementation_mode
                === ImplementationMode::DIRECT_ADMINISTRATION
            && $project->status === ProjectStatus::FOR_PAYMENT
            && $this->completionReady($project)
        ) {
            $project->setStatusTransitionContext(
                actorId: $userId,
                remarks: 'Automatic workflow: Obligations are complete, the full project cost is disbursed, and the Release of Assistance payout date has been reached.',
            );

            $project->update([
                'status' => ProjectStatus::COMPLETED,
                'updated_by' => $userId,
            ]);

            $project->clearStatusTransitionContext();

            return true;
        }

        return false;
    }
}
