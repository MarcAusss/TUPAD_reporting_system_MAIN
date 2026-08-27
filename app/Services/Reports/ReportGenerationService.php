<?php

namespace App\Services\Reports;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\Adl;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Reports\ReportFilters;
use Illuminate\Support\Collection;

final class ReportGenerationService
{
    public function __construct(
        private readonly ReportingDataService $reportingData,
    ) {}

    public function generate(
        ReportType $type,
        ReportDimension $dimension,
        ReportFilters $filters,
    ): array {
        $rows = $this->dataRows($type, $dimension, $filters);
        $columns = $this->columns($type, $dimension);
        $rows = $this->normalizeRows($type, $rows);

        return [
            'type' => $type,
            'dimension' => $dimension,
            'title' => $type->label(),
            'description' => $type->description(),
            'columns' => $columns,
            'rows' => $rows,
            'display_rows' => $rows->map(
                fn (array $row): array => collect($columns)
                    ->mapWithKeys(fn (array $column): array => [
                        $column['key'] => $this->formatValue(
                            $column['format'],
                            $row[$column['key']] ?? null,
                        ),
                    ])
                    ->all()
            ),
            'summary_cards' => $this->summaryCards($type, $filters),
            'criteria' => $this->criteria($type, $dimension, $filters),
            'warning' => $this->warning($type, $dimension, $rows),
            'generated_at' => now(),
            'file_base_name' => sprintf(
                'tupad-%s-%s-%s',
                $type->value,
                $dimension->value,
                now()->format('Ymd-His'),
            ),
        ];
    }

    public function formatValue(string $format, mixed $value): string
    {
        if ($value === null) {
            return in_array($format, ['money', 'integer'], true)
                ? 'Not allocated'
                : '—';
        }

        return match ($format) {
            'money' => 'PHP '.$this->formatCents((int) $value),
            'integer' => number_format((int) $value),
            'boolean' => $value ? 'Yes' : 'No',
            'list' => collect((array) $value)
                ->filter()
                ->implode('; ') ?: '—',
            default => filled($value) ? (string) $value : '—',
        };
    }

