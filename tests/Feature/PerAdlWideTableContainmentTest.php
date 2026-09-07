<?php

namespace Tests\Feature;

use Tests\TestCase;

class PerAdlWideTableContainmentTest extends TestCase
{
    public function test_per_adl_register_keeps_remarks_inside_the_table_and_action_pinned(): void
    {
        $view = file_get_contents(resource_path('views/monitoring/per-adl.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('tupad-per-adl-table', $view);
        $this->assertStringContainsString('colspan="8">Workflow Exposure', $view);
        $this->assertStringContainsString('colspan="4">Balances &amp; Notes', $view);
        $this->assertStringContainsString('tupad-table-remarks', $view);
        $this->assertStringContainsString('tupad-table-action', $view);

        $this->assertStringContainsString('white-space: normal !important;', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $css);
        $this->assertStringContainsString('position: sticky !important;', $css);
        $this->assertStringContainsString('right: 0;', $css);
    }
}
