<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DenseMonitoringUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_per_adl_page_has_clear_filter_and_register_context(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('fund-monitoring.per-adl-current'));

        $response->assertOk();
        $response->assertSee('Current ADL Monitoring Register');
        $response->assertSee('ADL Filter');
        $response->assertSee('Horizontal scroll available');
    }

    public function test_per_province_page_has_dense_table_context(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('fund-monitoring.per-province-current'));

        $response->assertOk();
        $response->assertSee('Provincial Fund Position');
        $response->assertSee('Horizontal scroll available');
    }

    public function test_adl_detail_has_jump_navigation_for_fund_work(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R12-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('adl.show', $adl));

        $response->assertOk();
        $response->assertSee('Fund Position');
        $response->assertSee('PER ADL Breakdown');
        $response->assertSee('Fund Actions');
        $response->assertSee('Allocation Records');
    }
}