    public function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return sprintf(
            '%s%s.%02d',
            $negative ? '-' : '',
            number_format($whole),
            $fraction,
        );
    }

    private function dataRows(
        ReportType $type,
        ReportDimension $dimension,
        ReportFilters $filters,
    ): Collection {
        return match ($type) {
            ReportType::PHYSICAL_FINANCIAL =>
                $this->reportingData->physicalFinancial(
                    $filters,
                    $dimension,
                ),
            ReportType::FUND_STATUS =>
                $this->reportingData->fundStatus(
                    $filters,
                    $dimension,
                ),
            ReportType::GEOGRAPHIC_BENEFICIARIES =>
                $this->reportingData->beneficiaryGeography(
                    $filters,
                    $dimension,
                ),
            ReportType::BENEFICIARY_SECTORS =>
                $this->reportingData->sectorAggregation(
                    $filters,
                    $dimension,
                ),
            ReportType::INTERVENTION_FOCUS =>
                $this->reportingData->interventionAggregation(
                    $filters,
                    $dimension,
                ),
            ReportType::LABOR_MARKET_REFERRALS =>
                $this->reportingData->laborMarketAggregation(
                    $filters,
                    $dimension,
                ),
        };
    }

    private function columns(
        ReportType $type,
        ReportDimension $dimension,
    ): array {
        return match ($type) {
            ReportType::PHYSICAL_FINANCIAL =>
                $this->physicalFinancialColumns($dimension),
            ReportType::FUND_STATUS => $this->fundStatusColumns($dimension),
            ReportType::GEOGRAPHIC_BENEFICIARIES =>
                $this->geographicColumns(),
            ReportType::BENEFICIARY_SECTORS =>
                $this->sectorColumns($dimension),
            ReportType::INTERVENTION_FOCUS =>
                $this->interventionColumns($dimension),
            ReportType::LABOR_MARKET_REFERRALS =>
                $this->laborMarketColumns(),
        };
    }

    private function physicalFinancialColumns(
        ReportDimension $dimension,
    ): array {
        if ($dimension->isFineGeography()) {
            return [
                $this->column('label', $dimension->label()),
                $this->column('project_count', 'Projects', 'integer'),
                $this->column('beneficiaries_total', 'Exact Beneficiaries', 'integer'),
                $this->column('beneficiaries_female', 'Exact Female', 'integer'),
                $this->column('exact_project_count', 'Exact Projects', 'integer'),
                $this->column(
                    'legacy_unallocated_project_count',
                    'Legacy Unallocated',
                    'integer',
                ),
                $this->column('allocation_status', 'Data Integrity Status'),
            ];
        }

        return [
            $this->column('label', $dimension->label()),
            $this->column('project_count', 'Projects', 'integer'),
            $this->column('completed_project_count', 'Completed', 'integer'),
            $this->column('beneficiaries_total', 'Beneficiaries', 'integer'),
            $this->column('beneficiaries_female', 'Female', 'integer'),
            $this->column('wages_cents', 'Wages', 'money'),
            $this->column('ppe_cents', 'PPE', 'money'),
            $this->column('insurance_cents', 'Insurance', 'money'),
            $this->column('project_cost_cents', 'Project Cost', 'money'),
            $this->column('obligated_cents', 'Obligated', 'money'),
            $this->column('disbursed_cents', 'Disbursed', 'money'),
        ];
    }

    private function fundStatusColumns(ReportDimension $dimension): array
    {
        if ($dimension->isFineGeography()) {
            return $this->physicalFinancialColumns($dimension);
        }

        return [
            $this->column('label', $dimension->label()),
            $this->column('project_count', 'Projects', 'integer'),
            $this->column('allocation_cents', 'TUPAD Allocation', 'money'),
            $this->column('payable_wages_cents', 'Payable Wages', 'money'),
            $this->column('obligated_cents', 'Obligated', 'money'),
            $this->column('disbursed_cents', 'Disbursed', 'money'),
            $this->column(
                'unobligated_balance_cents',
                'Unobligated Balance',
                'money',
            ),
            $this->column(
                'undisbursed_obligation_cents',
                'Undisbursed Obligation',
                'money',
            ),
            $this->column('balance_cents', 'Cash Balance', 'money'),
        ];
    }

    private function geographicColumns(): array
    {
        return [
            $this->column('label', 'Geographic Area'),
            $this->column('project_count', 'Projects', 'integer'),
            $this->column('beneficiaries_total', 'Beneficiaries', 'integer'),
            $this->column('beneficiaries_female', 'Female', 'integer'),
            $this->column('exact_project_count', 'Exact Projects', 'integer'),
            $this->column(
                'legacy_unallocated_project_count',
                'Legacy Unallocated',
                'integer',
            ),
            $this->column('allocation_status', 'Data Integrity Status'),
        ];
    }

    private function sectorColumns(ReportDimension $dimension): array
    {
        return array_values(array_filter([
            $dimension !== ReportDimension::SECTOR
                ? $this->column('group_label', $dimension->label())
                : null,
            $this->column('sector_group_label', 'Sector Group'),
            $this->column('sector_label', 'Sector'),
            $this->column('project_count', 'Projects', 'integer'),
            $this->column('beneficiaries_total', 'Beneficiaries', 'integer'),
            $this->column('beneficiaries_female', 'Female', 'integer'),
        ]));
    }

    private function interventionColumns(ReportDimension $dimension): array
    {
        return array_values(array_filter([
            $dimension !== ReportDimension::INTERVENTION_FOCUS
                ? $this->column('group_label', $dimension->label())
                : null,
            $this->column(
                'intervention_focus_label',
                'Primary Intervention Focus',
            ),
            $this->column('project_count', 'Projects', 'integer'),
            $this->column('beneficiaries_total', 'Beneficiaries', 'integer'),
            $this->column('beneficiaries_female', 'Female', 'integer'),
            $this->column('project_cost_cents', 'Project Cost', 'money'),
        ]));
    }

    private function laborMarketColumns(): array
    {
        return [
            $this->column('label', 'Reporting Group'),
            $this->column('referral_record_count', 'Records', 'integer'),
            $this->column('project_count', 'Projects', 'integer'),
            $this->column(
                'interested_referred_total',
                'Interested Referred',
                'integer',
            ),
            $this->column(
                'interested_referred_female',
                'Female Referred',
                'integer',
            ),
            $this->column(
                'provided_intervention_total',
                'Provided Intervention',
                'integer',
            ),
            $this->column(
                'provided_intervention_female',
                'Female Provided',
                'integer',
            ),
            $this->column(
                'amount_released_cents',
                'Amount Released',
                'money',
            ),
            $this->column('services_availed', 'Services Availed', 'list'),
        ];
    }

    private function column(
        string $key,
        string $label,
        string $format = 'text',
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'format' => $format,
            'align' => in_array($format, ['integer', 'money'], true)
                ? 'right'
                : 'left',
        ];
    }

    private function normalizeRows(
        ReportType $type,
        Collection $rows,
    ): Collection {
        return $rows->map(function (array $row) use ($type): array {
            if (array_key_exists('has_complete_exact_allocation', $row)) {
                $row['allocation_status'] =
                    $row['has_complete_exact_allocation']
                        ? 'Complete exact allocation'
                        : 'Includes legacy unallocated records';
            }

            if ($type === ReportType::BENEFICIARY_SECTORS) {
                $row['sector_group_label'] = match ($row['sector_group']) {
                    BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE =>
                        'Priority / Vulnerable',
                    BeneficiarySectorCategory::GROUP_OCCUPATIONAL_LIVELIHOOD =>
                        'Occupational / Livelihood',
                    default => (string) $row['sector_group'],
                };
            }

            return $row;
        });
    }

    private function summaryCards(
        ReportType $type,
        ReportFilters $filters,
    ): array {
        return match ($type) {
            ReportType::PHYSICAL_FINANCIAL =>
                $this->physicalSummaryCards($filters),
            ReportType::FUND_STATUS => $this->fundSummaryCards($filters),
            ReportType::GEOGRAPHIC_BENEFICIARIES =>
                $this->geographicSummaryCards($filters),
            ReportType::BENEFICIARY_SECTORS =>
                $this->sectorSummaryCards($filters),
            ReportType::INTERVENTION_FOCUS =>
                $this->interventionSummaryCards($filters),
            ReportType::LABOR_MARKET_REFERRALS =>
                $this->laborSummaryCards($filters),
        };
    }

    private function physicalSummaryCards(ReportFilters $filters): array
    {
        $row = $this->reportingData
            ->physicalFinancial($filters, ReportDimension::OVERALL)
            ->first() ?? [];

        return [
            $this->card('Projects', $row['project_count'] ?? 0, 'integer'),
            $this->card('Beneficiaries', $row['beneficiaries_total'] ?? 0, 'integer'),
            $this->card('Female', $row['beneficiaries_female'] ?? 0, 'integer'),
            $this->card('Project Cost', $row['project_cost_cents'] ?? 0, 'money'),
            $this->card('Obligated', $row['obligated_cents'] ?? 0, 'money'),
            $this->card('Disbursed', $row['disbursed_cents'] ?? 0, 'money'),
        ];
    }

    private function fundSummaryCards(ReportFilters $filters): array
    {
        $row = $this->reportingData
            ->fundStatus($filters, ReportDimension::OVERALL)
            ->first() ?? [];

        return [
            $this->card('TUPAD Allocation', $row['allocation_cents'] ?? 0, 'money'),
            $this->card('Payable Wages', $row['payable_wages_cents'] ?? 0, 'money'),
            $this->card('Obligated', $row['obligated_cents'] ?? 0, 'money'),
            $this->card('Disbursed', $row['disbursed_cents'] ?? 0, 'money'),
            $this->card('Unobligated', $row['unobligated_balance_cents'] ?? 0, 'money'),
            $this->card('Cash Balance', $row['balance_cents'] ?? 0, 'money'),
        ];
    }

    private function geographicSummaryCards(ReportFilters $filters): array
    {
        $rows = $this->reportingData->beneficiaryGeography(
            $filters,
            ReportDimension::PROVINCE,
        );

        return [
            $this->card('Provinces', $rows->count(), 'integer'),
            $this->card('Projects', $rows->sum('project_count'), 'integer'),
            $this->card('Beneficiaries', $rows->sum('beneficiaries_total'), 'integer'),
            $this->card('Female', $rows->sum('beneficiaries_female'), 'integer'),
        ];
    }

    private function sectorSummaryCards(ReportFilters $filters): array
    {
        $rows = $this->reportingData->sectorAggregation($filters);

        return [
            $this->card('Sector Categories', $rows->count(), 'integer'),
            $this->card(
                'Classified Counts (Overlapping)',
                $rows->sum('beneficiaries_total'),
                'integer',
            ),
            $this->card('Female Classified Counts', $rows->sum('beneficiaries_female'), 'integer'),
        ];
    }

    private function interventionSummaryCards(ReportFilters $filters): array
    {
        $rows = $this->reportingData->interventionAggregation($filters);

        return [
            $this->card('Intervention Categories', $rows->count(), 'integer'),
            $this->card('Projects', $rows->sum('project_count'), 'integer'),
            $this->card('Beneficiaries', $rows->sum('beneficiaries_total'), 'integer'),
            $this->card('Project Cost', $rows->sum('project_cost_cents'), 'money'),
        ];
    }

    private function laborSummaryCards(ReportFilters $filters): array
    {
        $row = $this->reportingData
            ->laborMarketAggregation($filters, ReportDimension::OVERALL)
            ->first() ?? [];

        return [
            $this->card('Interested Referred', $row['interested_referred_total'] ?? 0, 'integer'),
            $this->card('Female Referred', $row['interested_referred_female'] ?? 0, 'integer'),
            $this->card('Provided Intervention', $row['provided_intervention_total'] ?? 0, 'integer'),
            $this->card('Female Provided', $row['provided_intervention_female'] ?? 0, 'integer'),
            $this->card('Amount Released', $row['amount_released_cents'] ?? 0, 'money'),
        ];
    }

    private function card(string $label, mixed $value, string $format): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'format' => $format,
            'display_value' => $this->formatValue($format, $value),
        ];
    }

    private function criteria(
        ReportType $type,
        ReportDimension $dimension,
        ReportFilters $filters,
    ): array {
        $criteria = [
            'Report Type' => $type->label(),
            'Grouped By' => $dimension->label(),
        ];

        if ($filters->fiscalYear) {
            $criteria['Fiscal Year'] = (string) $filters->fiscalYear;
        }

        if ($filters->quarter) {
            $criteria['Quarter'] = 'Q'.$filters->quarter;
        }

        if ($filters->month) {
            $criteria['Month'] = now()
                ->setDate(2000, $filters->month, 1)
                ->format('F');
        }

        if ($filters->dateFrom || $filters->dateTo) {
            $criteria['Date Range'] = implode(' to ', [
                $filters->dateFrom?->format('M d, Y') ?? 'Beginning',
                $filters->dateTo?->format('M d, Y') ?? 'Present',
            ]);
        }

        if ($filters->term) {
            $criteria['Term'] = $filters->term->label();
        }

        if ($filters->status) {
            $criteria['Status'] = $filters->status->label();
        }

        if ($filters->adlId) {
            $criteria['ADL'] = Adl::find($filters->adlId)?->adl_number
                ?? 'ADL #'.$filters->adlId;
        }

        if ($filters->provinceId) {
            $criteria['Province'] = Province::find($filters->provinceId)?->name
                ?? 'Province #'.$filters->provinceId;
        }

        if ($filters->district) {
            $criteria['District'] = $filters->district;
        }

        if ($filters->municipalityId) {
            $criteria['Municipality'] =
                Municipality::find($filters->municipalityId)?->name
                ?? 'Municipality #'.$filters->municipalityId;
        }

        if ($filters->barangayId) {
            $criteria['Barangay'] = Barangay::find($filters->barangayId)?->name
                ?? 'Barangay #'.$filters->barangayId;
        }

        if ($filters->sponsor) {
            $criteria['Sponsor'] = $filters->sponsor;
        }

        if ($filters->partner) {
            $criteria['Partner / NGA'] = $filters->partner;
        }

        if ($filters->projectCode) {
            $criteria['Project Code'] = $filters->projectCode;
        }

        if ($filters->sector) {
            $criteria['Sector'] = $filters->sector->label();
        }

        if ($filters->interventionFocus) {
            $criteria['Intervention Focus'] =
                $filters->interventionFocus->label();
        }

        if ($filters->laborMarketProgram) {
            $criteria['Labor Market Program'] =
                $filters->laborMarketProgram->label();
        }

        return $criteria;
    }

    private function warning(
        ReportType $type,
        ReportDimension $dimension,
        Collection $rows,
    ): ?string {
        if (
            in_array(
                $type,
                [ReportType::PHYSICAL_FINANCIAL, ReportType::FUND_STATUS],
                true,
            )
            && $dimension->isFineGeography()
        ) {
            return 'Financial amounts are intentionally omitted because no official project financial allocation exists by district, municipality, or barangay.';
        }

        if (
            in_array(
                $type,
                [
                    ReportType::GEOGRAPHIC_BENEFICIARIES,
                    ReportType::PHYSICAL_FINANCIAL,
                    ReportType::FUND_STATUS,
                ],
                true,
            )
            && $rows->contains(
                fn (array $row): bool =>
                    ($row['legacy_unallocated_project_count'] ?? 0) > 0
            )
        ) {
            return 'Some legacy project-location rows have no exact barangay allocation. Their project totals were not guessed or duplicated.';
        }

        if ($type === ReportType::BENEFICIARY_SECTORS) {
            return 'Sector counts may overlap because one beneficiary may belong to more than one sector.';
        }

        return null;
    }
}
