<?php

namespace App\Services\Dashboards;

use App\Enums\ReportDimension;
use App\Models\Municipality;
use App\Models\Province;
use App\Reports\ReportFilters;
use App\Services\Mapping\BicolGeographicFoundation;
use App\Services\Reports\ReportingDataService;
use Illuminate\Support\Collection;

/**
 * Beneficiary distribution for the Focal dashboard, modeled on the
 * "Resource Mapping" illustrations: one pie per Bicol province split by
 * congressional district, drilling down to municipality and barangay bars.
 *
 * Counts are the Actual Beneficiary Mapping (beneficiary address allocations
 * minus the TC's deductions) across every official project, the same dataset
 * the Geographic Mapping report uses.
 */
final class DashboardBeneficiaryDistributionService
{
    /**
     * Province color families, darkest first. District N uses shade N-1, so
     * the colors stay consistent between the pie and the bar charts.
     *
     * @var array<string, array{key:string,label:string,shades:list<string>}>
     */
    private const PALETTES = [
        'albay' => ['key' => 'blue', 'label' => 'Blue', 'shades' => ['#1e3a8a', '#1d4ed8', '#3b82f6', '#60a5fa', '#93c5fd']],
        'camarines norte' => ['key' => 'orange', 'label' => 'Orange', 'shades' => ['#9a3412', '#ea580c', '#f97316', '#fb923c', '#fdba74']],
        'camarines sur' => ['key' => 'red', 'label' => 'Red', 'shades' => ['#7f1d1d', '#b91c1c', '#ef4444', '#f87171', '#fca5a5']],
        'catanduanes' => ['key' => 'green', 'label' => 'Green', 'shades' => ['#15803d', '#16a34a', '#22c55e', '#4ade80', '#86efac']],
        'masbate' => ['key' => 'purple', 'label' => 'Purple', 'shades' => ['#581c87', '#7e22ce', '#a855f7', '#c084fc', '#d8b4fe']],
        'sorsogon' => ['key' => 'pink', 'label' => 'Pink', 'shades' => ['#831843', '#be185d', '#ec4899', '#f472b6', '#f9a8d4']],
    ];

    private const FALLBACK_PALETTE = ['key' => 'slate', 'label' => 'Slate', 'shades' => ['#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1']];

    public const UNASSIGNED_DISTRICT = 'Unassigned District';

    public function __construct(
        private readonly ReportingDataService $reporting,
        private readonly BicolGeographicFoundation $foundation,
    ) {}

    /**
     * @param  int|null  $provinceId  Only this province (a TUPAD Coordinator's assigned province).
     * @return array<string, mixed>
     */
    public function build(?int $provinceId = null): array
    {
        $municipalityCounts = $this->reporting
            ->beneficiaryGeography($this->filters(), ReportDimension::MUNICIPALITY)
            ->keyBy(fn (array $row): string => (string) $row['key']);

        return $this->buildFromCounts(
            $municipalityCounts,
            $provinceId,
            route('dashboard.beneficiary-distribution.barangays'),
        );
    }

    /**
     * The province → district → municipality structure from counts keyed by
     * municipality id ("beneficiaries_total" is the charted value and
     * "beneficiaries_female" the secondary one). Shared with the project
     * distribution so both charts look and behave the same.
     *
     * @param  Collection<string, array<string, mixed>>  $municipalityCounts
     * @return array<string, mixed>
     */
    public function buildFromCounts(Collection $municipalityCounts, ?int $provinceId, string $barangayEndpoint): array
    {
        $provinces = $this->foundation->provinces()
            ->when($provinceId !== null, fn ($provinces) => $provinces->where('id', $provinceId))
            ->map(fn (Province $province): array => $this->provinceRow($province, $municipalityCounts))
            ->values();

        return [
            'provinces' => $provinces->all(),
            'summary' => [
                'total' => (int) $provinces->sum('total'),
                'female' => (int) $provinces->sum('female'),
                'municipalities_served' => (int) $provinces->sum(
                    fn (array $province): int => collect($province['municipalities'])->where('total', '>', 0)->count()
                ),
            ],
            'barangay_endpoint' => $barangayEndpoint,
        ];
    }

