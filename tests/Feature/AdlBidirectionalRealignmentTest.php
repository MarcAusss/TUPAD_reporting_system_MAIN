<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdlBidirectionalRealignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    private Adl $adl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal =
            User::factory()->create([
                'role' =>
                    UserRole::FOCAL,

                'is_active' =>
                    true,
            ]);

        $this->adl =
            Adl::create([
                'adl_number' =>
                    'ADL-REALIGN-001',

                'grants' =>
                    8000000,

                'admin_cost' =>
                    0,

                'total' =>
                    8000000,

                'created_by' =>
                    $this->focal->id,
            ]);
    }

    public function test_tupad_to_gip_reduces_adjusted_tupad_amount(): void
    {
        $this
            ->actingAs(
                $this->focal
            )
            ->post(
                route(
                    'adl.realignments.store',
                    $this->adl
                ),
                [
                    'direction' =>
                        'tupad_to_gip',

                    'amount' =>
                        1000000,

                    'realignment_date' =>
                        '2026-08-26',
                ]
            )
            ->assertRedirect(
                route(
                    'adl.show',
                    $this->adl
                )
            );

        $this->assertDatabaseHas(
            'adl_realignments',
            [
                'adl_id' =>
                    $this->adl->id,

                'direction' =>
                    'tupad_to_gip',

                'amount' =>
                    -1000000,
            ]
        );

        $this->assertSame(
            7000000.0,
            (float) $this
                ->adl
                ->fresh()
                ->adjusted_grants
        );
    }

    public function test_gip_to_tupad_increases_adjusted_tupad_amount(): void
    {
        $this
            ->actingAs(
                $this->focal
            )
            ->post(
                route(
                    'adl.realignments.store',
                    $this->adl
                ),
                [
                    'direction' =>
                        'gip_to_tupad',

                    'amount' =>
                        1000000,

                    'realignment_date' =>
                        '2026-08-26',
                ]
            )
            ->assertRedirect(
                route(
                    'adl.show',
                    $this->adl
                )
            );

        $this->assertDatabaseHas(
            'adl_realignments',
            [
                'adl_id' =>
                    $this->adl->id,

                'direction' =>
                    'gip_to_tupad',

                'amount' =>
                    1000000,
            ]
        );

        $this->assertSame(
            9000000.0,
            (float) $this
                ->adl
                ->fresh()
                ->adjusted_grants
        );
    }

    public function test_direction_is_required(): void
    {
        $this
            ->actingAs(
                $this->focal
            )
            ->post(
                route(
                    'adl.realignments.store',
                    $this->adl
                ),
                [
                    'amount' =>
                        1000000,

                    'realignment_date' =>
                        '2026-08-26',
                ]
            )
            ->assertSessionHasErrors([
                'direction',
            ]);

        $this->assertDatabaseCount(
            'adl_realignments',
            0
        );
    }

    public function test_tupad_to_gip_cannot_make_tupad_amount_negative(): void
    {
        $this
            ->actingAs(
                $this->focal
            )
            ->post(
                route(
                    'adl.realignments.store',
                    $this->adl
                ),
                [
                    'direction' =>
                        'tupad_to_gip',

                    'amount' =>
                        9000000,

                    'realignment_date' =>
                        '2026-08-26',
                ]
            )
            ->assertSessionHasErrors([
                'amount',
            ]);

        $this->assertDatabaseCount(
            'adl_realignments',
            0
        );
    }
}
