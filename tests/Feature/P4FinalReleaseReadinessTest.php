<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class P4FinalReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_four_release_audit_passes_in_uat_mode(): void
    {
        $this->artisan('tupad:phase4-audit')
            ->assertSuccessful();
    }

    public function test_phase_four_audit_does_not_override_laravel_command_fail_method(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/Phase4ReleaseAudit.php'));

        $this->assertStringContainsString('private function recordFailure(', $source);
        $this->assertStringNotContainsString('private function fail(', $source);
        $this->assertStringNotContainsString('$this->fail(', $source);
    }

    public function test_critical_release_routes_keep_expected_access_middleware(): void
    {
        $matrix = [
            'dashboard' => ['auth', 'password.changed', 'province.scope'],
            'notifications.index' => ['auth', 'password.changed', 'province.scope'],
            'audit.index' => ['role:admin'],
            'users.index' => ['role:admin,focal'],
            'projects.index' => ['role:admin,tc,focal'],
            'project-workflow.index' => ['role:admin,tc'],
            'payments.index' => ['role:admin,focal'],
            'reports.index' => ['role:admin,tc,focal'],
        ];

        foreach ($matrix as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, $routeName);

            foreach ($middleware as $expected) {
                $this->assertContains($expected, $route->gatherMiddleware(), "{$routeName}: {$expected}");
            }
        }
    }

    public function test_retired_gip_role_and_draft_schema_are_absent(): void
    {
        $this->assertFalse(Schema::hasTable('project_drafts'));
        $this->assertFalse(Schema::hasTable('project_draft_ppe_items'));
        $this->assertFalse(Schema::hasColumn('users', 'supervisor_tc_id'));
        $this->assertFalse(Route::has('project-drafts.index'));
        $this->assertFalse(Route::has('project-draft-reviews.index'));
    }

    public function test_report_release_routes_include_all_official_output_formats(): void
    {
        foreach ([
            'reports.print',
            'reports.export.pdf',
            'reports.export.excel',
            'reports.export.csv',
            'reports.periodic.print',
            'reports.periodic.export.pdf',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), $routeName);
        }

        $reportsConfig = config('tupad_reports');

        $this->assertNotEmpty($reportsConfig['document']['version']);
        $this->assertArrayHasKey('revision', $reportsConfig['document']);
        $this->assertNotNull($reportsConfig['document']['revision']);
        $this->assertNotSame('', (string) $reportsConfig['document']['revision']);
        $this->assertNotEmpty($reportsConfig['document']['reference_prefix']);
        $this->assertSame('Asia/Manila', $reportsConfig['document']['timezone']);
    }

    public function test_production_environment_template_contains_required_release_controls(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        foreach ([
            'APP_ENV=production',
            'APP_DEBUG=false',
            'DB_CONNECTION=mysql',
            'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE=true',
            'TUPAD_DASHBOARD_ATTENTION_DAYS=7',
            'TUPAD_DASHBOARD_CRITICAL_DAYS=14',
            'TUPAD_REPORT_VERSION=1.0',
            'TUPAD_REPORT_REFERENCE_PREFIX=DOLE-RO5-TUPAD',
            'TUPAD_REPORT_TIMEZONE=Asia/Manila',
            'TUPAD_REPORT_PREPARED_BY_NAME=',
            'TUPAD_REPORT_REVIEWED_BY_NAME=',
            'TUPAD_REPORT_APPROVED_BY_NAME=',
        ] as $expected) {
            $this->assertStringContainsString($expected, $env);
        }
    }

    public function test_phase_four_release_documentation_is_complete(): void
    {
        foreach ([
            'docs/release/README.md',
            'docs/release/P4_1_FULL_SYSTEM_REGRESSION_ROUTE_AUDIT.md',
            'docs/release/P4_2_FORMS_VALIDATION_UAT.md',
            'docs/release/P4_3_REPORT_PRINT_EXPORT_QA.md',
            'docs/release/P4_4_SECURITY_PRODUCTION_CHECKLIST.md',
            'docs/release/P4_5_DATABASE_BACKUP_RESTORE.md',
            'docs/release/P4_6_MASTER_UAT_CHECKLIST.md',
            'docs/release/P4_7_DEPLOYMENT_HANDOVER_RUNBOOK.md',
            'docs/release/P4_8_FINAL_SYSTEM_USER_MANUAL.md',
            'docs/release/P4_FINAL_RELEASE_SIGNOFF.md',
            'P4_RELEASE_CHECK.cmd',
        ] as $path) {
            $this->assertFileExists(base_path($path), $path);
        }
    }

    public function test_release_script_never_uses_destructive_database_reset_commands(): void
    {
        $script = file_get_contents(base_path('P4_RELEASE_CHECK.cmd'));

        $this->assertStringNotContainsString('migrate:fresh', $script);
        $this->assertStringNotContainsString('migrate:refresh', $script);
        $this->assertStringNotContainsString('db:wipe', $script);
        $this->assertStringContainsString('tupad:phase4-audit', $script);
        $this->assertStringContainsString('php artisan test', $script);
    }
}
