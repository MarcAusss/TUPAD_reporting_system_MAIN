<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_dashboard_shows_fund_and_payment_shortcuts(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee(
            'Focal Fund Monitoring Dashboard'
        );

        $response->assertSee(
            'Focal Work Queue'
        );

        $response->assertSee(
            'Payment of Wages / obligation'
        );

        $response->assertDontSee(
            'individually registered'
        );
    }

    public function test_tc_dashboard_exposes_workflow_queues(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee(
            'TUPAD Coordinator Dashboard'
        );

        $response->assertSee(
            'Project Workflow'
        );

        $response->assertSee(
            'TSSD Evaluation'
        );

        $response->assertSee(
            'Release of Assistance'
        );
    }

    public function test_gip_dashboard_is_draft_focused(): void
    {
        $gip = User::factory()->create([
            'role' => UserRole::GIP,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($gip)
            ->get(route('dashboard'));

        $response->assertOk();

        $response->assertSee(
            'GIP Workspace'
        );

        $response->assertSee(
            'Recent Project Drafts'
        );

        $response->assertSee(
            'Recommended Workflow'
        );
    }
}
