<?php

namespace App\Services\Dashboards;

use App\Enums\ProjectStatus;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Dashboard filters (Focal and TUPAD Coordinator dashboards). Every filter is
 * optional; an empty value
 * means "All". A TUPAD Coordinator's province is locked to their assigned
 * province. Project figures use all filters. Fund figures (ADL allocation,
 * allocated, balance) use only the filters that exist on ADLs and their
 * allocations: year, ADL, province, sponsor and partner.
 */
class DashboardFilters
{
    public const QUARTER_MONTHS = [
        1 => [1, 2, 3],
        2 => [4, 5, 6],
        3 => [7, 8, 9],
        4 => [10, 11, 12],
    ];

    public function __construct(
        public readonly ?int $year = null,
        public readonly ?int $quarter = null,
        public readonly ?int $month = null,
        public readonly ?int $provinceId = null,
        public readonly ?int $municipalityId = null,
        public readonly ?string $sponsor = null,
        public readonly ?string $partner = null,
        public readonly ?int $adlId = null,
        public readonly ?string $status = null,
        public readonly bool $provinceLocked = false,
    ) {}

    /** $lockedProvinceId: the TUPAD Coordinator's assigned province (0 when none). */
    public static function fromRequest(Request $request, ?int $lockedProvinceId = null): self
    {
        $int = function (string $key, int $min, int $max) use ($request): ?int {
            $value = $request->query($key);

            return is_numeric($value) && (int) $value >= $min && (int) $value <= $max ? (int) $value : null;
        };

        $text = function (string $key) use ($request): ?string {
            $value = trim((string) $request->query($key, ''));

            return $value === '' ? null : mb_substr($value, 0, 255);
        };

        $status = $text('status');
        $provinceId = $lockedProvinceId ?? $int('province', 1, PHP_INT_MAX);

        return new self(
            year: $int('year', 2000, 2100),
            quarter: $int('quarter', 1, 4),
            month: $int('month', 1, 12),
            provinceId: $provinceId,
            // A municipality only counts inside the chosen province.
            municipalityId: $provinceId ? $int('municipality', 1, PHP_INT_MAX) : null,
            sponsor: $text('sponsor'),
            partner: $text('partner'),
            adlId: $int('adl', 1, PHP_INT_MAX),
            status: $status !== null && ProjectStatus::tryFrom($status) ? $status : null,
            provinceLocked: $lockedProvinceId !== null,
        );
    }

    public function isActive(): bool
    {
        return $this->toArray() !== [];
    }

    /** Filters that only apply to projects, so fund figures ignore them. */
    public function hasProjectOnlyFilters(): bool
    {
        return $this->quarter !== null || $this->month !== null
            || $this->municipalityId !== null || $this->status !== null;
    }

    /** @return array<string, int|string> query-string values of the active filters */
    public function toArray(): array
    {
        return array_filter([
            'year' => $this->year,
            'quarter' => $this->quarter,
            'month' => $this->month,
            // A locked province is not a user choice, so it is not listed or cleared.
            'province' => $this->provinceLocked ? null : $this->provinceId,
            'municipality' => $this->municipalityId,
            'sponsor' => $this->sponsor,
            'partner' => $this->partner,
            'adl' => $this->adlId,
            'status' => $this->status,
        ], fn ($value) => $value !== null);
    }

