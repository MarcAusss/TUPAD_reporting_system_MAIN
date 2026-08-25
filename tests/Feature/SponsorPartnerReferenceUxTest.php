<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorPartnerReferenceUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_can_store_sponsor_and_partner_on_adl_allocation(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R14-1-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $this
            ->actingAs($focal)
            ->post(
                route(
                    'adl.allocations.store',
                    $adl
                ),
                [
                    'fund_sponsor' =>
                        'DOLE Regional Office V',

                    'partner' =>
                        'LGU Legazpi City',

                    'location' =>
                        'Albay',

                    'province' =>
                        'Albay',

                    'district' =>
                        '2nd District',

                    'municipality' =>
                        'Legazpi City',

                    'grant_amount' =>
                        500000,

                    'admin_cost_amount' =>
                        0,
                ]
            )
            ->assertRedirect(
                route(
                    'adl.show',
                    $adl
                )
            );

        $this->assertDatabaseHas(
            'adl_allocations',
            [
                'adl_id' => $adl->id,
                'fund_sponsor' =>
                    'DOLE Regional Office V',
                'partner' =>
                    'LGU Legazpi City',
            ]
        );
    }

    public function test_tc_project_create_shows_focal_reference_values_and_other_option(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R14-1-002',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' =>
                'DOLE Regional Office V',
            'partner' =>
                'LGU Albay',
            'location' =>
                'Albay',
            'amount' =>
                1000000,
            'created_by' =>
                $focal->id,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(
                route(
                    'projects.create'
                )
            );

        $response->assertOk();

        $response->assertSee(
            'DOLE Regional Office V'
        );

        $response->assertSee(
            'LGU Albay'
        );

        $response->assertSee(
            'Other — specify below'
        );
    }

    public function test_other_sponsor_and_partner_are_required_when_other_is_selected(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->post(
                route('projects.store'),
                [
                    'fund_sponsor' =>
                        '__other__',

                    'partner' =>
                        '__other__',
                ]
            );

        $response->assertSessionHasErrors([
            'fund_sponsor_other',
            'partner_other',
        ]);
    }
}
