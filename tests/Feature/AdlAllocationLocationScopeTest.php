<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdlAllocationLocationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_display_is_generated_from_structured_allocation_scope(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-SCOPE-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $this->actingAs($focal)
            ->post(route('adl.allocations.store', $adl), [
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Legazpi City',
                'province' => 'Albay',
                'district' => '2nd District',
                'municipality' => 'Legazpi City',
                // A client-supplied display value must not control the stored scope.
                'location' => 'Wrong manual display',
                'grant_amount' => 250000,
                'admin_cost_amount' => 0,
            ])
            ->assertRedirect(route('adl.show', $adl));

        $this->assertDatabaseHas('adl_allocations', [
            'adl_id' => $adl->id,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'location' => 'Legazpi City, 2nd District, Albay',
        ]);
    }

    public function test_district_and_municipality_are_optional_for_province_wide_allocation(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-SCOPE-002',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $this->actingAs($focal)
            ->post(route('adl.allocations.store', $adl), [
                'province' => 'Catanduanes',
                'grant_amount' => 250000,
                'admin_cost_amount' => 0,
            ])
            ->assertRedirect(route('adl.show', $adl));

        $this->assertDatabaseHas('adl_allocations', [
            'adl_id' => $adl->id,
            'province' => 'Catanduanes',
            'district' => null,
            'municipality' => null,
            'location' => 'Catanduanes',
        ]);
    }

    public function test_province_is_required_for_allocation_scope(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-SCOPE-003',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $this->actingAs($focal)
            ->post(route('adl.allocations.store', $adl), [
                'grant_amount' => 250000,
                'admin_cost_amount' => 0,
            ])
            ->assertSessionHasErrors('province');
    }
}