    public function applyToProjects(Builder $projects): Builder
    {
        $months = $this->months();

        return $projects
            ->when($this->year, fn (Builder $query) => $query->whereYear('projects.date_received', $this->year))
            ->when($months !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($months) {
                foreach ($months as $month) {
                    $query->orWhereMonth('projects.date_received', $month);
                }
            }))
            ->when($this->provinceId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('projects.province_id', $this->provinceId)
                ->orWhereHas('projectLocations', fn (Builder $location) => $location->where('province_id', $this->provinceId))))
            ->when($this->municipalityId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('projects.municipality_id', $this->municipalityId)
                ->orWhereHas('projectLocations', fn (Builder $location) => $location->where('municipality_id', $this->municipalityId))))
            ->when($this->sponsor, fn (Builder $query) => $query->where('projects.fund_sponsor', $this->sponsor))
            ->when($this->partner, fn (Builder $query) => $query->where('projects.partner', $this->partner))
            ->when($this->adlId, fn (Builder $query) => $query->whereHas('allocation', fn (Builder $allocation) => $allocation->where('adl_id', $this->adlId)))
            ->when($this->status, fn (Builder $query) => $query->where('projects.status', $this->status));
    }

    /**
     * Fund position for the filtered ADLs and allocations.
     *
     * @return array{budget: float, allocated: float, remaining: float, utilization: float, adl_count: int}
     */
    public function fundPosition(): array
    {
        $adls = Adl::query()
            ->when($this->adlId, fn (Builder $query) => $query->whereKey($this->adlId))
            ->when($this->year, fn (Builder $query) => $query->whereYear('date_received', $this->year))
            ->get();

        $allocations = AdlAllocation::query()
            ->whereIn('adl_id', $adls->modelKeys())
            ->when($this->sponsor, fn (Builder $query) => $query->where('fund_sponsor', $this->sponsor))
            ->when($this->partner, fn (Builder $query) => $query->where('partner', $this->partner))
            ->when($this->provinceId !== null, function (Builder $query) {
                $name = Province::query()->whereKey($this->provinceId)->value('name');
                $query->whereRaw('lower(province) = ?', [mb_strtolower((string) $name)]);
            });

        $allocated = (float) $allocations->sum('amount');

        // With an allocation-level filter the "budget" is what those allocations hold.
        $allocationLevel = $this->sponsor || $this->partner || $this->provinceId !== null;
        $budget = $allocationLevel
            ? $allocated
            : (float) $adls->sum(fn (Adl $adl) => (float) ($adl->adjusted_total_grants ?? $adl->adjusted_grants ?? $adl->grants ?? 0));

        return [
            'budget' => $budget,
            'allocated' => $allocated,
            'remaining' => max(0, $budget - $allocated),
            'utilization' => $budget > 0 ? min(100, $allocated / $budget * 100) : 0,
            'adl_count' => $allocationLevel ? (int) (clone $allocations)->distinct()->count('adl_id') : $adls->count(),
        ];
    }

    /**
     * Select options for the filter bar. $projects limits sponsors, partners
     * and ADLs to what the user can see (a TUPAD Coordinator's province).
     *
     * @return array<string, mixed>
     */
    public function options(?Builder $projects = null): array
    {
        $scoped = fn (): Builder => $projects ? (clone $projects)->reorder() : Project::query();

        $range = Project::query()->selectRaw('min(date_received) as first, max(date_received) as last')->first();
        $currentYear = (int) now()->year;
        $firstYear = $range?->first ? (int) substr((string) $range->first, 0, 4) : $currentYear;
        $lastYear = max($currentYear, $range?->last ? (int) substr((string) $range->last, 0, 4) : $currentYear);

        $distinct = fn (string $column): Collection => $scoped()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        return [
            'years' => range($lastYear, min($firstYear, $lastYear)),
            'quarters' => [1 => 'Q1 (Jan–Mar)', 2 => 'Q2 (Apr–Jun)', 3 => 'Q3 (Jul–Sep)', 4 => 'Q4 (Oct–Dec)'],
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $month) => [$month => date('F', mktime(0, 0, 0, $month, 1))])->all(),
            'provinces' => Province::query()->orderBy('name')->get(['id', 'name']),
            'municipalities' => $this->provinceId !== null
                ? Municipality::query()->where('province_id', $this->provinceId)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'sponsors' => $distinct('fund_sponsor'),
            'partners' => $distinct('partner'),
            'adls' => Adl::query()
                ->when($projects, fn (Builder $query) => $query->whereIn('id', AdlAllocation::query()->whereIn('id', $scoped()->select('projects.adl_allocation_id'))->select('adl_id')))
                ->orderByDesc('date_received')->orderBy('adl_number')->get(['id', 'adl_number']),
            'statuses' => ProjectStatus::cases(),
        ];
    }

    /** @return list<int>|null months selected by the quarter and/or month filters */
    private function months(): ?array
    {
        if ($this->quarter === null && $this->month === null) {
            return null;
        }

        $months = $this->quarter ? self::QUARTER_MONTHS[$this->quarter] : range(1, 12);

        if ($this->month !== null) {
            $months = in_array($this->month, $months, true) ? [$this->month] : [];
        }

        // A month outside the chosen quarter matches nothing.
        return $months === [] ? [0] : $months;
    }
}
