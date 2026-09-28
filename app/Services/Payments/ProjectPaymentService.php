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

    /**
     * Wage per beneficiary, matching the project formula
     * wages_total = wage_rate × beneficiaries × number_of_days.
     */
    public function wagePerBeneficiaryCents(Project $project): int
    {
        return (int) round(
            (float) $project->wage_rate * (int) $project->number_of_days * 100
        );
    }

    /**
     * Insurance per beneficiary, matching the project formula
     * insurance_total = insurance_rate × insured beneficiaries.
     */
    public function insurancePerBeneficiaryCents(Project $project): int
    {
        return (int) round((float) $project->insurance_rate * 100);
    }

    /**
     * The project's own figures, which obligation tranches may add up to
     * but never exceed.
     *
     * @return array{beneficiaries_total:int,beneficiaries_female:int,wages:int,insurance:int,ppe:int,total:int}
     */
    public function obligationLimits(Project $project): array
    {
        return [
            'beneficiaries_total' => (int) $project->beneficiaries_total,
            'beneficiaries_female' => (int) $project->beneficiaries_female,
            'wages' => $this->amountToCents($project->wages_total),
            'insurance' => $this->amountToCents($project->insurance_total),
            'ppe' => $this->amountToCents($project->ppe_total),
            'total' => $this->payableCents($project),
        ];
    }

    /**
     * @param  iterable<ProjectObligation>  $obligations
     * @return array{beneficiaries_total:int,beneficiaries_female:int,wages:int,insurance:int,ppe:int,total:int}
     */
    public function obligatedTotals(iterable $obligations): array
    {
        $totals = array_fill_keys(
            ['beneficiaries_total', 'beneficiaries_female', 'wages', 'insurance', 'ppe', 'total'],
            0
        );

        foreach ($obligations as $obligation) {
            $totals['beneficiaries_total'] += (int) $obligation->beneficiaries_total;
            $totals['beneficiaries_female'] += (int) $obligation->beneficiaries_female;
            $totals['wages'] += $this->amountToCents($obligation->wages_amount);
            $totals['insurance'] += $this->amountToCents($obligation->insurance_amount);
            $totals['ppe'] += $this->amountToCents($obligation->ppe_amount);
            $totals['total'] += $this->obligationCents($obligation);
        }

        return $totals;
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
        $completed = $project->obligations_completed_at !== null;

        // Tranches may be completed below the project cost, so once they are
        // locked the completed obligation total is what must be disbursed.
        $disbursementBasis = $completed ? $obligated : $payable;

        return [
            'payable_cents' => $payable,
            'obligated_cents' => $obligated,
            'disbursed_cents' => $disbursed,
            'disbursement_basis_cents' => $disbursementBasis,
            'unobligated_cents' => max(0, $payable - $obligated),
            'remaining_cents' => max(0, $disbursementBasis - $disbursed),
            'progress_percent' => $disbursementBasis > 0
                ? min(100, (int) floor(($disbursed * 100) / $disbursementBasis))
                : 0,
            'is_fully_obligated' =>
                $payable > 0
                && $obligated === $payable,
            'obligations_completed' => $completed,
            'is_fully_paid' =>
                $disbursementBasis > 0
                && $obligated === $disbursementBasis
                && $disbursed === $disbursementBasis,
        ];
    }

    public function trancheFullyDisbursed(ProjectObligation $obligation): bool
    {
        $obligated = $this->obligationCents($obligation);

        return $obligated > 0
            && $this->disbursedForObligationCents($obligation) >= $obligated;
    }

    /**
     * Where a tranche stands in the obligation → disbursement → release flow:
     * awaiting_disbursement (Focal), ready_for_release (TUPAD Coordinator),
     * release_scheduled (payout date not reached), or released.
     */
    public function trancheReleaseState(
        ProjectObligation $obligation,
        ?CarbonInterface $today = null
    ): string {
        if (! $this->trancheFullyDisbursed($obligation)) {
            return 'awaiting_disbursement';
        }

        if (! $obligation->isReleased()) {
            return 'ready_for_release';
        }

        return $this->releaseDatePending($obligation, $today)
            ? 'release_scheduled'
            : 'released';
    }

    public function releaseDatePending(
        ProjectObligation $obligation,
        ?CarbonInterface $today = null
    ): bool {
        if ($obligation->release_date === null) {
            return false;
        }

        $releaseDate = CarbonImmutable::parse(
            $obligation->release_date->format('Y-m-d'),
            'Asia/Manila',
        )->startOfDay();

        return $releaseDate->gt($this->manilaToday($today));
    }

    /**
     * Per-tranche Release of Assistance progress for the project.
     *
     * @return array{tranche_count:int,awaiting_disbursement:int,ready_for_release:int,release_scheduled:int,released:int,all_released:bool,all_release_dates_reached:bool}
     */
    public function releaseSummary(
        Project $project,
        ?CarbonInterface $today = null
    ): array {
        $project->loadMissing('obligations.disbursements');

        $states = $project->obligations
            ->map(fn (ProjectObligation $obligation): string => $this->trancheReleaseState($obligation, $today))
            ->countBy();

        $trancheCount = $project->obligations->count();
        $released = (int) ($states['released'] ?? 0);
        $scheduled = (int) ($states['release_scheduled'] ?? 0);

        return [
            'tranche_count' => $trancheCount,
            'awaiting_disbursement' => (int) ($states['awaiting_disbursement'] ?? 0),
            'ready_for_release' => (int) ($states['ready_for_release'] ?? 0),
            'release_scheduled' => $scheduled,
            'released' => $released,
            'all_released' => $trancheCount > 0 && $released + $scheduled === $trancheCount,
            'all_release_dates_reached' => $trancheCount > 0 && $released === $trancheCount,
        ];
    }

    /**
     * A Direct Administration project completes only when:
     * - the Focal completed the obligation tranches,
     * - every tranche is fully disbursed,
     * - the TUPAD Coordinator recorded a Release of Assistance for every
     *   tranche, and
     * - every release date has been reached (Asia/Manila calendar date).
     */
    public function completionReady(
        Project $project,
        ?CarbonInterface $today = null
    ): bool {
        $project->loadMissing('obligations.disbursements');

        return $project->obligations_completed_at !== null
            && $this->summary($project)['is_fully_paid']
            && $this->releaseSummary($project, $today)['all_release_dates_reached'];
    }

    private function manilaToday(?CarbonInterface $today): CarbonImmutable
    {
        return $today
            ? CarbonImmutable::instance($today)->setTimezone('Asia/Manila')->startOfDay()
            : CarbonImmutable::now('Asia/Manila')->startOfDay();
    }

    public function synchronizeCompletion(
        Project $project,
        int $userId
    ): bool {
        $project->load('obligations.disbursements');

        if (
            $project->implementation_mode
                === ImplementationMode::DIRECT_ADMINISTRATION
            && $project->status === ProjectStatus::FOR_PAYMENT
            && $this->completionReady($project)
        ) {
            $project->setStatusTransitionContext(
                actorId: $userId,
                remarks: 'Automatic workflow: Obligations are complete, every tranche is disbursed and released, and all Release of Assistance dates have been reached.',
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
