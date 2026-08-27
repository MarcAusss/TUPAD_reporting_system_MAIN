<?php

namespace Tests\Feature;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
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
use App\Services\Exports\PdfTableWriter;
use App\Services\Exports\XlsxTableWriter;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase9ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tc;
    private User $focal;
    private User $gip;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user(UserRole::ADMIN);
        $this->tc = $this->user(UserRole::TC);
        $this->focal = $this->user(UserRole::FOCAL);
        $this->gip = $this->user(UserRole::GIP);
        $this->createReportingData();
    }

    public function test_admin_tc_and_focal_can_generate_reports_but_gip_cannot(): void
    {
        foreach ([$this->admin, $this->tc, $this->focal] as $user) {
            $this->actingAs($user)
                ->get(route('reports.index'))
                ->assertOk()
                ->assertSee('Report Generation')
                ->assertSee('Physical and Financial Accomplishment');
        }

        foreach ([
            'reports.index',
            'reports.print',
            'reports.export.pdf',
            'reports.export.excel',
            'reports.export.csv',
        ] as $route) {
            $this->actingAs($this->gip)
                ->get(route($route))
                ->assertForbidden();
        }
    }

    public function test_all_phase_nine_report_families_render_from_the_phase_eight_data_layer(): void
    {
        $expectations = [
            [ReportType::PHYSICAL_FINANCIAL, ReportDimension::PROJECT_CODE, 'ALB-P9-001'],
            [ReportType::FUND_STATUS, ReportDimension::ADL, 'ADL-PHASE9-001'],
            [ReportType::GEOGRAPHIC_BENEFICIARIES, ReportDimension::BARANGAY, 'Barangay Mabuhay'],
            [ReportType::BENEFICIARY_SECTORS, ReportDimension::SECTOR, 'Youth'],
            [ReportType::INTERVENTION_FOCUS, ReportDimension::INTERVENTION_FOCUS, 'Environmental Conservation'],
            [ReportType::LABOR_MARKET_REFERRALS, ReportDimension::LABOR_MARKET_PROGRAM, 'Skills Training'],
        ];

        foreach ($expectations as [$type, $dimension, $expected]) {
            $this->actingAs($this->admin)
                ->get(route('reports.index', [
                    'report_type' => $type->value,
                    'group_by' => $dimension->value,
                    'fiscal_year' => 2026,
                ]))
                ->assertOk()
                ->assertSee($type->label())
                ->assertSee($expected);
        }
    }

    public function test_report_parameters_are_server_validated(): void
    {
        $this->actingAs($this->admin)
            ->from(route('reports.index'))
            ->get(route('reports.index', [
                'report_type' => ReportType::GEOGRAPHIC_BENEFICIARIES->value,
                'group_by' => ReportDimension::ADL->value,
            ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('group_by');

        $this->actingAs($this->admin)
            ->from(route('reports.index'))
            ->get(route('reports.export.pdf', [
                'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
                'group_by' => ReportDimension::OVERALL->value,
                'quarter' => 3,
            ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('fiscal_year');

        $this->actingAs($this->admin)
            ->from(route('reports.index'))
            ->get(route('reports.index', [
                'report_type' => 'not-a-report',
                'group_by' => 'overall',
            ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('report_type');
    }

    public function test_print_pdf_and_csv_use_the_same_report_and_filters(): void
    {
        $query = [
            'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
            'group_by' => ReportDimension::PROJECT_CODE->value,
            'project_code' => 'ALB-P9-001',
        ];

        $this->actingAs($this->focal)
            ->get(route('reports.print', $query))
            ->assertOk()
            ->assertSee('ALB-P9-001')
            ->assertSee('Physical and Financial Accomplishment');

        $pdf = $this->actingAs($this->focal)
            ->get(route('reports.export.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('ALB-P9-001', $pdf->getContent());

        $csv = $this->actingAs($this->focal)
            ->get(route('reports.export.csv', $query))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csv->streamedContent();
        $this->assertStringContainsString('Project Code', $csvContent);
        $this->assertStringContainsString('ALB-P9-001', $csvContent);
    }

    public function test_excel_writer_creates_a_valid_dependency_free_xlsx_container(): void
    {
        $report = app(ReportGenerationService::class)->generate(
            ReportType::FUND_STATUS,
            ReportDimension::ADL,
            new ReportFilters(),
        );
        $path = app(XlsxTableWriter::class)->write($report);

        try {
            $contents = file_get_contents($path);
            $this->assertIsString($contents);
            $this->assertStringStartsWith("PK\x03\x04", $contents);
            $this->assertStringContainsString('[Content_Types].xml', $contents);
            $this->assertStringContainsString('xl/worksheets/sheet1.xml', $contents);
            $this->assertStringContainsString('ADL-PHASE9-001', $contents);
        } finally {
            @unlink($path);
        }

        $this->actingAs($this->admin)
            ->get(route('reports.export.excel', [
                'report_type' => ReportType::FUND_STATUS->value,
                'group_by' => ReportDimension::ADL->value,
            ]))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_fine_geographic_exports_do_not_fabricate_financial_allocations(): void
    {
        $report = app(ReportGenerationService::class)->generate(
            ReportType::PHYSICAL_FINANCIAL,
            ReportDimension::BARANGAY,
            new ReportFilters(),
        );

        $this->assertSame(10, $report['rows']->sole()['beneficiaries_total']);
        $this->assertFalse(
            collect($report['columns'])->pluck('key')->contains('project_cost_cents')
        );
        $this->assertStringContainsString(
            'Financial amounts are intentionally omitted',
            $report['warning'],
        );

        $pdf = app(PdfTableWriter::class)->render($report);
        $this->assertStringContainsString('Barangay Mabuhay', $pdf);
        $this->assertStringNotContainsString('Project Cost', $pdf);
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
        $province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);
        $municipality = Municipality::create([
            'province_id' => $province->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);
        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'code' => '050501001',
            'name' => 'Barangay Mabuhay',
            'is_active' => true,
        ]);
        $adl = Adl::create([
            'adl_number' => 'ADL-PHASE9-001',
            'date_received' => '2026-01-10',
            'grants' => '5000.00',
            'admin_cost' => '0.00',
            'total' => '5000.00',
            'created_by' => $this->focal->id,
        ]);
        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi',
            'location' => 'Legazpi City, Albay',
            'province' => 'Albay',
            'amount' => '5000.00',
            'grant_amount' => '5000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '5000.00',
            'created_by' => $this->focal->id,
        ]);

        $this->project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-15',
            'project_title' => 'Phase 9 Report Project',
            'nature_of_work' => 'Environmental clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi',
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Barangay Mabuhay',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => '455.00',
            'wages_total' => '1000.00',
            'ppe_total' => '100.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '50.00',
            'total_project_cost' => '1150.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'intervention_focus' =>
                ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION,
            'created_by' => $this->tc->id,
        ]);

        ProjectApproval::create([
            'project_id' => $this->project->id,
            'approval_date' => '2026-08-16',
            'project_code' => 'ALB-P9-001',
            'approved_by' => $this->tc->id,
            'approved_at' => now(),
        ]);

        $location = ProjectLocation::create([
            'project_id' => $this->project->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $location->barangays()->sync([
            $barangay->id => [
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 6,
            ],
        ]);

        ProjectBeneficiarySector::create([
            'project_id' => $this->project->id,
            'sector_group' => BeneficiarySectorCategory::YOUTH->group(),
            'sector_key' => BeneficiarySectorCategory::YOUTH,
            'beneficiaries_total' => 7,
            'beneficiaries_female' => 4,
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);

        ProjectLaborMarketReferral::create([
            'project_id' => $this->project->id,
            'reporting_month' => '2026-08-01',
            'program' => LaborMarketProgram::SKILLS_TRAINING,
            'interested_referred_total' => 5,
            'interested_referred_female' => 3,
            'provided_intervention_total' => 4,
            'provided_intervention_female' => 2,
            'amount_released' => '7500.00',
            'services_availed' => 'Electrical Installation and Maintenance NC II',
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);

        $obligation = ProjectObligation::create([
            'project_id' => $this->project->id,
            'tranche_number' => 1,
            'adl_number' => $adl->adl_number,
            'fund_sponsor' => $this->project->fund_sponsor,
            'partner' => $this->project->partner,
            'project_location' => $this->project->full_location,
            'term' => $this->project->term->label(),
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'amount' => '1000.00',
            'obligation_date' => '2026-08-20',
            'month' => '2026-08',
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->focal->id,
        ]);
        ProjectDisbursement::create([
            'project_obligation_id' => $obligation->id,
            'amount' => '600.00',
            'date_disbursed' => '2026-08-25',
            'ldap_check_number' => 'LDAP-P9-001',
            'recorded_by' => $this->focal->id,
        ]);
    }
}
