<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Report Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        $validated = $this->validateFilters(
            $request
        );

        $query = $this->buildFilteredQuery(
            $validated
        );

        $summaryQuery = clone $query;

        $summary = $this->buildSummary(
            $summaryQuery
        );

        $projects = $query
            ->latest('date_received')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view(
            'reports.index',
            [
                'projects' =>
                    $projects,

                'summary' =>
                    $summary,

                'provinces' =>
                    $this->provinceOptions(),

                'municipalities' =>
                    $this->municipalityOptions(
                        $validated['province']
                        ?? null
                    ),

                'statuses' =>
                    ProjectStatus::cases(),

                'geographicSummary' =>
                    $this->buildGeographicSummary(
                        $validated
                    ),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CSV Export
    |--------------------------------------------------------------------------
    */

    public function exportCsv(
        Request $request
    ): StreamedResponse {
        $validated = $this->validateFilters(
            $request
        );

        $projects = $this
            ->buildFilteredQuery(
                $validated
            )
            ->orderBy('province')
            ->orderBy('municipality')
            ->orderBy('barangay')
            ->orderBy('project_title')
            ->get();

        $filename =
            'tupad-project-report-'
            . now()->format('Y-m-d-His')
            . '.csv';

        return response()->streamDownload(
            function () use ($projects) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                /*
                |--------------------------------------------------------------------------
                | UTF-8 BOM
                |--------------------------------------------------------------------------
                |
                | Helps Excel display UTF-8 content correctly.
                |
                */

                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $handle,
                    [
                        'Project Code',
                        'Project Title',
                        'ADL Number',
                        'Partner',
                        'Province',
                        'Municipality',
                        'Barangay',
                        'District',
                        'Income Class',
                        'Status',
                        'Date Received',
                        'Beneficiaries',
                        'Female Beneficiaries',
                        'Registered Beneficiaries',
                        'Wage Rate',
                        'Wages Total',
                        'PPE Total',
                        'Insurance Total',
                        'Total Project Cost',
                    ]
                );

                foreach ($projects as $project) {
                    fputcsv(
                        $handle,
                        [
                            $project
                                ->approval
                                    ?->project_code
                            ?? '',

                            $project
                                ->project_title,

                            $project
                                ->allocation
                                ->adl
                                ->adl_number,

                            $project
                                ->allocation
                                ->partner,

                            $project
                                ->province,

                            $project
                                ->municipality,

                            $project
                                ->barangay,

                            $project
                                ->district,

                            $project
                                ->income_class
                            ?? '',

                            $project
                                ->status
                                ->label(),

                            $project
                                ->date_received
                                ->format('Y-m-d'),

                            $project
                                ->beneficiaries_total,

                            $project
                                ->beneficiaries_female,

                            $project
                                ->beneficiaries_count,

                            $project
                                ->wage_rate,

                            $project
                                ->wages_total,

                            $project
                                ->ppe_total,

                            $project
                                ->insurance_total,

                            $project
                                ->total_project_cost,
                        ]
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Printable Report
    |--------------------------------------------------------------------------
    */

    public function print(
        Request $request
    ): View {
        $validated = $this->validateFilters(
            $request
        );

        $query = $this->buildFilteredQuery(
            $validated
        );

        $projects = $query
            ->orderBy('province')
            ->orderBy('municipality')
            ->orderBy('barangay')
            ->orderBy('project_title')
            ->get();

        return view(
            'reports.print',
            [
                'projects' =>
                    $projects,

                'summary' =>
                    $this->buildSummary(
                        clone $query
                    ),

                'filters' =>
                    $validated,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Filter Validation
    |--------------------------------------------------------------------------
    */

    private function validateFilters(
        Request $request
    ): array {
        return $request->validate([
            'province' => [
                'nullable',
                'string',
                'max:150',
            ],

            'municipality' => [
                'nullable',
                'string',
                'max:150',
            ],

            'barangay' => [
                'nullable',
                'string',
                'max:150',
            ],

            'status' => [
                'nullable',
                'string',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Base Filtered Query
    |--------------------------------------------------------------------------
    */

    private function buildFilteredQuery(
        array $validated
    ): Builder {
        $query = Project::query()
            ->with([
                'allocation.adl',
                'approval',

                'provinceReference',
                'municipalityReference',
                'barangayReference',
            ])
            ->withCount(
                'beneficiaries'
            );

        if (
            filled(
                $validated['province']
                ?? null
            )
        ) {
            $query->where(
                'province',
                $validated['province']
            );
        }

        if (
            filled(
                $validated['municipality']
                ?? null
            )
        ) {
            $query->where(
                'municipality',
                $validated['municipality']
            );
        }

        if (
            filled(
                $validated['barangay']
                ?? null
            )
        ) {
            $query->where(
                'barangay',
                $validated['barangay']
            );
        }

        if (
            filled(
                $validated['status']
                ?? null
            )
        ) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (
            filled(
                $validated['date_from']
                ?? null
            )
        ) {
            $query->whereDate(
                'date_received',
                '>=',
                $validated['date_from']
            );
        }

        if (
            filled(
                $validated['date_to']
                ?? null
            )
        ) {
            $query->whereDate(
                'date_received',
                '<=',
                $validated['date_to']
            );
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    private function buildSummary(
        Builder $query
    ): array {
        return [
            'projects' =>
                (clone $query)->count(),

            'beneficiaries' =>
                (int) (clone $query)
                    ->sum(
                        'beneficiaries_total'
                    ),

            'female_beneficiaries' =>
                (int) (clone $query)
                    ->sum(
                        'beneficiaries_female'
                    ),

            'project_cost' =>
                (float) (clone $query)
                    ->sum(
                        'total_project_cost'
                    ),

            'wages' =>
                (float) (clone $query)
                    ->sum(
                        'wages_total'
                    ),

            'ppe' =>
                (float) (clone $query)
                    ->sum(
                        'ppe_total'
                    ),

            'insurance' =>
                (float) (clone $query)
                    ->sum(
                        'insurance_total'
                    ),

            'completed' =>
                (clone $query)
                    ->where(
                        'status',
                        ProjectStatus::COMPLETED
                    )
                    ->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Province Filter Options
    |--------------------------------------------------------------------------
    */

    private function provinceOptions(): Collection
    {
        return Project::query()
            ->whereNotNull('province')
            ->distinct()
            ->orderBy('province')
            ->pluck('province');
    }

    /*
    |--------------------------------------------------------------------------
    | Municipality Filter Options
    |--------------------------------------------------------------------------
    */

    private function municipalityOptions(
        ?string $province
    ): Collection {
        return Project::query()
            ->when(
                filled($province),
                fn(Builder $query) =>
                $query->where(
                    'province',
                    $province
                )
            )
            ->whereNotNull(
                'municipality'
            )
            ->distinct()
            ->orderBy(
                'municipality'
            )
            ->pluck(
                'municipality'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Geographic Summary
    |--------------------------------------------------------------------------
    */

    private function buildGeographicSummary(
        array $validated
    ): Collection {
        $query = $this->buildFilteredQuery(
            $validated
        );

        return $query
            ->get()
            ->groupBy(
                fn(Project $project) =>
                implode(
                    '|',
                    [
                        $project->province,
                        $project->municipality,
                    ]
                )
            )
            ->map(
                function (Collection $projects) {
                    $first =
                        $projects->first();

                    return [
                        'province' =>
                            $first->province,

                        'municipality' =>
                            $first->municipality,

                        'projects' =>
                            $projects->count(),

                        'beneficiaries' =>
                            $projects->sum(
                                'beneficiaries_total'
                            ),

                        'female' =>
                            $projects->sum(
                                'beneficiaries_female'
                            ),

                        'project_cost' =>
                            $projects->sum(
                                'total_project_cost'
                            ),
                    ];
                }
            )
            ->values()
            ->sortBy([
                ['province', 'asc'],
                ['municipality', 'asc'],
            ])
            ->values();
    }
}