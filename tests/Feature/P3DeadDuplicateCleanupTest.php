<?php

namespace Tests\Feature;

use App\Models\ProjectPayout;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class P3DeadDuplicateCleanupTest extends TestCase
{
    public function test_retired_runtime_files_and_overlay_artifacts_are_absent(): void
    {
        foreach ([
            'app/Http/Controllers/ProjectBeneficiaryController.php',
            'app/Http/Controllers/ProjectPayoutController.php',
            'resources/views/projects/beneficiaries',
            'resources/views/projects/gip-placeholder.blade.php',
            'resources/views/welcome.blade.php',
            'resources/css/app.css.phase13_4_backup',
            'resources/js/app.js.phase13_4_backup',
            'FIX_FRESH_BASELINE.cmd',
            'FIX_FRESH_BASELINE.ps1',
            'verify-r14-2.ps1',
            'README_OVERLAY.md',
            'TUPAD_P1_NAVIGATION_TC_PROVINCE_FIX_OVERLAY',
        ] as $relativePath) {
            $this->assertFileDoesNotExist(base_path($relativePath), $relativePath);
        }
    }

    public function test_retired_routes_stay_removed_while_historical_payout_model_remains_available(): void
    {
        foreach ([
            'projects.beneficiaries.index',
            'projects.beneficiaries.store',
            'projects.beneficiaries.edit',
            'projects.beneficiaries.update',
            'projects.beneficiaries.destroy',
            'projects.payout.store',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), $routeName);
        }

        $this->assertTrue(class_exists(ProjectPayout::class));
    }

    public function test_repository_guards_against_future_overlay_and_backup_artifacts(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));

        foreach ([
            '/TUPAD_*_OVERLAY/',
            '/resources/css/*.phase*_backup',
            '/resources/js/*.phase*_backup',
            '*.bak',
            '*.old',
            '*.orig',
        ] as $pattern) {
            $this->assertStringContainsString($pattern, $gitignore);
        }

        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertStringNotContainsString('CurrentSystemDemoSeeder', $envExample);
        $this->assertStringContainsString('migrate:fresh', $envExample);
    }
}
