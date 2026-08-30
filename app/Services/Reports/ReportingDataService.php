<?php

namespace App\Services\Reports;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Reports\ReportFilters;
use App\Services\Payments\ProjectPaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class ReportingDataService
{
    public function __construct(
        private readonly ProjectPaymentService $paymentService,
    ) {}

    /**
     * Physical and financial project-cohort totals.
     *
     * Project period filters use projects.date_received. Payment values are
     * the current obligation/disbursement totals for the selected cohort.
     */
    public function physicalFinancial(
        ReportFilters $filters,
        ReportDimension $groupBy = ReportDimension::OVERALL,
        ?Collection $projects = null,
    ): Collection {
        if ($groupBy->isFineGeography()) {
            return $this->beneficiaryGeography($filters, $groupBy, $projects)
                ->map(fn (array $row): array => $row + [
                    'wages_cents' => null,
                    'ppe_cents' => null,
                    'insurance_cents' => null,
                    'project_cost_cents' => null,
                    'obligated_cents' => null,
                    'disbursed_cents' => null,
                    'financial_allocation_available' => false,
                    'financial_allocation_reason' =>
                        'No financial allocation exists below the project/province level.',
                    'period_basis' => 'projects.date_received',
                ]);
        }

        $projects ??= $this->projects($filters);

        return $this->groupProjects($projects, $groupBy)
            ->map(function (array $group) use ($groupBy): array {
                return $this->projectMetrics($group['projects']) + [
                    'dimension' => $groupBy->value,
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'financial_allocation_available' => true,
                    'period_basis' => 'projects.date_received',
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Current allocation, obligation, and disbursement position.
     */
    public function fundStatus(
        ReportFilters $filters,
        ReportDimension $groupBy = ReportDimension::ADL,
        ?Collection $projects = null,
    ): Collection {
        if ($groupBy->isFineGeography()) {
            return $this->beneficiaryGeography($filters, $groupBy, $projects)
                ->map(fn (array $row): array => $row + [
                    'allocation_cents' => null,
                    'payable_wages_cents' => null,
                    'obligated_cents' => null,
                    'disbursed_cents' => null,
                    'unobligated_balance_cents' => null,
                    'undisbursed_obligation_cents' => null,
                    'balance_cents' => null,
                    'financial_allocation_available' => false,
                    'financial_allocation_reason' =>
                        'No financial allocation exists below the project/province level.',
                ]);
        }

        $projects ??= $this->projects($filters);
        $groups = $this->groupProjects($projects, $groupBy);

        if ($groupBy === ReportDimension::OVERALL && $groups->isEmpty()) {
            $groups->put('overall', [
                'key' => 'overall',
                'label' => 'Overall',
                'projects' => collect(),
            ]);
        }

        if (in_array($groupBy, [ReportDimension::OVERALL, ReportDimension::ADL, ReportDimension::LCE], true)) {
            $groups = $this->mergeAllocationOnlyGroups(
                $groups,
                $filters,
                $projects,
                $groupBy,
            );
        }

        return $groups
            ->map(function (array $group) use ($groupBy): array {
                /** @var Collection<int, Project> $groupProjects */
                $groupProjects = $group['projects'];

                $allocationCents = $this->allocationCentsForProjects(
                    $groupProjects,
                    $group['allocations'] ?? null,
                );
                $directAdminProjects = $groupProjects
                    ->filter(fn (Project $project): bool =>
                        $project->implementation_mode === ImplementationMode::DIRECT_ADMINISTRATION
                    )
                    ->values();
                $payableCents = $this->sumProjectMoney(
                    $directAdminProjects,
                    'wages_total',
                );
                $directAdminObligatedCents = $this->directAdminObligatedCents($groupProjects);
                $directAdminDisbursedCents = $this->directAdminDisbursedCents($groupProjects);
                $acpPaymentCents = $this->acpPaymentCents($groupProjects);
                $acpCheckReleasedCents = $this->acpCheckReleasedCents($groupProjects);
                $acpLiquidatedCents = $this->acpLiquidatedCents($groupProjects);
                $obligatedCents = $directAdminObligatedCents + $acpPaymentCents;
                $disbursedCents = $directAdminDisbursedCents + $acpCheckReleasedCents;

                return [
                    'dimension' => $groupBy->value,
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'project_count' => $groupProjects->count(),
                    'allocation_cents' => $allocationCents,
                    'payable_wages_cents' => $payableCents,
                    'direct_admin_obligated_cents' => $directAdminObligatedCents,
                    'direct_admin_disbursed_cents' => $directAdminDisbursedCents,
                    'acp_payment_cents' => $acpPaymentCents,
                    'acp_check_released_cents' => $acpCheckReleasedCents,
                    'acp_liquidated_cents' => $acpLiquidatedCents,
                    'obligated_cents' => $obligatedCents,
                    'disbursed_cents' => $disbursedCents,
                    'unobligated_balance_cents' =>
                        $allocationCents - $obligatedCents,
                    'undisbursed_obligation_cents' =>
                        $obligatedCents - $disbursedCents,
                    'balance_cents' => $allocationCents - $disbursedCents,
                    'balance_basis' => 'allocation_less_disbursed',
                    'is_over_obligated' => $obligatedCents > $allocationCents,
                    'is_over_disbursed' => $disbursedCents > $obligatedCents,
                    'financial_allocation_available' => true,
                    'allocation_totals_additive_across_groups' =>
                        in_array(
                            $groupBy,
                            [ReportDimension::OVERALL, ReportDimension::ADL, ReportDimension::LCE],
                            true,
                        ),
                    'period_basis' => 'projects.date_received',
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Exact beneficiary geography. Fine-grained rows never infer or divide
     * project money and never substitute a project total for a missing pivot.
     */
    public function beneficiaryGeography(
        ReportFilters $filters,
        ReportDimension $groupBy = ReportDimension::BARANGAY,
        ?Collection $projects = null,
    ): Collection {
        if (! in_array($groupBy, [
            ReportDimension::PROVINCE,
            ReportDimension::DISTRICT,
            ReportDimension::MUNICIPALITY,
            ReportDimension::BARANGAY,
        ], true)) {
            throw new InvalidArgumentException(
                'Beneficiary geography supports province, district, municipality, or barangay grouping.'
            );
        }

        $projects ??= $this->projects($filters);

        $rows = [];

        foreach ($projects as $project) {
            if ($project->projectLocations->isEmpty()) {
                $descriptor = $this->legacyGeographyDescriptor(
                    $project,
                    $groupBy,
                );
                $this->addGeographicAllocation(
                    $rows,
                    $descriptor,
                    $project->id,
                    0,
                    0,
                    false,
                );

                continue;
            }

            $matchingLocations = $this->matchingProjectLocations(
                $project,
                $filters,
            );

            foreach ($matchingLocations as $location) {
                $matchingBarangays = $this->matchingLocationBarangays(
                    $location,
                    $filters,
                );

                if ($groupBy === ReportDimension::BARANGAY) {
                    if ($matchingBarangays->isEmpty()) {
                        $this->addGeographicAllocation(
                            $rows,
                            $this->geographyDescriptor(
                                $project,
                                $location,
                                null,
                                $groupBy,
                            ),
                            $project->id,
                            0,
                            0,
                            false,
                        );
                    }

                    foreach ($matchingBarangays as $barangay) {
                        $isExact =
                            $barangay->pivot->beneficiaries_total !== null
                            && $barangay->pivot->beneficiaries_female !== null;

                        $this->addGeographicAllocation(
                            $rows,
                            $this->geographyDescriptor(
                                $project,
                                $location,
                                $barangay,
                                $groupBy,
                            ),
                            $project->id,
                            $isExact
                                ? (int) $barangay->pivot->beneficiaries_total
                                : 0,
                            $isExact
                                ? (int) $barangay->pivot->beneficiaries_female
                                : 0,
                            $isExact,
                        );
                    }

                    continue;
                }

                $isExact =
                    $matchingBarangays->isNotEmpty()
                    && $matchingBarangays->every(
                        fn ($barangay): bool =>
                            $barangay->pivot->beneficiaries_total !== null
                            && $barangay->pivot->beneficiaries_female !== null
                    );

                $this->addGeographicAllocation(
                    $rows,
                    $this->geographyDescriptor(
                        $project,
                        $location,
                        null,
                        $groupBy,
                    ),
                    $project->id,
                    $isExact
                        ? (int) $matchingBarangays->sum(
                            fn ($barangay): int =>
                                (int) $barangay->pivot->beneficiaries_total
                        )
                        : 0,
                    $isExact
                        ? (int) $matchingBarangays->sum(
                            fn ($barangay): int =>
                                (int) $barangay->pivot->beneficiaries_female
                        )
                        : 0,
                    $isExact,
                );
            }
        }

        return collect($rows)
            ->map(function (array $row) use ($groupBy): array {
                $projectIds = array_keys($row['project_ids']);
                $incompleteProjectIds = array_keys(
                    $row['incomplete_project_ids']
                );

                return [
                    'dimension' => $groupBy->value,
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'project_count' => count($projectIds),
                    'beneficiaries_total' => $row['beneficiaries_total'],
                    'beneficiaries_female' => $row['beneficiaries_female'],
                    'exact_project_count' =>
                        count(array_diff($projectIds, $incompleteProjectIds)),
                    'legacy_unallocated_project_count' =>
                        count($incompleteProjectIds),
                    'has_complete_exact_allocation' =>
                        count($incompleteProjectIds) === 0,
                    'allocation_basis' =>
                        'project_location_barangay_exact_only',
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function sectorAggregation(
        ReportFilters $filters,
        ReportDimension $groupBy = ReportDimension::SECTOR,
        ?Collection $projects = null,
    ): Collection {
        $this->assertProjectClassificationDimension($groupBy);

        $projects ??= $this->projects($filters);
        $rows = [];

        foreach ($projects as $project) {
            $groupDescriptors = $groupBy === ReportDimension::SECTOR
                ? [[
                    'key' => 'all-projects',
                    'label' => 'All Projects',
                ]]
                : $this->projectDescriptors($project, $groupBy);

            foreach ($groupDescriptors as $descriptor) {
                foreach (BeneficiarySectorCategory::cases() as $category) {
                    if ($filters->sectorGroup && $category->group() !== $filters->sectorGroup) {
                        continue;
                    }

                    if ($filters->sector && $filters->sector !== $category) {
                        continue;
                    }

                    $sector = $project->beneficiarySectors->first(
                        fn ($record): bool => $record->sector_key === $category
                    );
                    $key = $descriptor['key'].'|'.$category->value;

                    $rows[$key] ??= [
                        'dimension' => $groupBy->value,
                        'key' => $key,
                        'label' => $groupBy === ReportDimension::SECTOR
                            ? $category->label()
                            : $descriptor['label'],
                        'group_key' => $descriptor['key'],
                        'group_label' => $descriptor['label'],
                        'sector_group' => $category->group(),
                        'sector_key' => $category->value,
                        'sector_label' => $category->label(),
                        'beneficiaries_total' => 0,
                        'beneficiaries_female' => 0,
                        'project_ids' => [],
                    ];

                    if ($sector) {
                        $rows[$key]['beneficiaries_total'] +=
                            (int) $sector->beneficiaries_total;
                        $rows[$key]['beneficiaries_female'] +=
                            (int) $sector->beneficiaries_female;
                        $rows[$key]['project_ids'][$project->id] = true;
                    }
                }
            }
        }

        if ($projects->isEmpty() && $groupBy === ReportDimension::SECTOR) {
            foreach (BeneficiarySectorCategory::cases() as $category) {
                if ($filters->sectorGroup && $category->group() !== $filters->sectorGroup) {
                    continue;
                }

                if ($filters->sector && $filters->sector !== $category) {
                    continue;
                }

                $rows[$category->value] = [
                    'dimension' => $groupBy->value,
                    'key' => $category->value,
                    'label' => $category->label(),
                    'group_key' => 'all-projects',
                    'group_label' => 'All Projects',
                    'sector_group' => $category->group(),
                    'sector_key' => $category->value,
                    'sector_label' => $category->label(),
                    'beneficiaries_total' => 0,
                    'beneficiaries_female' => 0,
                    'project_ids' => [],
                ];
            }
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['project_count'] = count($row['project_ids']);
                unset($row['project_ids']);

                return $row;
            })
            ->sortBy([
                ['group_label', 'asc'],
                ['sector_group', 'asc'],
                ['sector_label', 'asc'],
            ])
            ->values();
    }

    public function interventionAggregation(
        ReportFilters $filters,
        ReportDimension $groupBy = ReportDimension::INTERVENTION_FOCUS,
        ?Collection $projects = null,
    ): Collection {
        $this->assertProjectClassificationDimension($groupBy);

        $projects ??= $this->projects($filters);
        $groups = [];

        foreach ($projects as $project) {
            $focus = $project->intervention_focus;

            if ($groupBy === ReportDimension::INTERVENTION_FOCUS) {
                $descriptors = [[
                    'key' => $focus?->value ?? 'unclassified',
                    'label' => $focus?->label() ?? 'Unclassified',
                ]];
            } else {
                $descriptors = $this->projectDescriptors($project, $groupBy);
            }

            foreach ($descriptors as $descriptor) {
                $focusKey = $focus?->value ?? 'unclassified';
                $key = $descriptor['key'].'|'.$focusKey;
                $groups[$key] ??= [
                    'dimension' => $groupBy->value,
                    'key' => $key,
                    'label' => $descriptor['label'],
                    'group_key' => $descriptor['key'],
                    'group_label' => $descriptor['label'],
                    'intervention_focus' => $focusKey,
                    'intervention_focus_label' =>
                        $focus?->label() ?? 'Unclassified',
                    'projects' => collect(),
                ];
                $groups[$key]['projects']->push($project);
            }
        }

        if ($groupBy === ReportDimension::INTERVENTION_FOCUS) {
            foreach (ProjectInterventionFocus::cases() as $focus) {
                $key = $focus->value.'|'.$focus->value;
                $groups[$key] ??= [
                    'dimension' => $groupBy->value,
                    'key' => $key,
                    'label' => $focus->label(),
                    'group_key' => $focus->value,
                    'group_label' => $focus->label(),
                    'intervention_focus' => $focus->value,
                    'intervention_focus_label' => $focus->label(),
                    'projects' => collect(),
                ];
            }
        }

        return collect($groups)
            ->map(function (array $row): array {
                /** @var Collection<int, Project> $projects */
                $projects = $row['projects']->unique('id')->values();
                unset($row['projects']);

                return $row + [
                    'project_count' => $projects->count(),
                    'beneficiaries_total' =>
                        (int) $projects->sum('beneficiaries_total'),
                    'beneficiaries_female' =>
                        (int) $projects->sum('beneficiaries_female'),
                    'project_cost_cents' =>
                        $this->sumProjectMoney($projects, 'total_project_cost'),
                ];
            })
            ->sortBy([
                ['group_label', 'asc'],
                ['intervention_focus_label', 'asc'],
            ])
            ->values();
    }

    public function laborMarketAggregation(
        ReportFilters $filters,
        ReportDimension $groupBy = ReportDimension::LABOR_MARKET_PROGRAM,
        ?Collection $projects = null,
    ): Collection {
        if ($groupBy->isFineGeography()) {
            throw new InvalidArgumentException(
                'Labor market referrals are project aggregates and cannot be divided by fine-grained geography.'
            );
        }

        $supported = [
            ReportDimension::OVERALL,
            ReportDimension::MONTH,
            ReportDimension::QUARTER,
            ReportDimension::FISCAL_YEAR,
            ReportDimension::LABOR_MARKET_PROGRAM,
            ReportDimension::ADL,
            ReportDimension::PROVINCE,
            ReportDimension::STATUS,
            ReportDimension::SPONSOR,
            ReportDimension::PARTNER,
            ReportDimension::PROJECT_CODE,
            ReportDimension::TERM,
            ReportDimension::INTERVENTION_FOCUS,
        ];

        if (! in_array($groupBy, $supported, true)) {
            throw new InvalidArgumentException(
                'Unsupported labor market reporting dimension.'
            );
        }

        $projects ??= $this->projects($filters, applyPeriod: false);
        [$periodStart, $periodEnd] = $filters->periodBounds();
        $rows = [];

        foreach ($projects as $project) {
            foreach ($project->laborMarketReferrals as $referral) {
                if (
                    $filters->laborMarketProgram
                    && $referral->program !== $filters->laborMarketProgram
                ) {
                    continue;
                }

                $reportingMonth = $referral->reporting_month->toImmutable();

                if ($periodStart && $reportingMonth->lt($periodStart->startOfMonth())) {
                    continue;
                }

                if ($periodEnd && $reportingMonth->gt($periodEnd->endOfMonth())) {
                    continue;
                }

                $descriptors = $this->laborDescriptors(
                    $project,
                    $referral->program,
                    $reportingMonth->year,
                    $reportingMonth->month,
                    $groupBy,
                );

                foreach ($descriptors as $descriptor) {
                    $rows[$descriptor['key']] ??= [
                        'dimension' => $groupBy->value,
                        'key' => $descriptor['key'],
                        'label' => $descriptor['label'],
                        'referral_record_count' => 0,
                        'interested_referred_total' => 0,
                        'interested_referred_female' => 0,
                        'provided_intervention_total' => 0,
                        'provided_intervention_female' => 0,
                        'amount_released_cents' => 0,
                        'project_ids' => [],
                        'services_availed' => [],
                        'period_basis' =>
                            'project_labor_market_referrals.reporting_month',
                    ];

                    $rows[$descriptor['key']]['referral_record_count']++;
                    $rows[$descriptor['key']]['interested_referred_total'] +=
                        (int) $referral->interested_referred_total;
                    $rows[$descriptor['key']]['interested_referred_female'] +=
                        (int) $referral->interested_referred_female;
                    $rows[$descriptor['key']]['provided_intervention_total'] +=
                        (int) $referral->provided_intervention_total;
                    $rows[$descriptor['key']]['provided_intervention_female'] +=
                        (int) $referral->provided_intervention_female;
                    $rows[$descriptor['key']]['amount_released_cents'] +=
                        $this->paymentService->amountToCents(
                            $referral->amount_released
                        );
                    $rows[$descriptor['key']]['project_ids'][$project->id] = true;
                    $rows[$descriptor['key']]['services_availed'][] =
                        $referral->services_availed;
                }
            }
        }

        if ($groupBy === ReportDimension::LABOR_MARKET_PROGRAM) {
            foreach (LaborMarketProgram::cases() as $program) {
                if ($filters->laborMarketProgram && $filters->laborMarketProgram !== $program) {
                    continue;
                }

                $rows[$program->value] ??= [
                    'dimension' => $groupBy->value,
                    'key' => $program->value,
                    'label' => $program->label(),
                    'referral_record_count' => 0,
                    'interested_referred_total' => 0,
                    'interested_referred_female' => 0,
                    'provided_intervention_total' => 0,
                    'provided_intervention_female' => 0,
                    'amount_released_cents' => 0,
                    'project_ids' => [],
                    'services_availed' => [],
                    'period_basis' =>
                        'project_labor_market_referrals.reporting_month',
                ];
            }
        }

        return collect($rows)
            ->map(function (array $row): array {
                $row['project_count'] = count($row['project_ids']);
                $row['services_availed'] = collect($row['services_availed'])
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                unset($row['project_ids']);

                return $row;
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return Collection<int, Project> */
    public function projects(
        ReportFilters $filters,
        bool $applyPeriod = true,
    ): Collection {
        return $this->projectQuery($filters, $applyPeriod)->get();
    }

    private function projectQuery(
        ReportFilters $filters,
        bool $applyPeriod,
    ): Builder {
        $query = Project::query()->with([
            'allocation.adl',
            'approval',
            'obligations.disbursements',
            'acpPayment',
            'acpCheckRelease',
            'acpLiquidations',
            'provinceReference',
            'municipalityReference',
            'barangayReference',
            'projectLocations.province',
            'projectLocations.municipality',
            'projectLocations.barangays',
            'beneficiarySectors',
            'laborMarketReferrals',
        ]);

        if ($filters->term) {
            $query->where('term', $filters->term->value);
        }

        if ($filters->status) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->implementationMode) {
            $query->where(
                'implementation_mode',
                $filters->implementationMode->value,
            );
        }

        if ($filters->adlId) {
            $query->whereHas(
                'allocation',
                fn (Builder $allocation): Builder =>
                    $allocation->where('adl_id', $filters->adlId)
            );
        }

        if ($filters->provinceId) {
            $query->where(function (Builder $location) use ($filters): void {
                $location
                    ->where('province_id', $filters->provinceId)
                    ->orWhereHas(
                        'projectLocations',
                        fn (Builder $projectLocation): Builder =>
                            $projectLocation->where(
                                'province_id',
                                $filters->provinceId,
                            )
                    );
            });
        }

        if ($filters->district) {
            $query->where(function (Builder $location) use ($filters): void {
                $location
                    ->where('district', $filters->district)
                    ->orWhereHas(
                        'projectLocations',
                        fn (Builder $projectLocation): Builder =>
                            $projectLocation->where(
                                'district',
                                $filters->district,
                            )
                    );
            });
        }

        if ($filters->municipalityId) {
            $query->where(function (Builder $location) use ($filters): void {
                $location
                    ->where('municipality_id', $filters->municipalityId)
                    ->orWhereHas(
                        'projectLocations',
                        fn (Builder $projectLocation): Builder =>
                            $projectLocation->where(
                                'municipality_id',
                                $filters->municipalityId,
                            )
                    );
            });
        }

        if ($filters->barangayId) {
            $query->where(function (Builder $location) use ($filters): void {
                $location
                    ->where('barangay_id', $filters->barangayId)
                    ->orWhereHas(
                        'projectLocations.barangays',
                        fn (Builder $barangay): Builder =>
                            $barangay->whereKey($filters->barangayId)
                    );
            });
        }

        if ($filters->sponsor) {
            $query->where('fund_sponsor', $filters->sponsor);
        }

        if ($filters->partner) {
            $query->where('partner', $filters->partner);
        }

        if ($filters->projectCode) {
            $query->whereHas(
                'approval',
                fn (Builder $approval): Builder =>
                    $approval->where('project_code', $filters->projectCode)
            );
        }

        if ($filters->sector) {
            $query->whereHas(
                'beneficiarySectors',
                fn (Builder $sector): Builder =>
                    $sector->where('sector_key', $filters->sector->value)
            );
        }

        if ($filters->interventionFocus) {
            $query->where(
                'intervention_focus',
                $filters->interventionFocus->value,
            );
        }

        if ($filters->laborMarketProgram) {
            $query->whereHas(
                'laborMarketReferrals',
                fn (Builder $referral): Builder =>
                    $referral->where(
                        'program',
                        $filters->laborMarketProgram->value,
                    )
            );
        }

        if ($applyPeriod) {
            [$periodStart, $periodEnd] = $filters->periodBounds();

            if ($periodStart) {
                $query->whereDate(
                    'date_received',
                    '>=',
                    $periodStart->toDateString(),
                );
            }

            if ($periodEnd) {
                $query->whereDate(
                    'date_received',
                    '<=',
                    $periodEnd->toDateString(),
                );
            }
        }

        return $query->orderBy('projects.id');
    }

    /**
     * @param Collection<int, Project> $projects
     * @return Collection<string, array{key: string, label: string, projects: Collection<int, Project>}>
     */
    private function groupProjects(
        Collection $projects,
        ReportDimension $dimension,
    ): Collection {
        $groups = collect();

        foreach ($projects as $project) {
            foreach ($this->projectDescriptors($project, $dimension) as $descriptor) {
                $group = $groups->get($descriptor['key'], [
                    'key' => $descriptor['key'],
                    'label' => $descriptor['label'],
                    'projects' => collect(),
                ]);

                if (! $group['projects']->contains('id', $project->id)) {
                    $group['projects']->push($project);
                }

                $groups->put($descriptor['key'], $group);
            }
        }

        return $groups;
    }

    /** @return array<int, array{key: string, label: string}> */
    private function projectDescriptors(
        Project $project,
        ReportDimension $dimension,
    ): array {
        return match ($dimension) {
            ReportDimension::OVERALL => [[
                'key' => 'overall',
                'label' => 'Overall',
            ]],
            ReportDimension::MONTH => [[
                'key' => $project->date_received->format('Y-m'),
                'label' => $project->date_received->format('F Y'),
            ]],
            ReportDimension::QUARTER => [[
                'key' => sprintf(
                    '%d-Q%d',
                    $project->date_received->year,
                    $project->date_received->quarter,
                ),
                'label' => sprintf(
                    'Q%d %d',
                    $project->date_received->quarter,
                    $project->date_received->year,
                ),
            ]],
            ReportDimension::FISCAL_YEAR => [[
                'key' => (string) $project->date_received->year,
                'label' => 'FY '.$project->date_received->year,
            ]],
            ReportDimension::TERM => [[
                'key' => $project->term->value,
                'label' => $project->term->label(),
            ]],
            ReportDimension::ADL => [[
                'key' => (string) ($project->allocation?->adl?->id ?? 'unassigned'),
                'label' => $project->allocation?->adl?->adl_number ?? 'Unassigned ADL',
            ]],
            ReportDimension::PROVINCE => $this->provinceDescriptors($project),
            ReportDimension::DISTRICT => $this->districtDescriptors($project),
            ReportDimension::MUNICIPALITY => $this->municipalityDescriptors($project),
            ReportDimension::BARANGAY => $this->barangayDescriptors($project),
            ReportDimension::STATUS => [[
                'key' => $project->status->value,
                'label' => $project->status->label(),
            ]],
            ReportDimension::SPONSOR => [[
                'key' => $this->normalizedKey(
                    $project->fund_sponsor ?: 'Unassigned Sponsor'
                ),
                'label' => $project->fund_sponsor ?: 'Unassigned Sponsor',
            ]],
            ReportDimension::PARTNER => [[
                'key' => $this->normalizedKey(
                    $project->partner ?: 'Unassigned Partner / NGA'
                ),
                'label' => $project->partner ?: 'Unassigned Partner / NGA',
            ]],
            ReportDimension::LCE => [[
                'key' => $this->normalizedKey(
                    $project->allocation?->local_chief_executive_partylist
                        ?: 'Unassigned LCE / Party-list'
                ),
                'label' => $project->allocation?->local_chief_executive_partylist
                    ?: 'Unassigned LCE / Party-list',
            ]],
            ReportDimension::PROJECT_CODE => [[
                'key' => $this->normalizedKey(
                    $project->approval?->project_code ?: 'Unapproved'
                ),
                'label' => $project->approval?->project_code ?: 'Unapproved',
            ]],
            ReportDimension::INTERVENTION_FOCUS => [[
                'key' => $project->intervention_focus?->value ?? 'unclassified',
                'label' => $project->intervention_focus?->label() ?? 'Unclassified',
            ]],
            ReportDimension::SECTOR,
            ReportDimension::LABOR_MARKET_PROGRAM => throw new InvalidArgumentException(
                sprintf('%s is not a project-level grouping dimension.', $dimension->label())
            ),
        };
    }

    /** @param Collection<int, Project> $projects */
    private function projectMetrics(Collection $projects): array
    {
        return [
            'project_count' => $projects->count(),
            'completed_project_count' => $projects->filter(
                fn (Project $project): bool =>
                    $project->status === ProjectStatus::COMPLETED
            )->count(),
            'beneficiaries_total' =>
                (int) $projects->sum('beneficiaries_total'),
            'beneficiaries_female' =>
                (int) $projects->sum('beneficiaries_female'),
            'wages_cents' => $this->sumProjectMoney($projects, 'wages_total'),
            'ppe_cents' => $this->sumProjectMoney($projects, 'ppe_total'),
            'insurance_cents' =>
                $this->sumProjectMoney($projects, 'insurance_total'),
            'project_cost_cents' =>
                $this->sumProjectMoney($projects, 'total_project_cost'),
            'direct_admin_obligated_cents' => $this->directAdminObligatedCents($projects),
            'direct_admin_disbursed_cents' => $this->directAdminDisbursedCents($projects),
            'acp_payment_cents' => $this->acpPaymentCents($projects),
            'acp_check_released_cents' => $this->acpCheckReleasedCents($projects),
            'acp_liquidated_cents' => $this->acpLiquidatedCents($projects),
            'obligated_cents' => $this->obligatedCents($projects),
            'disbursed_cents' => $this->disbursedCents($projects),
        ];
    }

    /** @param Collection<int, Project> $projects */
    private function sumProjectMoney(Collection $projects, string $column): int
    {
        return (int) $projects->sum(
            fn (Project $project): int =>
                $this->paymentService->amountToCents($project->{$column})
        );
    }

    /** @param Collection<int, Project> $projects */
    private function obligatedCents(Collection $projects): int
    {
        return $this->directAdminObligatedCents($projects)
            + $this->acpPaymentCents($projects);
    }

    /** @param Collection<int, Project> $projects */
    private function disbursedCents(Collection $projects): int
    {
        return $this->directAdminDisbursedCents($projects)
            + $this->acpCheckReleasedCents($projects);
    }

    /** @param Collection<int, Project> $projects */
    private function directAdminObligatedCents(Collection $projects): int
    {
        return (int) $projects
            ->filter(fn (Project $project): bool =>
                $project->implementation_mode === ImplementationMode::DIRECT_ADMINISTRATION
            )
            ->sum(
                fn (Project $project): int =>
                    (int) $project->obligations->sum(
                        fn ($obligation): int =>
                            $this->paymentService->amountToCents($obligation->amount)
                    )
            );
    }

    /** @param Collection<int, Project> $projects */
    private function directAdminDisbursedCents(Collection $projects): int
    {
        return (int) $projects
            ->filter(fn (Project $project): bool =>
                $project->implementation_mode === ImplementationMode::DIRECT_ADMINISTRATION
            )
            ->sum(
                fn (Project $project): int =>
                    (int) $project->obligations->sum(
                        fn ($obligation): int =>
                            (int) $obligation->disbursements->sum(
                                fn ($disbursement): int =>
                                    $this->paymentService->amountToCents(
                                        $disbursement->amount
                                    )
                            )
                    )
            );
    }

    /** @param Collection<int, Project> $projects */
    private function acpPaymentCents(Collection $projects): int
    {
        return (int) $projects
            ->filter(fn (Project $project): bool =>
                $project->implementation_mode === ImplementationMode::THROUGH_ACP
                && $project->acpPayment !== null
            )
            ->sum(fn (Project $project): int =>
                $this->paymentService->amountToCents($project->acpPayment->amount)
            );
    }

    /** @param Collection<int, Project> $projects */
    private function acpCheckReleasedCents(Collection $projects): int
    {
        return (int) $projects
            ->filter(fn (Project $project): bool =>
                $project->implementation_mode === ImplementationMode::THROUGH_ACP
                && $project->acpCheckRelease !== null
            )
            ->sum(fn (Project $project): int =>
                $this->paymentService->amountToCents($project->acpCheckRelease->amount)
            );
    }

    /** @param Collection<int, Project> $projects */
    private function acpLiquidatedCents(Collection $projects): int
    {
        return (int) $projects
            ->filter(fn (Project $project): bool =>
                $project->implementation_mode === ImplementationMode::THROUGH_ACP
            )
            ->sum(fn (Project $project): int =>
                (int) $project->acpLiquidations->sum(
                    fn ($liquidation): int =>
                        $this->paymentService->amountToCents($liquidation->amount)
                )
            );
    }

    /**
     * @param Collection<int, Project> $projects
     * @param Collection<int, AdlAllocation>|null $allocations
     */
    private function allocationCentsForProjects(
        Collection $projects,
        ?Collection $allocations = null,
    ): int {
        $allocations ??= $projects
            ->pluck('allocation')
            ->filter()
            ->unique('id')
            ->values();

        return (int) $allocations->sum(
            fn (AdlAllocation $allocation): int =>
                $this->paymentService->amountToCents(
                    $allocation->grant_amount
                    ?? $allocation->amount
                )
        );
    }

    private function mergeAllocationOnlyGroups(
        Collection $groups,
        ReportFilters $filters,
        Collection $projects,
        ReportDimension $groupBy,
    ): Collection {
        $allocationQuery = AdlAllocation::query()->with('adl');

        if ($filters->adlId) {
            $allocationQuery->where('adl_id', $filters->adlId);
        }

        if ($this->hasProjectSpecificFilters($filters)) {
            $allocationQuery->whereIn(
                'id',
                $projects->pluck('adl_allocation_id')->unique()->values(),
            );
        }

        foreach ($allocationQuery->get() as $allocation) {
            $descriptor = match ($groupBy) {
                ReportDimension::OVERALL => [
                    'key' => 'overall',
                    'label' => 'Overall',
                ],
                ReportDimension::ADL => [
                    'key' => (string) $allocation->adl_id,
                    'label' => $allocation->adl?->adl_number ?? 'Unassigned ADL',
                ],
                ReportDimension::LCE => [
                    'key' => $this->normalizedKey(
                        $allocation->local_chief_executive_partylist
                            ?: 'Unassigned LCE / Party-list'
                    ),
                    'label' => $allocation->local_chief_executive_partylist
                        ?: 'Unassigned LCE / Party-list',
                ],
                default => throw new InvalidArgumentException(
                    'Allocation-only groups are not supported for this reporting dimension.'
                ),
            };

            $group = $groups->get($descriptor['key'], [
                'key' => $descriptor['key'],
                'label' => $descriptor['label'],
                'projects' => collect(),
            ]);
            $group['allocations'] ??= collect();
            $group['allocations']->push($allocation);
            $groups->put($descriptor['key'], $group);
        }

        return $groups;
    }

    private function hasProjectSpecificFilters(ReportFilters $filters): bool
    {
        [$periodStart, $periodEnd] = $filters->periodBounds();

        return $periodStart !== null
            || $periodEnd !== null
            || $filters->term !== null
            || $filters->status !== null
            || $filters->implementationMode !== null
            || $filters->provinceId !== null
            || $filters->district !== null
            || $filters->municipalityId !== null
            || $filters->barangayId !== null
            || $filters->sponsor !== null
            || $filters->partner !== null
            || $filters->projectCode !== null
            || $filters->sector !== null
            || $filters->interventionFocus !== null
            || $filters->laborMarketProgram !== null;
    }

    /** @return Collection<int, ProjectLocation> */
    private function matchingProjectLocations(
        Project $project,
        ReportFilters $filters,
    ): Collection {
        return $project->projectLocations
            ->filter(function (ProjectLocation $location) use ($filters): bool {
                if ($filters->provinceId && $location->province_id !== $filters->provinceId) {
                    return false;
                }

                if ($filters->district && $location->district !== $filters->district) {
                    return false;
                }

                if ($filters->municipalityId && $location->municipality_id !== $filters->municipalityId) {
                    return false;
                }

                if (
                    $filters->barangayId
                    && ! $location->barangays->contains('id', $filters->barangayId)
                ) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    private function matchingLocationBarangays(
        ProjectLocation $location,
        ReportFilters $filters,
    ): Collection {
        if (! $filters->barangayId) {
            return $location->barangays->values();
        }

        return $location->barangays
            ->where('id', $filters->barangayId)
            ->values();
    }

    private function addGeographicAllocation(
        array &$rows,
        array $descriptor,
        int $projectId,
        int $beneficiaries,
        int $female,
        bool $isExact,
    ): void {
        $key = $descriptor['key'];
        $rows[$key] ??= [
            'key' => $key,
            'label' => $descriptor['label'],
            'beneficiaries_total' => 0,
            'beneficiaries_female' => 0,
            'project_ids' => [],
            'incomplete_project_ids' => [],
        ];
        $rows[$key]['beneficiaries_total'] += $beneficiaries;
        $rows[$key]['beneficiaries_female'] += $female;
        $rows[$key]['project_ids'][$projectId] = true;

        if (! $isExact) {
            $rows[$key]['incomplete_project_ids'][$projectId] = true;
        }
    }

    private function geographyDescriptor(
        Project $project,
        ProjectLocation $location,
        mixed $barangay,
        ReportDimension $dimension,
    ): array {
        return match ($dimension) {
            ReportDimension::PROVINCE => [
                'key' => (string) ($location->province_id ?: $project->province_id ?: 'unassigned'),
                'label' => $location->province?->name
                    ?: $project->province
                    ?: 'Unassigned Province',
            ],
            ReportDimension::DISTRICT => [
                'key' => $this->normalizedKey(
                    ($location->province_id ?: $project->province_id).'-'.
                    ($location->district ?: $project->district)
                ),
                'label' => $location->district ?: $project->district ?: 'Unassigned District',
            ],
            ReportDimension::MUNICIPALITY => [
                'key' => (string) ($location->municipality_id ?: 'unassigned'),
                'label' => $location->municipality?->name
                    ?: $project->municipality
                    ?: 'Unassigned Municipality',
            ],
            ReportDimension::BARANGAY => [
                'key' => (string) ($barangay?->id ?: 'unassigned-'.$location->id),
                'label' => $barangay?->name ?: 'Unassigned Barangay',
            ],
            default => throw new InvalidArgumentException(
                'Invalid fine-grained geographic dimension.'
            ),
        };
    }

    private function legacyGeographyDescriptor(
        Project $project,
        ReportDimension $dimension,
    ): array {
        return match ($dimension) {
            ReportDimension::PROVINCE => [
                'key' => (string) (
                    $project->province_id
                    ?: $this->normalizedKey($project->province ?: 'unassigned')
                ),
                'label' => $project->province ?: 'Unassigned Province',
            ],
            ReportDimension::DISTRICT => [
                'key' => $this->normalizedKey(
                    ($project->province_id ?: $project->province).'-'.$project->district
                ),
                'label' => $project->district ?: 'Unassigned District',
            ],
            ReportDimension::MUNICIPALITY => [
                'key' => (string) (
                    $project->municipality_id
                    ?: $this->normalizedKey($project->municipality ?: 'unassigned')
                ),
                'label' => $project->municipality ?: 'Unassigned Municipality',
            ],
            ReportDimension::BARANGAY => [
                'key' => (string) (
                    $project->barangay_id
                    ?: $this->normalizedKey($project->barangay ?: 'unassigned')
                ),
                'label' => $project->barangay ?: 'Unassigned Barangay',
            ],
            default => throw new InvalidArgumentException(
                'Invalid legacy geographic dimension.'
            ),
        };
    }

    /** @return array<int, array{key: string, label: string}> */
    private function provinceDescriptors(Project $project): array
    {
        $provinces = $project->projectLocations
            ->map(fn (ProjectLocation $location): array => [
                'key' => (string) $location->province_id,
                'label' => $location->province?->name ?: $project->province,
            ])
            ->filter(fn (array $row): bool => filled($row['key']))
            ->unique('key')
            ->values()
            ->all();

        return $provinces ?: [[
            'key' => (string) (
                $project->province_id
                ?: $this->normalizedKey($project->province ?: 'unassigned')
            ),
            'label' => $project->province ?: 'Unassigned Province',
        ]];
    }

    /** @return array<int, array{key: string, label: string}> */
    private function districtDescriptors(Project $project): array
    {
        $districts = $project->projectLocations
            ->map(fn (ProjectLocation $location): array => [
                'key' => $this->normalizedKey(
                    $location->province_id.'-'.$location->district
                ),
                'label' => $location->district,
            ])
            ->filter(fn (array $row): bool => filled($row['label']))
            ->unique('key')
            ->values()
            ->all();

        return $districts ?: [[
            'key' => $this->normalizedKey(
                ($project->province_id ?: $project->province).'-'.
                ($project->district ?: 'unassigned')
            ),
            'label' => $project->district ?: 'Unassigned District',
        ]];
    }

    /** @return array<int, array{key: string, label: string}> */
    private function municipalityDescriptors(Project $project): array
    {
        $municipalities = $project->projectLocations
            ->map(fn (ProjectLocation $location): array => [
                'key' => (string) $location->municipality_id,
                'label' => $location->municipality?->name ?: $project->municipality,
            ])
            ->unique('key')
            ->values()
            ->all();

        return $municipalities ?: [[
            'key' => (string) (
                $project->municipality_id
                ?: $this->normalizedKey($project->municipality ?: 'unassigned')
            ),
            'label' => $project->municipality ?: 'Unassigned Municipality',
        ]];
    }

    /** @return array<int, array{key: string, label: string}> */
    private function barangayDescriptors(Project $project): array
    {
        $barangays = $project->projectLocations
            ->flatMap(fn (ProjectLocation $location) =>
                $location->barangays->map(fn ($barangay): array => [
                    'key' => (string) $barangay->id,
                    'label' => $barangay->name,
                ])
            )
            ->unique('key')
            ->values()
            ->all();

        return $barangays ?: [[
            'key' => (string) (
                $project->barangay_id
                ?: $this->normalizedKey($project->barangay ?: 'unassigned')
            ),
            'label' => $project->barangay ?: 'Unassigned Barangay',
        ]];
    }

    private function assertProjectClassificationDimension(
        ReportDimension $dimension,
    ): void {
        if ($dimension->isFineGeography()) {
            throw new InvalidArgumentException(
                'Project-level classification totals cannot be divided across fine-grained geography.'
            );
        }

        if ($dimension === ReportDimension::LABOR_MARKET_PROGRAM) {
            throw new InvalidArgumentException(
                'Use laborMarketAggregation() for labor market program grouping.'
            );
        }
    }

    /** @return array<int, array{key: string, label: string}> */
    private function laborDescriptors(
        Project $project,
        LaborMarketProgram $program,
        int $year,
        int $month,
        ReportDimension $dimension,
    ): array {
        return match ($dimension) {
            ReportDimension::OVERALL => [[
                'key' => 'overall',
                'label' => 'Overall',
            ]],
            ReportDimension::MONTH => [[
                'key' => sprintf('%04d-%02d', $year, $month),
                'label' => \Carbon\CarbonImmutable::create(
                    $year,
                    $month,
                    1,
                )->format('F Y'),
            ]],
            ReportDimension::QUARTER => [[
                'key' => sprintf('%d-Q%d', $year, (int) ceil($month / 3)),
                'label' => sprintf('Q%d %d', (int) ceil($month / 3), $year),
            ]],
            ReportDimension::FISCAL_YEAR => [[
                'key' => (string) $year,
                'label' => 'FY '.$year,
            ]],
            ReportDimension::LABOR_MARKET_PROGRAM => [[
                'key' => $program->value,
                'label' => $program->label(),
            ]],
            default => $this->projectDescriptors($project, $dimension),
        };
    }

    private function normalizedKey(string $value): string
    {
        return (string) str($value)->lower()->squish()->slug('-');
    }
}
