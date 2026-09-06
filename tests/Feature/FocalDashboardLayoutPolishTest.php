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
            ->assertSee('data-focal-work-queue', false)
            ->assertSee('Operational Overview')
            ->assertSee('Fund Position')
            ->assertSee('Focal Work Queue')
            ->assertSee('Financial action')
            ->assertSee('Geographic Report Analytics')
            ->assertDontSee('Projects currently waiting in your role-specific queues.')
            ->assertDontSee('tupad-metric-card', false);
    }

    public function test_tc_dashboard_keeps_existing_role_specific_layout(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $this->actingAs($tc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Project Workflow')
            ->assertSee('tupad-metric-card', false)
            ->assertDontSee('data-focal-operations-overview', false)
            ->assertDontSee('data-focal-work-queue', false);
    }
}
