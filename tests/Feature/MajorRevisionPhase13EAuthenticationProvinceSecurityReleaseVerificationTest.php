<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\Fy2025TupadProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MajorRevisionPhase13EAuthenticationProvinceSecurityReleaseVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_verifier_passes_when_active_coordinators_have_active_provinces(): void
    {
        $masbate = $this->province('Masbate', '054100000');

        User::factory()->create([
            'name' => 'Masbate Coordinator',
            'username' => 'masbate.release.tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $masbate->id,
            'password' => 'safe-release-password',
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Release verification PASSED')
            ->assertExitCode(0);
    }

    public function test_release_verifier_blocks_active_coordinator_without_assigned_province(): void
    {
        User::factory()->create([
            'name' => 'Unassigned Coordinator',
            'username' => 'unassigned.release.tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => null,
            'password' => 'safe-release-password',
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Active TUPAD Coordinator account(s) have no valid assigned province')
            ->expectsOutputToContain('unassigned.release.tc')
            ->assertExitCode(1);
    }

    public function test_release_verifier_blocks_active_coordinator_assigned_to_inactive_province(): void
    {
        $inactiveProvince = $this->province('Inactive Province', '059900000', false);

        User::factory()->create([
            'name' => 'Inactive Province Coordinator',
            'username' => 'inactive.province.tc',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $inactiveProvince->id,
            'password' => 'safe-release-password',
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Active TUPAD Coordinator account(s) are assigned to an inactive province')
            ->expectsOutputToContain('inactive.province.tc')
            ->assertExitCode(1);
    }

    public function test_fy2025_seeder_assigns_demo_tc_to_active_albay_reference(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $albay = Province::query()->where('code', '050500000')->firstOrFail();

        $tc = User::query()->where('username', 'tc')->firstOrFail();

        $this->assertSame(UserRole::TC, $tc->role);
        $this->assertSame($albay->id, $tc->assigned_province_id);
        $this->assertTrue($tc->assignedProvince()->where('is_active', true)->exists());

        foreach (['admin', 'focal', 'gip'] as $username) {
            $this->assertNull(User::query()->where('username', $username)->value('assigned_province_id'));
        }
    }

    public function test_focal_created_coordinator_login_password_change_and_province_scope_work_end_to_end(): void
    {
        $masbate = $this->province('Masbate', '054100000');
        $albay = $this->province('Albay', '050500000');

        Municipality::query()->create([
            'province_id' => $masbate->id,
            'code' => '054101000',
            'name' => 'Masbate City',
            'district' => '1st District',
            'is_city' => true,
            'is_active' => true,
        ]);

        Municipality::query()->create([
            'province_id' => $albay->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'name' => 'TUPAD Focal',
            'username' => 'phase13e.focal',
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'assigned_province_id' => null,
            'password' => 'focal-password',
        ]);

        $this->actingAs($focal)
            ->post(route('users.store'), [
                'name' => 'Juls Coordinator',
                'username' => 'juls',
                'position' => 'TUPAD Coordinator',
                'assigned_province_id' => $masbate->id,
                'is_active' => '1',
                'role' => UserRole::ADMIN->value,
                'password' => 'browser-supplied-password',
            ])
            ->assertRedirect();

        $juls = User::query()->where('username', 'juls')->firstOrFail();

        $this->assertSame(UserRole::TC, $juls->role);
        $this->assertSame($masbate->id, $juls->assigned_province_id);
        $this->assertTrue(Hash::check('password', $juls->password));
        $this->assertFalse(Hash::check('browser-supplied-password', $juls->password));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'username' => 'juls',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('account.show'))
            ->assertOk()
            ->assertSee('Masbate');

        $this->get(route('users.index'))->assertForbidden();

        $this->get(route('locations.municipalities', $masbate))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Masbate City']);

        $this->get(route('locations.municipalities', $albay))
            ->assertForbidden();

        $this->patch(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'Juls-secure-password-2026',
            'password_confirmation' => 'Juls-secure-password-2026',
            'assigned_province_id' => $albay->id,
            'role' => UserRole::ADMIN->value,
        ])->assertRedirect(route('account.show'));

        $juls->refresh();
        $this->assertSame(UserRole::TC, $juls->role);
        $this->assertSame($masbate->id, $juls->assigned_province_id);
        $this->assertTrue(Hash::check('Juls-secure-password-2026', $juls->password));

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => 'juls',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username']);

        $this->post(route('login.store'), [
            'username' => 'juls',
            'password' => 'Juls-secure-password-2026',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_security_critical_authenticated_routes_keep_province_scope_middleware(): void
    {
        foreach ([
            'dashboard',
            'projects.index',
            'search.index',
            'reports.index',
            'reports.export.csv',
            'reports.export.excel',
            'reports.export.pdf',
            'reports.print',
            'executive-dashboard.index',
            'executive-dashboard.presentation',
            'locations.municipalities',
            'locations.barangays',
            'project-monitoring.province',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Expected route [{$routeName}] to be registered.");
            $this->assertContains(
                'province.scope',
                $route->gatherMiddleware(),
                "Expected route [{$routeName}] to enforce province.scope middleware."
            );
        }
    }

    private function province(string $name, string $code, bool $active = true): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => $active,
        ]);
    }
}
