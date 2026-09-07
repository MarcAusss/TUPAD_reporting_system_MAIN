<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class P5ProductionGoLiveReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_five_audit_passes_in_predeploy_mode(): void
    {
        $this->artisan('tupad:phase5-audit')
            ->assertSuccessful();
    }

    public function test_live_role_and_workflow_baseline_is_the_three_role_model(): void
    {
        $this->assertSame(
            ['admin', 'focal', 'tc'],
            array_map(static fn (UserRole $role): string => $role->value, UserRole::assignable()),
        );
        $this->assertSame('Ongoing Profiling', ProjectStatus::ONGOING_PROFILING->label());
        $this->assertTrue(Route::has('projects.evaluation.start'));
        $this->assertFalse(Route::has('project-drafts.index'));
        $this->assertFalse(Route::has('project-draft-reviews.index'));
    }

    public function test_clean_reference_data_sync_loads_only_reviewed_region_five_geography(): void
    {
        $this->artisan('tupad:reference-data-sync')
            ->assertSuccessful();

        $this->assertSame(6, Province::query()->where('is_active', true)->count());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('adls', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertGreaterThan(0, (int) DB::table('municipalities')->count());
        $this->assertGreaterThan(0, (int) DB::table('barangays')->count());
    }

    public function test_initial_admin_bootstrap_creates_only_one_forced_change_administrator(): void
    {
        config([
            'tupad_operations.initial_admin.name' => 'Release Administrator',
            'tupad_operations.initial_admin.username' => 'release.admin',
            'tupad_operations.initial_admin.email' => 'release.admin@example.gov.ph',
            'tupad_operations.initial_admin.position' => 'System Administrator',
        ]);

        $this->artisan('tupad:initial-admin')
            ->assertSuccessful();
        $this->artisan('tupad:initial-admin')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $admin = User::query()->firstOrFail();
        $this->assertSame(UserRole::ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->must_change_password);
        $this->assertNull($admin->assigned_province_id);
    }

    public function test_phase_five_deployment_assets_and_documentation_are_complete(): void
    {
        foreach ([
            'config/tupad_operations.php',
            'P5_PREDEPLOY_CHECK.cmd',
            'P5_DEPLOY_PRODUCTION.cmd',
            'P5_BACKUP_DATABASE.cmd',
            'P5_RESTORE_DATABASE.cmd',
            'P5_SMOKE_CHECK.cmd',
            'README_P5_GO_LIVE.md',
            'docs/go-live/README.md',
            'docs/go-live/P5_1_PRODUCTION_ENVIRONMENT.md',
            'docs/go-live/P5_2_CLEAN_DATABASE_DEPLOYMENT.md',
            'docs/go-live/P5_3_BUILD_OPTIMIZATION.md',
            'docs/go-live/P5_4_PRODUCTION_RELEASE_GATE.md',
            'docs/go-live/P5_5_GO_LIVE_SMOKE_TEST.md',
            'docs/go-live/P5_6_BACKUP_RECOVERY.md',
            'docs/go-live/P5_7_OPERATIONS_MONITORING.md',
            'docs/go-live/P5_8_FINAL_TURNOVER.md',
        ] as $path) {
            $this->assertFileExists(base_path($path), $path);
        }
    }

    public function test_production_script_does_not_use_demo_seeding_or_destructive_database_reset(): void
    {
        $script = file_get_contents(base_path('P5_DEPLOY_PRODUCTION.cmd'));

        foreach (['migrate:fresh', 'migrate:refresh', 'db:wipe', 'Fy2025TupadProjectSeeder', 'db:seed'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script);
        }

        foreach ([
            'tupad:backup-database',
            'migrate --force',
            'tupad:reference-data-sync',
            'tupad:initial-admin',
            'tupad:phase5-audit --production',
        ] as $required) {
            $this->assertStringContainsString($required, $script);
        }
    }

    public function test_production_environment_template_contains_phase_five_operations_controls(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        foreach ([
            'TUPAD_BACKUP_DIRECTORY=',
            'TUPAD_BACKUP_RETENTION_DAYS=14',
            'TUPAD_HEALTH_MIN_FREE_MB=1024',
            'TUPAD_HEALTH_REPORT_PATH=',
            'TUPAD_INITIAL_ADMIN_NAME=',
            'TUPAD_INITIAL_ADMIN_USERNAME=',
            'TUPAD_INITIAL_ADMIN_EMAIL=',
        ] as $expected) {
            $this->assertStringContainsString($expected, $env);
        }
    }

    public function test_production_health_command_uses_portable_bootstrap_cache_path(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/ProductionHealthCheck.php'));

        $this->assertStringContainsString("base_path('bootstrap/cache')", $source);
        $this->assertStringNotContainsString("bootstrap_path('cache')", $source);
    }

    public function test_scheduler_runs_status_sync_and_daily_operational_health_check(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("'projects:sync-statuses'", $consoleRoutes);
        $this->assertStringContainsString("'tupad:production-health --report=storage/app/health/latest.json'", $consoleRoutes);
        $this->assertStringContainsString("->dailyAt('06:15')", $consoleRoutes);
        $this->assertStringContainsString("->timezone('Asia/Manila')", $consoleRoutes);
    }
}
