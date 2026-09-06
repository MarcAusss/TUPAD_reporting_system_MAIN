<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class P3SystemVisualUxPolishTest extends TestCase
{
    public function test_shared_shell_exposes_accessible_responsive_polish_hooks(): void
    {
        $layout = File::get(resource_path('views/layouts/app.blade.php'));
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('tupad-topbar', $layout);
        $this->assertStringContainsString('tupad-main-content', $layout);
        $this->assertStringContainsString('aria-controls="sidebar"', $layout);
        $this->assertStringContainsString('aria-expanded="false"', $layout);
        $this->assertStringContainsString("setAttribute('aria-expanded', 'true')", $layout);
        $this->assertStringContainsString("setAttribute('aria-expanded', 'false')", $layout);

        $this->assertStringContainsString('.tupad-metric-card', $css);
        $this->assertStringContainsString('.tupad-filter-panel', $css);
        $this->assertStringContainsString('.tupad-table-shell', $css);
        $this->assertStringContainsString('.tupad-empty-state', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_core_registries_use_consistent_filter_table_and_metric_primitives(): void
    {
        $projects = File::get(resource_path('views/projects/index.blade.php'));
        $users = File::get(resource_path('views/users/index.blade.php'));
        $audit = File::get(resource_path('views/audit/index.blade.php'));
        $notifications = File::get(resource_path('views/notifications/index.blade.php'));

        $this->assertStringContainsString('tupad-filter-panel', $projects);
        $this->assertStringContainsString('tupad-table-shell', $projects);

        $this->assertStringContainsString('tupad-filter-panel', $users);
        $this->assertStringContainsString('tupad-table-shell', $users);
        $this->assertStringContainsString('tupad-metric-card', $users);

        $this->assertStringContainsString('tupad-filter-panel', $audit);
        $this->assertStringContainsString('tupad-table-shell', $audit);
        $this->assertStringContainsString('<x-empty-state', $audit);

        $this->assertStringContainsString('tupad-metric-card', $notifications);
        $this->assertStringContainsString('tupad-table-shell', $notifications);
        $this->assertStringContainsString('<x-page-header', $notifications);
    }

    public function test_production_report_views_do_not_expose_internal_development_phase_language(): void
    {
        $views = [
            'reports/workspace.blade.php',
            'reports/quarterly/index.blade.php',
            'reports/monthly/index.blade.php',
            'reports/fund-status/index.blade.php',
            'reports/print.blade.php',
            'executive-dashboard/index.blade.php',
            'executive-dashboard/presentation.blade.php',
        ];

        foreach ($views as $view) {
            $source = File::get(resource_path('views/'.$view));

            $this->assertDoesNotMatchRegularExpression('/Phase\s+\d+/i', $source, $view.' still exposes internal phase terminology.');
            $this->assertStringNotContainsString('implementation follows', $source, $view.' still exposes implementation-stage wording.');
        }

        $workspace = File::get(resource_path('views/reports/workspace.blade.php'));
        $this->assertStringContainsString('Official reporting:', $workspace);
        $this->assertStringContainsString('Unavailable', $workspace);
        $this->assertStringContainsString('Open Report View', $workspace);
    }
}
