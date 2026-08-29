<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MajorRevisionPhase13BFocalUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $focal;
    private User $tc;
    private User $gip;
    private Province $albay;
    private Province $masbate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->albay = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->masbate = Province::create([
            'code' => '054100000',
            'name' => 'Masbate',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->albay->id,
        ]);

        $this->gip = User::factory()->create([
            'role' => UserRole::GIP,
            'is_active' => true,
            'supervisor_tc_id' => $this->tc->id,
        ]);
    }

    public function test_focal_and_admin_can_access_coordinator_accounts_but_tc_and_gip_cannot(): void
    {
        foreach ([$this->focal, $this->admin] as $authorizedUser) {
            $this->actingAs($authorizedUser)
                ->get(route('users.index'))
                ->assertOk()
                ->assertSee('TUPAD Coordinator Accounts')
                ->assertSee('Add Coordinator');
        }

        foreach ([$this->tc, $this->gip] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get(route('users.index'))
                ->assertForbidden();
        }
    }

    public function test_focal_creates_province_assigned_tc_with_server_fixed_role_and_default_password(): void
    {
        $response = $this->actingAs($this->focal)
            ->post(route('users.store'), [
                'name' => 'Juls Coordinator',
                'username' => 'JULS.MASBATE',
                'position' => 'TUPAD Coordinator',
                'assigned_province_id' => $this->masbate->id,
                'is_active' => '1',
                'role' => UserRole::ADMIN->value,
                'password' => 'browser-supplied-password',
            ]);

        $coordinator = User::query()->where('username', 'juls.masbate')->firstOrFail();

        $response->assertRedirect(route('users.edit', $coordinator));
        $this->assertSame(UserRole::TC, $coordinator->role);
        $this->assertSame($this->masbate->id, $coordinator->assigned_province_id);
        $this->assertTrue($coordinator->is_active);
        $this->assertTrue(Hash::check('password', $coordinator->password));
        $this->assertFalse(Hash::check('browser-supplied-password', $coordinator->password));
        $this->assertSame('juls.masbate@accounts.tupad.invalid', $coordinator->email);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->focal->id,
            'action' => 'created',
            'module' => 'User Management',
            'auditable_type' => User::class,
            'auditable_id' => $coordinator->id,
        ]);
    }

    public function test_focal_can_edit_username_province_position_and_status_without_changing_role_or_password(): void
    {
        $coordinator = User::factory()->create([
            'name' => 'Juls',
            'username' => 'juls',
            'email' => 'juls@accounts.tupad.invalid',
            'position' => 'Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->albay->id,
            'password' => 'existing-secret',
        ]);
        $originalPassword = $coordinator->password;

        $this->actingAs($this->focal)
            ->put(route('users.update', $coordinator), [
                'name' => 'Juls Masbate',
                'username' => 'JULS.MASBATE',
                'position' => 'TUPAD Coordinator II',
                'assigned_province_id' => $this->masbate->id,
                'is_active' => '0',
                'role' => UserRole::ADMIN->value,
                'password' => 'should-not-be-used',
            ])
            ->assertRedirect(route('users.edit', $coordinator));

        $coordinator->refresh();

        $this->assertSame('Juls Masbate', $coordinator->name);
        $this->assertSame('juls.masbate', $coordinator->username);
        $this->assertSame('juls.masbate@accounts.tupad.invalid', $coordinator->email);
        $this->assertSame('TUPAD Coordinator II', $coordinator->position);
        $this->assertSame($this->masbate->id, $coordinator->assigned_province_id);
        $this->assertSame(UserRole::TC, $coordinator->role);
        $this->assertFalse($coordinator->is_active);
        $this->assertSame($originalPassword, $coordinator->password);
    }

    public function test_focal_can_reset_coordinator_password_to_password(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
            'password' => 'different-password',
        ]);

        $this->assertTrue(Hash::check('different-password', $coordinator->password));

        $this->actingAs($this->focal)
            ->post(route('users.reset-password', $coordinator))
            ->assertRedirect();

        $this->assertTrue(Hash::check('password', $coordinator->fresh()->password));
    }

    public function test_focal_can_activate_and_deactivate_coordinator_without_deleting_account(): void
    {
        $coordinator = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $this->actingAs($this->focal)
            ->patch(route('users.status', $coordinator))
            ->assertRedirect();

        $this->assertFalse($coordinator->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $coordinator->id]);

        $this->actingAs($this->focal)
            ->patch(route('users.status', $coordinator))
            ->assertRedirect();

        $this->assertTrue($coordinator->fresh()->is_active);
    }

    public function test_user_management_cannot_edit_or_reset_non_tc_accounts(): void
    {
        $this->actingAs($this->focal)
            ->get(route('users.edit', $this->admin))
            ->assertNotFound();

        $this->actingAs($this->focal)
            ->put(route('users.update', $this->admin), [
                'name' => 'Changed Admin',
                'username' => $this->admin->username,
                'assigned_province_id' => $this->masbate->id,
                'is_active' => '1',
            ])
            ->assertNotFound();

        $this->actingAs($this->focal)
            ->post(route('users.reset-password', $this->admin))
            ->assertNotFound();
    }

    public function test_username_must_be_unique_and_assigned_province_must_be_active(): void
    {
        $inactiveProvince = Province::create([
            'code' => '052000000',
            'name' => 'Camarines Norte',
            'is_active' => false,
        ]);

        $this->actingAs($this->focal)
            ->post(route('users.store'), [
                'name' => 'Duplicate Username',
                'username' => $this->tc->username,
                'assigned_province_id' => $inactiveProvince->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors(['username', 'assigned_province_id']);
    }

    public function test_registry_lists_only_coordinators_and_supports_province_filter(): void
    {
        $masbateCoordinator = User::factory()->create([
            'name' => 'Masbate Coordinator',
            'username' => 'masbate.tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $this->actingAs($this->focal)
            ->get(route('users.index', ['province_id' => $this->masbate->id]))
            ->assertOk()
            ->assertSee($masbateCoordinator->username)
            ->assertDontSee($this->tc->username)
            ->assertDontSee($this->admin->username)
            ->assertDontSee($this->gip->username);
    }
}
