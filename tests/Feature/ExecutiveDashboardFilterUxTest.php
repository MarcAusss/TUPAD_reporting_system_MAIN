<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardFilterUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_dashboard_uses_progressive_disclosure_for_filters(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('executive-dashboard.index'));

        $response->assertOk()
            ->assertSee('Focus the executive view')
            ->assertSee('More Filters')
            ->assertSee('Apply View')
            ->assertSee('data-executive-quarter-input', false)
            ->assertSee('data-executive-quarter="1"', false)
            ->assertSee('Implementation Mode')
            ->assertSee('Detailed Geography')
            ->assertSee('Organization & Program');
    }

    public function test_advanced_filters_open_when_one_is_active(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('executive-dashboard.index', ['project_code' => 'TEST-CODE']))
            ->assertOk()
            ->assertSee('1 active')
            ->assertSee('data-executive-more-filters', false);
    }
}
