<?php

namespace App\Services\Projects;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Mapping\BicolGeographicFoundation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ProjectRegistryService
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
        private readonly BicolGeographicFoundation $bicol,
    ) {}

    /**
     * Build all authorization-aware Project Registry data from an already
     * validated HTTP filter payload.
     *
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function viewData(User $user, array $validated): array
    {
        $filters = $this->normalizeFilters($validated);
        $provinces = $this->availableProvinces($user);

        $this->assertProvinceIsAvailable(
            $filters['province_id'],
            $provinces,
        );

        $municipalities = $this->availableMunicipalities(
            $user,
            $filters['province_id'],
        );

        $this->assertMunicipalityIsAvailable(
            $filters['municipality_id'],
            $municipalities,
        );

        $baseQuery = $this->provinceAccess->scopeProjects(
            Project::query(),
            $user,
        );

        $fiscalYears = $this->fiscalYears(clone $baseQuery);

        $projectsQuery = (clone $baseQuery)->with([
            'allocation.adl',
            'approval',
            'creator',
            'provinceReference',
            'municipalityReference',
            'barangayReference',
            'projectLocations.province',
            'projectLocations.municipality',
            'projectLocations.barangays',
        ]);

        $this->applySearch($projectsQuery, $filters['q']);
        $this->applyFilters($projectsQuery, $filters);
        $this->applySort($projectsQuery, $filters['sort']);

        return [
            'projects' => $projectsQuery
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'provinces' => $provinces,
            'municipalities' => $municipalities,
            'fiscalYears' => $fiscalYears,
            'statuses' => ProjectStatus::cases(),
            'implementationModes' => ImplementationMode::cases(),
            'assignedProvince' => $this->provinceAccess->isProvinceScoped($user)
                ? $provinces->first()
                : null,
        ];
    }

    /** @param array<string, mixed> $validated
     *  @return array{q:string,status:mixed,implementation_mode:mixed,province_id:?int,municipality_id:?int,fiscal_year:?int,sort:string}
     */
    private function normalizeFilters(array $validated): array
    {
        return [
            'q' => trim((string) ($validated['q'] ?? '')),
            'status' => $validated['status'] ?? null,
            'implementation_mode' => $validated['implementation_mode'] ?? null,
            'province_id' => isset($validated['province_id'])
                ? (int) $validated['province_id']
                : null,
            'municipality_id' => isset($validated['municipality_id'])
                ? (int) $validated['municipality_id']
                : null,
            'fiscal_year' => isset($validated['fiscal_year'])
                ? (int) $validated['fiscal_year']
                : null,
            'sort' => (string) ($validated['sort'] ?? 'newest'),
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

    /** @return Collection<int, Municipality> */
    private function availableMunicipalities(User $user, ?int $provinceId): Collection
    {
        $query = $this->provinceAccess
            ->scopeMunicipalities(
                Municipality::query()->with('province:id,name'),
                $user,
            )
            ->where('is_active', true)
            ->whereHas('province', function (Builder $province): void {
                $province
                    ->where('is_active', true)
                    ->whereIn('code', array_keys($this->bicol->provinceDefinitions()));
            });

        if ($provinceId !== null) {
            $query->where('province_id', $provinceId);
        }

        return $query->orderBy('name')->get();
    }

    /** @param Collection<int, Province> $provinces */
    private function assertProvinceIsAvailable(?int $provinceId, Collection $provinces): void
    {
        if ($provinceId === null || $provinces->contains('id', $provinceId)) {
            return;
        }

        throw ValidationException::withMessages([
            'province_id' => 'The selected province is outside the available Bicol project scope.',
        ]);
    }

    /** @param Collection<int, Municipality> $municipalities */
    private function assertMunicipalityIsAvailable(?int $municipalityId, Collection $municipalities): void
    {
        if ($municipalityId === null || $municipalities->contains('id', $municipalityId)) {
            return;
        }

        throw ValidationException::withMessages([
            'municipality_id' => 'The selected municipality is outside the available province scope.',
        ]);
    }

    /** @return Collection<int, int> */
    private function fiscalYears(Builder $query): Collection
    {
        return $query
            ->whereNotNull('date_received')
            ->get(['date_received'])
            ->pluck('date_received')
            ->filter()
            ->map(fn ($date): int => (int) $date->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $project) use ($search): void {
            $like = '%'.$search.'%';

            $project
                ->where('project_title', 'like', $like)
                ->orWhere('project_series', 'like', $like)
                ->orWhere('fund_sponsor', 'like', $like)
                ->orWhere('partner', 'like', $like)
                ->orWhere('province', 'like', $like)
                ->orWhere('municipality', 'like', $like)
                ->orWhere('barangay', 'like', $like)
                ->orWhereHas(
                    'approval',
                    fn (Builder $approval) => $approval->where('project_code', 'like', $like),
                )
                ->orWhereHas(
                    'allocation.adl',
                    fn (Builder $adl) => $adl->where('adl_number', 'like', $like),
                )
                ->orWhereHas('projectLocations', function (Builder $location) use ($like): void {
                    $location
                        ->where('district', 'like', $like)
                        ->orWhereHas(
                            'province',
                            fn (Builder $province) => $province->where('name', 'like', $like),
                        )
                        ->orWhereHas(
                            'municipality',
                            fn (Builder $municipality) => $municipality->where('name', 'like', $like),
                        )
                        ->orWhereHas(
                            'barangays',
                            fn (Builder $barangay) => $barangay->where('name', 'like', $like),
                        );
                });
        });
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['implementation_mode'] !== null) {
            $query->where('implementation_mode', $filters['implementation_mode']);
        }

        if ($filters['province_id'] !== null) {
            $provinceId = $filters['province_id'];

            $query->where(function (Builder $project) use ($provinceId): void {
                $project
                    ->whereHas(
                        'projectLocations',
                        fn (Builder $location) => $location->where('province_id', $provinceId),
                    )
                    ->orWhere(function (Builder $legacy) use ($provinceId): void {
                        $legacy
                            ->whereDoesntHave('projectLocations')
                            ->where('province_id', $provinceId);
                    });
            });
        }

        if ($filters['municipality_id'] !== null) {
            $municipalityId = $filters['municipality_id'];

            $query->where(function (Builder $project) use ($municipalityId): void {
                $project
                    ->whereHas(
                        'projectLocations',
                        fn (Builder $location) => $location->where('municipality_id', $municipalityId),
                    )
                    ->orWhere(function (Builder $legacy) use ($municipalityId): void {
                        $legacy
                            ->whereDoesntHave('projectLocations')
                            ->where('municipality_id', $municipalityId);
                    });
            });
        }

        if ($filters['fiscal_year'] !== null) {
            $query->whereYear('date_received', $filters['fiscal_year']);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query
                ->orderBy('date_received')
                ->orderBy('id'),
            'title_asc' => $query
                ->orderBy('project_title')
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
            'title_desc' => $query
                ->orderByDesc('project_title')
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
            'beneficiaries_desc' => $query
                ->orderByDesc('beneficiaries_total')
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
            'beneficiaries_asc' => $query
                ->orderBy('beneficiaries_total')
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
            'cost_desc' => $query
                ->orderByDesc('total_project_cost')
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
            'cost_asc' => $query
                ->orderBy('total_project_cost')
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
            'updated_desc' => $query
                ->orderByDesc('updated_at')
                ->orderByDesc('id'),
            default => $query
                ->orderByDesc('date_received')
                ->orderByDesc('id'),
        };
    }
}
