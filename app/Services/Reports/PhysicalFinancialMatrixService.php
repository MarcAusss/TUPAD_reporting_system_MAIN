<?php

namespace App\Services\Reports;

use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Models\Project;
use App\Models\Province;
use App\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class PhysicalFinancialMatrixService
{
    private const BICOL_PROVINCES = [
        'Albay',
        'Camarines Norte',
        'Camarines Sur',
        'Catanduanes',
        'Masbate',
        'Sorsogon',
    ];

    public function __construct(
        private readonly ReportingDataService $reportingData,
    ) {}

    public function build(
        ReportFilters $filters,
        ReportDimension $dimension,
    ): array {
        if (! in_array($dimension, [
            ReportDimension::OVERALL,
            ReportDimension::SEMESTER,
            ReportDimension::QUARTER,
            ReportDimension::MONTH,
        ], true)) {
            throw new InvalidArgumentException(
                'The official Physical & Financial matrix supports overall, semester, quarter, and month views only.'
            );
        }

        $projects = $this->reportingData->projects($filters);
        $periods = $this->periods($dimension);
        $provinceBuckets = $this->provinceBuckets($projects, $filters);

        $rows = $provinceBuckets
            ->map(
                fn (Collection $provinceProjects, string $province): array =>
                    $this->matrixRow($province, $provinceProjects, $periods)
            )
            ->values();

        return [
            'dimension' => $dimension->value,
            'fiscal_year' => $filters->fiscalYear,
            'periods' => $periods,
            'rows' => $rows,
            'total' => $this->matrixRow('TOTAL', $projects, $periods),
            'basis_note' => implode(' ', [
                'Physical target uses encoded project beneficiaries.',
                'Physical accomplishment uses beneficiaries on projects currently marked Completed.',
                'Financial target uses encoded total project cost.',
                'Financial accomplishment uses recorded disbursements.',
                'Balance is target less accomplishment.',
                'Period columns use projects.date_received as the reporting-period basis.',
            ]),
        ];
    }

    /**
     * @param Collection<int, Project> $projects
     * @return Collection<string, Collection<int, Project>>
     */
    private function provinceBuckets(
        Collection $projects,
        ReportFilters $filters,
    ): Collection {
        if ($filters->provinceId !== null) {
            $province = Province::query()->find($filters->provinceId);
            $names = collect([$province?->name ?? 'Selected Province']);
        } else {
            $names = collect(self::BICOL_PROVINCES);
        }

        $buckets = $names->mapWithKeys(
            fn (string $name): array => [$name => collect()]
        );

        foreach ($projects as $project) {
            foreach ($this->provinceNames($project) as $provinceName) {
                if (! $buckets->has($provinceName)) {
                    if ($filters->provinceId !== null) {
                        continue;
                    }

                    $buckets->put($provinceName, collect());
                }

                /** @var Collection<int, Project> $bucket */
                $bucket = $buckets->get($provinceName);

                if (! $bucket->contains('id', $project->id)) {
                    $bucket->push($project);
                }
            }
        }

        return $buckets;
    }

    /** @return array<int, string> */
    private function provinceNames(Project $project): array
    {
        $names = $project->projectLocations
            ->map(fn ($location): ?string => $location->province?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names !== []) {
            return $names;
        }

        $legacy = trim((string) (
            $project->provinceReference?->name
            ?: $project->province
        ));

        return [$legacy !== '' ? $legacy : 'Unspecified Province'];
    }

    /**
     * @param Collection<int, Project> $projects
     * @param array<int, array{key:string,label:string}> $periods
     */
    private function matrixRow(
        string $province,
        Collection $projects,
        array $periods,
    ): array {
        $target = $this->targetMetrics($projects);
        $accomplishment = $this->accomplishmentMetrics($projects);

        $periodRows = [];

        foreach ($periods as $period) {
            $periodProjects = $projects
                ->filter(
                    fn (Project $project): bool =>
                        $this->projectMatchesPeriod($project, $period['key'])
                )
                ->values();

            $periodRows[$period['key']] =
                $this->accomplishmentMetrics($periodProjects);
        }

        return [
            'province' => $province,
            'project_count' => $projects->count(),
            'target' => $target,
            'accomplishment' => $accomplishment,
            'periods' => $periodRows,
            'balance' => [
                'physical' =>
                    $target['physical'] - $accomplishment['physical'],
                'financial_cents' =>
                    $target['financial_cents'] - $accomplishment['financial_cents'],
            ],
        ];
    }

    /** @param Collection<int, Project> $projects */
    private function targetMetrics(Collection $projects): array
    {
        $metrics = $this->reportingData
            ->physicalFinancial(
                new ReportFilters(),
                ReportDimension::OVERALL,
                $projects,
            )
            ->first() ?? [];

        return [
            'physical' => (int) $projects->sum('beneficiaries_total'),
            'financial_cents' => (int) ($metrics['project_cost_cents'] ?? 0),
        ];
    }

    /** @param Collection<int, Project> $projects */
    private function accomplishmentMetrics(Collection $projects): array
    {
        $metrics = $this->reportingData
            ->physicalFinancial(
                new ReportFilters(),
                ReportDimension::OVERALL,
                $projects,
            )
            ->first() ?? [];

        return [
            'physical' => (int) $projects
                ->filter(
                    fn (Project $project): bool =>
                        $project->status === ProjectStatus::COMPLETED
                )
                ->sum('beneficiaries_total'),
            'financial_cents' => (int) ($metrics['disbursed_cents'] ?? 0),
        ];
    }

    private function projectMatchesPeriod(
        Project $project,
        string $periodKey,
    ): bool {
        $date = $project->date_received;

        if ($date === null) {
            return false;
        }

        if (str_starts_with($periodKey, 'semester-')) {
            $semester = (int) substr($periodKey, strlen('semester-'));

            return $semester === 1
                ? $date->month <= 6
                : $date->month >= 7;
        }

        if (str_starts_with($periodKey, 'quarter-')) {
            return $date->quarter === (int) substr(
                $periodKey,
                strlen('quarter-')
            );
        }

        if (str_starts_with($periodKey, 'month-')) {
            return $date->month === (int) substr(
                $periodKey,
                strlen('month-')
            );
        }

        return false;
    }

    /** @return array<int, array{key:string,label:string}> */
    private function periods(ReportDimension $dimension): array
    {
        return match ($dimension) {
            ReportDimension::SEMESTER => [
                ['key' => 'semester-1', 'label' => '1st Semester'],
                ['key' => 'semester-2', 'label' => '2nd Semester'],
            ],

            ReportDimension::QUARTER => [
                ['key' => 'quarter-1', 'label' => '1st Quarter'],
                ['key' => 'quarter-2', 'label' => '2nd Quarter'],
                ['key' => 'quarter-3', 'label' => '3rd Quarter'],
                ['key' => 'quarter-4', 'label' => '4th Quarter'],
            ],

            ReportDimension::MONTH => collect(range(1, 12))
                ->map(
                    fn (int $month): array => [
                        'key' => 'month-'.$month,
                        'label' => CarbonImmutable::create(
                            2000,
                            $month,
                            1,
                        )->format('F'),
                    ]
                )
                ->all(),

            default => [],
        };
    }
}
