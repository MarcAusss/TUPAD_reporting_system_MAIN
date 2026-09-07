<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase14BPhysicalFinancialAccomplishmentReportsTest extends TestCase
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

    public function test_workspace_exposes_four_table_first_accomplishment_views_and_existing_export_actions(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial'))
            ->assertOk()
            ->assertSee('Overall Accomplishment')
            ->assertSee('Accomplishment per Semester')
            ->assertSee('Accomplishment per Quarter')
            ->assertSee('Accomplishment per Month')
            ->assertDontSee('Short-Term Accomplishment')
            ->assertDontSee('Long-Term Accomplishment')
            ->assertSee('Detailed Generator')
            ->assertSee('Print')
            ->assertSee('PDF')
            ->assertSee('Excel')
            ->assertSee('CSV');

        $response->assertSee(route('reports.print', [
            'report_type' => 'physical_financial',
            'group_by' => 'overall',
        ]));
    }

    public function test_quarter_and_month_views_use_current_fiscal_year_by_default_and_existing_reporting_dimensions(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 12:00:00');

        $this->project($this->masbate, [
            'date_received' => '2026-01-15',
            'project_title' => 'Q1 MASBATE',
        ]);
        $this->project($this->masbate, [
            'date_received' => '2026-04-15',
            'project_title' => 'Q2 MASBATE',
        ]);
        $this->project($this->masbate, [
            'date_received' => '2025-08-15',
            'project_title' => 'OLD MASBATE',
        ]);

        $quarter = $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial', ['view' => 'quarter']))
            ->assertOk()
            ->assertSee('1st Quarter')
            ->assertSee('2nd Quarter');

        $this->assertSame(2026, $quarter->viewData('filters')['fiscal_year']);
        $this->assertSame('quarter', $quarter->viewData('report')['dimension']->value);
        $this->assertSame(2, (int) $quarter->viewData('matrixTotal')['project_count']);

        $month = $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial', ['view' => 'month']))
            ->assertOk()
            ->assertSee('January')
            ->assertSee('April');

        $this->assertSame('month', $month->viewData('report')['dimension']->value);
        $this->assertSame(2, (int) $month->viewData('matrixTotal')['project_count']);

        CarbonImmutable::setTestNow();
    }

    public function test_semester_view_uses_authoritative_period_matrix_and_ignores_removed_term_filters(): void
    {
        $this->project($this->masbate, [
            'project_title' => 'FIRST SEMESTER PROJECT',
            'date_received' => '2026-02-15',
            'status' => ProjectStatus::COMPLETED,
            'beneficiaries_total' => 10,
        ]);
        $this->project($this->masbate, [
            'project_title' => 'SECOND SEMESTER PROJECT',
            'date_received' => '2026-08-15',
            'status' => ProjectStatus::COMPLETED,
            'beneficiaries_total' => 20,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial', [
                'view' => 'semester',
                'fiscal_year' => 2026,
                'term' => ProjectTerm::LONG_TERM->value,
            ]))
            ->assertOk()
            ->assertSee('1st Semester')
            ->assertSee('2nd Semester')
            ->assertDontSee('Short-Term Accomplishment')
            ->assertDontSee('Long-Term Accomplishment');

        $this->assertArrayNotHasKey('term', $response->viewData('filters'));
        $this->assertArrayNotHasKey('term', $response->viewData('exportQuery'));
        $this->assertSame(10, $response->viewData('matrixTotal')['periods']['semester-1']['physical']);
        $this->assertSame(20, $response->viewData('matrixTotal')['periods']['semester-2']['physical']);
    }

    public function test_coordinator_workspace_is_forced_to_assigned_province_and_foreign_filter_is_denied(): void
    {
        $this->project($this->masbate, ['project_title' => 'MASBATE DATA']);
        $this->project($this->albay, ['project_title' => 'ALBAY DATA']);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $response = $this->actingAs($tc)
            ->get(route('reports.workspace.physical-financial'))
            ->assertOk();

        $this->assertSame($this->masbate->id, (int) $response->viewData('filters')['province_id']);
        $this->assertSame(1, (int) $response->viewData('matrixTotal')['project_count']);
        $this->assertTrue($response->viewData('provinceLocked'));

        $this->actingAs($tc)
            ->get(route('reports.workspace.physical-financial', [
                'province_id' => $this->albay->id,
            ]))
            ->assertForbidden();
    }

    public function test_cross_mode_status_combinations_are_rejected_in_accomplishment_workspace(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial', [
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION->value,
                'status' => ProjectStatus::FOR_LIQUIDATION->value,
            ]))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial', [
                'implementation_mode' => ImplementationMode::THROUGH_ACP->value,
                'status' => ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value,
            ]))
            ->assertSessionHasErrors('status');
    }

    public function test_screen_uses_official_target_accomplishment_and_balance_matrix_without_invented_ratios(): void
    {
        $this->project($this->masbate, [
            'status' => ProjectStatus::COMPLETED,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
        ]);
        $this->project($this->masbate, [
            'status' => ProjectStatus::APPROVED,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.physical-financial'))
            ->assertOk();

        $matrix = $response->viewData('matrixTotal');
        $this->assertSame(20, $matrix['target']['physical']);
        $this->assertSame(10, $matrix['accomplishment']['physical']);
        $this->assertSame(10, $matrix['balance']['physical']);
        $this->assertArrayNotHasKey('ratios', $response->viewData());
        $response
            ->assertSee('Reporting basis:')
            ->assertDontSee('Short-Term Accomplishment')
            ->assertDontSee('Long-Term Accomplishment');
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
            'adl_number' => 'ADL-P14B-'.$province->id.'-'.uniqid(),
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
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
            'amount' => '1000000.00',
            'grant_amount' => '1000000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 14B Project',
            'nature_of_work' => 'Physical and financial accomplishment report test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'barangay' => 'Sample Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wage_rate' => '500.00',
            'wages_total' => '5000.00',
            'ppe_total' => '500.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '500.00',
            'total_project_cost' => '6000.00',
            'status' => ProjectStatus::APPROVED,
            'created_by' => $this->admin->id,
        ], $overrides));
    }
}
