<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportScreenTableColorPolishTest extends TestCase
{
    public function test_all_screen_report_tables_use_the_system_report_table_palette_hook(): void
    {
        $views = [
            'reports/index.blade.php',
            'reports/physical-financial/index.blade.php',
            'reports/fund-status/index.blade.php',
            'reports/geographic-mapping/index.blade.php',
            'reports/monthly/index.blade.php',
            'reports/quarterly/index.blade.php',
        ];

        foreach ($views as $view) {
            $source = File::get(resource_path('views/'.$view));

            $this->assertStringContainsString('tupad-report-screen-table', $source, $view.' is missing the screen report table palette hook.');
        }
    }

    public function test_screen_palette_uses_the_existing_tupad_navy_visual_language(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('main .tupad-report-screen-table', $css);
        $this->assertStringContainsString('background: #063b86 !important;', $css);
        $this->assertStringContainsString('background: #e8f0fa !important;', $css);
        $this->assertStringContainsString('background: #0f2d5c !important;', $css);
    }

    public function test_official_print_table_palette_is_unchanged(): void
    {
        $print = File::get(resource_path('views/reports/print.blade.php'));

        $this->assertStringContainsString('.pf-head-target { background: #cb3f1d;', $print);
        $this->assertStringContainsString('.pf-head-accomplishment { background: #f8d45b;', $print);
        $this->assertStringContainsString('.pf-head-balance { background: #f0b27a;', $print);
        $this->assertStringContainsString('.pf-head-leaf { background: #fff7db;', $print);
    }
}
