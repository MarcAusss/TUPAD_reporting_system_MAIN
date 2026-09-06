<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalFundSummaryLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_regional_summary_uses_compact_monitoring_layout(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('fund-monitoring.summary-current'));

        $response->assertOk()
            ->assertSee('data-regional-fund-summary="true"', false)
            ->assertSee('Regional Fund Position')
            ->assertSee('Coverage Snapshot')
            ->assertSee('Cost Composition')
            ->assertSee('Beneficiary Delivery')
            ->assertSee('Workflow Exposure')
            ->assertSee('Open PER ADL Register')
            ->assertSee('data-workflow-exposure="true"', false);
    }

    public function test_regional_summary_template_has_no_legacy_broken_card_grid_markup(): void
    {
        $source = file_get_contents(resource_path('views/monitoring/summary.blade.php'));

        $this->assertStringNotContainsString('grid gap-4 sm:grid-cols-2 xl:grid-cols-4', $source);
        $this->assertStringContainsString('data-regional-fund-summary="true"', $source);
        $this->assertStringContainsString('Regional Fund Position', $source);
    }
}
