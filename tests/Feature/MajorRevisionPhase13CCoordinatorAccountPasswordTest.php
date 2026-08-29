<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MajorRevisionPhase13CCoordinatorAccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Province $masbate;
    private User $admin;
    private User $focal;
    private User $tc;
    private User $gip;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name' => 'Juls Coordinator',
            'username' => 'juls',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
            'password' => 'starting-password',
        ]);

        $this->gip = User::factory()->create([
            'role' => UserRole::GIP,
            'is_active' => true,
            'supervisor_tc_id' => $this->tc->id,
        ]);
    }

    public function test_tc_can_open_read_only_account_page_with_assigned_province(): void
    {
        $this->actingAs($this->tc)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSee('My Account')
            ->assertSee('Juls Coordinator')
            ->assertSee('juls')
            ->assertSee('TUPAD Coordinator')
            ->assertSee('Masbate')
            ->assertSee('Change Password')
            ->assertDontSee('name="username"', false)
            ->assertDontSee('name="assigned_province_id"', false)
            ->assertDontSee('name="role"', false);
    }

    public function test_non_tc_roles_cannot_access_coordinator_self_service_account_routes(): void
    {
        foreach ([$this->admin, $this->focal, $this->gip] as $user) {
            $this->actingAs($user)
                ->get(route('account.show'))
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('account.password.update'), [
                    'current_password' => 'password',
                    'password' => 'replacement-password',
                    'password_confirmation' => 'replacement-password',
                ])
                ->assertForbidden();
        }
    }

    public function test_tc_can_change_only_own_password_with_correct_current_password(): void
    {
        $originalUsername = $this->tc->username;
        $originalProvinceId = $this->tc->assigned_province_id;

        $this->actingAs($this->tc)
            ->patch(route('account.password.update'), [
                'current_password' => 'starting-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
                'username' => 'browser.changed.username',
                'assigned_province_id' => null,
                'role' => UserRole::ADMIN->value,
                'is_active' => '0',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('success', 'Password changed successfully.');

        $this->tc->refresh();

        $this->assertTrue(Hash::check('new-secure-password', $this->tc->password));
        $this->assertFalse(Hash::check('starting-password', $this->tc->password));
        $this->assertSame($originalUsername, $this->tc->username);
        $this->assertSame($originalProvinceId, $this->tc->assigned_province_id);
        $this->assertSame(UserRole::TC, $this->tc->role);
        $this->assertTrue($this->tc->is_active);
    }

    public function test_incorrect_current_password_is_rejected_without_changing_password(): void
    {
        $this->actingAs($this->tc)
            ->from(route('account.show'))
            ->patch(route('account.password.update'), [
                'current_password' => 'wrong-current-password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHasErrors(['current_password']);

        $this->assertTrue(Hash::check('starting-password', $this->tc->fresh()->password));
    }

    public function test_new_password_requires_confirmation_minimum_length_and_must_differ(): void
    {
        $this->actingAs($this->tc)
            ->from(route('account.show'))
            ->patch(route('account.password.update'), [
                'current_password' => 'starting-password',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHasErrors(['password']);

        $this->actingAs($this->tc)
            ->from(route('account.show'))
            ->patch(route('account.password.update'), [
                'current_password' => 'starting-password',
                'password' => 'starting-password',
                'password_confirmation' => 'starting-password',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('starting-password', $this->tc->fresh()->password));
    }

    public function test_tc_navigation_exposes_my_account_without_user_management(): void
    {
        $this->actingAs($this->tc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Account')
            ->assertDontSee('User Accounts');
    }
}
