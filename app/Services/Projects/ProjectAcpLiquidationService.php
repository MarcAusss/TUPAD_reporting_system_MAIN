<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectAcpLiquidation;

class ProjectAcpLiquidationService
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

    public function requiredCents(Project $project): int
    {
        $project->loadMissing('acpCheckRelease');

        return $project->acpCheckRelease
            ? $this->amountToCents($project->acpCheckRelease->amount)
            : 0;
    }

    public function liquidatedCents(Project $project): int
    {
        $project->loadMissing('acpLiquidations');

        return $project->acpLiquidations->sum(
            fn (ProjectAcpLiquidation $liquidation): int =>
                $this->amountToCents($liquidation->amount)
        );
    }

    /**
     * @return array{
     *   required_cents: int,
     *   liquidated_cents: int,
     *   remaining_cents: int,
     *   progress_percent: int,
     *   has_liquidation: bool,
     *   is_fully_liquidated: bool
     * }
     */
    public function summary(Project $project): array
    {
        $required = $this->requiredCents($project);
        $liquidated = $this->liquidatedCents($project);

        return [
            'required_cents' => $required,
            'liquidated_cents' => $liquidated,
            'remaining_cents' => max(0, $required - $liquidated),
            'progress_percent' => $required > 0
                ? min(100, (int) floor(($liquidated * 100) / $required))
                : 0,
            'has_liquidation' => $liquidated > 0,
            'is_fully_liquidated' =>
                $required > 0
                && $liquidated === $required,
        ];
    }
}
