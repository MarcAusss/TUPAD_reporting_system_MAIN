<?php

namespace Tests\Feature;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectBeneficiarySector;
use App\Models\ProjectDisbursement;
use App\Models\ProjectLaborMarketReferral;
use App\Models\ProjectLocation;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Reports\ReportingDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MajorRevisionPhase8ReportingDataLayerTest extends TestCase
{
    use RefreshDatabase;

    private ReportingDataService $reports;
    private User $encoder;
    private Adl $adl;
    private AdlAllocation $allocation;
    private Province $province;
    private Municipality $firstMunicipality;
    private Municipality $secondMunicipality;
    private Barangay $firstBarangay;
    private Barangay $secondBarangay;
    private Project $augustProject;
    private Project $septemberProject;
    private Project $februaryProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reports = app(ReportingDataService::class);
        $this->encoder = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $this->createReferenceData();
        $this->createProjects();
        $this->createPaymentData();
        $this->createClassificationData();
        $this->createLaborMarketData();
    }

    public function test_overall_quarterly_monthly_and_term_accomplishments_are_aggregated_in_cents(): void
    {
        $quarterFilters = ReportFilters::fromArray([
            'fiscal_year' => 2026,
            'quarter' => 3,
        ]);

        $overall = $this->reports
            ->physicalFinancial($quarterFilters)
            ->sole();

        $this->assertSame(2, $overall['project_count']);
        $this->assertSame(150, $overall['beneficiaries_total']);
        $this->assertSame(80, $overall['beneficiaries_female']);
        $this->assertSame(300000, $overall['wages_cents']);
        $this->assertSame(30000, $overall['ppe_cents']);
        $this->assertSame(15000, $overall['insurance_cents']);
        $this->assertSame(345000, $overall['project_cost_cents']);
        $this->assertSame(300000, $overall['obligated_cents']);
        $this->assertSame(260000, $overall['disbursed_cents']);

        $monthly = $this->reports->physicalFinancial(
            $quarterFilters,
            ReportDimension::MONTH,
        );

        $this->assertSame(['2026-08', '2026-09'], $monthly->pluck('key')->sort()->values()->all());
        $this->assertSame(
            100,
            $monthly->firstWhere('key', '2026-08')['beneficiaries_total'],
        );

        $shortTerm = $this->reports
            ->physicalFinancial(ReportFilters::fromArray([
                'fiscal_year' => 2026,
                'quarter' => 3,
                'term' => 'short_term',
            ]))
            ->sole();

        $this->assertSame(1, $shortTerm['project_count']);
        $this->assertSame(100000, $shortTerm['wages_cents']);
    }

    public function test_fund_status_reconciles_allocation_obligation_disbursement_and_balances(): void
    {
        $row = $this->reports
            ->fundStatus(new ReportFilters(), ReportDimension::ADL)
            ->sole();

        $this->assertSame('ADL-PHASE8-001', $row['label']);
        $this->assertSame(1000000, $row['allocation_cents']);
        $this->assertSame(350000, $row['payable_wages_cents']);
        $this->assertSame(300000, $row['obligated_cents']);
        $this->assertSame(260000, $row['disbursed_cents']);
        $this->assertSame(700000, $row['unobligated_balance_cents']);
        $this->assertSame(40000, $row['undisbursed_obligation_cents']);
        $this->assertSame(740000, $row['balance_cents']);
        $this->assertSame('allocation_less_disbursed', $row['balance_basis']);
        $this->assertFalse($row['is_over_obligated']);
        $this->assertFalse($row['is_over_disbursed']);
    }

    public function test_exact_barangay_allocations_are_aggregated_without_guessing_legacy_values_or_money(): void
    {
        $rows = $this->reports->beneficiaryGeography(
            new ReportFilters(),
            ReportDimension::BARANGAY,
        );

        $first = $rows->firstWhere('key', (string) $this->firstBarangay->id);
        $second = $rows->firstWhere('key', (string) $this->secondBarangay->id);

        $this->assertSame(110, $first['beneficiaries_total']);
        $this->assertSame(60, $first['beneficiaries_female']);
        $this->assertSame(2, $first['project_count']);
        $this->assertTrue($first['has_complete_exact_allocation']);

        // The February project's NULL pivot allocation is not replaced with
        // its project total. Only the August project's exact 40/20 is counted.
        $this->assertSame(40, $second['beneficiaries_total']);
        $this->assertSame(20, $second['beneficiaries_female']);
        $this->assertSame(1, $second['legacy_unallocated_project_count']);
        $this->assertFalse($second['has_complete_exact_allocation']);

        $financialRows = $this->reports->physicalFinancial(
            new ReportFilters(),
            ReportDimension::BARANGAY,
        );
        $financialFirst = $financialRows->firstWhere(
            'key',
            (string) $this->firstBarangay->id,
        );

        $this->assertNull($financialFirst['project_cost_cents']);
        $this->assertNull($financialFirst['obligated_cents']);
        $this->assertFalse($financialFirst['financial_allocation_available']);
    }

    public function test_priority_and_occupational_sector_totals_remain_overlapping_project_statistics(): void
    {
        $rows = $this->reports->sectorAggregation(
            ReportFilters::fromArray([
                'fiscal_year' => 2026,
                'quarter' => 3,
            ])
        );

        $this->assertCount(count(BeneficiarySectorCategory::cases()), $rows);

        $youth = $rows->firstWhere('sector_key', 'youth');
        $vendors = $rows->firstWhere('sector_key', 'vendors');

        $this->assertSame(50, $youth['beneficiaries_total']);
        $this->assertSame(30, $youth['beneficiaries_female']);
        $this->assertSame(2, $youth['project_count']);
        $this->assertSame(25, $vendors['beneficiaries_total']);
        $this->assertSame(12, $vendors['beneficiaries_female']);

        $this->expectException(InvalidArgumentException::class);
        $this->reports->sectorAggregation(
            new ReportFilters(),
            ReportDimension::BARANGAY,
        );
    }

    public function test_intervention_focus_is_aggregated_from_the_single_primary_project_classification(): void
    {
        $rows = $this->reports->interventionAggregation(
            ReportFilters::fromArray([
                'fiscal_year' => 2026,
                'quarter' => 3,
            ])
        );

        $environment = $rows->firstWhere(
            'intervention_focus',
            ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION->value,
        );
        $emergency = $rows->firstWhere(
            'intervention_focus',
            ProjectInterventionFocus::EMERGENCY_PREPAREDNESS->value,
        );

        $this->assertSame(1, $environment['project_count']);
        $this->assertSame(100, $environment['beneficiaries_total']);
        $this->assertSame(115000, $environment['project_cost_cents']);
        $this->assertSame(1, $emergency['project_count']);
        $this->assertSame(50, $emergency['beneficiaries_total']);
    }

    public function test_labor_market_referrals_are_aggregated_by_program_month_and_quarter(): void
    {
        $filters = ReportFilters::fromArray([
            'fiscal_year' => 2026,
            'quarter' => 3,
        ]);

        $programs = $this->reports->laborMarketAggregation($filters);
        $skills = $programs->firstWhere(
            'key',
            LaborMarketProgram::SKILLS_TRAINING->value,
        );
        $dilp = $programs->firstWhere(
            'key',
            LaborMarketProgram::DOLE_INTEGRATED_LIVELIHOOD_PROGRAM->value,
        );

        $this->assertCount(count(LaborMarketProgram::cases()), $programs);
        $this->assertSame(18, $skills['interested_referred_total']);
        $this->assertSame(11, $skills['interested_referred_female']);
        $this->assertSame(14, $skills['provided_intervention_total']);
        $this->assertSame(9, $skills['provided_intervention_female']);
        $this->assertSame(3250050, $skills['amount_released_cents']);
        $this->assertSame(2, $skills['project_count']);
        $this->assertSame(500000, $dilp['amount_released_cents']);

        $months = $this->reports->laborMarketAggregation(
            $filters,
            ReportDimension::MONTH,
        );
        $this->assertSame(
            ['2026-07', '2026-08', '2026-09'],
            $months->pluck('key')->sort()->values()->all(),
        );

        $quarter = $this->reports
            ->laborMarketAggregation($filters, ReportDimension::QUARTER)
            ->sole();
        $this->assertSame('2026-Q3', $quarter['key']);
        $this->assertSame(22, $quarter['interested_referred_total']);
        $this->assertSame(3750050, $quarter['amount_released_cents']);
    }

    public function test_project_filters_and_required_safe_grouping_dimensions_are_supported(): void
    {
        $projectCode = $this->reports
            ->physicalFinancial(ReportFilters::fromArray([
                'project_code' => 'ALB-P8-002',
            ]))
            ->sole();
        $this->assertSame(1, $projectCode['project_count']);
        $this->assertSame(50, $projectCode['beneficiaries_total']);

        $municipality = $this->reports
            ->physicalFinancial(ReportFilters::fromArray([
                'municipality_id' => $this->secondMunicipality->id,
                'fiscal_year' => 2026,
                'quarter' => 3,
            ]))
            ->sole();
        $this->assertSame(1, $municipality['project_count']);
        $this->assertSame(100, $municipality['beneficiaries_total']);
        $this->assertSame(100000, $municipality['wages_cents']);

        $expectedLabels = [
            ReportDimension::ADL->value => 'ADL-PHASE8-001',
            ReportDimension::PROVINCE->value => 'Albay',
            ReportDimension::STATUS->value => 'For Payment',
            ReportDimension::SPONSOR->value => 'DOLE Regional Office V',
            ReportDimension::PARTNER->value => 'LGU Partner A',
            ReportDimension::PROJECT_CODE->value => 'ALB-P8-001',
        ];

        foreach ($expectedLabels as $dimensionValue => $expectedLabel) {
            $dimension = ReportDimension::from($dimensionValue);
            $rows = $this->reports->physicalFinancial(
                ReportFilters::fromArray([
                    'project_code' => 'ALB-P8-001',
                ]),
                $dimension,
            );

            $this->assertSame($expectedLabel, $rows->sole()['label']);
        }
    }

    public function test_report_filter_period_validation_rejects_ambiguous_or_invalid_ranges(): void
    {
        try {
            ReportFilters::fromArray(['quarter' => 3]);
            $this->fail('Quarter without fiscal year should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Fiscal year', $exception->getMessage());
        }

        try {
            ReportFilters::fromArray([
                'fiscal_year' => 2026,
                'quarter' => 3,
                'month' => 8,
            ]);
            $this->fail('Quarter and month cannot be combined.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('either', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        ReportFilters::fromArray([
            'date_from' => '2026-09-01',
            'date_to' => '2026-08-01',
        ]);
    }

    private function createReferenceData(): void
    {
        $this->province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->firstMunicipality = Municipality::create([
            'province_id' => $this->province->id,
            'code' => '050501000',
            'name' => 'Municipality One',
            'district' => '1st District',
            'income_class' => '1st Class',
            'is_city' => false,
            'is_active' => true,
        ]);
        $this->secondMunicipality = Municipality::create([
            'province_id' => $this->province->id,
            'code' => '050502000',
            'name' => 'Municipality Two',
            'district' => '2nd District',
            'income_class' => '2nd Class',
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->firstBarangay = Barangay::create([
            'municipality_id' => $this->firstMunicipality->id,
            'code' => '050501001',
            'name' => 'Barangay One',
            'is_active' => true,
        ]);
        $this->secondBarangay = Barangay::create([
            'municipality_id' => $this->secondMunicipality->id,
            'code' => '050502001',
            'name' => 'Barangay Two',
            'is_active' => true,
        ]);

        $this->adl = Adl::create([
            'adl_number' => 'ADL-PHASE8-001',
            'date_received' => '2026-01-05',
            'grants' => '10000.00',
            'admin_cost' => '0.00',
            'total' => '10000.00',
            'created_by' => $this->encoder->id,
        ]);
        $this->allocation = AdlAllocation::create([
            'adl_id' => $this->adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Legacy Allocation Partner',
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => '10000.00',
            'grant_amount' => '10000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '10000.00',
            'created_by' => $this->encoder->id,
        ]);
    }

    private function createProjects(): void
    {
        $this->augustProject = $this->createProject([
            'date_received' => '2026-08-10',
            'project_title' => 'August Short-Term Project',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Partner A',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 100,
            'beneficiaries_female' => 60,
            'wages_total' => '1000.00',
            'ppe_total' => '100.00',
            'insurance_total' => '50.00',
            'total_project_cost' => '1150.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'intervention_focus' =>
                ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION,
        ], 'ALB-P8-001');

        $firstLocation = ProjectLocation::create([
            'project_id' => $this->augustProject->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->firstMunicipality->id,
            'district' => '1st District',
            'sort_order' => 1,
        ]);
        $firstLocation->barangays()->sync([
            $this->firstBarangay->id => [
                'beneficiaries_total' => 60,
                'beneficiaries_female' => 40,
            ],
        ]);

        $secondLocation = ProjectLocation::create([
            'project_id' => $this->augustProject->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->secondMunicipality->id,
            'district' => '2nd District',
            'sort_order' => 2,
        ]);
        $secondLocation->barangays()->sync([
            $this->secondBarangay->id => [
                'beneficiaries_total' => 40,
                'beneficiaries_female' => 20,
            ],
        ]);

        $this->septemberProject = $this->createProject([
            'date_received' => '2026-09-05',
            'project_title' => 'September Long-Term Project',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'NGA Partner B',
            'number_of_days' => 45,
            'term' => 'long_term',
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 20,
            'wages_total' => '2000.00',
            'ppe_total' => '200.00',
            'insurance_total' => '100.00',
            'total_project_cost' => '2300.00',
            'status' => ProjectStatus::COMPLETED,
            'intervention_focus' =>
                ProjectInterventionFocus::EMERGENCY_PREPAREDNESS,
        ], 'ALB-P8-002');

        $septemberLocation = ProjectLocation::create([
            'project_id' => $this->septemberProject->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->firstMunicipality->id,
            'district' => '1st District',
            'sort_order' => 1,
        ]);
        $septemberLocation->barangays()->sync([
            $this->firstBarangay->id => [
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 20,
            ],
        ]);

        $this->februaryProject = $this->createProject([
            'date_received' => '2026-02-12',
            'project_title' => 'February Legacy Allocation Project',
            'fund_sponsor' => 'Other Sponsor',
            'partner' => 'LGU Partner A',
            'number_of_days' => 15,
            'term' => 'short_term',
            'beneficiaries_total' => 30,
            'beneficiaries_female' => 15,
            'wages_total' => '500.00',
            'ppe_total' => '50.00',
            'insurance_total' => '25.00',
            'total_project_cost' => '575.00',
            'status' => ProjectStatus::APPROVED,
            'intervention_focus' =>
                ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION,
        ], 'ALB-P8-003');

        $legacyLocation = ProjectLocation::create([
            'project_id' => $this->februaryProject->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->secondMunicipality->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $legacyLocation->barangays()->sync([$this->secondBarangay->id]);
    }

    private function createProject(array $overrides, string $projectCode): Project
    {
        $project = Project::create(array_merge([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-01-01',
            'project_title' => 'Reporting Project',
            'nature_of_work' => 'Community work',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Partner A',
            'province' => 'Albay',
            'district' => '1st District',
            'municipality' => 'Municipality One',
            'barangay' => 'Barangay One',
            'province_id' => $this->province->id,
            'municipality_id' => $this->firstMunicipality->id,
            'barangay_id' => $this->firstBarangay->id,
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 1,
            'beneficiaries_female' => 0,
            'wage_rate' => '455.00',
            'wages_total' => '455.00',
            'ppe_total' => '0.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 1,
            'insurance_total' => '50.00',
            'total_project_cost' => '505.00',
            'status' => ProjectStatus::APPROVED,
            'created_by' => $this->encoder->id,
        ], $overrides));

        ProjectApproval::create([
            'project_id' => $project->id,
            'approval_date' => $project->date_received,
            'project_code' => $projectCode,
            'approved_by' => $this->encoder->id,
            'approved_at' => now(),
        ]);

        return $project;
    }

    private function createPaymentData(): void
    {
        $first = $this->createObligation(
            $this->augustProject,
            '1000.00',
            '2026-08-20',
        );
        ProjectDisbursement::create([
            'project_obligation_id' => $first->id,
            'amount' => '600.00',
            'date_disbursed' => '2026-08-25',
            'ldap_check_number' => 'LDAP-P8-001',
            'recorded_by' => $this->encoder->id,
        ]);

        $second = $this->createObligation(
            $this->septemberProject,
            '2000.00',
            '2026-09-15',
        );
        ProjectDisbursement::create([
            'project_obligation_id' => $second->id,
            'amount' => '2000.00',
            'date_disbursed' => '2026-09-20',
            'ldap_check_number' => 'LDAP-P8-002',
            'recorded_by' => $this->encoder->id,
        ]);
    }

    private function createObligation(
        Project $project,
        string $amount,
        string $date,
    ): ProjectObligation {
        return ProjectObligation::create([
            'project_id' => $project->id,
            'tranche_number' => 1,
            'adl_number' => $this->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'project_location' => $project->full_location,
            'term' => $project->term->label(),
            'beneficiaries_total' => $project->beneficiaries_total,
            'beneficiaries_female' => $project->beneficiaries_female,
            'amount' => $amount,
            'obligation_date' => $date,
            'month' => substr($date, 0, 7),
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->encoder->id,
        ]);
    }

    private function createClassificationData(): void
    {
        $this->createSector(
            $this->augustProject,
            BeneficiarySectorCategory::YOUTH,
            30,
            20,
        );
        $this->createSector(
            $this->augustProject,
            BeneficiarySectorCategory::VENDORS,
            10,
            5,
        );
        $this->createSector(
            $this->septemberProject,
            BeneficiarySectorCategory::YOUTH,
            20,
            10,
        );
        $this->createSector(
            $this->septemberProject,
            BeneficiarySectorCategory::VENDORS,
            15,
            7,
        );
    }

    private function createSector(
        Project $project,
        BeneficiarySectorCategory $category,
        int $total,
        int $female,
    ): void {
        ProjectBeneficiarySector::create([
            'project_id' => $project->id,
            'sector_group' => $category->group(),
            'sector_key' => $category,
            'beneficiaries_total' => $total,
            'beneficiaries_female' => $female,
            'recorded_by' => $this->encoder->id,
            'updated_by' => $this->encoder->id,
        ]);
    }

    private function createLaborMarketData(): void
    {
        $this->createReferral(
            $this->augustProject,
            '2026-08-01',
            LaborMarketProgram::SKILLS_TRAINING,
            8,
            5,
            6,
            4,
            '12500.50',
            'Shielded Metal Arc Welding NC II',
        );
        $this->createReferral(
            $this->augustProject,
            '2026-09-01',
            LaborMarketProgram::DOLE_INTEGRATED_LIVELIHOOD_PROGRAM,
            4,
            2,
            3,
            1,
            '5000.00',
            'Starter livelihood assistance',
        );
        $this->createReferral(
            $this->septemberProject,
            '2026-07-01',
            LaborMarketProgram::SKILLS_TRAINING,
            10,
            6,
            8,
            5,
            '20000.00',
            'Electrical Installation and Maintenance NC II',
        );
        $this->createReferral(
            $this->februaryProject,
            '2026-02-01',
            LaborMarketProgram::EMPLOYMENT_FACILITATION_SERVICES,
            3,
            1,
            2,
            1,
            '0.00',
            'PESO job matching',
        );
    }

    private function createReferral(
        Project $project,
        string $month,
        LaborMarketProgram $program,
        int $referred,
        int $referredFemale,
        int $provided,
        int $providedFemale,
        string $amount,
        string $services,
    ): void {
        ProjectLaborMarketReferral::create([
            'project_id' => $project->id,
            'reporting_month' => $month,
            'program' => $program,
            'interested_referred_total' => $referred,
            'interested_referred_female' => $referredFemale,
            'provided_intervention_total' => $provided,
            'provided_intervention_female' => $providedFemale,
            'amount_released' => $amount,
            'services_availed' => $services,
            'recorded_by' => $this->encoder->id,
            'updated_by' => $this->encoder->id,
        ]);
    }
}
