<?php

namespace App\Services\Mapping;

final class BicolMapMetricService
{
    public const BENEFICIARIES = 'beneficiaries';
    public const PROJECTS = 'projects';
    public const ALLOCATION = 'allocation';

    /** @return array<int,string> */
    public static function keys(): array
    {
        return [
            self::BENEFICIARIES,
            self::PROJECTS,
            self::ALLOCATION,
        ];
    }

    /** @param array<string,mixed> $payload */
    public function apply(array $payload, string $requestedMetric): array
    {
        $level = (string) ($payload['map_level'] ?? 'region');
        $allocationAvailable = $level === 'region';
        $requestedMetric = in_array($requestedMetric, self::keys(), true)
            ? $requestedMetric
            : self::BENEFICIARIES;

        $effectiveMetric = $requestedMetric;
        $notice = null;

        if ($requestedMetric === self::ALLOCATION && ! $allocationAvailable) {
            $effectiveMetric = self::BENEFICIARIES;
            $notice = 'Allocation is available only on the Bicol Region province map because no authoritative municipality/barangay financial split exists.';
        }

        $metric = $this->definition($effectiveMetric);

        foreach (['areas', 'provinces', 'municipalities', 'barangays'] as $key) {
            $payload[$key] = $this->rowsWithMetric(
                is_array($payload[$key] ?? null) ? $payload[$key] : [],
                $effectiveMetric,
            );
        }

        $mapRows = $level === 'region'
            ? ($payload['provinces'] ?? [])
            : ($payload['municipalities'] ?? []);
        $areaRows = $payload['areas'] ?? [];

        $summaryValue = match ($effectiveMetric) {
            self::PROJECTS => (int) \data_get($payload, 'summary.projects', 0),
            self::ALLOCATION => (int) \data_get($payload, 'summary.allocation_cents', 0),
            default => (int) \data_get($payload, 'summary.beneficiaries', 0),
        };

        $payload['metric'] = $metric + [
            'requested_key' => $requestedMetric,
            'available' => true,
        ];
        $payload['metric_options'] = $this->options($allocationAvailable);
        $payload['metric_notice'] = $notice;
        $payload['metric_summary'] = [
            'value' => $summaryValue,
            'display' => $this->formatValue($effectiveMetric, $summaryValue),
            'label' => $metric['label'],
        ];
        $payload['legend'] = $this->legend($mapRows, $effectiveMetric);
        $payload['empty_state'] = [
            'has_rows' => count($areaRows) > 0,
            'has_values' => \collect($areaRows)->contains(
                static fn (array $row): bool => (int) ($row['value'] ?? 0) > 0,
            ),
            'message' => 'No '.$metric['label'].' values matched the current reporting filters for this geographic scope.',
        ];
        $payload['metric_note'] = $this->metricNote($effectiveMetric, $level, (string) ($payload['data_note'] ?? ''));

        return $payload;
    }

    /** @return array{key:string,label:string,description:string,unit:string} */
    private function definition(string $metric): array
    {
        return match ($metric) {
            self::PROJECTS => [
                'key' => self::PROJECTS,
                'label' => 'Projects',
                'description' => 'Projects associated with each authoritative geographic row',
                'unit' => 'projects',
            ],
            self::ALLOCATION => [
                'key' => self::ALLOCATION,
                'label' => 'Allocation',
                'description' => 'Province-level project-cohort allocation from the fund-status data layer',
                'unit' => 'PHP',
            ],
            default => [
                'key' => self::BENEFICIARIES,
                'label' => 'Beneficiaries',
                'description' => 'Exact geographically allocated beneficiaries',
                'unit' => 'beneficiaries',
            ],
        };
    }

    /** @return array<int,array{key:string,label:string,available:bool,reason:?string}> */
    private function options(bool $allocationAvailable): array
    {
        return [
            [
                'key' => self::BENEFICIARIES,
                'label' => 'Beneficiaries',
                'available' => true,
                'reason' => null,
            ],
            [
                'key' => self::PROJECTS,
                'label' => 'Projects',
                'available' => true,
                'reason' => null,
            ],
            [
                'key' => self::ALLOCATION,
                'label' => 'Allocation',
                'available' => $allocationAvailable,
                'reason' => $allocationAvailable
                    ? null
                    : 'Available only when the map is showing Bicol provinces.',
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function rowsWithMetric(array $rows, string $metric): array
    {
        $mapped = \collect($rows)
            ->map(function (array $row) use ($metric): array {
                $value = match ($metric) {
                    self::PROJECTS => (int) ($row['projects'] ?? 0),
                    self::ALLOCATION => ($row['allocation_available'] ?? false)
                        ? (int) ($row['allocation_cents'] ?? 0)
                        : 0,
                    default => (int) ($row['beneficiaries'] ?? 0),
                };

                $row['value'] = $value;
                $row['value_display'] = $this->formatValue($metric, $value);

                return $row;
            })
            ->sort(static function (array $a, array $b): int {
                $valueOrder = ((int) ($b['value'] ?? 0)) <=> ((int) ($a['value'] ?? 0));

                return $valueOrder !== 0
                    ? $valueOrder
                    : strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            })
            ->values();

        return $mapped->all();
    }

    /**
     * @param array<int,array<string,mixed>> $mapRows
     * @return array<int,array{max:int,label:string}>
     */
    private function legend(array $mapRows, string $metric): array
    {
        $max = (int) \collect($mapRows)->max(
            static fn (array $row): int => (int) ($row['value'] ?? 0),
        );

        if ($max <= 0) {
            return array_fill(0, 5, [
                'max' => 0,
                'label' => $this->formatValue($metric, 0),
            ]);
        }

        $bands = [];
        foreach ([0.20, 0.40, 0.60, 0.80, 1.00] as $ratio) {
            $upper = max(1, (int) ceil($max * $ratio));
            $bands[] = [
                'max' => $upper,
                'label' => '≤ '.$this->formatValue($metric, $upper),
            ];
        }

        return $bands;
    }

    private function formatValue(string $metric, int $value): string
    {
        if ($metric === self::ALLOCATION) {
            return '₱'.number_format($value / 100, 0);
        }

        return number_format($value);
    }

    private function metricNote(string $metric, string $level, string $baseNote): string
    {
        return match ($metric) {
            self::PROJECTS => 'Project choropleth values count projects associated with each authoritative geographic row. Cohort KPI project totals remain unique and should not be interpreted as the sum of all geographic row counts.',
            self::ALLOCATION => 'Allocation choropleth values use the existing province-level fund-status project cohorts. Allocation is intentionally unavailable below province geography; no municipality or barangay amount is inferred.',
            default => $baseNote !== ''
                ? $baseNote
                : 'Beneficiary choropleth values use exact project-location/barangay geographic allocations.',
        };
    }
}
