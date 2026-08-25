<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiLocationProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_can_create_one_project_across_multiple_districts_of_same_province(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $albay = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $tabaco = Municipality::create([
            'province_id' => $albay->id,
            'name' => 'Tabaco City',
            'district' => '1st District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $legazpi = Municipality::create([
            'province_id' => $albay->id,
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $tabacoA = Barangay::create([
            'municipality_id' => $tabaco->id,
            'name' => 'Barangay A',
            'is_active' => true,
        ]);

        $tabacoB = Barangay::create([
            'municipality_id' => $tabaco->id,
            'name' => 'Barangay B',
            'is_active' => true,
        ]);

        $rawis = Barangay::create([
            'municipality_id' => $legazpi->id,
            'name' => 'Rawis',
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R14-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => null,
            'partner' => null,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        $response = $this
            ->actingAs($tc)
            ->post(route('projects.store'), [
                'adl_allocation_id' => $allocation->id,
                'date_received' => now()->toDateString(),
                'project_title' => 'Albay Multi-District Project',
                'nature_of_work' => 'Community clean-up',
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Partners',
                'project_series' => 'Regular TUPAD 2026',
                'tevs_date_verified' => now()->toDateString(),

                'province_id' => $albay->id,

                'project_locations' => [
                    [
                        'district' => '1st District',
                        'municipality_id' => $tabaco->id,
                        'barangay_ids' => [
                            $tabacoA->id,
                            $tabacoB->id,
                        ],
                    ],
                    [
                        'district' => '2nd District',
                        'municipality_id' => $legazpi->id,
                        'barangay_ids' => [
                            $rawis->id,
                        ],
                    ],
                ],

                'implementation_mode' =>
                    ImplementationMode::DIRECT_ADMINISTRATION->value,

                'number_of_days' => 20,
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 5,
                'wage_rate' => 455,
                'insurance_rate' => 50,
                'ppe_items' => [],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'project_title' => 'Albay Multi-District Project',
            'province_id' => $albay->id,

            // First location is retained as compatibility snapshot.
            'municipality_id' => $tabaco->id,
            'barangay_id' => $tabacoA->id,
        ]);

        $this->assertDatabaseCount(
            'project_locations',
            2
        );

        $this->assertDatabaseCount(
            'project_location_barangay',
            3
        );

        $this->assertDatabaseHas(
            'project_locations',
            [
                'municipality_id' => $tabaco->id,
                'district' => '1st District',
            ]
        );

        $this->assertDatabaseHas(
            'project_locations',
            [
                'municipality_id' => $legazpi->id,
                'district' => '2nd District',
            ]
        );
    }

    public function test_municipality_from_another_province_is_rejected(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $albay = Province::create([
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $sorsogon = Province::create([
            'name' => 'Sorsogon',
            'is_active' => true,
        ]);

        $sorsogonCity = Municipality::create([
            'province_id' => $sorsogon->id,
            'name' => 'Sorsogon City',
            'district' => '1st District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $sorsogonCity->id,
            'name' => 'Abuyog',
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R14-002',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => null,
            'partner' => null,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        $response = $this
            ->actingAs($tc)
            ->post(route('projects.store'), [
                'adl_allocation_id' => $allocation->id,
                'date_received' => now()->toDateString(),
                'project_title' => 'Invalid Cross Province Project',
                'nature_of_work' => 'Community clean-up',
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU',
                'project_series' => 'Regular TUPAD 2026',
                'tevs_date_verified' => now()->toDateString(),
                'province_id' => $albay->id,
                'project_locations' => [[
                    'district' => '1st District',
                    'municipality_id' => $sorsogonCity->id,
                    'barangay_ids' => [$barangay->id],
                ]],
                'implementation_mode' =>
                    ImplementationMode::DIRECT_ADMINISTRATION->value,
                'number_of_days' => 20,
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 5,
                'wage_rate' => 455,
                'insurance_rate' => 50,
                'ppe_items' => [],
            ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('projects', [
            'project_title' => 'Invalid Cross Province Project',
        ]);
    }

    public function test_location_api_filters_district_and_municipality_by_province(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $albay = Province::create([
            'name' => 'Albay',
            'is_active' => true,
        ]);

        Municipality::create([
            'province_id' => $albay->id,
            'name' => 'Tabaco City',
            'district' => '1st District',
            'is_city' => true,
            'is_active' => true,
        ]);

        Municipality::create([
            'province_id' => $albay->id,
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $this
            ->actingAs($tc)
            ->getJson(
                route(
                    'locations.districts',
                    $albay
                )
            )
            ->assertOk()
            ->assertJson([
                '1st District',
                '2nd District',
            ]);

        $this
            ->actingAs($tc)
            ->getJson(
                route(
                    'locations.municipalities',
                    [
                        'province' => $albay,
                        'district' => '1st District',
                    ]
                )
            )
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'name' => 'Tabaco City',
            ])
            ->assertJsonMissing([
                'name' => 'Legazpi City',
            ]);
    }
}
