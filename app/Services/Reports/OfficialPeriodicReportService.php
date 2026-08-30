<?php

namespace App\Services\Reports;

use App\Enums\LaborMarketProgram;
use App\Enums\ReportDimension;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Auth\ProvinceAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OfficialPeriodicReportService
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
        private readonly ReportingDataService $data,
        private readonly ReportGenerationService $formatter,
    ) {}

    public function build(string $form, array $filters, User $user): array
    {
        return match ($form) {
            'sprs' => $this->sprs($filters, $user),
            'orientations' => $this->orientations($filters, $user),
            'cqpr' => $this->cqpr($filters, $user),
            'labor-market' => $this->laborMarket($filters, $user),
            default => abort(404),
        };
    }

    private function sprs(array $filters, User $user): array
    {
        $year = (int) $filters['fiscal_year'];
        $month = (int) $filters['month'];
        $projects = $this->projectQuery($filters, $user)
            ->whereHas('monitoringDetail', fn (Builder $query): Builder => $query
                ->whereYear('sprs_date', $year)
                ->whereMonth('sprs_date', $month))
            ->with(['monitoringDetail', 'provinceReference'])
            ->get();

        $rows = $projects
            ->groupBy(fn (Project $project): string => $project->provinceReference?->name ?: $project->province ?: 'Unspecified Province')
            ->map(fn (Collection $group, string $province): array => [
                'province' => $province,
                'projects' => $group->count(),
                'total' => $group->sum(fn (Project $project): int => (int) $project->beneficiaries_total),
                'female' => $group->sum(fn (Project $project): int => (int) $project->beneficiaries_female),
            ])
            ->sortBy('province', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->report(
            'Statistical Performance Reporting System (SPRS)',
            'MONTHLY REPORT · SPRS',
            CarbonImmutable::create($year, $month, 1)->format('F Y'),
            $this->criteria($filters, 'project_monitoring_details.sprs_date'),
            [
                $this->column('province', 'Province'),
                $this->column('projects', 'Projects', 'integer'),
                $this->column('total', 'Overall Total', 'integer'),
                $this->column('female', 'Female', 'integer'),
            ],
            $rows,
            'SPRS membership is based on the encoded SPRS monitoring date. No month is inferred from the project receipt date.',
        );
    }

    private function orientations(array $filters, User $user): array
    {
        $year = (int) $filters['fiscal_year'];
        $month = (int) $filters['month'];
        $projects = $this->projectQuery($filters, $user)
            ->whereHas('orientation', fn (Builder $query): Builder => $query
                ->whereYear('orientation_date', $year)
                ->whereMonth('orientation_date', $month))
            ->with(['orientation', 'provinceReference', 'municipalityReference'])
            ->get();

        $rows = $projects->map(function (Project $project): array {
            $orientation = $project->orientation;
            $programs = collect([
                $orientation?->alkansssya_conducted ? 'AlkanSSSya' : null,
                $orientation?->yakap_conducted ? 'YAKAP Program for TUPAD Beneficiaries' : null,
            ])->filter()->implode('; ');

            return [
                'date' => $orientation?->orientation_date?->format('M d, Y') ?: '—',
                'project' => $project->project_title,
                'province' => $project->provinceReference?->name ?: $project->province ?: '—',
                'municipality' => $project->municipalityReference?->name ?: $project->municipality ?: '—',
                'beneficiaries' => (int) $project->beneficiaries_total,
                'programs' => $programs !== '' ? $programs : 'Program coverage not specified',
            ];
        })->sortBy('date')->values();

        return $this->report(
            'List of Orientations Conducted',
            'MONTHLY REPORT · ORIENTATIONS',
            CarbonImmutable::create($year, $month, 1)->format('F Y'),
            $this->criteria($filters, 'project_orientations.orientation_date'),
            [
                $this->column('date', 'Orientation Date'),
                $this->column('project', 'Project'),
                $this->column('province', 'Province'),
                $this->column('municipality', 'City / Municipality'),
                $this->column('beneficiaries', 'Beneficiaries', 'integer'),
                $this->column('programs', 'Program Coverage'),
            ],
            $rows,
            'AlkanSSSya and YAKAP coverage is printed only when explicitly encoded. Legacy remarks are not parsed to infer a program.',
        );
    }

    private function cqpr(array $filters, User $user): array
    {
        $year = (int) $filters['fiscal_year'];
        $quarter = (int) $filters['quarter'];
        $months = range((($quarter - 1) * 3) + 1, (($quarter - 1) * 3) + 3);
        $projects = $this->projectQuery($filters, $user)
            ->whereHas('monitoringDetail', fn (Builder $query): Builder => $query->whereYear('cqpr_date', $year))
            ->with(['monitoringDetail', 'provinceReference', 'municipalityReference', 'approval'])
            ->get()
            ->filter(fn (Project $project): bool => in_array((int) $project->monitoringDetail?->cqpr_date?->month, $months, true))
            ->values();

        $rows = $projects->map(fn (Project $project): array => [
            'project' => $project->project_title,
            'proponent' => $project->monitoringDetail?->proponent ?: '—',
            'barangay' => $project->barangay ?: '—',
            'municipality' => $project->municipalityReference?->name ?: $project->municipality ?: '—',
            'province' => $project->provinceReference?->name ?: $project->province ?: '—',
            'district' => $project->district ?: '—',
            'term' => $project->term?->label() ?: '—',
            'beneficiaries' => (int) $project->beneficiaries_total,
            'female' => (int) $project->beneficiaries_female,
            'amount' => 'PHP '.number_format((float) $project->total_project_cost, 2),
            'fund_source' => $project->fund_sponsor ?: '—',
            'convergence' => $project->partner ?: '—',
            'status' => $project->status?->label() ?: '—',
        ])->values();

        return $this->report(
            'Consolidated Quarterly Progress Report (CQPR)',
            'QUARTERLY REPORT · CQPR',
            'Q'.$quarter.' '.$year,
            $this->criteria($filters, 'project_monitoring_details.cqpr_date'),
            [
                $this->column('project', 'Name & Nature of Project'),
                $this->column('proponent', 'Implementer / Proponent'),
                $this->column('barangay', 'Barangay'),
                $this->column('municipality', 'City / Municipality'),
                $this->column('province', 'Province'),
                $this->column('district', 'District'),
                $this->column('term', 'Work Period'),
                $this->column('beneficiaries', 'Beneficiaries', 'integer'),
                $this->column('female', 'Female', 'integer'),
                $this->column('amount', 'Amount'),
                $this->column('fund_source', 'Fund Source'),
                $this->column('convergence', 'Convergence / Partner'),
                $this->column('status', 'Project Status'),
            ],
            $rows,
            'CQPR membership uses the recorded CQPR monitoring date. Columns print only values already encoded in the TUPAD Reporting System.',
        );
    }

    private function laborMarket(array $filters, User $user): array
    {
        if ($user->isTc()) {
            abort_unless($user->assigned_province_id !== null, 403);
            if (filled($filters['province_id'] ?? null) && (int) $filters['province_id'] !== (int) $user->assigned_province_id) {
                abort(403);
            }
            $filters['province_id'] = (int) $user->assigned_province_id;
        }

        $reportFilters = ReportFilters::fromArray($filters);
        $rows = $this->data->laborMarketAggregation($reportFilters, ReportDimension::LABOR_MARKET_PROGRAM)
            ->map(fn (array $row): array => [
                'intervention' => $row['label'],
                'referred' => (int) $row['interested_referred_total'],
                'female_referred' => (int) $row['interested_referred_female'],
                'provided' => (int) $row['provided_intervention_total'],
                'female_provided' => (int) $row['provided_intervention_female'],
                'amount' => $this->formatter->formatValue('money', $row['amount_released_cents']),
                'services' => $this->formatter->formatValue('list', $row['services_availed']),
            ]);

        return $this->report(
            'Number of TUPAD Beneficiaries Referred to Active Labor Market',
            'QUARTERLY REPORT · ACTIVE LABOR MARKET',
            'Q'.(int) $filters['quarter'].' '.(int) $filters['fiscal_year'],
            $this->criteria($filters, 'project_labor_market_referrals.reporting_month'),
            [
                $this->column('intervention', 'Intervention'),
                $this->column('referred', 'Interested TUPAD Beneficiaries Referred', 'integer'),
                $this->column('female_referred', 'Female', 'integer'),
                $this->column('provided', 'Referred Beneficiaries Provided with Intervention', 'integer'),
                $this->column('female_provided', 'Female', 'integer'),
                $this->column('amount', 'Amount Released under the Intervention'),
                $this->column('services', 'Types of Skills Training / Livelihood Assistance / Employment Services Availed'),
            ],
            $rows,
            'Referral totals use the encoded reporting month and do not infer participation from project beneficiary totals.',
        );
    }

    private function projectQuery(array $filters, User $user): Builder
    {
        return $this->provinceAccess
            ->scopeProjects(Project::query(), $user)
            ->when(filled($filters['province_id'] ?? null), fn (Builder $query): Builder => $query->where('province_id', (int) $filters['province_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when(filled($filters['implementation_mode'] ?? null), fn (Builder $query): Builder => $query->where('implementation_mode', (string) $filters['implementation_mode']));
    }

    private function criteria(array $filters, string $periodBasis): array
    {
        $criteria = ['Period Basis' => $periodBasis];
        if (filled($filters['fiscal_year'] ?? null)) $criteria['Fiscal Year'] = (string) $filters['fiscal_year'];
        if (filled($filters['month'] ?? null)) $criteria['Month'] = CarbonImmutable::create(2000, (int) $filters['month'], 1)->format('F');
        if (filled($filters['quarter'] ?? null)) $criteria['Quarter'] = 'Q'.$filters['quarter'];
        if (filled($filters['province_id'] ?? null)) $criteria['Province'] = Province::find((int) $filters['province_id'])?->name ?? 'Province #'.$filters['province_id'];
        if (filled($filters['status'] ?? null)) $criteria['Status'] = (string) $filters['status'];
        if (filled($filters['implementation_mode'] ?? null)) $criteria['Implementation Mode'] = (string) $filters['implementation_mode'];
        if (filled($filters['labor_market_program'] ?? null)) $criteria['Labor Market Program'] = LaborMarketProgram::tryFrom((string) $filters['labor_market_program'])?->label() ?? (string) $filters['labor_market_program'];
        return $criteria;
    }

    private function report(string $title, string $code, string $period, array $criteria, array $columns, Collection $rows, ?string $warning): array
    {
        $displayRows = $rows->map(fn (array $row): array => collect($columns)->mapWithKeys(function (array $column) use ($row): array {
            $value = $row[$column['key']] ?? null;
            return [$column['key'] => $column['format'] === 'integer' ? number_format((int) $value) : (filled($value) ? (string) $value : '—')];
        })->all());

        return [
            'title' => $title,
            'official_title' => $title,
            'official_kicker' => 'TUPAD Reporting System',
            'official_code' => $code,
            'official_period' => $period,
            'columns' => $columns,
            'rows' => $rows,
            'display_rows' => $displayRows,
            'summary_cards' => [],
            'criteria' => $criteria,
            'warning' => $warning,
            'generated_at' => now('Asia/Manila'),
            'file_base_name' => 'tupad-'.str($code)->slug('-').'-'.now('Asia/Manila')->format('Ymd-His'),
            'official_layout' => true,
        ];
    }

    private function column(string $key, string $label, string $format = 'text'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'format' => $format,
            'align' => $format === 'integer' ? 'right' : 'left',
        ];
    }
}
