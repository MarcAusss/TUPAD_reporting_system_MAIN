<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveDashboardPageSimplificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_dashboard_uses_concise_information_hierarchy(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('executive-dashboard.index'))
            ->assertOk()
            ->assertSee('Executive Snapshot')
            ->assertSee('Regional performance at a glance')
            ->assertSee('Operational Pulse')
            ->assertSee('Program Position')
            ->assertSee('Fund Position')
            ->assertSee('Delivery Trend')
            ->assertSee('Geographic Delivery')
            ->assertSee('Project Mix')
            ->assertSee('Detailed Program Analytics')
            ->assertSee('data-executive-scope-panel', false)
            ->assertSee('data-executive-analytics', false)
            ->assertDontSee('Key Indicators')
            ->assertDontSee('Total versus Female Beneficiaries');
    }

    public function test_filters_and_secondary_analytics_use_progressive_disclosure(): void
    {
        $source = file_get_contents(resource_path('views/executive-dashboard/index.blade.php'));

        $this->assertStringContainsString('data-executive-scope-panel', $source);
        $this->assertStringContainsString('Focus the executive view', $source);
        $this->assertStringContainsString('More Filters', $source);
        $this->assertStringContainsString('data-executive-analytics', $source);
        $this->assertStringContainsString('Collapsed by default to keep the executive view concise.', $source);
        $this->assertStringNotContainsString('<details data-executive-analytics open', $source);
    }
}
