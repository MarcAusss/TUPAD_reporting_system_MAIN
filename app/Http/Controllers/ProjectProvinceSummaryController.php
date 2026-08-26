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
        | Only geographic areas represented by project data
        |--------------------------------------------------------------------------
        |
        | The previous version displayed every municipality and barangay from
        | the geographic reference table. This report is project-driven:
        |
        | Province
        | -> Districts represented by projects
        | -> Municipalities/Cities represented by projects
        | -> Barangays represented by projects
        |
        */

        $province->load([
            'municipalities' => fn ($query) =>
                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderBy('district')
                    ->orderBy('name'),

            'municipalities.barangays' => fn ($query) =>
                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderBy('name'),
        ]);

        $projects =
            $sourceProject
                ? $this->singleProjectCollection(
                    $sourceProject
                )
                : $this->projectsForProvince(
                    $province
                );

        $coverageByBarangay = [];
        $coverageByMunicipality = [];

        foreach (
            $projects
            as $provinceProject
        ) {
            $municipalityIds =
                collect();

            $barangayIds =
                collect();

            foreach (
                $provinceProject->projectLocations
                as $location
            ) {
                if (
                    $location->municipality_id
                ) {
                    $municipalityIds->push(
                        $location->municipality_id
                    );
                }

                $barangayIds->push(
                    ...$location
                        ->barangays
                        ->pluck('id')
                        ->all()
                );
            }

            if (
                $municipalityIds->isEmpty()
                && $provinceProject->municipality_id
            ) {
                $municipalityIds->push(
                    $provinceProject->municipality_id
                );
            }

            if (
                $barangayIds->isEmpty()
                && $provinceProject->barangay_id
            ) {
                $barangayIds->push(
                    $provinceProject->barangay_id
                );
            }

            /*
             * Legacy fallback for old records whose location IDs were not
             * populated but whose text fields still identify the location.
             */
            if (
                $municipalityIds->isEmpty()
                && filled(
                    $provinceProject->municipality
                )
            ) {
                $matchedMunicipality =
                    $province
                        ->municipalities
                        ->first(
                            fn ($municipality) =>
                                mb_strtolower(
                                    trim(
                                        $municipality->name
                                    )
                                )
                                ===
                                mb_strtolower(
                                    trim(
                                        $provinceProject->municipality
                                    )
                                )
                        );

                if ($matchedMunicipality) {
                    $municipalityIds->push(
                        $matchedMunicipality->id
                    );
                }
            }

            foreach (
                $municipalityIds
                    ->filter()
                    ->unique()
                as $municipalityId
            ) {
                $coverageByMunicipality[
                    $municipalityId
                ] ??= collect();

                $coverageByMunicipality[
                    $municipalityId
                ]->push(
                    $provinceProject
                );
            }

            foreach (
                $barangayIds
                    ->filter()
                    ->unique()
                as $barangayId
            ) {
                $coverageByBarangay[
                    $barangayId
                ] ??= collect();

                $coverageByBarangay[
                    $barangayId
                ]->push(
                    $provinceProject
                );
            }
        }

        $districts =
            $province
                ->municipalities
                ->map(
                    function (
                        $municipality
                    ) use (
                        $coverageByBarangay,
                        $coverageByMunicipality
                    ) {
                        $barangayNodes =
                            $municipality
                                ->barangays
                                ->map(
                                    function (
                                        $barangay
                                    ) use (
                                        $coverageByBarangay
                                    ) {
                                        $projects =
                                            (
                                                $coverageByBarangay[
                                                    $barangay->id
                                                ]
                                                ?? collect()
                                            )
                                            ->unique('id')
                                            ->values();

                                        return [
                                            'id' =>
                                                $barangay->id,

                                            'name' =>
                                                $barangay->name,

                                            'project_count' =>
                                                $projects->count(),

                                            /*
                                             * Beneficiary coverage:
                                             * the current schema stores a
                                             * project aggregate, not a true
                                             * barangay allocation.
                                             */
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

                                            'projects' =>
                                                $projects,
                                        ];
                                    }
                                )
                                ->filter(
                                    fn (array $barangay) =>
                                        $barangay[
                                            'project_count'
                                        ] > 0
                                )
                                ->values();

                        $municipalityProjects =
                            collect(
                                $coverageByMunicipality[
                                    $municipality->id
                                ]
                                ?? []
                            )
                            ->merge(
                                $barangayNodes
                                    ->pluck(
                                        'projects'
                                    )
                                    ->flatten(1)
                            )
                            ->unique('id')
                            ->values();

                        if (
                            $municipalityProjects
                                ->isEmpty()
                        ) {
                            return null;
                        }

                        return [
                            'id' =>
                                $municipality->id,

                            'name' =>
                                $municipality->name,

                            'is_city' =>
                                (bool) $municipality->is_city,

                            'district' =>
                                $municipality->district
                                ?: 'Unassigned District',

                            'barangay_count' =>
                                $barangayNodes->count(),

                            'project_count' =>
                                $municipalityProjects->count(),

                            'beneficiaries' =>
                                (int) $municipalityProjects
                                    ->sum(
                                        'beneficiaries_total'
                                    ),

                            'female_beneficiaries' =>
                                (int) $municipalityProjects
                                    ->sum(
                                        'beneficiaries_female'
                                    ),

                            'amount_assisted' =>
                                (float) $municipalityProjects
                                    ->sum(
                                        'total_project_cost'
                                    ),

                            'barangays' =>
                                $barangayNodes,

                            'projects' =>
                                $municipalityProjects,
                        ];
                    }
                )
                ->filter()
                ->groupBy(
                    'district'
                )
                ->map(
                    function (
                        Collection $municipalities,
                        string $districtName
                    ) {
                        $districtProjects =
                            $municipalities
                                ->pluck(
                                    'projects'
                                )
                                ->flatten(1)
                                ->unique('id')
                                ->values();

                        return [
                            'name' =>
                                $districtName,

                            'municipality_count' =>
                                $municipalities
                                    ->count(),

                            'barangay_count' =>
                                (int) $municipalities
                                    ->sum(
                                        'barangay_count'
                                    ),

                            'project_count' =>
                                $districtProjects
                                    ->count(),

                            'beneficiaries' =>
                                (int) $districtProjects
                                    ->sum(
                                        'beneficiaries_total'
                                    ),

                            'female_beneficiaries' =>
                                (int) $districtProjects
                                    ->sum(
                                        'beneficiaries_female'
                                    ),

                            'amount_assisted' =>
                                (float) $districtProjects
                                    ->sum(
                                        'total_project_cost'
                                    ),

                            'municipalities' =>
                                $municipalities
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
            'project_count' =>
                $projects->count(),

            'district_count' =>
                $districts->count(),

            'municipality_count' =>
                (int) $districts
                    ->sum(
                        'municipality_count'
                    ),

            'barangay_count' =>
                (int) $districts
                    ->sum(
                        'barangay_count'
                    ),

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

        return view(
            'projects.province-summary',
            [
                'sourceProject' =>
                    $sourceProject,

                'province' =>
                    $province,

                'projects' =>
                    $projects,

                'districts' =>
                    $districts,

                'provinceStats' =>
                    $provinceStats,
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
