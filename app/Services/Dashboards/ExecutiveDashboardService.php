<?php

namespace App\Services\Dashboards;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\ReportDimension;
use App\Models\Adl;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLaborMarketReferral;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Reports\ReportFilters;
use App\Services\Reports\ReportingDataService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class ExecutiveDashboardService
{
    public function __construct(
        private readonly ReportingDataService $reporting,
    ) {}

    public function build(ReportFilters $filters): array
    {
        $projectCohort = $this->reporting->projects($filters);
        [$periodStart, $periodEnd] = $filters->periodBounds();
        $laborCohort = $periodStart === null && $periodEnd === null
            ? $projectCohort
            : $this->reporting->projects($filters, applyPeriod: false);

        $overall = $this->overallRow($filters, $projectCohort);
        $statusRows = $this->normalizeProjectStatusRows(
            $this->reporting->physicalFinancial(
                $filters,
                ReportDimension::STATUS,
                $projectCohort,
            )
        );
        $termRows = $this->normalizeTermRows(
            $this->reporting->physicalFinancial(
                $filters,
                ReportDimension::TERM,
                $projectCohort,
            )
        );

        $implementationModeRows = collect(ImplementationMode::cases())
            ->map(function (ImplementationMode $mode) use ($projectCohort): array {
                $projects = $projectCohort
                    ->filter(fn (Project $project): bool =>
                        $project->implementation_mode === $mode
                    );

                return [
                    'key' => $mode->value,
                    'label' => $mode->label(),
                    'project_count' => $projects->count(),
                    'beneficiaries_total' => (int) $projects->sum('beneficiaries_total'),
                ];
            })
            ->values()
            ->all();

        $trendDimension = $filters->fiscalYear !== null
            ? ReportDimension::MONTH
            : ReportDimension::QUARTER;
        $trendRows = $this->reporting->physicalFinancial(
            $filters,
            $trendDimension,
            $projectCohort,
        );

        $provinceRows = $this->reporting->beneficiaryGeography(
            $filters,
            ReportDimension::PROVINCE,
            $projectCohort,
        );
        $sectorRows = $this->reporting->sectorAggregation(
            $filters,
            ReportDimension::SECTOR,
            $projectCohort,
        );
        $interventionRows = $this->reporting->interventionAggregation(
            $filters,
            ReportDimension::INTERVENTION_FOCUS,
            $projectCohort,
        );
        $laborRows = $this->reporting->laborMarketAggregation(
            $filters,
            ReportDimension::LABOR_MARKET_PROGRAM,
            $laborCohort,
        );
        $laborOverall = $this->reporting->laborMarketAggregation(
            $filters,
            ReportDimension::OVERALL,
            $laborCohort,
        )->first() ?? $this->emptyLaborRow();

        $fineGeographySelected = $filters->district !== null
            || $filters->municipalityId !== null
            || $filters->barangayId !== null;

        $fund = $fineGeographySelected
            ? null
            : $this->reporting->fundStatus(
                $filters,
                ReportDimension::OVERALL,
                $projectCohort,
            )->first();

        $geographicBeneficiaries = $this->geographicBeneficiaryTotals(
            $filters,
            $projectCohort,
        );
        $beneficiariesTotal = $geographicBeneficiaries['beneficiaries_total']
            ?? (int) $overall['beneficiaries_total'];
        $beneficiariesFemale = $geographicBeneficiaries['beneficiaries_female']
            ?? (int) $overall['beneficiaries_female'];

        $totalProjects = (int) $overall['project_count'];
        $completedProjects = (int) $overall['completed_project_count'];
        $allocationCents = $fund ? (int) $fund['allocation_cents'] : null;
        $obligatedCents = $fund ? (int) $fund['obligated_cents'] : null;
        $disbursedCents = $fund ? (int) $fund['disbursed_cents'] : null;
        $balanceCents = $fund ? (int) $fund['balance_cents'] : null;
        $acpCheckReleasedCents = $fund ? (int) ($fund['acp_check_released_cents'] ?? 0) : null;
        $acpLiquidatedCents = $fund ? (int) ($fund['acp_liquidated_cents'] ?? 0) : null;

        $kpis = [
            'total_projects' => $totalProjects,
            'completed_projects' => $completedProjects,
            'ongoing_implementation' => (int) (
                collect($statusRows)
                    ->firstWhere('key', ProjectStatus::ONGOING_IMPLEMENTATION->value)['project_count']
                ?? 0
            ),
            'for_payment' => (int) (
                collect($statusRows)
                    ->firstWhere('key', ProjectStatus::FOR_PAYMENT->value)['project_count']
                ?? 0
            ),
            'for_check_release' => (int) (
                collect($statusRows)
                    ->firstWhere('key', ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT->value)['project_count']
                ?? 0
            ),
            'for_liquidation' => (int) (
                collect($statusRows)
                    ->firstWhere('key', ProjectStatus::FOR_LIQUIDATION->value)['project_count']
                ?? 0
            ),
            'partially_liquidated' => (int) (
                collect($statusRows)
                    ->firstWhere('key', ProjectStatus::PARTIALLY_LIQUIDATED->value)['project_count']
                ?? 0
            ),
            'beneficiaries_total' => $beneficiariesTotal,
            'beneficiaries_female' => $beneficiariesFemale,
            'project_cost_cents' => $fineGeographySelected
                ? null
                : (int) $overall['project_cost_cents'],
            'allocation_cents' => $allocationCents,
            'obligated_cents' => $obligatedCents,
            'disbursed_cents' => $disbursedCents,
            'balance_cents' => $balanceCents,
            'physical_accomplishment_percent' => $totalProjects > 0
                ? round(($completedProjects / $totalProjects) * 100, 2)
                : 0.0,
            'financial_accomplishment_percent' => $allocationCents && $allocationCents > 0
                ? round(($disbursedCents / $allocationCents) * 100, 2)
                : null,
            'acp_liquidation_percent' => $acpCheckReleasedCents && $acpCheckReleasedCents > 0
                ? round((($acpLiquidatedCents ?? 0) / $acpCheckReleasedCents) * 100, 2)
                : null,
        ];

        return [
            'generated_at' => CarbonImmutable::now('Asia/Manila'),
            'filters' => $filters,
            'active_filters' => $this->activeFilterLabels($filters),
            'kpis' => $kpis,
            'projects_by_status' => $statusRows,
            'physical_trend' => $trendRows->values()->all(),
            'physical_trend_dimension' => $trendDimension,
            'projects_by_term' => $termRows,
            'projects_by_implementation_mode' => $implementationModeRows,
            'financial_position' => $fund,
            'beneficiaries_by_province' => $provinceRows->values()->all(),
            'sector_priority' => $sectorRows
                ->where('sector_group', BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE)
                ->values()
                ->all(),
            'sector_occupational' => $sectorRows
                ->where('sector_group', BeneficiarySectorCategory::GROUP_OCCUPATIONAL_LIVELIHOOD)
                ->values()
                ->all(),
            'intervention_focus' => $interventionRows->values()->all(),
            'labor_market_programs' => $laborRows->values()->all(),
            'labor_market_overall' => $laborOverall,
            'fine_geography_financials_available' => ! $fineGeographySelected,
            'financial_note' => $fineGeographySelected
                ? 'Financial KPIs are intentionally unavailable for district, municipality, or barangay filters because the system has no authoritative financial allocation at those levels.'
                : null,
            'financial_basis_note' => 'Direct Administration obligation/disbursement values come from wage obligation tranches and disbursements. Through ACP obligation-stage values come from the official ACP payment record, disbursement-stage values come from the released check, and liquidation is reported separately.',
            'geography_note' => 'Geographic beneficiary totals use exact project_location_barangay beneficiary allocations. Legacy rows without an exact pivot allocation are not guessed.',
            'sector_note' => 'Sector classifications may overlap. Sector counts are project-level classification totals for the matching project cohort and must not be added together as unique beneficiary totals or treated as geographic allocations.',
            'labor_market_note' => 'Labor market metrics use project_labor_market_referrals.reporting_month. Geographic filters select matching project cohorts; referral values remain project-level and are not divided across localities.',
        ];
    }

    public function filterOptions(): array
    {
        $projectYears = Project::query()
            ->whereNotNull('date_received')
            ->pluck('date_received')
            ->map(fn ($date): int => CarbonImmutable::parse($date)->year);
        $laborYears = ProjectLaborMarketReferral::query()
            ->whereNotNull('reporting_month')
            ->pluck('reporting_month')
            ->map(fn ($date): int => CarbonImmutable::parse($date)->year);

        $years = $projectYears
            ->merge($laborYears)
            ->push((int) now('Asia/Manila')->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        $districts = Municipality::query()
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->get(['province_id', 'district'])
            ->concat(
                ProjectLocation::query()
                    ->whereNotNull('district')
                    ->where('district', '!=', '')
                    ->get(['province_id', 'district'])
            )
            ->unique(fn ($row): string => $row->province_id.'|'.$row->district)
            ->sortBy(fn ($row): string => $row->district)
            ->values();

        return [
            'fiscal_years' => $years,
            'terms' => ProjectTerm::cases(),
            'implementation_modes' => ImplementationMode::cases(),
            'statuses' => ProjectStatus::cases(),
            'sectors' => BeneficiarySectorCategory::cases(),
            'intervention_focuses' => ProjectInterventionFocus::cases(),
            'labor_market_programs' => LaborMarketProgram::cases(),
            'adls' => Adl::query()
                ->orderByDesc('date_received')
                ->orderBy('adl_number')
                ->get(['id', 'adl_number']),
            'provinces' => Province::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'districts' => $districts,
            'municipalities' => Municipality::query()
                ->orderBy('name')
                ->get(['id', 'province_id', 'district', 'name']),
            'barangays' => Barangay::query()
                ->with('municipality:id,province_id,district,name')
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name']),
            'sponsors' => Project::query()
                ->whereNotNull('fund_sponsor')
                ->where('fund_sponsor', '!=', '')
                ->distinct()
                ->orderBy('fund_sponsor')
                ->pluck('fund_sponsor'),
            'partners' => Project::query()
                ->whereNotNull('partner')
                ->where('partner', '!=', '')
                ->distinct()
                ->orderBy('partner')
                ->pluck('partner'),
        ];
    }

    private function geographicBeneficiaryTotals(
        ReportFilters $filters,
        Collection $projects,
    ): ?array
    {
        $dimension = match (true) {
            $filters->barangayId !== null => ReportDimension::BARANGAY,
            $filters->municipalityId !== null => ReportDimension::MUNICIPALITY,
            $filters->district !== null => ReportDimension::DISTRICT,
            $filters->provinceId !== null => ReportDimension::PROVINCE,
            default => null,
        };

        if ($dimension === null) {
            return null;
        }

        $rows = $this->reporting->beneficiaryGeography(
            $filters,
            $dimension,
            $projects,
        );

        return [
            'beneficiaries_total' => (int) $rows->sum('beneficiaries_total'),
            'beneficiaries_female' => (int) $rows->sum('beneficiaries_female'),
        ];
    }

    private function overallRow(
        ReportFilters $filters,
        Collection $projects,
    ): array {
        return $this->reporting->physicalFinancial(
            $filters,
            ReportDimension::OVERALL,
            $projects,
        )->first() ?? [
            'project_count' => 0,
            'completed_project_count' => 0,
            'beneficiaries_total' => 0,
            'beneficiaries_female' => 0,
            'wages_cents' => 0,
            'ppe_cents' => 0,
            'insurance_cents' => 0,
            'project_cost_cents' => 0,
            'obligated_cents' => 0,
            'disbursed_cents' => 0,
        ];
    }

    private function normalizeProjectStatusRows(Collection $rows): array
    {
        return collect(ProjectStatus::cases())
            ->map(function (ProjectStatus $status) use ($rows): array {
                $row = $rows->firstWhere('key', $status->value);

                return [
                    'key' => $status->value,
                    'label' => $status->label(),
                    'project_count' => (int) ($row['project_count'] ?? 0),
                    'beneficiaries_total' => (int) ($row['beneficiaries_total'] ?? 0),
                ];
            })
            ->all();
    }

    private function normalizeTermRows(Collection $rows): array
    {
        return collect(ProjectTerm::cases())
            ->map(function (ProjectTerm $term) use ($rows): array {
                $row = $rows->firstWhere('key', $term->value);

                return [
                    'key' => $term->value,
                    'label' => $term->label(),
                    'project_count' => (int) ($row['project_count'] ?? 0),
                    'beneficiaries_total' => (int) ($row['beneficiaries_total'] ?? 0),
                ];
            })
            ->all();
    }

    private function emptyLaborRow(): array
    {
        return [
            'project_count' => 0,
            'referral_record_count' => 0,
            'interested_referred_total' => 0,
            'interested_referred_female' => 0,
            'provided_intervention_total' => 0,
            'provided_intervention_female' => 0,
            'amount_released_cents' => 0,
            'services_availed' => [],
            'period_basis' => 'project_labor_market_referrals.reporting_month',
        ];
    }

    private function activeFilterLabels(ReportFilters $filters): array
    {
        $labels = [];

        if ($filters->fiscalYear) {
            $labels['Fiscal Year'] = (string) $filters->fiscalYear;
        }
        if ($filters->quarter) {
            $labels['Quarter'] = 'Q'.$filters->quarter;
        }
        if ($filters->month) {
            $labels['Month'] = CarbonImmutable::create(2000, $filters->month, 1)->format('F');
        }
        if ($filters->term) {
            $labels['Term'] = $filters->term->label();
        }
        if ($filters->adlId) {
            $labels['ADL'] = Adl::query()->find($filters->adlId)?->adl_number ?? (string) $filters->adlId;
        }
        if ($filters->provinceId) {
            $labels['Province'] = Province::query()->find($filters->provinceId)?->name ?? (string) $filters->provinceId;
        }
        if ($filters->district) {
            $labels['District'] = $filters->district;
        }
        if ($filters->municipalityId) {
            $labels['Municipality'] = Municipality::query()->find($filters->municipalityId)?->name ?? (string) $filters->municipalityId;
        }
        if ($filters->barangayId) {
            $labels['Barangay'] = Barangay::query()->find($filters->barangayId)?->name ?? (string) $filters->barangayId;
        }
        if ($filters->status) {
            $labels['Project Status'] = $filters->status->label();
        }
        if ($filters->implementationMode) {
            $labels['Implementation Mode'] = $filters->implementationMode->label();
        }
        if ($filters->sponsor) {
            $labels['Sponsor'] = $filters->sponsor;
        }
        if ($filters->partner) {
            $labels['Partner / NGA'] = $filters->partner;
        }
        if ($filters->projectCode) {
            $labels['Project Code'] = $filters->projectCode;
        }
        if ($filters->sector) {
            $labels['Sector'] = $filters->sector->label();
        }
        if ($filters->interventionFocus) {
            $labels['Intervention Focus'] = $filters->interventionFocus->label();
        }
        if ($filters->laborMarketProgram) {
            $labels['Labor Market Program'] = $filters->laborMarketProgram->label();
        }

        return $labels;
    }
}
