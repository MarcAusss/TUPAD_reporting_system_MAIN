<?php

namespace App\Services\Payments;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectObligation;

class ProjectPaymentService
{
    public function amountToCents(string|int|float $amount): int
    {
        $normalized = trim((string) $amount);
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

    public function payableCents(Project $project): int
    {
        return $this->amountToCents($project->wages_total);
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
            'is_fully_paid' =>
                $payable > 0
                && $obligated === $payable
                && $disbursed === $payable,
        ];
    }

    public function synchronizeCompletion(
        Project $project,
        int $userId
    ): bool {
        $project->load('obligations.disbursements');

        if (
            $project->status === ProjectStatus::FOR_PAYMENT
            && $this->summary($project)['is_fully_paid']
        ) {
            $project->setStatusTransitionContext(
                actorId: $userId,
                remarks: 'Automatic workflow: The full payable wage amount is obligated and disbursed.',
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
