<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectLaborMarketReferral;
use App\Models\ProjectMonitoringDetail;
use App\Models\ProjectOrientation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MajorRevisionPhase14DMonthlyQuarterlyReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $masbate;
    private Province $albay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);
        $this->masbate = $this->province('Masbate', '054100000');
        $this->albay = $this->province('Albay', '050500000');
    }

    public function test_monthly_and_quarterly_routes_use_dedicated_reports_and_keep_security_middleware(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.workspace.monthly', [
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('Statistical Performance Reporting System (SPRS)')
            ->assertSee('List of Orientations Conducted')
            ->assertSee('project_monitoring_details.sprs_date');

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.quarterly', [
                'fiscal_year' => 2026,
                'quarter' => 3,
            ]))
            ->assertOk()
            ->assertSee('Consolidated Quarterly Progress Report (CQPR)')
            ->assertSee('Number of TUPAD Beneficiaries Referred to Active Labor Market')
            ->assertSee('project_monitoring_details.cqpr_date');

        foreach (['reports.workspace.monthly', 'reports.workspace.quarterly'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('province.scope', $route->gatherMiddleware());
            $this->assertContains('role:admin,tc,focal', $route->gatherMiddleware());
        }
    }

    public function test_orientation_program_coverage_is_explicitly_encoded_and_optional_for_legacy_compatibility(): void
    {
        $this->assertTrue(Schema::hasColumn('project_orientations', 'alkansssya_conducted'));
        $this->assertTrue(Schema::hasColumn('project_orientations', 'yakap_conducted'));

        $project = $this->project($this->masbate, [
            'status' => ProjectStatus::FOR_IMPLEMENTATION,
        ]);

        $this->actingAs($this->admin)
            ->post(route('projects.implementation.orientation', $project), [
                'orientation_date' => '2026-08-12',
                'alkansssya_conducted' => '1',
                'yakap_conducted' => '1',
                'remarks' => 'Both program topics were covered.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_orientations', [
            'project_id' => $project->id,
            'orientation_date' => '2026-08-12 00:00:00',
            'alkansssya_conducted' => 1,
            'yakap_conducted' => 1,
        ]);

        $legacy = $this->project($this->masbate, [
            'project_title' => 'Legacy Orientation',
            'status' => ProjectStatus::FOR_IMPLEMENTATION,
        ]);

        $this->actingAs($this->admin)
            ->post(route('projects.implementation.orientation', $legacy), [
                'orientation_date' => '2026-08-13',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_orientations', [
            'project_id' => $legacy->id,
            'alkansssya_conducted' => 0,
            'yakap_conducted' => 0,
        ]);
    }

    public function test_monthly_orientation_report_uses_orientation_date_and_never_infers_programs(): void
    {
        $august = $this->project($this->masbate, ['project_title' => 'August Orientation']);
        ProjectOrientation::query()->create([
            'project_id' => $august->id,
            'orientation_date' => '2026-08-09',
            'alkansssya_conducted' => true,
            'yakap_conducted' => false,
            'recorded_by' => $this->admin->id,
        ]);

        $legacy = $this->project($this->masbate, ['project_title' => 'Legacy Program Unspecified']);
        ProjectOrientation::query()->create([
            'project_id' => $legacy->id,
            'orientation_date' => '2026-08-10',
            'alkansssya_conducted' => false,
            'yakap_conducted' => false,
            'remarks' => 'YAKAP text in legacy remarks must not be parsed as structured coverage.',
            'recorded_by' => $this->admin->id,
        ]);

        $july = $this->project($this->masbate, ['project_title' => 'July Orientation']);
        ProjectOrientation::query()->create([
            'project_id' => $july->id,
            'orientation_date' => '2026-07-31',
            'yakap_conducted' => true,
            'recorded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.monthly', [
                'view' => 'orientations',
                'fiscal_year' => 2026,
                'month' => 8,
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('August Orientation')
            ->assertSee('Legacy Program Unspecified')
            ->assertDontSee('July Orientation')
            ->assertSee('AlkanSSSya')
            ->assertSee('Program coverage not specified');

        $counts = $response->viewData('orientationCounts');
        $this->assertSame(2, $counts['orientation_records']);
        $this->assertSame(1, $counts['alkansssya']);
        $this->assertSame(0, $counts['yakap']);
        $this->assertSame(1, $counts['program_unspecified']);
    }

    public function test_sprs_and_cqpr_cohorts_use_monitoring_dates_not_project_date_received(): void
    {
        $included = $this->project($this->masbate, [
            'project_title' => 'Monitoring Included',
            'date_received' => '2026-01-05',
        ]);
        ProjectMonitoringDetail::query()->create([
            'project_id' => $included->id,
            'sprs_date' => '2026-08-20',
            'cqpr_date' => '2026-09-15',
            'updated_by' => $this->admin->id,
        ]);

        $excluded = $this->project($this->masbate, [
            'project_title' => 'Monitoring Outside Period',
            'date_received' => '2026-08-01',
        ]);
        ProjectMonitoringDetail::query()->create([
            'project_id' => $excluded->id,
            'sprs_date' => '2026-07-31',
            'cqpr_date' => '2026-10-01',
            'updated_by' => $this->admin->id,
        ]);

        $sprs = $this->actingAs($this->admin)
            ->get(route('reports.workspace.monthly', [
                'view' => 'sprs',
                'fiscal_year' => 2026,
                'month' => 8,
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('Monitoring Included')
            ->assertDontSee('Monitoring Outside Period');

        $this->assertSame(1, $sprs->viewData('summary')['project_count']);

        $cqpr = $this->actingAs($this->admin)
            ->get(route('reports.workspace.quarterly', [
                'view' => 'cqpr',
                'fiscal_year' => 2026,
                'quarter' => 3,
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('Monitoring Included')
            ->assertDontSee('Monitoring Outside Period');

        $this->assertSame(1, $cqpr->viewData('summary')['project_count']);
    }

    public function test_quarterly_active_labor_market_report_uses_referral_reporting_month_and_existing_export_engine(): void
    {
        $project = $this->project($this->masbate);

        ProjectLaborMarketReferral::query()->create([
            'project_id' => $project->id,
            'reporting_month' => '2026-08-01',
            'program' => LaborMarketProgram::SKILLS_TRAINING,
            'interested_referred_total' => 12,
            'interested_referred_female' => 7,
            'provided_intervention_total' => 8,
            'provided_intervention_female' => 5,
            'amount_released' => '25000.00',
            'services_availed' => 'Skills assessment',
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ProjectLaborMarketReferral::query()->create([
            'project_id' => $project->id,
            'reporting_month' => '2026-10-01',
            'program' => LaborMarketProgram::SKILLS_TRAINING,
            'interested_referred_total' => 99,
            'interested_referred_female' => 50,
            'provided_intervention_total' => 90,
            'provided_intervention_female' => 45,
            'amount_released' => '99999.00',
            'services_availed' => 'Skills assessment outside selected quarter',
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.quarterly', [
                'view' => 'labor-market',
                'fiscal_year' => 2026,
                'quarter' => 3,
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('Skills Training')
            ->assertSee('Excel')
            ->assertSee('CSV')
            ->assertSee('project_labor_market_referrals.reporting_month');

        $overall = $response->viewData('laborOverall');
        $this->assertSame(12, (int) $overall['interested_referred_total']);
        $this->assertSame(7, (int) $overall['interested_referred_female']);
        $this->assertSame(8, (int) $overall['provided_intervention_total']);
        $this->assertSame(2500000, (int) $overall['amount_released_cents']);

        $exportQuery = $response->viewData('exportQuery');
        $this->assertSame('labor_market_referrals', $exportQuery['report_type']);
        $this->assertSame('labor_market_program', $exportQuery['group_by']);
        $this->assertSame(3, (int) $exportQuery['quarter']);
    }

    public function test_coordinator_monthly_and_quarterly_reports_are_forced_to_assigned_province(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
            'password' => Hash::make('secure-password'),
        ]);

        $masbate = $this->project($this->masbate, ['project_title' => 'Masbate SPRS']);
        ProjectMonitoringDetail::query()->create([
            'project_id' => $masbate->id,
            'sprs_date' => '2026-08-20',
            'cqpr_date' => '2026-08-21',
            'updated_by' => $this->admin->id,
        ]);

        $albay = $this->project($this->albay, ['project_title' => 'Albay SPRS']);
        ProjectMonitoringDetail::query()->create([
            'project_id' => $albay->id,
            'sprs_date' => '2026-08-20',
            'cqpr_date' => '2026-08-21',
            'updated_by' => $this->admin->id,
        ]);

        $monthly = $this->actingAs($tc)
            ->get(route('reports.workspace.monthly', [
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('Masbate SPRS')
            ->assertDontSee('Albay SPRS');

        $this->assertSame($this->masbate->id, (int) $monthly->viewData('filters')['province_id']);

        $this->actingAs($tc)
            ->get(route('reports.workspace.quarterly', [
                'fiscal_year' => 2026,
                'quarter' => 3,
                'province_id' => $this->albay->id,
            ]))
            ->assertForbidden();
    }

    public function test_cross_mode_status_validation_is_preserved_in_periodic_reports(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.workspace.monthly', [
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION->value,
                'status' => ProjectStatus::FOR_LIQUIDATION->value,
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.quarterly', [
                'implementation_mode' => ImplementationMode::THROUGH_ACP->value,
                'status' => ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value,
                'fiscal_year' => 2026,
                'quarter' => 3,
            ]))
            ->assertSessionHasErrors('status');
    }

    private function province(string $name, string $code): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function project(Province $province, array $overrides = []): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P14D-'.$province->id.'-'.uniqid(),
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'location' => $province->name,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 14D Project '.uniqid(),
            'nature_of_work' => 'Phase 14D reporting test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'barangay' => 'Sample Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => '500.00',
            'wages_total' => '50000.00',
            'ppe_total' => '5000.00',
            'insurance_rate' => '100.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '1000.00',
            'total_project_cost' => '56000.00',
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }
}
