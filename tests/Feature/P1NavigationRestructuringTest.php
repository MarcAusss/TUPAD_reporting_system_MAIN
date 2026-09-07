<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1NavigationRestructuringTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_sidebar_groups_fund_acp_and_reports_without_legacy_monitoring_link(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this->actingAs($focal)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Project Registry');
        $response->assertSee('Funds & Payments', false);
        $response->assertSee('Fund Monitoring');
        $response->assertSee('PER ADL (Current)');
        $response->assertSee('Regional Summary');
        $response->assertSee('Per Province');
        $response->assertSee('Through ACP');
        $response->assertSee('Reports');
        $response->assertSee('User Accounts');
        $response->assertDontSee('Legacy Summary');
        $response->assertDontSee('Workflow Queues');
    }

    public function test_tc_sidebar_groups_workflow_and_acp_without_financial_administration_actions(): void
    {
        $province = Province::create([
            'code' => '052000000',
            'name' => 'Catanduanes',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'assigned_province_id' => $province->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($tc)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Project Operations');
        $response->assertSee('Project Registry');
        $response->assertSee('Provincial Summary');
        $response->assertSee('Workflow Queues');
        $response->assertSee('TSSD Evaluation');
        $response->assertSee('Post-Documents');
        $response->assertSee('Through ACP');
        $response->assertSee('ACP Implementation');
        $response->assertSee('Reports');
        $response->assertDontSee('ADL Management');
        $response->assertDontSee('Payment of Wages');
        $response->assertDontSee('ACP Payment');
        $response->assertDontSee('User Accounts');
    }

    public function test_admin_sidebar_keeps_financial_acp_reporting_and_user_administration_access(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Project Operations');
        $response->assertSee('ADL Management');
        $response->assertSee('Workflow Queues');
        $response->assertSee('Through ACP');
        $response->assertSee('ACP Implementation');
        $response->assertSee('ACP Payment');
        $response->assertSee('Check Release');
        $response->assertSee('Liquidation');
        $response->assertSee('Payment of Wages');
        $response->assertSee('Executive Dashboard');
        $response->assertSee('Reports');
        $response->assertSee('User Accounts');
    }
    public function test_navigation_source_uses_collapsible_groups_and_role_partials(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $projectOperations = file_get_contents(resource_path('views/layouts/partials/sidebar-project-operations.blade.php'));
        $focal = file_get_contents(resource_path('views/layouts/partials/sidebar-focal.blade.php'));
        $reports = file_get_contents(resource_path('views/layouts/partials/report-navigation.blade.php'));

        $this->assertStringContainsString("@include('layouts.partials.sidebar-focal')", $layout);
        $this->assertStringContainsString("@include('layouts.partials.sidebar-project-operations')", $layout);
        $this->assertStringNotContainsString("sidebar-gip", $layout);
        $this->assertStringContainsString('group/workflow-nav', $projectOperations);
        $this->assertStringContainsString('group/acp-operations', $projectOperations);
        $this->assertStringContainsString('group/fund-monitoring', $focal);
        $this->assertStringContainsString('group/acp-nav', $focal);
        $this->assertStringContainsString('group/report-nav', $reports);
        $this->assertStringContainsString('@if ($workflowOpen) open @endif', $projectOperations);
        $this->assertStringContainsString('@if ($reportsOpen) open @endif', $reports);
    }
}
