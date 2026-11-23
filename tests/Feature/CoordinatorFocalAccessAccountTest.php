<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorFocalAccessAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
    }

    public function test_admin_can_create_a_tupad_coordinator_account_with_focal_access(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('TUPAD Coordinator — Focal Access');

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Juana Coordinator',
                'username' => 'juana.focal',
                'position' => 'TUPAD Coordinator',
                'role' => User::COORDINATOR_FOCAL_ACCESS_CHOICE,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'TUPAD Coordinator (Focal Access) account created. Copy the temporary password now; it will not be shown again after this request.');

        $account = User::query()->where('username', 'juana.focal')->firstOrFail();

        $this->assertSame(UserRole::FOCAL, $account->role);
        $this->assertTrue($account->has_focal_access);
        $this->assertNull($account->assigned_province_id);
        $this->assertTrue($account->isFocal());
        $this->assertFalse($account->isTc());
        $this->assertTrue($account->isCoordinatorWithFocalAccess());
        $this->assertSame('TUPAD Coordinator (Focal Access)', $account->roleLabel());
    }

    public function test_account_has_focal_access_and_shows_the_coordinator_label(): void
    {
        $account = $this->coordinatorWithFocalAccess();

        // Same access as Focal: Focal-only pages open.
        $this->actingAs($account)
            ->get(route('reports.district-representatives.edit'))
            ->assertOk()
            ->assertSee('TUPAD Coordinator (Focal Access)');

        $this->actingAs($account)
            ->get(route('locations.local-chief-executives.edit'))
            ->assertOk();
    }

    public function test_plain_focal_and_tc_labels_are_unchanged(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $province = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $province->id,
        ]);

        $this->assertSame('Focal', $focal->roleLabel());
        $this->assertFalse($focal->isCoordinatorWithFocalAccess());
        $this->assertSame('TUPAD Coordinator', $tc->roleLabel());
        $this->assertFalse($tc->isFocal());
    }

    public function test_admin_can_switch_between_focal_and_coordinator_focal_access(): void
    {
        $account = $this->coordinatorWithFocalAccess();

        $this->actingAs($this->admin)
            ->get(route('users.edit', $account))
            ->assertOk()
            ->assertSee('value="'.User::COORDINATOR_FOCAL_ACCESS_CHOICE.'" selected', false);

        $this->actingAs($this->admin)
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'username' => $account->username,
                'role' => UserRole::FOCAL->value,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.edit', $account));

        $this->assertFalse($account->fresh()->has_focal_access);
        $this->assertSame('Focal', $account->fresh()->roleLabel());

        $this->actingAs($this->admin)
            ->put(route('users.update', $account), [
                'name' => $account->name,
                'username' => $account->username,
                'role' => User::COORDINATOR_FOCAL_ACCESS_CHOICE,
                'is_active' => '1',
            ]);

        $this->assertTrue($account->fresh()->has_focal_access);
    }

    public function test_user_list_labels_and_filters_coordinator_focal_access_accounts(): void
    {
        $account = $this->coordinatorWithFocalAccess();
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true, 'name' => 'Plain Focal']);

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Focal Access')
            ->assertSee('TUPAD Coordinator — Focal Access');

        $this->actingAs($this->admin)
            ->get(route('users.index', ['role' => User::COORDINATOR_FOCAL_ACCESS_CHOICE]))
            ->assertOk()
            ->assertSee($account->name)
            ->assertDontSee('Plain Focal');

        $this->actingAs($this->admin)
            ->get(route('users.index', ['role' => UserRole::FOCAL->value]))
            ->assertOk()
            ->assertSee('Plain Focal')
            ->assertDontSee($account->name);
    }

    public function test_focal_can_create_and_view_a_coordinator_with_focal_access(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('TUPAD Coordinator — Focal Access');

        $this->actingAs($focal)
            ->post(route('users.store'), [
                'name' => 'Rosa Focal Access',
                'username' => 'rosa.focal',
                'role' => User::COORDINATOR_FOCAL_ACCESS_CHOICE,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('temporary_password');

        $account = User::query()->where('username', 'rosa.focal')->firstOrFail();

        $this->assertSame(UserRole::FOCAL, $account->role);
        $this->assertTrue($account->has_focal_access);
        $this->assertNull($account->assigned_province_id);

        $this->actingAs($focal)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Rosa Focal Access')
            ->assertSee('Focal Access');

        $this->actingAs($focal)
            ->get(route('users.index', ['role' => User::COORDINATOR_FOCAL_ACCESS_CHOICE]))
            ->assertOk()
            ->assertSee('Rosa Focal Access');

        $this->actingAs($focal)
            ->get(route('users.edit', $account))
            ->assertOk()
            ->assertSee('value="'.User::COORDINATOR_FOCAL_ACCESS_CHOICE.'" selected', false);
    }

    public function test_focal_can_switch_a_coordinator_between_plain_and_focal_access(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $province = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $province->id,
        ]);

        $this->actingAs($focal)
            ->put(route('users.update', $tc), [
                'name' => $tc->name,
                'username' => $tc->username,
                'role' => User::COORDINATOR_FOCAL_ACCESS_CHOICE,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.edit', $tc));

        $tc->refresh();
        $this->assertTrue($tc->isCoordinatorWithFocalAccess());
        $this->assertNull($tc->assigned_province_id);

        // Editing without naming a role keeps the current account type.
        $this->actingAs($focal)
            ->put(route('users.update', $tc), [
                'name' => 'Renamed',
                'username' => $tc->username,
                'is_active' => '1',
            ]);
        $this->assertTrue($tc->fresh()->isCoordinatorWithFocalAccess());

        $this->actingAs($focal)
            ->put(route('users.update', $tc), [
                'name' => $tc->name,
                'username' => $tc->username,
                'role' => UserRole::TC->value,
                'assigned_province_id' => $province->id,
                'is_active' => '1',
            ]);

        $tc->refresh();
        $this->assertSame(UserRole::TC, $tc->role);
        $this->assertFalse($tc->has_focal_access);
        $this->assertSame($province->id, $tc->assigned_province_id);
    }

    public function test_focal_still_cannot_create_or_manage_admin_or_plain_focal_accounts(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $otherFocal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true, 'name' => 'Other Plain Focal']);
        $province = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        // Any role other than the two coordinator types becomes a plain TC.
        $this->actingAs($focal)->post(route('users.store'), [
            'name' => 'Tries Admin',
            'username' => 'tries.admin',
            'role' => UserRole::ADMIN->value,
            'assigned_province_id' => $province->id,
            'is_active' => '1',
        ]);
        $this->assertSame(UserRole::TC, User::query()->where('username', 'tries.admin')->firstOrFail()->role);

        $this->actingAs($focal)->get(route('users.edit', $otherFocal))->assertNotFound();
        $this->actingAs($focal)->get(route('users.edit', $this->admin))->assertNotFound();
        $this->actingAs($focal)->get(route('users.index'))->assertDontSee('Other Plain Focal');
    }

    public function test_coordinator_with_focal_access_cannot_edit_itself_from_user_accounts(): void
    {
        $account = $this->coordinatorWithFocalAccess();

        $this->actingAs($account)
            ->get(route('users.index'))
            ->assertOk()
            // Their own name is in the header; their account row is not listed.
            ->assertDontSee(route('users.edit', $account), false);

        $this->actingAs($account)
            ->get(route('users.edit', $account))
            ->assertNotFound();
    }

    private function coordinatorWithFocalAccess(): User
    {
        $account = User::factory()->create([
            'name' => 'Pedro Focal Access',
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);
        $account->forceFill(['has_focal_access' => true])->save();

        return $account;
    }
}
