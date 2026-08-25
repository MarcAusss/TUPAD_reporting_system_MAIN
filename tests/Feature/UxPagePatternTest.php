<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UxPagePatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_project_page_uses_clear_registry_and_primary_action(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('Official Projects');
        $response->assertSee('Project Registry');
        $response->assertSee('Add Project');
    }

    public function test_focal_adl_page_uses_clear_registry_and_primary_action(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('adl.index'));

        $response->assertOk();
        $response->assertSee('ADL Management');
        $response->assertSee('ADL Registry');
        $response->assertSee('Add ADL');
    }

    public function test_focal_payment_page_uses_payment_queue_language(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($focal)
            ->get(route('payments.index'));

        $response->assertOk();
        $response->assertSee('Payment of Wages Queue');
        $response->assertSee('Payment Processing');
    }
}
