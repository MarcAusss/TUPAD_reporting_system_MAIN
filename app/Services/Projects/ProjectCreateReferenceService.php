<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\PpeType;
use App\Models\AdlAllocation;
use App\Models\Province;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Mapping\BicolGeographicFoundation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ProjectCreateReferenceService
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
        private readonly BicolGeographicFoundation $bicol,
    ) {}

    /** @return array<string, mixed> */
    public function viewData(User $user): array
    {
        $allocationQuery = $this->provinceAccess->scopeAdlAllocations(
            AdlAllocation::query(),
            $user,
        );

        return [
            'allocations' => (clone $allocationQuery)
                ->with('adl')
                ->orderByDesc('id')
                ->get(),
            'provinces' => $this->availableProvinces($user),
            'fundSponsorOptions' => $this->distinctReferenceValues(
                clone $allocationQuery,
                'fund_sponsor',
            ),
            'partnerOptions' => $this->distinctReferenceValues(
                clone $allocationQuery,
                'partner',
            ),
            'implementationModes' => ImplementationMode::cases(),
            'ppeTypes' => PpeType::cases(),
        ];
    }

    /** @return Collection<int, Province> */
    private function availableProvinces(User $user): Collection
    {
        return $this->provinceAccess
            ->scopeProvinces(Province::query(), $user)
            ->where('is_active', true)
            ->whereIn('code', array_keys($this->bicol->provinceDefinitions()))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, string> */
    private function distinctReferenceValues(Builder $query, string $column): Collection
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
    }
}