    /**
     * Every active barangay of one municipality with its beneficiary count,
     * zeros included so the chart reads like the resource mapping sheets.
     *
     * @return array<string, mixed>
     */
    public function barangays(Municipality $municipality): array
    {
        $counts = $this->reporting
            ->beneficiaryGeography(
                $this->filters(['municipality_id' => $municipality->id]),
                ReportDimension::BARANGAY,
            )
            ->keyBy(fn (array $row): string => (string) $row['key']);

        return $this->barangaysFromCounts($municipality, $counts);
    }

    /**
     * Every active barangay of the municipality with its count from counts
     * keyed by barangay id (same keys as buildFromCounts()).
     *
     * @param  Collection<string, array<string, mixed>>  $counts
     * @return array<string, mixed>
     */
    public function barangaysFromCounts(Municipality $municipality, Collection $counts): array
    {
        $rows = $this->foundation->barangaysForMunicipality($municipality)
            ->map(fn ($barangay): array => [
                'id' => (int) $barangay->id,
                'name' => (string) $barangay->name,
                'total' => (int) ($counts->get((string) $barangay->id)['beneficiaries_total'] ?? 0),
                'female' => (int) ($counts->get((string) $barangay->id)['beneficiaries_female'] ?? 0),
            ])
            ->values();

        return [
            'municipality' => [
                'id' => (int) $municipality->id,
                'name' => (string) $municipality->name,
                'district' => $municipality->district ?: self::UNASSIGNED_DISTRICT,
            ],
            'rows' => $rows->all(),
            'total' => (int) $rows->sum('total'),
            'female' => (int) $rows->sum('female'),
        ];
    }

    /** @param array<string, mixed> $extra */
    private function filters(array $extra = []): ReportFilters
    {
        // Dashboards track the whole pipeline, not only reportable projects.
        return ReportFilters::fromArray([
            'all_projects' => true,
            'actual_beneficiaries' => true,
            ...$extra,
        ]);
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $municipalityCounts
     * @return array<string, mixed>
     */
    private function provinceRow(Province $province, Collection $municipalityCounts): array
    {
        $palette = self::PALETTES[strtolower(trim((string) $province->name))] ?? self::FALLBACK_PALETTE;

        $municipalities = $this->foundation->municipalitiesForProvince($province)
            ->map(function (Municipality $municipality) use ($municipalityCounts): array {
                $count = $municipalityCounts->get((string) $municipality->id, []);

                return [
                    'id' => (int) $municipality->id,
                    'name' => (string) $municipality->name,
                    'district' => $municipality->district ?: self::UNASSIGNED_DISTRICT,
                    'total' => (int) ($count['beneficiaries_total'] ?? 0),
                    'female' => (int) ($count['beneficiaries_female'] ?? 0),
                ];
            })
            ->values();

        $districtNames = $municipalities
            ->pluck('district')
            ->unique()
            ->sort(fn (string $a, string $b): int => $this->districtOrder($a) <=> $this->districtOrder($b) ?: strnatcasecmp($a, $b))
            ->values();

        $districts = $districtNames
            ->map(function (string $name, int $index) use ($municipalities, $palette): array {
                $members = $municipalities->where('district', $name);

                return [
                    'name' => $name,
                    'color' => $name === self::UNASSIGNED_DISTRICT
                        ? '#94a3b8'
                        : $palette['shades'][$index % count($palette['shades'])],
                    'total' => (int) $members->sum('total'),
                    'female' => (int) $members->sum('female'),
                    'municipality_count' => $members->count(),
                ];
            })
            ->values();

        // A lone district reads better in the province's main shade.
        if ($districts->count() === 1 && $districts[0]['name'] !== self::UNASSIGNED_DISTRICT) {
            $districts = collect([[...$districts[0], 'color' => $palette['shades'][1]]]);
        }

        return [
            'id' => (int) $province->id,
            'name' => (string) $province->name,
            'color' => $palette['shades'][1],
            'color_name' => $palette['label'],
            'total' => (int) $municipalities->sum('total'),
            'female' => (int) $municipalities->sum('female'),
            'districts' => $districts->all(),
            'municipalities' => $municipalities->all(),
        ];
    }

    /** "1st District" → 1, "Lone District" → 0, anything else last. */
    private function districtOrder(string $district): int
    {
        if ($district === self::UNASSIGNED_DISTRICT) {
            return PHP_INT_MAX;
        }

        if (preg_match('/^(\d+)/', $district, $matches) === 1) {
            return (int) $matches[1];
        }

        return str_starts_with(strtolower($district), 'lone') ? 0 : PHP_INT_MAX - 1;
    }
}
