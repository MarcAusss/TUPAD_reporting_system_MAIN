<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectProvinceSummaryController extends Controller
{
    /**
     * Sidebar entry: show all Bicol provinces that currently have project data.
     */
    public function index(
        Request $request
    ): View {
        $this->authorizeSummaryUser(
            $request
        );

        $provinces =
            Province::query()
                ->where(
                    'is_active',
                    true
                )
                ->whereIn(
                    'name',
                    [
                        'Albay',
                        'Camarines Norte',
                        'Camarines Sur',
                        'Catanduanes',
                        'Masbate',
                        'Sorsogon',
                    ]
                )
                ->orderBy('name')
                ->get()
                ->map(
                    function (
                        Province $province
                    ) {
                        $projects =
                            $this
                                ->projectsForProvince(
                                    $province
                                );

                        $municipalityIds =
                            $projects
                                ->pluck(
                                    'municipality_id'
                                )
                                ->filter();

                        foreach (
                            $projects
                            as $project
                        ) {
                            $municipalityIds->push(
                                ...$project
                                    ->projectLocations
                                    ->pluck(
                                        'municipality_id'
                                    )
                                    ->filter()
                                    ->all()
                            );
                        }

                        return [
                            'province' =>
                                $province,

                            'project_count' =>
                                $projects->count(),

                            'municipality_count' =>
                                $municipalityIds
                                    ->unique()
                                    ->count(),

                            'beneficiaries' =>
                                (int) $projects
                                    ->sum(
                                        'beneficiaries_total'
                                    ),

                            'female_beneficiaries' =>
                                (int) $projects
                                    ->sum(
                                        'beneficiaries_female'
                                    ),

                            'amount_assisted' =>
                                (float) $projects
                                    ->sum(
                                        'total_project_cost'
                                    ),
                        ];
                    }
                );

        return view(
            'projects.province-summary-index',
            [
                'provinces' =>
                    $provinces,
            ]
        );
    }

    /**
     * Existing Projects -> Summary action.
     *
     * A Project Summary is scoped to exactly ONE project. The project may
     * cover multiple municipalities and districts inside its province, but
     * other projects from the same province must never appear here.
     */
    public function show(
        Request $request,
        Project $project
    ): View {
        $this->authorizeSummaryUser(
            $request
        );

        $project->loadMissing([
            'provinceReference',
            'projectLocations.province',
        ]);

        $province =
            $project->provinceReference
            ?? $project
                ->projectLocations
                ->first()
                ?->province
            ?? Province::query()
                ->where(
                    'name',
                    $project->province
                )
                ->first();

        abort_if(
            ! $province,
            404,
            'The project does not have a valid province reference.'
        );

        return $this->renderProvinceSummary(
            $province,
            $project
        );
    }

    /**
     * Sidebar Province Summary -> select a province.
     */
    public function province(
        Request $request,
        Province $province
    ): View {
        $this->authorizeSummaryUser(
            $request
        );

        abort_unless(
            $province->is_active,
            404
        );

        return $this->renderProvinceSummary(
            $province
        );
    }

    private function renderProvinceSummary(
        Province $province,
        ?Project $sourceProject = null
    ): View {
        /*
        |--------------------------------------------------------------------------
        | Project-driven geography with exact beneficiary allocation
        |--------------------------------------------------------------------------
        |
        | New projects store beneficiary totals on the project/location/barangay
        | pivot. Older multi-barangay projects may still have NULL allocation
        | values because their historic project total cannot be split safely.
        |
        */

        $province->load([
            'municipalities' => fn ($query) =>
                $query
                    ->where('is_active', true)
                    ->orderBy('district')
                    ->orderBy('name'),

            'municipalities.barangays' => fn ($query) =>
                $query
                    ->where('is_active', true)
                    ->orderBy('name'),
        ]);

        $projects = $sourceProject
            ? $this->singleProjectCollection(
                $sourceProject
            )
            : $this->projectsForProvince(
                $province
            );

        $entriesByBarangay = [];
        $entriesByMunicipality = [];
        $hasLegacyCoverage = false;

        foreach ($projects as $provinceProject) {
            $hasStructuredLocation = false;

            foreach ($provinceProject->projectLocations as $location) {
                $hasStructuredLocation = true;

                $locationHasExactAllocation =
                    $location->barangays->isNotEmpty()
                    && $location->barangays->every(
                        fn ($barangay) =>
                            $barangay->pivot->beneficiaries_total !== null
                            && $barangay->pivot->beneficiaries_female !== null
                    );

                if (! $locationHasExactAllocation) {
                    $hasLegacyCoverage = true;
                }

                $municipalityBeneficiaries = $locationHasExactAllocation
                    ? (int) $location->barangays->sum(
                        fn ($barangay) =>
                            (int) $barangay->pivot->beneficiaries_total
                    )
                    : (int) $provinceProject->beneficiaries_total;

                $municipalityFemaleBeneficiaries = $locationHasExactAllocation
                    ? (int) $location->barangays->sum(
                        fn ($barangay) =>
                            (int) $barangay->pivot->beneficiaries_female
                    )
                    : (int) $provinceProject->beneficiaries_female;

                if ($location->municipality_id) {
                    $entriesByMunicipality[
                        $location->municipality_id
                    ] ??= collect();

                    $entriesByMunicipality[
                        $location->municipality_id
                    ]->push([
                        'project' => $provinceProject,
                        'beneficiaries' => $municipalityBeneficiaries,
                        'female_beneficiaries' =>
                            $municipalityFemaleBeneficiaries,
                        'is_exact' => $locationHasExactAllocation,
                    ]);
                }

                foreach ($location->barangays as $barangay) {
                    $barangayHasExactAllocation =
                        $barangay->pivot->beneficiaries_total !== null
                        && $barangay->pivot->beneficiaries_female !== null;

                    if (! $barangayHasExactAllocation) {
                        $hasLegacyCoverage = true;
                    }

                    $entriesByBarangay[
                        $barangay->id
                    ] ??= collect();

                    $entriesByBarangay[
                        $barangay->id
                    ]->push([
                        'project' => $provinceProject,

                        'beneficiaries' =>
                            $barangayHasExactAllocation
                                ? (int) $barangay->pivot->beneficiaries_total
                                : (int) $provinceProject->beneficiaries_total,

                        'female_beneficiaries' =>
                            $barangayHasExactAllocation
                                ? (int) $barangay->pivot->beneficiaries_female
                                : (int) $provinceProject->beneficiaries_female,

                        'is_exact' => $barangayHasExactAllocation,
                    ]);
                }
            }

            if ($hasStructuredLocation) {
                continue;
            }

            /*
             * Legacy fallback for records created before project_locations.
             */
            $hasLegacyCoverage = true;

            $municipalityId = $provinceProject->municipality_id;
            $barangayId = $provinceProject->barangay_id;

            if (
                ! $municipalityId
                && filled($provinceProject->municipality)
            ) {
                $municipalityId = $province
                    ->municipalities
                    ->first(
                        fn ($municipality) =>
                            mb_strtolower(trim($municipality->name))
                            === mb_strtolower(
                                trim($provinceProject->municipality)
                            )
                    )
                    ?->id;
            }

            if ($municipalityId) {
                $entriesByMunicipality[
                    $municipalityId
                ] ??= collect();

                $entriesByMunicipality[
                    $municipalityId
                ]->push([
                    'project' => $provinceProject,
                    'beneficiaries' =>
                        (int) $provinceProject->beneficiaries_total,
                    'female_beneficiaries' =>
                        (int) $provinceProject->beneficiaries_female,
                    'is_exact' => false,
                ]);
            }

            if ($barangayId) {
                $entriesByBarangay[
                    $barangayId
                ] ??= collect();

                $entriesByBarangay[
                    $barangayId
                ]->push([
                    'project' => $provinceProject,
                    'beneficiaries' =>
                        (int) $provinceProject->beneficiaries_total,
                    'female_beneficiaries' =>
                        (int) $provinceProject->beneficiaries_female,
                    'is_exact' => false,
                ]);
            }
        }

        $districts = $province
            ->municipalities
            ->map(
                function ($municipality) use (
                    $entriesByBarangay,
                    $entriesByMunicipality
                ) {
                    $barangayNodes = $municipality
                        ->barangays
                        ->map(
                            function ($barangay) use ($entriesByBarangay) {
                                $entries = collect(
                                    $entriesByBarangay[$barangay->id]
                                    ?? []
                                )
                                    ->unique(
                                        fn (array $entry) =>
                                            $entry['project']->id
                                    )
                                    ->values();

                                if ($entries->isEmpty()) {
                                    return null;
                                }

                                return [
                                    'id' => $barangay->id,
                                    'name' => $barangay->name,
                                    'project_count' => $entries->count(),
                                    'beneficiaries' =>
                                        (int) $entries->sum('beneficiaries'),
                                    'female_beneficiaries' =>
                                        (int) $entries->sum(
                                            'female_beneficiaries'
                                        ),
                                    'has_legacy_coverage' =>
                                        $entries->contains(
                                            fn (array $entry) =>
                                                ! $entry['is_exact']
                                        ),
                                    'project_entries' => $entries,
                                    'projects' => $entries
                                        ->pluck('project')
                                        ->unique('id')
                                        ->values(),
                                ];
                            }
                        )
                        ->filter()
                        ->values();

                    $municipalityEntries = collect(
                        $entriesByMunicipality[$municipality->id]
                        ?? []
                    )
                        ->unique(
                            fn (array $entry) =>
                                $entry['project']->id
                        )
                        ->values();

                    if (
                        $municipalityEntries->isEmpty()
                        && $barangayNodes->isNotEmpty()
                    ) {
                        $municipalityEntries = $barangayNodes
                            ->pluck('project_entries')
                            ->flatten(1)
                            ->groupBy(
                                fn (array $entry) =>
                                    $entry['project']->id
                            )
                            ->map(function (Collection $entries) {
                                $project = $entries->first()['project'];
                                $isExact = $entries->every(
                                    fn (array $entry) =>
                                        $entry['is_exact']
                                );

                                return [
                                    'project' => $project,
                                    'beneficiaries' => $isExact
                                        ? (int) $entries->sum(
                                            'beneficiaries'
                                        )
                                        : (int) $project->beneficiaries_total,
                                    'female_beneficiaries' => $isExact
                                        ? (int) $entries->sum(
                                            'female_beneficiaries'
                                        )
                                        : (int) $project->beneficiaries_female,
                                    'is_exact' => $isExact,
                                ];
                            })
                            ->values();
                    }

                    if ($municipalityEntries->isEmpty()) {
                        return null;
                    }

                    $municipalityProjects = $municipalityEntries
                        ->pluck('project')
                        ->unique('id')
                        ->values();

                    return [
                        'id' => $municipality->id,
                        'name' => $municipality->name,
                        'is_city' => (bool) $municipality->is_city,
                        'district' => $municipality->district
                            ?: 'Unassigned District',
                        'barangay_count' => $barangayNodes->count(),
                        'project_count' => $municipalityProjects->count(),
                        'beneficiaries' =>
                            (int) $municipalityEntries->sum(
                                'beneficiaries'
                            ),
                        'female_beneficiaries' =>
                            (int) $municipalityEntries->sum(
                                'female_beneficiaries'
                            ),
                        'amount_assisted' =>
                            (float) $municipalityProjects->sum(
                                'total_project_cost'
                            ),
                        'has_legacy_coverage' =>
                            $municipalityEntries->contains(
                                fn (array $entry) =>
                                    ! $entry['is_exact']
                            ),
                        'barangays' => $barangayNodes,
                        'projects' => $municipalityProjects,
                    ];
                }
            )
            ->filter()
            ->groupBy('district')
            ->map(
                function (
                    Collection $municipalities,
                    string $districtName
                ) {
                    $districtProjects = $municipalities
                        ->pluck('projects')
                        ->flatten(1)
                        ->unique('id')
                        ->values();

                    return [
                        'name' => $districtName,
                        'municipality_count' => $municipalities->count(),
                        'barangay_count' =>
                            (int) $municipalities->sum('barangay_count'),
                        'project_count' => $districtProjects->count(),
                        'beneficiaries' =>
                            (int) $municipalities->sum(
                                'beneficiaries'
                            ),
                        'female_beneficiaries' =>
                            (int) $municipalities->sum(
                                'female_beneficiaries'
                            ),
                        'amount_assisted' =>
                            (float) $districtProjects->sum(
                                'total_project_cost'
                            ),
                        'has_legacy_coverage' =>
                            $municipalities->contains(
                                fn (array $municipality) =>
                                    $municipality['has_legacy_coverage']
                            ),
                        'municipalities' => $municipalities
                            ->sortBy('name')
                            ->values(),
                    ];
                }
            )
            ->sortBy(
                fn (array $district) =>
                    $this->districtSortKey(
                        $district['name']
                    )
            )
            ->values();

        $provinceStats = [
            'project_count' => $projects->count(),
            'district_count' => $districts->count(),
            'municipality_count' =>
                (int) $districts->sum('municipality_count'),
            'barangay_count' =>
                (int) $districts->sum('barangay_count'),

            /*
             * Province total remains the authoritative project aggregate.
             * For exact projects it equals the sum of all barangay allocations.
             */
            'beneficiaries' =>
                (int) $projects->sum('beneficiaries_total'),
            'female_beneficiaries' =>
                (int) $projects->sum('beneficiaries_female'),
            'amount_assisted' =>
                (float) $projects->sum('total_project_cost'),
            'has_legacy_coverage' => $hasLegacyCoverage,
        ];

        return view(
            'projects.province-summary',
            [
                'sourceProject' => $sourceProject,
                'province' => $province,
                'projects' => $projects,
                'districts' => $districts,
                'provinceStats' => $provinceStats,
            ]
        );
    }

    /**
     * Build the data set for Projects -> Summary.
     *
     * This deliberately returns one project only. Its projectLocations
     * relation may still contain several municipalities from several
     * districts, which are expanded by the geographic tree below.
     */
    private function singleProjectCollection(
        Project $project
    ): Collection {
        $project->loadMissing([
            'allocation.adl',
            'approval',
            'barangayReference',
            'municipalityReference',
            'projectLocations.municipality',
            'projectLocations.barangays',
        ]);

        return collect([
            $project,
        ]);
    }

    private function projectsForProvince(
        Province $province
    ): Collection {
        return Project::query()
            ->where(
                function (
                    $query
                ) use (
                    $province
                ) {
                    $query
                        ->where(
                            'province_id',
                            $province->id
                        )
                        ->orWhere(
                            'province',
                            $province->name
                        )
                        ->orWhereHas(
                            'projectLocations',
                            fn ($locationQuery) =>
                                $locationQuery
                                    ->where(
                                        'province_id',
                                        $province->id
                                    )
                        );
                }
            )
            ->with([
                'allocation.adl',
                'approval',
                'barangayReference',
                'municipalityReference',
                'projectLocations.municipality',
                'projectLocations.barangays',
            ])
            ->orderBy('project_title')
            ->get()
            ->sortBy(
                fn (Project $project) =>
                    mb_strtolower(
                        $project->approval?->project_code
                        ?? $project->project_title
                    )
            )
            ->values();
    }

    private function authorizeSummaryUser(
        Request $request
    ): void {
        $user =
            $request->user();

        if (
            ! $user->isAdmin()
            && ! $user->isTc()
            && ! $user->isFocal()
        ) {
            abort(403);
        }
    }

    private function districtSortKey(
        string $district
    ): string {
        if (
            preg_match(
                '/(\d+)/',
                $district,
                $matches
            )
        ) {
            return sprintf(
                '%03d-%s',
                (int) $matches[1],
                mb_strtolower(
                    $district
                )
            );
        }

        if (
            str_contains(
                mb_strtolower(
                    $district
                ),
                'lone'
            )
        ) {
            return '001-lone';
        }

        return '999-'
            . mb_strtolower(
                $district
            );
    }
}
