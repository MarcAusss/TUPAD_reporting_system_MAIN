<?php

namespace App\Services\Mapping;

use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Reports\ReportingDataService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

final class BicolMapDataService
{
    public function __construct(
        private readonly ReportingDataService $reporting,
        private readonly BicolGeographicFoundation $foundation,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    /** @param array<string,mixed> $filterInput */
    public function regionPayload(User $user, array $filterInput = []): array
    {
        $this->assertRegionViewer($user);

        unset($filterInput['province_id'], $filterInput['municipality_id'], $filterInput['barangay_id']);
        $filters = ReportFilters::fromArray($filterInput);
        $projects = $this->reporting->projects($filters);

        $beneficiaryRows = $this->reporting
            ->beneficiaryGeography($filters, ReportDimension::PROVINCE, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $projectRows = $this->reporting
            ->physicalFinancial($filters, ReportDimension::PROVINCE, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $fundRows = $this->reporting
            ->fundStatus($filters, ReportDimension::PROVINCE, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $ongoingRows = $this->ongoingProvinceRows($filters, $projects, $projectRows);

        $provinces = $this->foundation->provinces()
            ->map(function ($province) use ($beneficiaryRows, $projectRows, $fundRows, $ongoingRows): array {
                $key = (string) $province->id;
                $beneficiary = $beneficiaryRows->get($key, []);
                $projects = $projectRows->get($key, []);
                $fund = $fundRows->get($key, []);
                $ongoing = $ongoingRows->get($key, []);

                return [
                    'id' => (int) $province->id,
                    'psgc_code' => (string) $province->code,
                    'name' => (string) $province->name,
                    'beneficiaries' => (int) ($beneficiary['beneficiaries_total'] ?? 0),
                    'beneficiaries_female' => (int) ($beneficiary['beneficiaries_female'] ?? 0),
                    'projects' => (int) ($projects['project_count'] ?? 0),
                    'ongoing_projects' => (int) ($ongoing['project_count'] ?? 0),
                    'completed_projects' => (int) ($projects['completed_project_count'] ?? 0),
                    'allocation_cents' => (int) ($fund['allocation_cents'] ?? 0),
                    'allocation_available' => true,
                    'has_complete_exact_allocation' => (bool) ($beneficiary['has_complete_exact_allocation'] ?? true),
                    'legacy_unallocated_project_count' => (int) ($beneficiary['legacy_unallocated_project_count'] ?? 0),
                    'value' => (int) ($beneficiary['beneficiaries_total'] ?? 0),
                ];
            })
            ->sortByDesc('value')
            ->values();

        $overallProjects = $this->reporting
            ->physicalFinancial($filters, ReportDimension::OVERALL, $projects)
            ->first() ?? [];
        $overallFund = $this->reporting
            ->fundStatus($filters, ReportDimension::OVERALL, $projects)
            ->first() ?? [];

        $ongoingTotal = $filters->status !== null
            ? ($filters->status === ProjectStatus::ONGOING_IMPLEMENTATION
                ? (int) ($overallProjects['project_count'] ?? 0)
                : 0)
            : (int) $ongoingRows->sum('project_count');

        $boundaryPath = trim((string) config('tupad_mapping.public_path', 'geojson/bicol'), '/\\')
            .'/provinces.geojson';

        return [
            'map_level' => 'region',
            'viewer_scope' => $this->viewerScope($user),
            'region' => [
                'psgc_code' => $this->foundation->regionCode(),
                'name' => (string) config('tupad_mapping.region.name', 'Bicol Region'),
            ],
            'selected_province' => null,
            'selected_municipality' => null,
            'breadcrumb' => [
                ['level' => 'region', 'label' => (string) config('tupad_mapping.region.name', 'Bicol Region')],
            ],
            'metric' => $this->beneficiaryMetric(),
            'areas' => $provinces->all(),
            'provinces' => $provinces->all(),
            'municipalities' => [],
            'barangays' => [],
            'summary' => [
                'beneficiaries' => (int) $provinces->sum('beneficiaries'),
                'projects' => (int) ($overallProjects['project_count'] ?? 0),
                'ongoing_projects' => $ongoingTotal,
                'completed_projects' => (int) ($overallProjects['completed_project_count'] ?? 0),
                'allocation_cents' => (int) ($overallFund['allocation_cents'] ?? 0),
                'allocation_available' => true,
                'areas_needing_review' => $provinces
                    ->filter(fn (array $province): bool => ! $province['has_complete_exact_allocation'])
                    ->count(),
            ],
            'boundary' => [
                'ready' => is_file($this->foundation->regionBoundaryPath()),
                'path' => $boundaryPath,
                'url' => asset($boundaryPath),
                'join_key' => 'properties.psgc_code',
            ],
            'label_boundary' => null,
            'filters' => $this->filterPayload($filters),
            'data_note' => 'Map and chart use the same exact province beneficiary allocation dataset. Legacy unallocated beneficiary records are never divided among areas.',
        ];
    }

    /** @param array<string,mixed> $filterInput */
    public function provincePayload(User $user, int $provinceId, array $filterInput = []): array
    {
        $this->assertMapViewer($user);

        $province = $this->foundation->provinceById($provinceId);
        if (! $province) {
            throw new AuthorizationException('The selected province is outside the active Bicol Region mapping scope.');
        }

        $this->assertProvinceAccess($user, $province);

        unset($filterInput['municipality_id'], $filterInput['barangay_id']);
        $filterInput['province_id'] = (int) $province->id;
        $filters = ReportFilters::fromArray($filterInput);
        $projects = $this->reporting->projects($filters);
        $municipalities = $this->municipalityRows($province, $filters, $projects);

        $overallProjects = $this->reporting
            ->physicalFinancial($filters, ReportDimension::OVERALL, $projects)
            ->first() ?? [];
        $overallFund = $this->reporting
            ->fundStatus($filters, ReportDimension::OVERALL, $projects)
            ->first() ?? [];

        $ongoingTotal = $filters->status !== null
            ? ($filters->status === ProjectStatus::ONGOING_IMPLEMENTATION
                ? (int) ($overallProjects['project_count'] ?? 0)
                : 0)
            : (int) $projects
                ->filter(fn (Project $project): bool => $project->status === ProjectStatus::ONGOING_IMPLEMENTATION)
                ->count();

        $boundaryPath = $this->foundation->municipalityBoundaryRelativePath((string) $province->code);

        return [
            'map_level' => 'province',
            'viewer_scope' => $this->viewerScope($user),
            'region' => [
                'psgc_code' => $this->foundation->regionCode(),
                'name' => (string) config('tupad_mapping.region.name', 'Bicol Region'),
            ],
            'selected_province' => $this->provincePayloadRow($province),
            'selected_municipality' => null,
            'breadcrumb' => [
                ['level' => 'region', 'label' => (string) config('tupad_mapping.region.name', 'Bicol Region')],
                ['level' => 'province', 'label' => (string) $province->name],
            ],
            'metric' => $this->beneficiaryMetric(),
            'areas' => $municipalities->all(),
            'provinces' => [],
            'municipalities' => $municipalities->all(),
            'barangays' => [],
            'summary' => [
                'beneficiaries' => (int) $municipalities->sum('beneficiaries'),
                'projects' => (int) ($overallProjects['project_count'] ?? 0),
                'ongoing_projects' => $ongoingTotal,
                'completed_projects' => (int) ($overallProjects['completed_project_count'] ?? 0),
                'allocation_cents' => (int) ($overallFund['allocation_cents'] ?? 0),
                'allocation_available' => true,
                'areas_needing_review' => $municipalities
                    ->filter(fn (array $municipality): bool => ! $municipality['has_complete_exact_allocation'])
                    ->count(),
            ],
            'boundary' => [
                'ready' => is_file($this->foundation->municipalityBoundaryPath((string) $province->code)),
                'path' => $boundaryPath,
                'url' => asset($boundaryPath),
                'join_key' => 'properties.psgc_code',
            ],
            'label_boundary' => null,
            'filters' => $this->filterPayload($filters),
            'data_note' => 'Municipality map and ranking use exact beneficiary allocations from project-location/barangay records. Project money is not divided or inferred across municipalities.',
        ];
    }

    /** @param array<string,mixed> $filterInput */
    public function municipalityPayload(
        User $user,
        int $provinceId,
        int $municipalityId,
        array $filterInput = [],
    ): array {
        $this->assertMapViewer($user);

        $province = $this->foundation->provinceById($provinceId);
        if (! $province) {
            throw new AuthorizationException('The selected province is outside the active Bicol Region mapping scope.');
        }

        $this->assertProvinceAccess($user, $province);

        $municipality = $this->foundation->municipalityById((int) $province->id, $municipalityId);
        if (! $municipality) {
            throw new AuthorizationException('The selected municipality/city is outside the selected Bicol province.');
        }

        unset($filterInput['barangay_id']);

        // Keep the visible municipality choropleth province-wide while the
        // detail/chart cohort is restricted to the server-validated municipality.
        $provinceInput = $filterInput;
        unset($provinceInput['municipality_id']);
        $provinceInput['province_id'] = (int) $province->id;
        $provinceFilters = ReportFilters::fromArray($provinceInput);
        $provinceProjects = $this->reporting->projects($provinceFilters);
        $municipalities = $this->municipalityRows($province, $provinceFilters, $provinceProjects);

        $detailInput = $filterInput;
        $detailInput['province_id'] = (int) $province->id;
        $detailInput['municipality_id'] = (int) $municipality->id;
        $detailFilters = ReportFilters::fromArray($detailInput);
        $projects = $this->reporting->projects($detailFilters);

        $beneficiaryRows = $this->reporting
            ->beneficiaryGeography($detailFilters, ReportDimension::BARANGAY, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $projectRows = $this->reporting
            ->physicalFinancial($detailFilters, ReportDimension::BARANGAY, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $ongoingRows = $this->statusGeographyRows(
            $detailFilters,
            $projects,
            ProjectStatus::ONGOING_IMPLEMENTATION,
            ReportDimension::BARANGAY,
            $beneficiaryRows,
        );
        $completedRows = $this->statusGeographyRows(
            $detailFilters,
            $projects,
            ProjectStatus::COMPLETED,
            ReportDimension::BARANGAY,
            $beneficiaryRows,
        );

        $barangays = $this->foundation->barangaysForMunicipality($municipality)
            ->map(function ($barangay) use ($beneficiaryRows, $projectRows, $ongoingRows, $completedRows): array {
                $key = (string) $barangay->id;
                $beneficiary = $beneficiaryRows->get($key, []);
                $project = $projectRows->get($key, []);
                $ongoing = $ongoingRows->get($key, []);
                $completed = $completedRows->get($key, []);

                return [
                    'id' => (int) $barangay->id,
                    'municipality_id' => (int) $barangay->municipality_id,
                    'psgc_code' => (string) $barangay->code,
                    'name' => (string) $barangay->name,
                    'beneficiaries' => (int) ($beneficiary['beneficiaries_total'] ?? 0),
                    'beneficiaries_female' => (int) ($beneficiary['beneficiaries_female'] ?? 0),
                    'projects' => (int) ($project['project_count'] ?? 0),
                    'ongoing_projects' => (int) ($ongoing['project_count'] ?? 0),
                    'completed_projects' => (int) ($completed['project_count'] ?? 0),
                    'allocation_cents' => null,
                    'allocation_available' => false,
                    'allocation_note' => 'No authoritative barangay-level financial split exists.',
                    'has_complete_exact_allocation' => (bool) ($beneficiary['has_complete_exact_allocation'] ?? true),
                    'legacy_unallocated_project_count' => (int) ($beneficiary['legacy_unallocated_project_count'] ?? 0),
                    'value' => (int) ($beneficiary['beneficiaries_total'] ?? 0),
                ];
            })
            ->sortByDesc('value')
            ->values();

        $overallProjects = $this->reporting
            ->physicalFinancial($detailFilters, ReportDimension::OVERALL, $projects)
            ->first() ?? [];
        $ongoingTotal = $detailFilters->status !== null
            ? ($detailFilters->status === ProjectStatus::ONGOING_IMPLEMENTATION
                ? (int) ($overallProjects['project_count'] ?? 0)
                : 0)
            : (int) $projects
                ->filter(fn (Project $project): bool => $project->status === ProjectStatus::ONGOING_IMPLEMENTATION)
                ->count();

        $boundaryPath = $this->foundation->municipalityBoundaryRelativePath((string) $province->code);
        $labelPath = $this->foundation->barangayLabelRelativePath((string) $municipality->code);
        $selectedMunicipalityRow = $municipalities->firstWhere('id', (int) $municipality->id) ?? [];

        return [
            'map_level' => 'municipality',
            'viewer_scope' => $this->viewerScope($user),
            'region' => [
                'psgc_code' => $this->foundation->regionCode(),
                'name' => (string) config('tupad_mapping.region.name', 'Bicol Region'),
            ],
            'selected_province' => $this->provincePayloadRow($province),
            'selected_municipality' => [
                'id' => (int) $municipality->id,
                'province_id' => (int) $municipality->province_id,
                'psgc_code' => (string) $municipality->code,
                'name' => (string) $municipality->name,
                'beneficiaries' => (int) ($selectedMunicipalityRow['beneficiaries'] ?? $barangays->sum('beneficiaries')),
            ],
            'breadcrumb' => [
                ['level' => 'region', 'label' => (string) config('tupad_mapping.region.name', 'Bicol Region')],
                ['level' => 'province', 'label' => (string) $province->name],
                ['level' => 'municipality', 'label' => (string) $municipality->name],
            ],
            'metric' => $this->beneficiaryMetric(),
            // `areas` remains the authoritative Chart.js/ranking dataset.
            'areas' => $barangays->all(),
            // The Leaflet polygon layer remains municipality-level in Phase 4.
            'provinces' => [],
            'municipalities' => $municipalities->all(),
            'barangays' => $barangays->all(),
            'summary' => [
                'beneficiaries' => (int) $barangays->sum('beneficiaries'),
                'projects' => (int) ($overallProjects['project_count'] ?? 0),
                'ongoing_projects' => $ongoingTotal,
                'completed_projects' => (int) ($overallProjects['completed_project_count'] ?? 0),
                'allocation_cents' => null,
                'allocation_available' => false,
                'areas_needing_review' => $barangays
                    ->filter(fn (array $barangay): bool => ! $barangay['has_complete_exact_allocation'])
                    ->count(),
            ],
            'boundary' => [
                'ready' => is_file($this->foundation->municipalityBoundaryPath((string) $province->code)),
                'path' => $boundaryPath,
                'url' => asset($boundaryPath),
                'join_key' => 'properties.psgc_code',
            ],
            'label_boundary' => [
                'ready' => is_file($this->foundation->barangayLabelPath((string) $municipality->code)),
                'path' => $labelPath,
                'url' => asset($labelPath),
                'join_key' => 'properties.psgc_code',
                'geometry' => 'Point',
            ],
            'filters' => $this->filterPayload($detailFilters),
            'data_note' => 'Selected-municipality chart rows use exact barangay beneficiary allocations. Municipality polygons stay visible for geographic context; barangay names are a lazy label-only layer. Financial values are not divided across barangays.',
        ];
    }

    /** @return Collection<int,array<string,mixed>> */
    private function municipalityRows(Province $province, ReportFilters $filters, Collection $projects): Collection
    {
        $beneficiaryRows = $this->reporting
            ->beneficiaryGeography($filters, ReportDimension::MUNICIPALITY, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $projectRows = $this->reporting
            ->physicalFinancial($filters, ReportDimension::MUNICIPALITY, $projects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
        $ongoingRows = $this->statusGeographyRows(
            $filters,
            $projects,
            ProjectStatus::ONGOING_IMPLEMENTATION,
            ReportDimension::MUNICIPALITY,
            $beneficiaryRows,
        );
        $completedRows = $this->statusGeographyRows(
            $filters,
            $projects,
            ProjectStatus::COMPLETED,
            ReportDimension::MUNICIPALITY,
            $beneficiaryRows,
        );

        return $this->foundation->municipalitiesForProvince($province)
            ->map(function ($municipality) use ($beneficiaryRows, $projectRows, $ongoingRows, $completedRows): array {
                $key = (string) $municipality->id;
                $beneficiary = $beneficiaryRows->get($key, []);
                $project = $projectRows->get($key, []);
                $ongoing = $ongoingRows->get($key, []);
                $completed = $completedRows->get($key, []);

                return [
                    'id' => (int) $municipality->id,
                    'province_id' => (int) $municipality->province_id,
                    'psgc_code' => (string) $municipality->code,
                    'name' => (string) $municipality->name,
                    'beneficiaries' => (int) ($beneficiary['beneficiaries_total'] ?? 0),
                    'beneficiaries_female' => (int) ($beneficiary['beneficiaries_female'] ?? 0),
                    'projects' => (int) ($project['project_count'] ?? 0),
                    'ongoing_projects' => (int) ($ongoing['project_count'] ?? 0),
                    'completed_projects' => (int) ($completed['project_count'] ?? 0),
                    'allocation_cents' => null,
                    'allocation_available' => false,
                    'allocation_note' => 'No authoritative municipality-level financial split exists.',
                    'has_complete_exact_allocation' => (bool) ($beneficiary['has_complete_exact_allocation'] ?? true),
                    'legacy_unallocated_project_count' => (int) ($beneficiary['legacy_unallocated_project_count'] ?? 0),
                    'value' => (int) ($beneficiary['beneficiaries_total'] ?? 0),
                ];
            })
            ->sortByDesc('value')
            ->values();
    }

    private function assertRegionViewer(User $user): void
    {
        if (! $user->isFocal() && ! $user->isAdmin()) {
            throw new AuthorizationException(
                'The regional Bicol map is available only to regional viewers.'
            );
        }
    }

    private function assertMapViewer(User $user): void
    {
        if ($user->isFocal() || $user->isAdmin()) {
            return;
        }

        if (
            $user->isTc()
            && $this->provinceAccess->assignedProvinceId($user) !== null
        ) {
            return;
        }

        throw new AuthorizationException(
            'The interactive geographic map is not available to the current role or assignment.'
        );
    }

    private function assertProvinceAccess(User $user, Province $province): void
    {
        if (! $this->provinceAccess->isProvinceScoped($user)) {
            return;
        }

        if (! $this->provinceAccess->canAccessProvince($user, $province)) {
            throw new AuthorizationException(
                'TUPAD Coordinators may only access their assigned province.'
            );
        }
    }

    /** @return array{type:string,province_id:?int,can_view_region:bool} */
    private function viewerScope(User $user): array
    {
        if ($this->provinceAccess->isProvinceScoped($user)) {
            return [
                'type' => 'province',
                'province_id' => $this->provinceAccess->assignedProvinceId($user),
                'can_view_region' => false,
            ];
        }

        return [
            'type' => 'region',
            'province_id' => null,
            'can_view_region' => true,
        ];
    }

    /** @return array{id:int,psgc_code:string,name:string} */
    private function provincePayloadRow(Province $province): array
    {
        return [
            'id' => (int) $province->id,
            'psgc_code' => (string) $province->code,
            'name' => (string) $province->name,
        ];
    }

    /** @return array{key:string,label:string,description:string} */
    private function beneficiaryMetric(): array
    {
        return [
            'key' => 'beneficiaries',
            'label' => 'Beneficiaries',
            'description' => 'Exact geographically allocated beneficiaries',
        ];
    }

    /** @return array{fiscal_year:?int,quarter:?int,month:?int,status:?string,implementation_mode:?string,province_id:?int,municipality_id:?int} */
    private function filterPayload(ReportFilters $filters): array
    {
        return [
            'fiscal_year' => $filters->fiscalYear,
            'quarter' => $filters->quarter,
            'month' => $filters->month,
            'status' => $filters->status?->value,
            'implementation_mode' => $filters->implementationMode?->value,
            'province_id' => $filters->provinceId,
            'municipality_id' => $filters->municipalityId,
        ];
    }

    /**
     * @param Collection<int,Project> $projects
     * @param Collection<string,array<string,mixed>> $projectRows
     * @return Collection<string,array<string,mixed>>
     */
    private function ongoingProvinceRows(
        ReportFilters $filters,
        Collection $projects,
        Collection $projectRows,
    ): Collection {
        if ($filters->status !== null) {
            return $filters->status === ProjectStatus::ONGOING_IMPLEMENTATION
                ? $projectRows
                : collect();
        }

        $ongoingProjects = $projects
            ->filter(fn (Project $project): bool => $project->status === ProjectStatus::ONGOING_IMPLEMENTATION)
            ->values();

        if ($ongoingProjects->isEmpty()) {
            return collect();
        }

        return $this->reporting
            ->physicalFinancial($filters, ReportDimension::PROVINCE, $ongoingProjects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
    }

    /**
     * @param Collection<int,Project> $projects
     * @param Collection<string,array<string,mixed>> $baseRows
     * @return Collection<string,array<string,mixed>>
     */
    private function statusGeographyRows(
        ReportFilters $filters,
        Collection $projects,
        ProjectStatus $status,
        ReportDimension $dimension,
        Collection $baseRows,
    ): Collection {
        if ($filters->status !== null) {
            return $filters->status === $status
                ? $baseRows
                : collect();
        }

        $statusProjects = $projects
            ->filter(fn (Project $project): bool => $project->status === $status)
            ->values();

        if ($statusProjects->isEmpty()) {
            return collect();
        }

        return $this->reporting
            ->beneficiaryGeography($filters, $dimension, $statusProjects)
            ->keyBy(fn (array $row): string => (string) ($row['key'] ?? ''));
    }
}
