<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class P0SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_failed_attempts_per_username_and_ip(): void
    {
        $user = User::factory()->create([
            'username' => 'rate.limit.user',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'password' => 'Correct!Password2026',
        ]);

        $key = strtolower($user->username).'|127.0.0.1';
        RateLimiter::clear($key);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from(route('login'))
                ->post(route('login.store'), [
                    'username' => $user->username,
                    'password' => 'Wrong!Password2026',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors(['username']);
        }

        $this->from(route('login'))
            ->post(route('login.store'), [
                'username' => $user->username,
                'password' => 'Wrong!Password2026',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username']);

        $this->assertStringContainsString(
            'Too many login attempts.',
            session('errors')->first('username'),
        );

        RateLimiter::clear($key);
    }

    public function test_required_password_change_blocks_application_routes_until_strong_password_is_set(): void
    {
        $user = User::factory()->create([
            'username' => 'temporary.user',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'password' => 'Temporary!Password2026',
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'Temporary!Password2026',
        ])->assertRedirect(route('password.change.required'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('password.change.required'));

        $this->patch(route('password.change.update'), [
            'current_password' => 'Temporary!Password2026',
            'password' => 'Permanent!Password2026',
            'password_confirmation' => 'Permanent!Password2026',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->get(route('dashboard'))->assertOk();
    }
}
