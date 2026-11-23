<?php

namespace App\Services\Dashboards;

use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Fund utilization per ADL: budget (adjusted grants) vs allocated vs
 * approved project cost vs actual amount (obligation tranches / ACP payment).
 */
final class AdlUtilizationService
{
    /**
     * @param  Collection<int, Adl>|null  $adls  limit to these ADLs (all when null)
     * @return Collection<int, array{label:string,budget:float,allocated:float,project_cost:float,actual_cost:float}>
     */
    public function rows(?Collection $adls = null): Collection
    {
        $adls ??= Adl::query()->get();

        $allocated = AdlAllocation::query()
            ->selectRaw('adl_id, SUM(amount) as total')
            ->groupBy('adl_id')
            ->pluck('total', 'adl_id');

        $projectCost = Project::query()
            ->join('adl_allocations', 'adl_allocations.id', '=', 'projects.adl_allocation_id')
            ->selectRaw('adl_allocations.adl_id, SUM(projects.total_project_cost) as total')
            ->groupBy('adl_allocations.adl_id')
            ->pluck('total', 'adl_id');

        // Actual amount per ADL: obligation tranches (after completion) / ACP payment.
        $actualCost = Project::query()
            ->whereNotNull('adl_allocation_id')
            ->with(['allocation:id,adl_id', 'obligations:id,project_id,amount', 'acpPayment:id,project_id,amount'])
            ->get(['id', 'adl_allocation_id', 'implementation_mode', 'obligations_completed_at', 'total_project_cost'])
            ->groupBy(fn (Project $project) => $project->allocation?->adl_id)
            ->map(fn ($projects) => (float) $projects->sum(fn (Project $project) => $project->actualAmount() ?? 0));

        return $adls
            ->sortBy('adl_number')
            ->map(fn (Adl $adl): array => [
                'label' => (string) $adl->adl_number,
                'budget' => (float) ($adl->adjusted_total_grants ?? $adl->adjusted_grants ?? $adl->grants ?? 0),
                'allocated' => (float) ($allocated[$adl->id] ?? 0),
                'project_cost' => (float) ($projectCost[$adl->id] ?? 0),
                'actual_cost' => (float) ($actualCost[$adl->id] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['budget'] > 0 || $row['allocated'] > 0)
            ->values();
    }
}
