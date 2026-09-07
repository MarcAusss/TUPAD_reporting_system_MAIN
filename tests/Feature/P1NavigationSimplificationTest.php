<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1NavigationSimplificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_navigation_groups_funds_monitoring_and_acp_without_removing_routes(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this->actingAs($focal)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Project Registry')
            ->assertSee('Provincial Summary')
            ->assertSee('Funds & Payments', false)
            ->assertSee('ADL Management')
            ->assertSee('Fund Monitoring')
            ->assertSee('PER ADL (Current)')
            ->assertSee('Payment of Wages')
            ->assertSee('Through ACP')
            ->assertSee('ACP Payment')
            ->assertSee('Check Release')
            ->assertSee('Liquidation')
            ->assertSee('Executive Dashboard')
            ->assertSee('Reports')
            ->assertSee('User Accounts');
    }

    public function test_tc_navigation_groups_direct_workflow_queues_and_keeps_financial_admin_actions_hidden(): void
    {
        $province = Province::query()->create([
            'name' => 'Catanduanes',
            'code' => '052000000',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $province->id,
        ]);

        $response = $this->actingAs($tc)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Project Operations')
            ->assertSee('Project Registry')
            ->assertSee('Provincial Summary')
            ->assertSee('Workflow Queues')
            ->assertSee('TSSD Evaluation')
            ->assertSee('For Compliance')
            ->assertSee('For Approval')
            ->assertSee('Implementation')
            ->assertSee('Post-Documents')
            ->assertSee('Through ACP')
            ->assertSee('ACP Implementation')
            ->assertDontSee('ACP Payment')
            ->assertDontSee('Check Release')
            ->assertDontSee('Liquidation')
            ->assertDontSee('Payment of Wages')
            ->assertDontSee('User Accounts');
    }

    public function test_admin_navigation_keeps_fund_financial_reporting_and_administration_access(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Project Operations')
            ->assertSee('ADL Management')
            ->assertSee('Workflow Queues')
            ->assertSee('Through ACP')
            ->assertSee('ACP Payment')
            ->assertSee('Check Release')
            ->assertSee('Liquidation')
            ->assertSee('Payment of Wages')
            ->assertSee('Executive Dashboard')
            ->assertSee('Reports')
            ->assertSee('User Accounts');
    }

    public function test_active_workflow_route_marks_workflow_group_open(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/partials/sidebar-project-operations.blade.php'));
        $focal = file_get_contents(resource_path('views/layouts/partials/sidebar-focal.blade.php'));
        $shell = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("request()->routeIs('project-workflow.*')", $layout);
        $this->assertStringContainsString('@if ($workflowOpen) open @endif', $layout);
        $this->assertStringContainsString('@if ($acpOpen) open @endif', $layout);
        $this->assertStringContainsString('@if ($fundMonitoringOpen) open @endif', $focal);
        $this->assertStringContainsString("@include('layouts.partials.sidebar-focal')", $shell);
        $this->assertStringContainsString("@include('layouts.partials.sidebar-project-operations')", $shell);
        $this->assertStringNotContainsString("sidebar-gip", $shell);
    }
}
