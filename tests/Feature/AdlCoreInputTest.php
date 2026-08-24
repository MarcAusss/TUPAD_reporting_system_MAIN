<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdlCoreInputTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::create([
            'name' => 'ADL Test Focal',
            'username' => 'adl-core-focal',
            'email' => 'adl-core-focal@example.test',
            'position' => 'TUPAD Focal',
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    public function test_total_automatically_equals_grants_and_admin_cost_remains_separate(): void
    {
        $response = $this
            ->actingAs($this->focal)
            ->post(route('adl.store'), [
                'adl_number' => 'ADL-CORE-001',
                'date_received' => '2026-08-24',
                'grants' => 1_000_000,
                'admin_cost' => 30_000,
            ]);

        $adl = Adl::query()
            ->where('adl_number', 'ADL-CORE-001')
            ->firstOrFail();

        $response->assertRedirect(route('adl.show', $adl));

        $this->assertSame('1000000.00', $adl->grants);
        $this->assertSame('30000.00', $adl->admin_cost);
        $this->assertSame('1000000.00', $adl->total);
    }

    public function test_manipulated_total_request_cannot_override_grants_based_total(): void
    {
        $this
            ->actingAs($this->focal)
            ->post(route('adl.store'), [
                'adl_number' => 'ADL-CORE-002',
                'date_received' => '2026-08-24',
                'grants' => 500_000,
                'admin_cost' => 15_000,
                'total' => 999_999_999,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('adls', [
            'adl_number' => 'ADL-CORE-002',
            'grants' => 500000.00,
            'admin_cost' => 15000.00,
            'total' => 500000.00,
        ]);
    }

    public function test_updating_grants_also_updates_total_to_the_same_amount(): void
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-CORE-003',
            'date_received' => '2026-08-24',
            'grants' => 400_000,
            'admin_cost' => 12_000,
            'total' => 400_000,
            'created_by' => $this->focal->id,
        ]);

        $this
            ->actingAs($this->focal)
            ->put(route('adl.update', $adl), [
                'adl_number' => 'ADL-CORE-003',
                'date_received' => '2026-08-24',
                'grants' => 600_000,
                'admin_cost' => 12_000,
            ])
            ->assertRedirect(route('adl.show', $adl));

        $adl->refresh();

        $this->assertSame('600000.00', $adl->grants);
        $this->assertSame('12000.00', $adl->admin_cost);
        $this->assertSame('600000.00', $adl->total);
    }
}
