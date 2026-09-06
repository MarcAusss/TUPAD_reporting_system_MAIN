<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class P2UserAdministrationAllRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $focal;
    private User $tc;
    private Province $albay;
    private Province $masbate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->albay = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $this->masbate = Province::create(['code' => '054100000', 'name' => 'Masbate', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->albay->id,
        ]);
    }

    public function test_admin_registry_contains_only_live_assignable_roles(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Administrator')
            ->assertSee('Focal')
            ->assertSee('TUPAD Coordinator')
            ->assertDontSee('Retired Account')
            ->assertDontSee('Retired Account');
    }

    public function test_focal_registry_is_tc_only(): void
    {
        $this->actingAs($this->focal)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('TUPAD Coordinator Accounts')
            ->assertDontSee('Add User Account');
    }

    public function test_admin_can_create_tc_with_temporary_password_and_required_province(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Masbate Coordinator',
            'username' => 'MASBATE.TC',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC->value,
            'assigned_province_id' => $this->masbate->id,
            'is_active' => '1',
        ]);

        $account = User::query()->where('username', 'masbate.tc')->firstOrFail();
        $temporaryPassword = session('temporary_password');
        $response->assertRedirect(route('users.edit', $account));
        $this->assertSame(UserRole::TC, $account->role);
        $this->assertSame($this->masbate->id, $account->assigned_province_id);
        $this->assertTrue($account->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $account->password));
    }

    public function test_tc_requires_region_five_province(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'No Province TC',
                'username' => 'no.province.tc',
                'role' => UserRole::TC->value,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('assigned_province_id');
    }

    public function test_regional_roles_do_not_keep_province_scope(): void
    {
        foreach ([UserRole::ADMIN, UserRole::FOCAL] as $role) {
            $response = $this->actingAs($this->admin)->post(route('users.store'), [
                'name' => $role->label().' Example',
                'username' => 'regional.'.$role->value,
                'role' => $role->value,
                'assigned_province_id' => $this->albay->id,
                'is_active' => '1',
            ]);
            $account = User::query()->where('username', 'regional.'.$role->value)->firstOrFail();
            $response->assertRedirect(route('users.edit', $account));
            $this->assertNull($account->assigned_province_id);
        }
    }

    public function test_focal_cannot_expand_privileges_by_submitting_admin_role(): void
    {
        $this->actingAs($this->focal)
            ->put(route('users.update', $this->tc), [
                'name' => $this->tc->name,
                'username' => $this->tc->username,
                'role' => UserRole::ADMIN->value,
                'assigned_province_id' => $this->masbate->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(UserRole::TC, $this->tc->fresh()->role);
        $this->assertSame($this->masbate->id, $this->tc->fresh()->assigned_province_id);
    }

    public function test_retired_internal_role_cannot_be_assigned_through_user_administration(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Retired Attempt',
                'username' => 'retired.attempt',
                'role' => UserRole::RETIRED->value,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'retired.attempt']);
    }
}
