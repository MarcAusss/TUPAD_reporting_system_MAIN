<?php

namespace App\Services\Dashboards;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\ProjectInterventionFocus;
use App\Models\User;
use App\Services\Mapping\BicolMapDataService;
use App\Services\Mapping\BicolMapMetricService;

final class DashboardGeographicAnalyticsService
{
    public const PROJECTS = 'projects';
    public const BENEFICIARIES = 'beneficiaries';
    public const SECTORS = 'sectors';
    public const INTERVENTIONS = 'interventions';

    public function __construct(
        private readonly BicolMapDataService $mapping,
        private readonly BicolMapMetricService $metrics,
    ) {}

    /** @return array<int,string> */
    public static function families(): array
    {
        return [
            self::PROJECTS,
            self::BENEFICIARIES,
            self::SECTORS,
            self::INTERVENTIONS,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function build(User $user, array $input = []): array
    {
        $family = in_array((string) ($input['family'] ?? self::PROJECTS), self::families(), true)
            ? (string) ($input['family'] ?? self::PROJECTS)
            : self::PROJECTS;

        $provinceId = filled($input['province_id'] ?? null) ? (int) $input['province_id'] : null;
        $municipalityId = filled($input['municipality_id'] ?? null) ? (int) $input['municipality_id'] : null;

        $filters = $this->familyFilters($family, $input);

        $payload = $this->mappingPayload($user, $provinceId, $municipalityId, $filters);

        $acpPayload = $family === self::PROJECTS
            ? $this->mappingPayload(
                $user,
                $provinceId,
                $municipalityId,
                [...$filters, 'implementation_mode' => ImplementationMode::THROUGH_ACP->value],
            )
            : null;

        $payload = $this->metrics->apply(
            $payload,
            $family === self::PROJECTS
                ? BicolMapMetricService::PROJECTS
                : BicolMapMetricService::BENEFICIARIES,
        );

        $acpRows = collect($acpPayload['areas'] ?? [])
            ->keyBy(fn (array $row): string => (string) ($row['id'] ?? ''));

        $rows = collect($payload['areas'] ?? [])
            ->map(function (array $row) use ($family, $acpRows): array {
                $rowId = isset($row['id']) ? (int) $row['id'] : null;
                $acpRow = $rowId !== null ? $acpRows->get((string) $rowId, []) : [];

                return [
                    'id' => $rowId,
                    'name' => (string) ($row['name'] ?? $row['label'] ?? 'Unknown area'),
                    'projects' => (int) ($row['projects'] ?? 0),
                    'total' => $family === self::PROJECTS
                        ? (int) ($row['projects'] ?? 0)
                        : (int) ($row['beneficiaries'] ?? 0),
                    'through_acp' => $family === self::PROJECTS
                        ? (int) ($acpRow['projects'] ?? 0)
                        : null,
                    'female' => $family === self::PROJECTS
                        ? null
                        : (int) ($row['beneficiaries_female'] ?? 0),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $level = (string) ($payload['map_level'] ?? 'region');
        $scopeLabel = (string) data_get(
            collect($payload['breadcrumb'] ?? [])->last(),
            'label',
            'Bicol Region',
        );

        return [
            'family' => $family,
            'family_label' => $this->familyLabel($family),
            'family_description' => $this->familyDescription($family, $input),
            'data_note' => $this->dataNote($family, $input),
            'level' => $level,
            'scope_label' => $scopeLabel,
            'category_axis_label' => match ($level) {
                'province' => 'Municipality / City',
                'municipality' => 'Barangay',
                default => 'Province',
            },
            'can_go_back' => $level !== 'region',
            'selected_province_id' => data_get($payload, 'selected_province.id'),
            'selected_municipality_id' => data_get($payload, 'selected_municipality.id'),
            'breadcrumbs' => $payload['breadcrumb'] ?? [],
            'rows' => $rows->all(),
            'series' => $family === self::PROJECTS
                ? [
                    ['key' => 'total', 'label' => 'Total'],
                    ['key' => 'through_acp', 'label' => 'Through ACP'],
                ]
                : [
                    ['key' => 'total', 'label' => 'Total'],
                    ['key' => 'female', 'label' => 'Female'],
                ],
            'summary' => [
                'projects' => (int) data_get($payload, 'summary.projects', 0),
                'through_acp' => (int) data_get($acpPayload, 'summary.projects', 0),
                'beneficiaries' => (int) data_get($payload, 'summary.beneficiaries', 0),
                'female' => (int) $rows->sum(fn (array $row): int => (int) ($row['female'] ?? 0)),
                'areas' => $rows->count(),
            ],
            'controls' => $this->controls(),
            'selection' => [
                'sector_group' => $input['sector_group'] ?? BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
                'sector' => $input['sector'] ?? null,
                'intervention_focus' => $input['intervention_focus'] ?? null,
            ],
        ];
    }


    /** @param array<string,mixed> $filters @return array<string,mixed> */
    private function mappingPayload(
        User $user,
        ?int $provinceId,
        ?int $municipalityId,
        array $filters,
    ): array {
        return match (true) {
            $provinceId !== null && $municipalityId !== null => $this->mapping->municipalityPayload(
                $user,
                $provinceId,
                $municipalityId,
                $filters,
            ),
            $provinceId !== null => $this->mapping->provincePayload(
                $user,
                $provinceId,
                $filters,
            ),
            default => $this->mapping->regionPayload($user, $filters),
        };
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function familyFilters(string $family, array $input): array
    {
        if ($family === self::SECTORS) {
            $group = (string) ($input['sector_group'] ?? BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE);
            $sector = BeneficiarySectorCategory::tryFrom((string) ($input['sector'] ?? ''));

            if ($sector && $sector->group() !== $group) {
                $sector = null;
            }

            return array_filter([
                'sector_group' => $group,
                'sector' => $sector?->value,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        if ($family === self::INTERVENTIONS) {
            return array_filter([
                'intervention_focus' => $input['intervention_focus'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return [];
    }

    private function familyLabel(string $family): string
    {
        return match ($family) {
            self::BENEFICIARIES => 'Beneficiary Profile',
            self::SECTORS => 'Sector Mapping',
            self::INTERVENTIONS => 'Intervention Focus',
            default => 'Project Mapping',
        };
    }

    /** @param array<string,mixed> $input */
    private function familyDescription(string $family, array $input): string
    {
        return match ($family) {
            self::BENEFICIARIES => 'Exact beneficiary totals by official geographic allocation, with female beneficiaries shown as a paired series.',
            self::SECTORS => 'Exact geographic beneficiary totals for projects matching the selected sector family/category.',
            self::INTERVENTIONS => 'Exact geographic beneficiary totals for projects matching the selected intervention focus.',
            default => 'Compare total official projects with Through ACP projects by geography. Select a bar group to drill from province to municipality/city to barangay.',
        };
    }

    /** @param array<string,mixed> $input */
    private function dataNote(string $family, array $input): string
    {
        if ($family === self::SECTORS) {
            return 'Sector counts are stored at project/category level, not per municipality or barangay. To avoid fabricated splits, this chart shows exact Total/Female geographic beneficiaries for the cohort of projects carrying the selected sector classification; it does not claim those beneficiaries are all members of that sector.';
        }

        if ($family === self::INTERVENTIONS) {
            return 'Total/Female values use exact beneficiary geographic allocations after filtering projects by their primary intervention focus.';
        }

        if ($family === self::BENEFICIARIES) {
            return 'Total/Female values use the same exact project-location and barangay allocations used by the Geographic Mapping report.';
        }

        return 'Total counts all official projects associated with each geographic row. Through ACP is the subset using Through ACP implementation mode. A multi-location project may appear in more than one geographic row, while the scope summary remains the authoritative unique project total.';
    }

    /** @return array<string,mixed> */
    private function controls(): array
    {
        return [
            'families' => [
                ['key' => self::PROJECTS, 'label' => 'Projects'],
                ['key' => self::BENEFICIARIES, 'label' => 'Beneficiaries'],
                ['key' => self::SECTORS, 'label' => 'Sectors'],
                ['key' => self::INTERVENTIONS, 'label' => 'Intervention Focus'],
            ],
            'sector_groups' => [
                [
                    'key' => BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
                    'label' => 'Priority / Vulnerable',
                ],
                [
                    'key' => BeneficiarySectorCategory::GROUP_OCCUPATIONAL_LIVELIHOOD,
                    'label' => 'Occupational / Livelihood',
                ],
            ],
            'sectors' => collect(BeneficiarySectorCategory::cases())
                ->map(fn (BeneficiarySectorCategory $sector): array => [
                    'key' => $sector->value,
                    'label' => $sector->label(),
                    'group' => $sector->group(),
                ])
                ->values()
                ->all(),
            'interventions' => collect(ProjectInterventionFocus::cases())
                ->map(fn (ProjectInterventionFocus $focus): array => [
                    'key' => $focus->value,
                    'label' => $focus->label(),
                ])
                ->values()
                ->all(),
        ];
    }
}
