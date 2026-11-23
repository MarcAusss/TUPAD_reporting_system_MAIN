<?php

namespace App\Services\Dashboards;

use App\Enums\ImplementationMode;
use App\Enums\ReportDimension;
use App\Models\Project;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Reports\OfficialPeriodicReportService;
use App\Services\Reports\ReportingDataService;
use Illuminate\Support\Collection;

/**
 * Pie charts of the official reports for the Focal and TUPAD Coordinator
 * dashboards. Each chart summarizes one report with the same data and
 * province scope the report itself uses: per province for Focal, per
 * municipality for a TUPAD Coordinator (their assigned province only).
 */
final class DashboardReportChartsService
{
    /** Province colors, matching the beneficiary distribution pies. */
    private const PROVINCE_COLORS = [
        'albay' => '#1d4ed8',
        'camarines norte' => '#ea580c',
        'camarines sur' => '#b91c1c',
        'catanduanes' => '#16a34a',
        'masbate' => '#7e22ce',
        'sorsogon' => '#be185d',
        'ro' => '#64748b',
    ];

    /** Categorical palette for everything that is not a province. */
    private const PALETTE = [
        '#063b86', '#0ea5e9', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444',
        '#14b8a6', '#f97316', '#6366f1', '#84cc16', '#ec4899', '#64748b',
    ];

