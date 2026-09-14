<?php

namespace App\Services\Finance;

use App\Models\AdlAllocation;
use App\Models\Project;
use App\Services\Payments\ProjectPaymentService;

class FinancialCeilingService
{
    public function __construct(
        private readonly ProjectPaymentService $paymentService,
    ) {
    }

    public function amountToCents(string|int|float $amount): int
    {
        return $this->paymentService->amountToCents($amount);
    }

    /**
     * Ceiling, committed, and remaining budget for an ADL allocation, based
     * on the total project cost of every official project created against it.
     *
     * @return array{allocation_cents: int, utilized_cents: int, remaining_cents: int}
     */
    public function allocationProjectSummary(AdlAllocation $allocation): array
    {
        $allocationCents = $this->amountToCents($allocation->amount);

        $utilizedCents = $this->amountToCents(
            (string) $allocation->projects()->sum('total_project_cost')
        );

        return [
            'allocation_cents' => $allocationCents,
            'utilized_cents' => $utilizedCents,
            'remaining_cents' => $allocationCents - $utilizedCents,
        ];
    }

    /**
     * Same as allocationProjectSummary(), but excludes one project's own
     * existing total_project_cost from "utilized". Use this when revising a
     * project's own cost — comparing the new cost against a remaining
     * balance that still includes the project's old cost would double-count
     * it and reject otherwise-valid revisions.
     *
     * @return array{allocation_cents: int, utilized_cents: int, remaining_cents: int}
     */
    public function allocationProjectSummaryExcludingProject(
        AdlAllocation $allocation,
        Project $excludedProject,
    ): array {
        $allocationCents = $this->amountToCents($allocation->amount);

        $utilizedCents = $this->amountToCents(
            (string) $allocation->projects()
                ->where('id', '!=', $excludedProject->id)
                ->sum('total_project_cost')
        );

        return [
            'allocation_cents' => $allocationCents,
            'utilized_cents' => $utilizedCents,
            'remaining_cents' => $allocationCents - $utilizedCents,
        ];
    }
}
