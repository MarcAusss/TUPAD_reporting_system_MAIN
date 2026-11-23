<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FocalDashboardLayoutPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_dashboard_uses_compact_operational_strip_and_table_queue_instead_of_card_wall(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-focal-operations-overview', false)
            ->assertSee('data-focal-fund-position', false)
            ->assertDontSee('data-focal-work-queue', false)
            ->assertDontSee('Focal Work Queue')
            ->assertSee('Fund Position')
            ->assertSee('data-beneficiary-distribution', false)
            ->assertDontSee('Project Cost Trend')
            ->assertDontSee('Fund Utilization per ADL')
            ->assertDontSee('Projects currently waiting in your role-specific queues.')
            ->assertDontSee('tupad-metric-card', false);
    }

    public function test_focal_dashboard_overview_has_one_status_chart_in_system_colours(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this->actingAs($focal)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-focal-overview', false)
            ->assertSee('data-focal-status-chart', false)
            ->assertSee('Projects by Status')
            ->assertSee('data-focal-beneficiaries', false)
            ->assertSee('data-focal-implementation', false)
            ->assertSee('data-focal-financial-overview', false)
            ->assertSee('data-focal-recent-activity', false)
            ->assertDontSee('Projects Overview');

        // Legend dots use the workflow-phase colours of the status pills.
        $response->assertSee(\App\Services\Dashboards\FocalDashboardOverviewService::STATUS_COLORS['tssd_evaluation'], false)
            ->assertSee(\App\Services\Dashboards\FocalDashboardOverviewService::STATUS_COLORS['completed'], false);
    }

    public function test_tc_dashboard_uses_the_focal_layout_for_their_province(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $this->actingAs($tc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Project Workflow')
            ->assertSee('data-focal-operations-overview', false)
            ->assertSee('data-beneficiary-distribution', false)
            ->assertSee('data-tc-distributions', false)
            ->assertSee('Beneficiary Distribution —')
            ->assertSee('Project Distribution —')
            ->assertSee('id="tcProjectDistributionData"', false)
            ->assertSee('data-dashboard-report-charts', false)
            ->assertSee('CQPR · Projects per Municipality')
            ->assertDontSee('data-focal-work-queue', false)
            ->assertDontSee('Bicol Region by Report');
    }

    public function test_focal_dashboard_shows_a_pie_chart_per_report(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this->actingAs($focal)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bicol Region by Report')
            ->assertSee('CQPR · Projects per Province')
            ->assertSee('SPRS · Beneficiaries per Province');

        foreach (['cqpr', 'sprs', 'status', 'implementation_mode', 'labor_market', 'gpai'] as $chart) {
            $response->assertSee('data-report-chart="'.$chart.'"', false);
        }
    }
}
