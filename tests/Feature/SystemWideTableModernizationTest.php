<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class SystemWideTableModernizationTest extends TestCase
{
    public function test_every_screen_table_uses_the_shared_modern_table_hook(): void
    {
        $viewsPath = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
        $tableCount = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($viewsPath) + 1));

            if ($relative === 'reports/print.blade.php') {
                continue;
            }

            $source = File::get($file->getPathname());
            preg_match_all('/<table\b[^>]*>/s', $source, $matches);

            foreach ($matches[0] as $tag) {
                $tableCount++;
                $this->assertStringContainsString(
                    'tupad-system-table',
                    $tag,
                    $relative.' contains a screen table without the system table hook.'
                );
            }
        }

        $this->assertGreaterThanOrEqual(30, $tableCount);
    }

    public function test_system_table_css_uses_the_tupad_government_palette_and_dense_layout(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('System-wide data table modernization', $css);
        $this->assertStringContainsString('main .tupad-system-table thead th', $css);
        $this->assertStringContainsString('background: #10294f !important;', $css);
        $this->assertStringContainsString('background: #f8fafd;', $css);
        $this->assertStringContainsString('background: #edf4fc !important;', $css);
        $this->assertStringContainsString('main .tupad-system-table.tupad-wide-table', $css);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums;', $css);
    }

    public function test_per_adl_monitoring_register_has_grouped_columns_and_sticky_wide_table_hook(): void
    {
        $source = File::get(resource_path('views/monitoring/per-adl.blade.php'));

        $this->assertStringContainsString('tupad-system-table tupad-wide-table', $source);
        $this->assertStringContainsString('ADL &amp; Geography', $source);
        $this->assertStringContainsString('Allocation &amp; Targets', $source);
        $this->assertStringContainsString('Project Cost &amp; Beneficiaries', $source);
        $this->assertStringContainsString('Workflow Exposure', $source);
        $this->assertStringContainsString('Balances &amp; Notes', $source);
        $this->assertStringNotContainsString('bg-[#ffe49a]', $source);
    }

    public function test_official_report_print_tables_are_not_affected_by_the_screen_table_hook(): void
    {
        $print = File::get(resource_path('views/reports/print.blade.php'));

        $this->assertStringNotContainsString('tupad-system-table', $print);
        $this->assertStringContainsString('.pf-head-target { background: #cb3f1d;', $print);
        $this->assertStringContainsString('.pf-head-accomplishment { background: #f8d45b;', $print);
        $this->assertStringContainsString('.pf-head-balance { background: #f0b27a;', $print);
    }
}
