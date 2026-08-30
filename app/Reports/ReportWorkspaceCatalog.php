<?php

namespace App\Reports;

use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Enums\ProjectTerm;

class ReportWorkspaceCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        return [
            'physical-financial' => [
                'route' => 'reports.workspace.physical-financial',
                'number' => '01',
                'label' => 'Physical & Financial Accomplishment',
                'short_label' => 'Physical & Financial',
                'description' => 'Review physical progress, beneficiary accomplishment, project cost, obligations, disbursements, and balances by reporting period or project term.',
                'phase' => 'Phase 14B',
                'items' => [
                    $this->availableItem(
                        'Overall Accomplishment',
                        'Regional or province-scoped accomplishment using the existing Phase 8 reporting data layer.',
                        ReportType::PHYSICAL_FINANCIAL,
                        ReportDimension::OVERALL,
                    ),
                    $this->availableItem(
                        'Accomplishment per Quarter',
                        'Physical and financial accomplishment grouped by fiscal quarter.',
                        ReportType::PHYSICAL_FINANCIAL,
                        ReportDimension::QUARTER,
                    ),
                    $this->availableItem(
                        'Accomplishment per Month',
                        'Physical and financial accomplishment grouped by month.',
                        ReportType::PHYSICAL_FINANCIAL,
                        ReportDimension::MONTH,
                    ),
                    $this->availableItem(
                        'Short-Term Accomplishment',
                        'Accomplishment for projects classified as short-term.',
                        ReportType::PHYSICAL_FINANCIAL,
                        ReportDimension::OVERALL,
                        ['term' => ProjectTerm::SHORT_TERM->value],
                    ),
                    $this->availableItem(
                        'Long-Term Accomplishment',
                        'Accomplishment for projects classified as long-term.',
                        ReportType::PHYSICAL_FINANCIAL,
                        ReportDimension::OVERALL,
                        ['term' => ProjectTerm::LONG_TERM->value],
                    ),
                ],
            ],
            'fund-status' => [
                'route' => 'reports.workspace.fund-status',
                'number' => '02',
                'label' => 'Fund Status Reports',
                'short_label' => 'Fund Status',
                'description' => 'Track TUPAD allocations, obligations, disbursements, balances, and fund utilization from the existing audited financial data sources.',
                'phase' => 'Phase 14C',
                'items' => [
                    $this->availableItem(
                        'Fund Utilization Report',
                        'One report containing TUPAD allocation, accomplishment (obligated), and remaining balance.',
                        ReportType::FUND_STATUS,
                        ReportDimension::OVERALL,
                        [],
                        ['TUPAD Allocation', 'Accomplishment (Obligated)', 'Balance'],
                    ),
                    $this->availableItem('Report ADL', 'Fund status grouped by ADL.', ReportType::FUND_STATUS, ReportDimension::ADL),
                    $this->availableItem('Report Province', 'Fund status grouped by province.', ReportType::FUND_STATUS, ReportDimension::PROVINCE),
                    $this->availableItem('Report Status', 'Fund status grouped by project workflow status.', ReportType::FUND_STATUS, ReportDimension::STATUS),
                    $this->availableItem('Report Sponsor', 'Fund status grouped by sponsor.', ReportType::FUND_STATUS, ReportDimension::SPONSOR),
                    $this->availableItem('Report NGA', 'Fund status grouped using the existing Partner / NGA reference.', ReportType::FUND_STATUS, ReportDimension::PARTNER),
                    $this->availableItem('Report District', 'Fund status grouped by legislative district.', ReportType::FUND_STATUS, ReportDimension::DISTRICT),
                    $this->availableItem('Report LCE', 'Fund status grouped by the authoritative Local Chief Executive / Party-list reference encoded on the ADL allocation.', ReportType::FUND_STATUS, ReportDimension::LCE),
                ],
            ],
            'monthly' => [
                'route' => 'reports.workspace.monthly',
                'number' => '03',
                'label' => 'Monthly Reports',
                'short_label' => 'Monthly',
                'description' => 'Monthly government reporting workspace for SPRS and beneficiary orientation monitoring.',
                'phase' => 'Phase 14D',
                'items' => [
                    $this->plannedItem(
                        'Statistical Performance Reporting System (SPRS)',
                        'Monthly SPRS data entry, review, and official print output will be implemented against the supplied government layout.',
                    ),
                    $this->plannedItem(
                        'List of Orientations Conducted',
                        'Monthly list of orientations conducted for TUPAD beneficiaries.',
                        ['AlkanSSSya', 'YAKAP Program for TUPAD Beneficiaries'],
                    ),
                ],
            ],
            'quarterly' => [
                'route' => 'reports.workspace.quarterly',
                'number' => '04',
                'label' => 'Quarterly Reports',
                'short_label' => 'Quarterly',
                'description' => 'Quarterly progress reporting and active labor market referral monitoring.',
                'phase' => 'Phase 14D',
                'items' => [
                    $this->plannedItem(
                        'Consolidated Quarterly Progress Report (CQPR)',
                        'CQPR screen workflow and official print layout will be implemented using the supplied reference format.',
                    ),
                    $this->availableItem(
                        'Number of TUPAD Beneficiaries Referred to Active Labor Market',
                        'Current referral data remains available from the Phase 8 reporting layer while the dedicated quarterly layout is prepared.',
                        ReportType::LABOR_MARKET_REFERRALS,
                        ReportDimension::QUARTER,
                    ),
                ],
            ],
            'geographic-mapping' => [
                'route' => 'reports.workspace.geographic-mapping',
                'number' => '05',
                'label' => 'TUPAD Geographic Mapping',
                'short_label' => 'Geographic Mapping',
                'description' => 'Live geographic intensity views for project implementation, exact beneficiary concentration, sector concentration, and intervention focus without fabricated GIS coordinates or allocations.',
                'phase' => 'Phase 14E',
                'items' => [
                    [
                        'label' => 'Project Mapping',
                        'description' => 'Distribution of TUPAD project implementation.',
                        'status' => 'available',
                        'children' => ['Province', 'District', 'Municipality'],
                        'links' => $this->dimensionLinks(ReportType::PHYSICAL_FINANCIAL, [
                            ReportDimension::PROVINCE,
                            ReportDimension::DISTRICT,
                            ReportDimension::MUNICIPALITY,
                        ]),
                    ],
                    [
                        'label' => 'Beneficiary Mapping',
                        'description' => 'Concentration of TUPAD beneficiaries using exact project geography allocations.',
                        'status' => 'available',
                        'children' => ['Province', 'District', 'Municipality', 'Barangay'],
                        'links' => $this->dimensionLinks(ReportType::GEOGRAPHIC_BENEFICIARIES, [
                            ReportDimension::PROVINCE,
                            ReportDimension::DISTRICT,
                            ReportDimension::MUNICIPALITY,
                            ReportDimension::BARANGAY,
                        ]),
                    ],
                    [
                        'label' => 'Sector Mapping',
                        'description' => 'Concentration of TUPAD beneficiaries by priority/vulnerable and occupational/livelihood sector.',
                        'status' => 'available',
                        'query' => [
                            'report_type' => ReportType::BENEFICIARY_SECTORS->value,
                            'group_by' => ReportDimension::SECTOR->value,
                        ],
                        'groups' => [
                            'Priority / Vulnerable Sectors' => [
                                'Female', 'Youth', 'Senior Citizens', 'Persons with Disabilities', 'Solo Parents',
                                'Indigenous Peoples', 'Former Rebels', 'Persons Deprived of Liberty', 'Parolees and Probationers',
                            ],
                            'Occupational / Livelihood Sectors' => [
                                'Transport Workers', 'Vendors', 'Crop Growers', 'Homebased Workers', 'Fisherfolk',
                                'Livestock / Poultry Raisers', 'Small Transport Drivers', 'Laborers', 'House Helpers', 'Others',
                            ],
                        ],
                    ],
                    [
                        'label' => 'Intervention-Focus Mapping',
                        'description' => 'Distribution of TUPAD interventions based on the primary project focus.',
                        'status' => 'available',
                        'query' => [
                            'report_type' => ReportType::INTERVENTION_FOCUS->value,
                            'group_by' => ReportDimension::INTERVENTION_FOCUS->value,
                        ],
                        'children' => [
                            'Disaster Risk Reduction and Mitigation',
                            'Emergency Preparedness',
                            'Environmental Conservation',
                            'Early Recovery and Rehabilitation',
                            'Administrative, Clerical and Logistical Support',
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function section(string $key): ?array
    {
        return $this->sections()[$key] ?? null;
    }

    /** @return array<string, mixed> */
    private function availableItem(
        string $label,
        string $description,
        ReportType $type,
        ReportDimension $dimension,
        array $extraQuery = [],
        array $children = [],
    ): array {
        return [
            'label' => $label,
            'description' => $description,
            'status' => 'available',
            'query' => array_merge([
                'report_type' => $type->value,
                'group_by' => $dimension->value,
            ], $extraQuery),
            'children' => $children,
        ];
    }

    /** @return array<string, mixed> */
    private function plannedItem(string $label, string $description, array $children = []): array
    {
        return [
            'label' => $label,
            'description' => $description,
            'status' => 'planned',
            'children' => $children,
        ];
    }

    /** @param array<int, ReportDimension> $dimensions */
    private function dimensionLinks(ReportType $type, array $dimensions): array
    {
        return array_map(
            static fn (ReportDimension $dimension): array => [
                'label' => $dimension->label(),
                'query' => [
                    'report_type' => $type->value,
                    'group_by' => $dimension->value,
                ],
            ],
            $dimensions,
        );
    }
}
