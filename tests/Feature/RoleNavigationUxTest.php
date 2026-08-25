<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleNavigationUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_sidebar_exposes_fund_monitoring_and_payment_navigation(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee('Fund Management');
        $response->assertSee('Monitoring');
        $response->assertSee('Payment of Wages');
        $response->assertSee('Reporting');
    }

    public function test_tc_sidebar_exposes_project_workflow_navigation(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee('Project Management');
        $response->assertSee('Project Workflow');
        $response->assertSee('TSSD Evaluation');
        $response->assertSee('For Approval');
        $response->assertSee('Implementation');
        $response->assertSee('Post-Documents');
        $response->assertSee('Release of Assistance');
    }

    public function test_layout_has_no_dead_notifications_control(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertDontSee(
            'Notifications are not yet enabled'
        );

        $response->assertSee(
            'Current Workspace'
        );
    }
}
