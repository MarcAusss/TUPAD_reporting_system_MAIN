<?php

namespace Tests\Feature;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
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
use App\Services\Dashboards\ExecutiveDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase10ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $focal;
    private User $tc;
    private User $gip;
    private Province $albay;
    private Province $camarinesSur;
    private Municipality $legazpi;
    private Municipality $naga;
    private Barangay $legazpiBarangay;
    private Barangay $nagaBarangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(UserRole::ADMIN);
        $this->focal = $this->user(UserRole::FOCAL);
        $this->tc = $this->user(UserRole::TC);
        $this->gip = $this->user(UserRole::GIP);

        $this->createReportingData();
    }

    public function test_admin_focal_and_tc_can_access_executive_dashboard_while_gip_cannot(): void
    {
        foreach ([$this->admin, $this->focal, $this->tc] as $user) {
            $this->actingAs($user)
                ->get(route('executive-dashboard.index'))
                ->assertOk()
                ->assertSee('Executive Dashboard')
                ->assertSee('Presentation Mode');
        }

        $this->actingAs($this->gip)
            ->get(route('executive-dashboard.index'))
            ->assertForbidden();
    }

    public function test_presentation_authorization_matches_dashboard_and_contains_no_write_actions(): void
    {
        foreach ([$this->admin, $this->focal, $this->tc] as $user) {
            $response = $this->actingAs($user)
                ->get(route('executive-dashboard.presentation'))
                ->assertOk()
                ->assertSee('TUPAD Executive Presentation')
                ->assertSee('Executive Overview')
                ->assertSee('Active Labor Market Referrals');

            $response->assertDontSee('<form', false);
            $response->assertDontSee('method="POST"', false);
            $response->assertDontSee('method="PUT"', false);
            $response->assertDontSee('method="DELETE"', false);
        }

        $this->actingAs($this->gip)
            ->get(route('executive-dashboard.presentation'))
            ->assertForbidden();
    }

    public function test_kpis_financial_totals_and_balances_are_sourced_from_phase_eight_reporting_data(): void
    {
        $data = app(ExecutiveDashboardService::class)->build(
            new ReportFilters(),
        );

        $this->assertSame(2, $data['kpis']['total_projects']);
        $this->assertSame(1, $data['kpis']['completed_projects']);
        $this->assertSame(30, $data['kpis']['beneficiaries_total']);
        $this->assertSame(17, $data['kpis']['beneficiaries_female']);
        $this->assertSame(165000, $data['kpis']['project_cost_cents']);
        $this->assertSame(500000, $data['kpis']['allocation_cents']);
        $this->assertSame(140000, $data['kpis']['obligated_cents']);
        $this->assertSame(100000, $data['kpis']['disbursed_cents']);
        $this->assertSame(400000, $data['kpis']['balance_cents']);
        $this->assertSame(50.0, $data['kpis']['physical_accomplishment_percent']);
        $this->assertSame(20.0, $data['kpis']['financial_accomplishment_percent']);
    }

    public function test_monthly_and_quarterly_filters_apply_consistently_to_project_and_labor_metrics(): void
    {
        $service = app(ExecutiveDashboardService::class);

        $august = $service->build(ReportFilters::fromArray([
            'fiscal_year' => 2026,
            'month' => 8,
        ]));

        $this->assertSame(1, $august['kpis']['total_projects']);
        $this->assertSame(20, $august['kpis']['beneficiaries_total']);
        $this->assertSame(5, $august['labor_market_overall']['interested_referred_total']);
        $this->assertSame(4, $august['labor_market_overall']['provided_intervention_total']);
        $this->assertSame(750000, $august['labor_market_overall']['amount_released_cents']);

        $quarter = $service->build(ReportFilters::fromArray([
            'fiscal_year' => 2026,
            'quarter' => 3,
        ]));

        $this->assertSame(2, $quarter['kpis']['total_projects']);
        $this->assertSame(8, $quarter['labor_market_overall']['interested_referred_total']);
        $this->assertSame(6, $quarter['labor_market_overall']['provided_intervention_total']);
        $this->assertSame(950000, $quarter['labor_market_overall']['amount_released_cents']);
        $this->assertSame(
            'project_labor_market_referrals.reporting_month',
            $quarter['labor_market_overall']['period_basis'],
        );
    }

    public function test_exact_province_and_barangay_allocations_are_preserved_without_financial_fabrication(): void
    {
        $service = app(ExecutiveDashboardService::class);
        $data = $service->build(new ReportFilters());

        $albay = collect($data['beneficiaries_by_province'])
            ->firstWhere('key', (string) $this->albay->id);
        $camarinesSur = collect($data['beneficiaries_by_province'])
            ->firstWhere('key', (string) $this->camarinesSur->id);

        $this->assertSame(22, $albay['beneficiaries_total']);
        $this->assertSame(13, $albay['beneficiaries_female']);
        $this->assertSame(8, $camarinesSur['beneficiaries_total']);
        $this->assertSame(4, $camarinesSur['beneficiaries_female']);
        $this->assertTrue($albay['has_complete_exact_allocation']);
        $this->assertTrue($camarinesSur['has_complete_exact_allocation']);

        $fine = $service->build(ReportFilters::fromArray([
            'municipality_id' => $this->legazpi->id,
        ]));

        $this->assertFalse($fine['fine_geography_financials_available']);
        $this->assertSame(22, $fine['kpis']['beneficiaries_total']);
        $this->assertSame(13, $fine['kpis']['beneficiaries_female']);
        $this->assertNull($fine['kpis']['project_cost_cents']);
        $this->assertNull($fine['kpis']['allocation_cents']);
        $this->assertNull($fine['kpis']['obligated_cents']);
        $this->assertNull($fine['kpis']['disbursed_cents']);
        $this->assertNull($fine['kpis']['balance_cents']);
        $this->assertStringContainsString(
            'no authoritative financial allocation',
            $fine['financial_note'],
        );
    }

    public function test_sector_overlap_is_identified_and_not_presented_as_unique_beneficiary_total(): void
    {
        $data = app(ExecutiveDashboardService::class)->build(new ReportFilters());

        $youth = collect($data['sector_priority'])->firstWhere(
            'sector_key',
            BeneficiarySectorCategory::YOUTH->value,
        );
        $vendors = collect($data['sector_occupational'])->firstWhere(
            'sector_key',
            BeneficiarySectorCategory::VENDORS->value,
        );

        $this->assertSame(15, $youth['beneficiaries_total']);
        $this->assertSame(10, $vendors['beneficiaries_total']);
        $this->assertStringContainsString('may overlap', $data['sector_note']);
        $this->assertStringContainsString('must not be added together', $data['sector_note']);
    }

    public function test_presentation_displays_the_same_filtered_totals_as_dashboard(): void
    {
        $query = ['fiscal_year' => 2026, 'month' => 8];

        $dashboard = $this->actingAs($this->admin)
            ->get(route('executive-dashboard.index', $query))
            ->assertOk()
            ->assertSee('Fiscal Year: 2026')
            ->assertSee('Month: August')
            ->assertSee('₱5,000.00')
            ->assertSee('₱600.00');

        $presentation = $this->actingAs($this->admin)
            ->get(route('executive-dashboard.presentation', $query))
            ->assertOk()
            ->assertSee('Fiscal Year: 2026')
            ->assertSee('Month: August')
            ->assertSee('₱5,000.00')
            ->assertSee('₱600.00');

        $this->assertStringContainsString('20', $dashboard->getContent());
        $this->assertStringContainsString('20', $presentation->getContent());
    }

    public function test_invalid_geographic_filter_combinations_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('executive-dashboard.index'))
            ->get(route('executive-dashboard.index', [
                'province_id' => $this->albay->id,
                'municipality_id' => $this->naga->id,
            ]))
            ->assertRedirect(route('executive-dashboard.index'))
            ->assertSessionHasErrors('municipality_id');

        $this->actingAs($this->admin)
            ->from(route('executive-dashboard.index'))
            ->get(route('executive-dashboard.index', [
                'district' => '2nd District',
                'barangay_id' => $this->nagaBarangay->id,
            ]))
            ->assertRedirect(route('executive-dashboard.index'))
            ->assertSessionHasErrors('barangay_id');
    }

    public function test_navigation_exposes_executive_dashboard_only_to_authorized_roles(): void
    {
        foreach ([$this->admin, $this->focal, $this->tc] as $user) {
            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Executive Dashboard');
        }

        $this->actingAs($this->gip)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Executive Dashboard');
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createReportingData(): void
    {
        $this->albay = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);
        $this->camarinesSur = Province::create([
            'code' => '051700000',
            'name' => 'Camarines Sur',
            'is_active' => true,
        ]);
        $this->legazpi = Municipality::create([
            'province_id' => $this->albay->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);
        $this->naga = Municipality::create([
            'province_id' => $this->camarinesSur->id,
            'code' => '051724000',
            'name' => 'Naga City',
            'district' => '3rd District',
            'is_city' => true,
            'is_active' => true,
        ]);
        $this->legazpiBarangay = Barangay::create([
            'municipality_id' => $this->legazpi->id,
            'code' => '050501001',
            'name' => 'Barangay Legazpi',
            'is_active' => true,
        ]);
        $this->nagaBarangay = Barangay::create([
            'municipality_id' => $this->naga->id,
            'code' => '051724001',
            'name' => 'Barangay Naga',
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-PHASE10-001',
            'date_received' => '2026-01-10',
            'grants' => '5000.00',
            'admin_cost' => '0.00',
            'total' => '5000.00',
            'created_by' => $this->focal->id,
        ]);
        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Consortium',
            'location' => 'Bicol Region',
            'province' => 'Albay',
            'amount' => '5000.00',
            'grant_amount' => '5000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '5000.00',
            'created_by' => $this->focal->id,
        ]);

        $august = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-15',
            'project_title' => 'Phase 10 August Project',
            'nature_of_work' => 'Environmental clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Consortium',
            'province_id' => $this->albay->id,
            'municipality_id' => $this->legazpi->id,
            'barangay_id' => $this->legazpiBarangay->id,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Barangay Legazpi',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 12,
            'wage_rate' => '455.00',
            'wages_total' => '1000.00',
            'ppe_total' => '100.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 20,
            'insurance_total' => '50.00',
            'total_project_cost' => '1150.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'intervention_focus' => ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION,
            'created_by' => $this->tc->id,
        ]);
        ProjectApproval::create([
            'project_id' => $august->id,
            'approval_date' => '2026-08-16',
            'project_code' => 'PH10-AUG-001',
            'approved_by' => $this->tc->id,
            'approved_at' => now(),
        ]);

        $albayLocation = ProjectLocation::create([
            'project_id' => $august->id,
            'province_id' => $this->albay->id,
            'municipality_id' => $this->legazpi->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $albayLocation->barangays()->sync([
            $this->legazpiBarangay->id => [
                'beneficiaries_total' => 12,
                'beneficiaries_female' => 8,
            ],
        ]);
        $camarinesLocation = ProjectLocation::create([
            'project_id' => $august->id,
            'province_id' => $this->camarinesSur->id,
            'municipality_id' => $this->naga->id,
            'district' => '3rd District',
            'sort_order' => 2,
        ]);
        $camarinesLocation->barangays()->sync([
            $this->nagaBarangay->id => [
                'beneficiaries_total' => 8,
                'beneficiaries_female' => 4,
            ],
        ]);

        ProjectBeneficiarySector::create([
            'project_id' => $august->id,
            'sector_group' => BeneficiarySectorCategory::YOUTH->group(),
            'sector_key' => BeneficiarySectorCategory::YOUTH,
            'beneficiaries_total' => 15,
            'beneficiaries_female' => 9,
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);
        ProjectBeneficiarySector::create([
            'project_id' => $august->id,
            'sector_group' => BeneficiarySectorCategory::VENDORS->group(),
            'sector_key' => BeneficiarySectorCategory::VENDORS,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);
        ProjectLaborMarketReferral::create([
            'project_id' => $august->id,
            'reporting_month' => '2026-08-01',
            'program' => LaborMarketProgram::SKILLS_TRAINING,
            'interested_referred_total' => 5,
            'interested_referred_female' => 3,
            'provided_intervention_total' => 4,
            'provided_intervention_female' => 2,
            'amount_released' => '7500.00',
            'services_availed' => 'Skills Training',
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);
        $augustObligation = ProjectObligation::create([
            'project_id' => $august->id,
            'tranche_number' => 1,
            'adl_number' => $adl->adl_number,
            'fund_sponsor' => $august->fund_sponsor,
            'partner' => $august->partner,
            'project_location' => 'Bicol Region',
            'term' => $august->term->label(),
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 12,
            'amount' => '1000.00',
            'obligation_date' => '2026-08-20',
            'month' => '2026-08',
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->focal->id,
        ]);
        ProjectDisbursement::create([
            'project_obligation_id' => $augustObligation->id,
            'amount' => '600.00',
            'date_disbursed' => '2026-08-25',
            'ldap_check_number' => 'LDAP-PH10-AUG',
            'recorded_by' => $this->focal->id,
        ]);

        $september = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-09-10',
            'project_title' => 'Phase 10 September Project',
            'nature_of_work' => 'Administrative support',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Consortium',
            'province_id' => $this->albay->id,
            'municipality_id' => $this->legazpi->id,
            'barangay_id' => $this->legazpiBarangay->id,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Barangay Legazpi',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 40,
            'term' => 'long_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wage_rate' => '455.00',
            'wages_total' => '400.00',
            'ppe_total' => '50.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '50.00',
            'total_project_cost' => '500.00',
            'status' => ProjectStatus::COMPLETED,
            'intervention_focus' => ProjectInterventionFocus::ADMINISTRATIVE_CLERICAL_AND_LOGISTICAL_SUPPORT,
            'created_by' => $this->tc->id,
        ]);
        ProjectApproval::create([
            'project_id' => $september->id,
            'approval_date' => '2026-09-11',
            'project_code' => 'PH10-SEP-001',
            'approved_by' => $this->tc->id,
            'approved_at' => now(),
        ]);
        $septemberLocation = ProjectLocation::create([
            'project_id' => $september->id,
            'province_id' => $this->albay->id,
            'municipality_id' => $this->legazpi->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $septemberLocation->barangays()->sync([
            $this->legazpiBarangay->id => [
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 5,
            ],
        ]);
        ProjectLaborMarketReferral::create([
            'project_id' => $september->id,
            'reporting_month' => '2026-09-01',
            'program' => LaborMarketProgram::EMPLOYMENT_FACILITATION_SERVICES,
            'interested_referred_total' => 3,
            'interested_referred_female' => 2,
            'provided_intervention_total' => 2,
            'provided_intervention_female' => 1,
            'amount_released' => '2000.00',
            'services_availed' => 'Employment Facilitation',
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);
        $septemberObligation = ProjectObligation::create([
            'project_id' => $september->id,
            'tranche_number' => 1,
            'adl_number' => $adl->adl_number,
            'fund_sponsor' => $september->fund_sponsor,
            'partner' => $september->partner,
            'project_location' => 'Legazpi City, Albay',
            'term' => $september->term->label(),
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'amount' => '400.00',
            'obligation_date' => '2026-09-15',
            'month' => '2026-09',
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->focal->id,
        ]);
        ProjectDisbursement::create([
            'project_obligation_id' => $septemberObligation->id,
            'amount' => '400.00',
            'date_disbursed' => '2026-09-20',
            'ldap_check_number' => 'LDAP-PH10-SEP',
            'recorded_by' => $this->focal->id,
        ]);
    }
}
