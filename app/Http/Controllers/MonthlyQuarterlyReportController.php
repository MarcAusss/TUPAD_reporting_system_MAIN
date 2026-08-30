<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\Project;
use App\Models\Province;
use App\Reports\ReportFilters;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Reports\ReportingDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonthlyQuarterlyReportController extends Controller
{
    private const MONTHLY_VIEWS = [
        'sprs' => [
            'label' => 'Statistical Performance Reporting System (SPRS)',
            'description' => 'Monthly SPRS cohort using the recorded SPRS monitoring date as the reporting-period basis.',
        ],
        'orientations' => [
            'label' => 'List of Orientations Conducted',
            'description' => 'Monthly TUPAD beneficiary orientations with explicitly recorded AlkanSSSya and YAKAP Program for TUPAD Beneficiaries coverage.',
        ],
    ];

    private const QUARTERLY_VIEWS = [
        'cqpr' => [
            'label' => 'Consolidated Quarterly Progress Report (CQPR)',
            'description' => 'Quarterly project cohort using the recorded CQPR monitoring date as the reporting-period basis.',
        ],
        'labor-market' => [
            'label' => 'Number of TUPAD Beneficiaries Referred to Active Labor Market',
            'description' => 'Quarterly referral totals using each referral record reporting month as the reporting-period basis.',
        ],
    ];

    public function __construct(
        private readonly ReportingDataService $data,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function monthly(Request $request): View
    {
        $now = now('Asia/Manila');
        $viewKey = array_key_exists((string) $request->query('view'), self::MONTHLY_VIEWS)
            ? (string) $request->query('view')
            : 'sprs';

        $input = $request->query();
        $input['fiscal_year'] = $input['fiscal_year'] ?? $now->year;
        $input['month'] = $input['month'] ?? $now->month;
        unset($input['quarter'], $input['labor_market_program']);

        $validated = $this->validateFilters($input, monthly: true, allowLaborProgram: false);
        $filters = ReportFilters::fromArray($validated);
        $projects = $this->baseProjects($filters);

        if ($viewKey === 'orientations') {
            $cohort = $projects
                ->filter(function (Project $project) use ($validated): bool {
                    $date = $project->orientation?->orientation_date;

                    return $date !== null
                        && (int) $date->year === (int) $validated['fiscal_year']
                        && (int) $date->month === (int) $validated['month'];
                })
                ->values();
        } else {
            $cohort = $projects
                ->filter(function (Project $project) use ($validated): bool {
                    $date = $project->monitoringDetail?->sprs_date;

                    return $date !== null
                        && (int) $date->year === (int) $validated['fiscal_year']
                        && (int) $date->month === (int) $validated['month'];
                })
                ->values();
        }

        $summary = $this->cohortSummary($filters, $cohort);
        $user = $request->user();

        return view('reports.monthly.index', [
            'viewKey' => $viewKey,
            'viewConfig' => self::MONTHLY_VIEWS[$viewKey],
            'views' => self::MONTHLY_VIEWS,
            'filters' => $validated,
            'summary' => $summary,
            'rows' => $viewKey === 'orientations'
                ? $this->orientationRows($cohort)
                : $this->monitoringRows($cohort, 'sprs_date'),
            'orientationCounts' => $viewKey === 'orientations'
                ? $this->orientationCounts($cohort)
                : null,
            'provinceLocked' => $user->isTc(),
            'options' => $this->options($user),
            'periodBasis' => $viewKey === 'orientations'
                ? 'project_orientations.orientation_date'
                : 'project_monitoring_details.sprs_date',
        ]);
    }

    public function quarterly(Request $request): View
    {
        $now = now('Asia/Manila');
        $viewKey = array_key_exists((string) $request->query('view'), self::QUARTERLY_VIEWS)
            ? (string) $request->query('view')
            : 'cqpr';

        $input = $request->query();
        $input['fiscal_year'] = $input['fiscal_year'] ?? $now->year;
        $input['quarter'] = $input['quarter'] ?? (int) ceil($now->month / 3);
        unset($input['month']);

        if ($viewKey !== 'labor-market') {
            unset($input['labor_market_program']);
        }

        $validated = $this->validateFilters(
            $input,
            monthly: false,
            allowLaborProgram: $viewKey === 'labor-market',
        );
        $filters = ReportFilters::fromArray($validated);
        $user = $request->user();

        if ($viewKey === 'labor-market') {
            $programRows = $this->data->laborMarketAggregation(
                $filters,
                ReportDimension::LABOR_MARKET_PROGRAM,
            );
            $overallRows = $this->data->laborMarketAggregation(
                $filters,
                ReportDimension::OVERALL,
            );
            $overall = $overallRows->first() ?? [
                'project_count' => 0,
                'referral_record_count' => 0,
                'interested_referred_total' => 0,
                'interested_referred_female' => 0,
                'provided_intervention_total' => 0,
                'provided_intervention_female' => 0,
                'amount_released_cents' => 0,
                'services_availed' => [],
            ];

            $exportQuery = array_filter([
                'report_type' => ReportType::LABOR_MARKET_REFERRALS->value,
                'group_by' => ReportDimension::LABOR_MARKET_PROGRAM->value,
                'fiscal_year' => $validated['fiscal_year'],
                'quarter' => $validated['quarter'],
                'status' => $validated['status'] ?? null,
                'implementation_mode' => $validated['implementation_mode'] ?? null,
                'province_id' => $validated['province_id'] ?? null,
                'labor_market_program' => $validated['labor_market_program'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            return view('reports.quarterly.index', [
                'viewKey' => $viewKey,
                'viewConfig' => self::QUARTERLY_VIEWS[$viewKey],
                'views' => self::QUARTERLY_VIEWS,
                'filters' => $validated,
                'laborRows' => $programRows,
                'laborOverall' => $overall,
                'exportQuery' => $exportQuery,
                'summary' => null,
                'rows' => collect(),
                'provinceLocked' => $user->isTc(),
                'options' => $this->options($user, includeLaborPrograms: true),
                'periodBasis' => 'project_labor_market_referrals.reporting_month',
            ]);
        }

        $projects = $this->baseProjects($filters);
        $quarter = (int) $validated['quarter'];
        $cohort = $projects
            ->filter(function (Project $project) use ($validated, $quarter): bool {
                $date = $project->monitoringDetail?->cqpr_date;

                return $date !== null
                    && (int) $date->year === (int) $validated['fiscal_year']
                    && (int) ceil($date->month / 3) === $quarter;
            })
            ->values();

        return view('reports.quarterly.index', [
            'viewKey' => $viewKey,
            'viewConfig' => self::QUARTERLY_VIEWS[$viewKey],
            'views' => self::QUARTERLY_VIEWS,
            'filters' => $validated,
            'summary' => $this->cohortSummary($filters, $cohort),
            'rows' => $this->monitoringRows($cohort, 'cqpr_date'),
            'laborRows' => collect(),
            'laborOverall' => null,
            'exportQuery' => [],
            'provinceLocked' => $user->isTc(),
            'options' => $this->options($user),
            'periodBasis' => 'project_monitoring_details.cqpr_date',
        ]);
    }

    private function validateFilters(
        array $input,
        bool $monthly,
        bool $allowLaborProgram,
    ): array {
        $rules = [
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
        ];

        if ($monthly) {
            $rules['month'] = ['required', 'integer', 'between:1,12'];
        } else {
            $rules['quarter'] = ['required', 'integer', 'between:1,4'];
        }

        if ($allowLaborProgram) {
            $rules['labor_market_program'] = ['nullable', Rule::enum(LaborMarketProgram::class)];
        }

        return validator($input, $rules)
            ->after(function ($validator) use ($input): void {
                $implementationMode = ImplementationMode::tryFrom(
                    (string) ($input['implementation_mode'] ?? '')
                );
                $status = ProjectStatus::tryFrom((string) ($input['status'] ?? ''));

                if (
                    $implementationMode === ImplementationMode::DIRECT_ADMINISTRATION
                    && in_array($status, [
                        ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                        ProjectStatus::FOR_LIQUIDATION,
                        ProjectStatus::PARTIALLY_LIQUIDATED,
                    ], true)
                ) {
                    $validator->errors()->add(
                        'status',
                        'The selected status belongs only to Through ACP projects.'
                    );
                }

                if (
                    $implementationMode === ImplementationMode::THROUGH_ACP
                    && $status === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
                ) {
                    $validator->errors()->add(
                        'status',
                        'For Submission of Post-Docs belongs only to Direct Administration projects.'
                    );
                }
            })
            ->validate();
    }

    /** @return Collection<int, Project> */
    private function baseProjects(ReportFilters $filters): Collection
    {
        return $this->data
            ->projects($filters, applyPeriod: false)
            ->loadMissing([
                'approval',
                'orientation',
                'monitoringDetail',
                'provinceReference',
                'municipalityReference',
            ]);
    }

    /**
     * @param Collection<int, Project> $projects
     * @return array<string, mixed>
     */
    private function cohortSummary(ReportFilters $filters, Collection $projects): array
    {
        $physical = $this->data->physicalFinancial(
            $filters,
            ReportDimension::OVERALL,
            $projects,
        )->first() ?? [];
        $fund = $this->data->fundStatus(
            $filters,
            ReportDimension::OVERALL,
            $projects,
        )->first() ?? [];
        $statuses = $this->data->physicalFinancial(
            $filters,
            ReportDimension::STATUS,
            $projects,
        );

        return [
            'project_count' => (int) ($physical['project_count'] ?? 0),
            'completed_project_count' => (int) ($physical['completed_project_count'] ?? 0),
            'beneficiaries_total' => (int) ($physical['beneficiaries_total'] ?? 0),
            'beneficiaries_female' => (int) ($physical['beneficiaries_female'] ?? 0),
            'project_cost_cents' => (int) ($physical['project_cost_cents'] ?? 0),
            'allocation_cents' => (int) ($fund['allocation_cents'] ?? 0),
            'obligated_cents' => (int) ($fund['obligated_cents'] ?? 0),
            'disbursed_cents' => (int) ($fund['disbursed_cents'] ?? 0),
            'unobligated_balance_cents' => (int) ($fund['unobligated_balance_cents'] ?? 0),
            'status_rows' => $statuses,
        ];
    }

    /**
     * @param Collection<int, Project> $projects
     * @return Collection<int, array<string, mixed>>
     */
    private function monitoringRows(Collection $projects, string $dateField): Collection
    {
        return $projects
            ->map(function (Project $project) use ($dateField): array {
                $monitoringDate = $project->monitoringDetail?->{$dateField};

                return [
                    'project_id' => $project->id,
                    'project_code' => $project->approval?->project_code,
                    'project_title' => $project->project_title,
                    'province' => $project->provinceReference?->name ?: $project->province,
                    'municipality' => $project->municipalityReference?->name ?: $project->municipality,
                    'status' => $project->status,
                    'implementation_mode' => $project->implementation_mode,
                    'beneficiaries_total' => (int) $project->beneficiaries_total,
                    'beneficiaries_female' => (int) $project->beneficiaries_female,
                    'report_date' => $monitoringDate,
                ];
            })
            ->sortBy('project_code', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @param Collection<int, Project> $projects
     * @return Collection<int, array<string, mixed>>
     */
    private function orientationRows(Collection $projects): Collection
    {
        return $projects
            ->map(function (Project $project): array {
                $orientation = $project->orientation;
                $programs = collect([
                    $orientation?->alkansssya_conducted ? 'AlkanSSSya' : null,
                    $orientation?->yakap_conducted ? 'YAKAP Program for TUPAD Beneficiaries' : null,
                ])->filter()->values()->all();

                return [
                    'project_id' => $project->id,
                    'project_code' => $project->approval?->project_code,
                    'project_title' => $project->project_title,
                    'province' => $project->provinceReference?->name ?: $project->province,
                    'municipality' => $project->municipalityReference?->name ?: $project->municipality,
                    'beneficiaries_total' => (int) $project->beneficiaries_total,
                    'beneficiaries_female' => (int) $project->beneficiaries_female,
                    'orientation_date' => $orientation?->orientation_date,
                    'alkansssya_conducted' => (bool) $orientation?->alkansssya_conducted,
                    'yakap_conducted' => (bool) $orientation?->yakap_conducted,
                    'programs' => $programs,
                    'remarks' => $orientation?->remarks,
                ];
            })
            ->sortBy('orientation_date')
            ->values();
    }

    /** @param Collection<int, Project> $projects */
    private function orientationCounts(Collection $projects): array
    {
        return [
            'orientation_records' => $projects->count(),
            'alkansssya' => $projects->filter(
                fn (Project $project): bool => (bool) $project->orientation?->alkansssya_conducted
            )->count(),
            'yakap' => $projects->filter(
                fn (Project $project): bool => (bool) $project->orientation?->yakap_conducted
            )->count(),
            'program_unspecified' => $projects->filter(
                fn (Project $project): bool => ! (bool) $project->orientation?->alkansssya_conducted
                    && ! (bool) $project->orientation?->yakap_conducted
            )->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function options($user, bool $includeLaborPrograms = false): array
    {
        $options = [
            'statuses' => ProjectStatus::cases(),
            'implementation_modes' => ImplementationMode::cases(),
            'provinces' => $this->provinceAccess
                ->scopeProvinces(Province::query(), $user)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];

        if ($includeLaborPrograms) {
            $options['labor_market_programs'] = LaborMarketProgram::cases();
        }

        return $options;
    }
}
