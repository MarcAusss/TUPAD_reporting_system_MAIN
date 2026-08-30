<?php

namespace App\Http\Controllers;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Models\Province;
use App\Reports\ReportFilters;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Reports\ReportGenerationService;
use App\Services\Reports\ReportingDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeographicMappingReportController extends Controller
{
    private const FAMILIES = [
        'projects' => [
            'label' => 'Project Mapping',
            'description' => 'Distribution of TUPAD project implementation by official province, district, or municipality.',
            'levels' => ['province', 'district', 'municipality'],
            'default_level' => 'province',
            'report_type' => ReportType::PHYSICAL_FINANCIAL,
        ],
        'beneficiaries' => [
            'label' => 'Beneficiary Mapping',
            'description' => 'Exact TUPAD beneficiary concentration by official province, district, municipality, or barangay.',
            'levels' => ['province', 'district', 'municipality', 'barangay'],
            'default_level' => 'province',
            'report_type' => ReportType::GEOGRAPHIC_BENEFICIARIES,
        ],
        'sectors' => [
            'label' => 'Sector Mapping',
            'description' => 'Priority/vulnerable and occupational/livelihood sector concentration from the encoded project classifications.',
            'levels' => [],
            'default_level' => null,
            'report_type' => ReportType::BENEFICIARY_SECTORS,
        ],
        'interventions' => [
            'label' => 'Intervention-Focus Mapping',
            'description' => 'Distribution of projects and beneficiaries by the single primary TUPAD intervention focus.',
            'levels' => [],
            'default_level' => null,
            'report_type' => ReportType::INTERVENTION_FOCUS,
        ],
    ];

    public function __construct(
        private readonly ReportGenerationService $reports,
        private readonly ReportingDataService $data,
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function index(Request $request): View
    {
        $familyKey = array_key_exists((string) $request->query('view'), self::FAMILIES)
            ? (string) $request->query('view')
            : 'projects';
        $family = self::FAMILIES[$familyKey];

        $input = $request->query();
        $input['view'] = $familyKey;

        if ($family['default_level'] !== null && blank($input['level'] ?? null)) {
            $input['level'] = $family['default_level'];
        }

        if ($familyKey === 'sectors' && blank($input['sector_group'] ?? null)) {
            $requestedSector = BeneficiarySectorCategory::tryFrom(
                (string) ($input['sector'] ?? '')
            );
            $input['sector_group'] = $requestedSector?->group()
                ?? BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE;
        }

        $validated = validator($input, [
            'view' => ['required', Rule::in(array_keys(self::FAMILIES))],
            'level' => ['nullable', Rule::in(['province', 'district', 'municipality', 'barangay'])],
            'fiscal_year' => [
                'nullable',
                'integer',
                'between:2000,2100',
                'required_with:quarter,month',
            ],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'implementation_mode' => ['nullable', Rule::enum(ImplementationMode::class)],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'sector_group' => [
                'nullable',
                Rule::in([
                    BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
                    BeneficiarySectorCategory::GROUP_OCCUPATIONAL_LIVELIHOOD,
                ]),
            ],
            'sector' => ['nullable', Rule::enum(BeneficiarySectorCategory::class)],
            'intervention_focus' => ['nullable', Rule::enum(ProjectInterventionFocus::class)],
        ])->after(function ($validator) use ($input, $familyKey, $family): void {
            $hasQuarter = array_key_exists('quarter', $input)
                && $input['quarter'] !== null
                && $input['quarter'] !== '';
            $hasMonth = array_key_exists('month', $input)
                && $input['month'] !== null
                && $input['month'] !== '';

            if ($hasQuarter && $hasMonth) {
                $validator->errors()->add(
                    'quarter',
                    'Quarter and month cannot be used at the same time.'
                );
                $validator->errors()->add(
                    'month',
                    'Month and quarter cannot be used at the same time.'
                );
            }

            $level = (string) ($input['level'] ?? '');

            if ($family['levels'] === [] && $level !== '') {
                $validator->errors()->add('level', 'This mapping family does not use a geographic-level selector.');
            }

            if ($family['levels'] !== [] && ! in_array($level, $family['levels'], true)) {
                $validator->errors()->add(
                    'level',
                    sprintf('%s does not support the selected geographic level.', $family['label'])
                );
            }

            if ($familyKey !== 'sectors' && filled($input['sector'] ?? null)) {
                $validator->errors()->add('sector', 'Sector filters are available only in Sector Mapping.');
            }

            if ($familyKey !== 'sectors' && filled($input['sector_group'] ?? null)) {
                $validator->errors()->add('sector_group', 'Sector group is available only in Sector Mapping.');
            }

            if ($familyKey !== 'interventions' && filled($input['intervention_focus'] ?? null)) {
                $validator->errors()->add('intervention_focus', 'Intervention focus is available only in Intervention-Focus Mapping.');
            }

            if ($familyKey === 'sectors' && filled($input['sector'] ?? null)) {
                $sector = BeneficiarySectorCategory::tryFrom((string) $input['sector']);
                $group = (string) ($input['sector_group'] ?? '');

                if ($sector && $group !== '' && $sector->group() !== $group) {
                    $validator->errors()->add('sector', 'The selected sector does not belong to the selected sector group.');
                }
            }

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
                $validator->errors()->add('status', 'The selected status belongs only to Through ACP projects.');
            }

            if (
                $implementationMode === ImplementationMode::THROUGH_ACP
                && $status === ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
            ) {
                $validator->errors()->add('status', 'For Submission of Post-Docs belongs only to Direct Administration projects.');
            }
        })->validate();

        $filterInput = array_filter([
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'quarter' => $validated['quarter'] ?? null,
            'month' => $validated['month'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' => $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
            'sector_group' => $familyKey === 'sectors' ? ($validated['sector_group'] ?? null) : null,
            'sector' => $familyKey === 'sectors' ? ($validated['sector'] ?? null) : null,
            'intervention_focus' => $familyKey === 'interventions'
                ? ($validated['intervention_focus'] ?? null)
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $filters = ReportFilters::fromArray($filterInput);
        [$type, $dimension] = $this->reportSelection($familyKey, $validated);
        $report = $this->reports->generate($type, $dimension, $filters);
        $rows = $report['rows'];

        $visualRows = $this->visualRows($familyKey, $rows);
        $displayRows = $rows->map(fn (array $row): array => collect($report['columns'])
            ->mapWithKeys(fn (array $column): array => [
                $column['key'] => $this->reports->formatValue(
                    $column['format'],
                    $row[$column['key']] ?? null,
                ),
            ])
            ->all());
        $summary = $this->summary($familyKey, $filters, $rows);
        $user = $request->user();

        $exportQuery = array_filter([
            'report_type' => $type->value,
            'group_by' => $dimension->value,
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'quarter' => $validated['quarter'] ?? null,
            'month' => $validated['month'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' => $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
            'sector_group' => $familyKey === 'sectors' ? ($validated['sector_group'] ?? null) : null,
            'sector' => $familyKey === 'sectors' ? ($validated['sector'] ?? null) : null,
            'intervention_focus' => $familyKey === 'interventions'
                ? ($validated['intervention_focus'] ?? null)
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $commonQuery = array_filter([
            'fiscal_year' => $validated['fiscal_year'] ?? null,
            'quarter' => $validated['quarter'] ?? null,
            'month' => $validated['month'] ?? null,
            'status' => $validated['status'] ?? null,
            'implementation_mode' => $validated['implementation_mode'] ?? null,
            'province_id' => $validated['province_id'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return view('reports.geographic-mapping.index', [
            'familyKey' => $familyKey,
            'family' => $family,
            'families' => self::FAMILIES,
            'level' => $validated['level'] ?? null,
            'filters' => $validated,
            'report' => $report,
            'rows' => $rows,
            'visualRows' => $visualRows,
            'displayRows' => $displayRows,
            'summary' => $summary,
            'exportQuery' => $exportQuery,
            'commonQuery' => $commonQuery,
            'provinceLocked' => $user->isTc(),
            'sectorGroups' => [
                BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE => [
                    'label' => 'Priority / Vulnerable Sectors',
                    'categories' => BeneficiarySectorCategory::priorityVulnerable(),
                ],
                BeneficiarySectorCategory::GROUP_OCCUPATIONAL_LIVELIHOOD => [
                    'label' => 'Occupational / Livelihood Sectors',
                    'categories' => BeneficiarySectorCategory::occupationalLivelihood(),
                ],
            ],
            'interventionFocuses' => ProjectInterventionFocus::cases(),
            'options' => [
                'statuses' => ProjectStatus::cases(),
                'implementation_modes' => ImplementationMode::cases(),
                'provinces' => $this->provinceAccess
                    ->scopeProvinces(Province::query(), $user)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    /** @return array{0: ReportType, 1: ReportDimension} */
    private function reportSelection(string $familyKey, array $validated): array
    {
        return match ($familyKey) {
            'projects' => [
                ReportType::PHYSICAL_FINANCIAL,
                ReportDimension::from((string) $validated['level']),
            ],
            'beneficiaries' => [
                ReportType::GEOGRAPHIC_BENEFICIARIES,
                ReportDimension::from((string) $validated['level']),
            ],
            'sectors' => [
                ReportType::BENEFICIARY_SECTORS,
                ReportDimension::SECTOR,
            ],
            'interventions' => [
                ReportType::INTERVENTION_FOCUS,
                ReportDimension::INTERVENTION_FOCUS,
            ],
        };
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function visualRows(string $familyKey, Collection $rows): Collection
    {
        $metricKey = match ($familyKey) {
            'projects', 'interventions' => 'project_count',
            'beneficiaries', 'sectors' => 'beneficiaries_total',
        };
        $max = max(0, (int) $rows->max($metricKey));

        return $rows->map(function (array $row) use ($familyKey, $metricKey, $max): array {
            $metric = (int) ($row[$metricKey] ?? 0);
            $intensity = $max > 0 ? round(($metric / $max) * 100, 1) : 0.0;

            return $row + [
                'map_metric' => $metric,
                'map_metric_key' => $metricKey,
                'map_metric_label' => match ($familyKey) {
                    'projects', 'interventions' => 'project(s)',
                    'beneficiaries', 'sectors' => 'beneficiary count',
                },
                'map_intensity' => $intensity,
            ];
        });
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, array{label: string, value: int|string, hint: string}>
     */
    private function summary(string $familyKey, ReportFilters $filters, Collection $rows): array
    {
        $overall = $this->data
            ->physicalFinancial($filters, ReportDimension::OVERALL)
            ->first() ?? [];

        return match ($familyKey) {
            'projects' => [
                [
                    'label' => 'Unique Projects',
                    'value' => (int) ($overall['project_count'] ?? 0),
                    'hint' => 'Unique projects in the selected reporting scope.',
                ],
                [
                    'label' => 'Mapped Areas',
                    'value' => $rows->count(),
                    'hint' => 'Geographic rows at the selected mapping level.',
                ],
                [
                    'label' => 'Beneficiaries',
                    'value' => (int) ($overall['beneficiaries_total'] ?? 0),
                    'hint' => 'Project-level beneficiary total for the selected cohort.',
                ],
                [
                    'label' => 'Female Beneficiaries',
                    'value' => (int) ($overall['beneficiaries_female'] ?? 0),
                    'hint' => 'Project-level female beneficiary total.',
                ],
            ],
            'beneficiaries' => [
                [
                    'label' => 'Mapped Areas',
                    'value' => $rows->count(),
                    'hint' => 'Geographic rows at the selected mapping level.',
                ],
                [
                    'label' => 'Exactly Allocated',
                    'value' => (int) $rows->sum('beneficiaries_total'),
                    'hint' => 'Only beneficiary counts with exact encoded geographic allocation.',
                ],
                [
                    'label' => 'Exact Female',
                    'value' => (int) $rows->sum('beneficiaries_female'),
                    'hint' => 'Female beneficiaries from exact geographic allocations only.',
                ],
                [
                    'label' => 'Areas Needing Review',
                    'value' => $rows->filter(
                        fn (array $row): bool => ! (bool) ($row['has_complete_exact_allocation'] ?? false)
                    )->count(),
                    'hint' => 'Areas containing legacy/unallocated beneficiary records.',
                ],
            ],
            'sectors' => [
                [
                    'label' => 'Sector Categories',
                    'value' => $rows->count(),
                    'hint' => 'Categories shown in the selected sector family.',
                ],
                [
                    'label' => 'Encoded Sector Counts',
                    'value' => (int) $rows->sum('beneficiaries_total'),
                    'hint' => 'Overlapping classification counts; not a unique beneficiary total.',
                ],
                [
                    'label' => 'Female Encoded Counts',
                    'value' => (int) $rows->sum('beneficiaries_female'),
                    'hint' => 'Female counts within the same overlapping classifications.',
                ],
                [
                    'label' => 'Projects in Scope',
                    'value' => (int) ($overall['project_count'] ?? 0),
                    'hint' => 'Unique project cohort behind the classification view.',
                ],
            ],
            'interventions' => [
                [
                    'label' => 'Intervention Categories',
                    'value' => $rows->count(),
                    'hint' => 'Primary intervention-focus categories displayed.',
                ],
                [
                    'label' => 'Projects',
                    'value' => (int) $rows->sum('project_count'),
                    'hint' => 'Each project has at most one primary intervention focus.',
                ],
                [
                    'label' => 'Beneficiaries',
                    'value' => (int) $rows->sum('beneficiaries_total'),
                    'hint' => 'Beneficiaries under the projects in each primary focus.',
                ],
                [
                    'label' => 'Female Beneficiaries',
                    'value' => (int) ($overall['beneficiaries_female'] ?? 0),
                    'hint' => 'Female beneficiary total for the selected project cohort.',
                ],
            ],
        };
    }
}
