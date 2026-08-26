<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvinceSummaryCopyCompatibilityTest extends TestCase
{
    #[Test]
    public function province_summary_keeps_legacy_and_new_report_labels(): void
    {
        $view =
            file_get_contents(
                resource_path(
                    'views/projects/province-summary.blade.php'
                )
            );

        $this->assertStringContainsString(
            'Province Summary',
            $view
        );

        $this->assertStringContainsString(
            'Project Summary',
            $view
        );

        $this->assertStringContainsString(
            'Barangay beneficiary figures are coverage totals',
            $view
        );
    }
}
