<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvinceSummaryExactMockupUxTest extends TestCase
{
    #[Test]
    public function province_summary_template_uses_stacked_report_layout(): void
    {
        $view =
            file_get_contents(
                resource_path(
                    'views/projects/province-summary.blade.php'
                )
            );

        $this->assertStringContainsString(
            'Project Summary',
            $view
        );

        $this->assertStringContainsString(
            'Total Amount Assisted',
            $view
        );

        $this->assertStringContainsString(
            'Micro-Insurance',
            $view
        );

        $this->assertStringContainsString(
            'Per Province Summary',
            $view
        );

        $this->assertStringContainsString(
            'Municipalities with Project Data',
            $view
        );

        $this->assertStringContainsString(
            'provinceHierarchy',
            $view
        );
    }
}
