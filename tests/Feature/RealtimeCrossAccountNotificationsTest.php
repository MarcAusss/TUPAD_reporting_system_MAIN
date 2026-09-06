<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RealtimeCrossAccountNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_feed_is_registered_inside_the_protected_application_routes(): void
    {
        $route = Route::getRoutes()->getByName('notifications.feed');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('password.changed', $middleware);
        $this->assertContains('province.scope', $middleware);
    }

    public function test_authenticated_user_can_poll_the_role_scoped_notification_feed(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonStructure([
                'total_count',
                'critical_count',
                'attention_count',
                'items',
                'generated_at',
                'poll_after_ms',
            ])
            ->assertJsonPath('poll_after_ms', 10000);
    }

    public function test_global_layout_and_javascript_enable_live_notification_updates_without_reload(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $appJs = file_get_contents(resource_path('js/app.js'));
        $realtimeJs = file_get_contents(resource_path('js/realtime-notifications.js'));
        $notificationView = file_get_contents(resource_path('views/notifications/index.blade.php'));

        $this->assertStringContainsString('data-notification-feed-url', $layout);
        $this->assertStringContainsString('data-notification-bell', $layout);
        $this->assertStringContainsString('data-notification-badge', $layout);
        $this->assertStringContainsString('data-notification-toast-region', $layout);

        $this->assertStringContainsString("import { initializeRealtimeNotifications } from './realtime-notifications';", $appJs);
        $this->assertStringContainsString('initializeRealtimeNotifications();', $appJs);
        $this->assertStringContainsString("fetch(feedUrl", $realtimeJs);
        $this->assertStringContainsString("tupad:notifications-updated", $realtimeJs);
        $this->assertStringContainsString('data-live-notification-list', $notificationView);
    }

    public function test_notification_feed_route_is_registered_and_layout_does_not_require_named_route_during_render(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('notifications.feed'));

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString("url('/notifications/feed')", $layout);
        $this->assertStringNotContainsString("route('notifications.feed')", $layout);
    }
}