    public function __construct(
        private readonly OfficialPeriodicReportService $periodic,
        private readonly ReportingDataService $reporting,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    /** @return array{year: int, scope: string, charts: list<array<string, mixed>>} */
    public function build(User $user, int $year): array
    {
        $byMunicipality = $this->provinceAccess->isProvinceScoped($user);
        $cqprRows = $this->cqprRows($user, $year);
        $groupKey = $byMunicipality ? 'municipality' : 'group';
        $place = $byMunicipality ? 'Municipality' : 'Province';

        $charts = [
            $this->chart(
                key: 'cqpr',
                title: 'CQPR · Projects per '.$place,
                subtitle: 'Consolidated Quarterly Progress Report, Q1–Q4 '.$year,
                unit: 'projects',
                url: route('reports.workspace.quarterly', ['view' => 'cqpr', 'fiscal_year' => $year]),
                slices: $cqprRows->groupBy($groupKey)
                    ->map(fn (Collection $rows, $label): array => [
                        'label' => $this->groupLabel((string) $label),
                        'value' => $rows->count(),
                        'note' => number_format((int) $rows->sum('beneficiaries')).' beneficiaries',
                        'color' => $byMunicipality ? null : $this->provinceColor((string) $label),
                    ]),
                byPlace: ! $byMunicipality,
            ),
            $this->chart(
                key: 'sprs',
                title: 'SPRS · Beneficiaries per '.$place,
                subtitle: 'Statistical Performance Reporting System, January–December '.$year,
                unit: 'beneficiaries',
                url: route('reports.workspace.monthly', ['view' => 'sprs', 'fiscal_year' => $year]),
                slices: $cqprRows->groupBy($groupKey)
                    ->map(fn (Collection $rows, $label): array => [
                        'label' => $this->groupLabel((string) $label),
                        'value' => (int) $rows->sum('beneficiaries'),
                        'note' => number_format((int) $rows->sum('female')).' female',
                        'color' => $byMunicipality ? null : $this->provinceColor((string) $label),
                    ]),
                byPlace: ! $byMunicipality,
            ),
            $this->statusChart($user),
            $this->implementationModeChart($user),
            $this->laborMarketChart($user, $year),
            $this->gpaiChart($user),
        ];

        return [
            'year' => $year,
            'scope' => $byMunicipality ? 'municipality' : 'province',
            'charts' => $charts,
        ];
    }

    /** CQPR rows of the year (the CQPR follows the SPRS reporting month). */
    private function cqprRows(User $user, int $year): Collection
    {
        $template = $this->periodic->cqprTemplate(['fiscal_year' => $year], $user);

        return collect($template['sections'])
            ->flatMap(fn (array $section): Collection => collect($section['groups'])
                ->flatMap(fn (array $group): Collection => collect($group['rows'])));
    }

    /** Fund Status · Report Status: every project by its workflow status. */
    private function statusChart(User $user): array
    {
        $projects = $this->provinceAccess->scopeProjects(Project::query(), $user)
            ->get(['id', 'status', 'total_project_cost']);

        return $this->chart(
            key: 'status',
            title: 'Fund Status · Projects by Status',
            subtitle: 'Report Status: every project in the workflow',
            unit: 'projects',
            url: route('reports.workspace.fund-status'),
            slices: $projects->groupBy(fn (Project $project): string => $project->status?->label() ?? 'Unspecified')
                ->map(fn (Collection $rows, $label): array => [
                    'label' => (string) $label,
                    'value' => $rows->count(),
                    'note' => '₱'.number_format((float) $rows->sum('total_project_cost'), 2),
                ]),
        );
    }

    /** Physical & Financial: Direct Administration vs Through ACP. */
    private function implementationModeChart(User $user): array
    {
        $projects = $this->provinceAccess->scopeProjects(Project::query(), $user)
            ->get(['id', 'implementation_mode', 'total_project_cost', 'beneficiaries_total']);

        return $this->chart(
            key: 'implementation_mode',
            title: 'Projects by Implementation Mode',
            subtitle: 'Physical & Financial: Direct Administration and Through ACP',
            unit: 'projects',
            url: route('reports.workspace.physical-financial'),
            slices: $projects->groupBy(fn (Project $project): string => $project->implementation_mode?->label() ?? 'Unspecified')
                ->map(fn (Collection $rows, $label): array => [
                    'label' => (string) $label,
                    'value' => $rows->count(),
                    'note' => '₱'.number_format((float) $rows->sum('total_project_cost'), 2),
                    'color' => match ($label) {
                        ImplementationMode::DIRECT_ADMINISTRATION->label() => '#063b86',
                        ImplementationMode::THROUGH_ACP->label() => '#f59e0b',
                        default => '#94a3b8',
                    },
                ]),
        );
    }

    /** Quarterly · Beneficiaries referred to the Active Labor Market, by program. */
    private function laborMarketChart(User $user, int $year): array
    {
        $filters = ['fiscal_year' => $year];

        if ($this->provinceAccess->isProvinceScoped($user)) {
            $filters['province_id'] = $this->provinceAccess->assignedProvinceId($user) ?? 0;
        }

        $rows = $this->reporting->laborMarketAggregation(
            ReportFilters::fromArray($filters),
            ReportDimension::LABOR_MARKET_PROGRAM,
        );

        return $this->chart(
            key: 'labor_market',
            title: 'Active Labor Market Referrals',
            subtitle: 'Beneficiaries referred per program, '.$year,
            unit: 'referred',
            url: route('reports.workspace.quarterly', ['view' => 'labor-market', 'fiscal_year' => $year]),
            slices: $rows->map(fn (array $row): array => [
                'label' => (string) $row['label'],
                'value' => (int) $row['interested_referred_total'],
                'note' => number_format((int) $row['provided_intervention_total']).' provided intervention',
            ]),
        );
    }

    /** GSIS GPAI enrollment of the projects with an insurance budget. */
    private function gpaiChart(User $user): array
    {
        $projects = $this->provinceAccess->scopeProjects(Project::query(), $user)
            ->where('insurance_total', '>', 0)
            ->with('gpaiEnrollmentItem.enrollment')
            ->get(['id', 'insurance_total']);

        $stateOf = function (Project $project): string {
            $enrollment = $project->gpaiEnrollmentItem?->enrollment;

            return match (true) {
                $enrollment === null => 'Awaiting Enrollment',
                $enrollment->isComplete() => 'Enrolled · OR & Policy Received',
                default => 'Enrollment In Process',
            };
        };

        $colors = [
            'Awaiting Enrollment' => '#f59e0b',
            'Enrollment In Process' => '#0ea5e9',
            'Enrolled · OR & Policy Received' => '#10b981',
        ];

        return $this->chart(
            key: 'gpai',
            title: 'GSIS GPAI Enrollment',
            subtitle: 'Projects with an insurance budget',
            unit: 'projects',
            url: route('gpai-status.index'),
            slices: $projects->groupBy($stateOf)
                ->map(fn (Collection $rows, $label): array => [
                    'label' => (string) $label,
                    'value' => $rows->count(),
                    'note' => '₱'.number_format((float) $rows->sum('insurance_total'), 2).' premium',
                    'color' => $colors[(string) $label],
                ]),
        );
    }

    /**
     * @param  Collection<array-key, array{label: string, value: int, note?: string, color?: ?string}>  $slices
     * @return array<string, mixed>
     */
    private function chart(string $key, string $title, string $subtitle, string $unit, string $url, Collection $slices, bool $byPlace = false): array
    {
        $slices = $slices
            ->values()
            ->filter(fn (array $slice): bool => $slice['value'] > 0)
            ->sortByDesc('value')
            ->values()
            ->map(fn (array $slice, int $index): array => [
                ...$slice,
                'color' => ($slice['color'] ?? null) ?: self::PALETTE[$index % count(self::PALETTE)],
            ]);

        // Province pies keep the official province order instead of size order.
        if ($byPlace) {
            $order = array_keys(self::PROVINCE_COLORS);
            $slices = $slices->sortBy(function (array $slice) use ($order): int {
                $position = array_search(strtolower($slice['label'] === 'Regional Office' ? 'ro' : $slice['label']), $order, true);

                return $position === false ? 99 : $position;
            })->values();
        }

        $total = (int) $slices->sum('value');
        $start = 0.0;
        $stops = [];

        foreach ($slices as $slice) {
            $end = $start + ($total > 0 ? $slice['value'] / $total * 100 : 0);
            $stops[] = sprintf('%s %.3f%% %.3f%%', $slice['color'], $start, $end);
            $start = $end;
        }

        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'unit' => $unit,
            'url' => $url,
            'total' => $total,
            'gradient' => $stops === [] ? null : 'conic-gradient('.implode(', ', $stops).')',
            'slices' => $slices
                ->map(fn (array $slice): array => [
                    ...$slice,
                    'percent' => $total > 0 ? round($slice['value'] / $total * 100, 1) : 0.0,
                ])
                ->all(),
        ];
    }

    private function groupLabel(string $label): string
    {
        return $label === 'RO' ? 'Regional Office' : $label;
    }

    private function provinceColor(string $label): ?string
    {
        return self::PROVINCE_COLORS[strtolower($label)] ?? null;
    }
}
