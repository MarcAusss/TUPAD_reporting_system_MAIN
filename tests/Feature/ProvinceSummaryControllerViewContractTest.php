<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvinceSummaryControllerViewContractTest extends TestCase
{
    #[Test]
    public function exact_summary_controller_supplies_project_entries_expected_by_view(): void
    {
        $controller =
            file_get_contents(
                app_path(
                    'Http/Controllers/ProjectProvinceSummaryController.php'
                )
            );

        $view =
            file_get_contents(
                resource_path(
                    'views/projects/province-summary.blade.php'
                )
            );

        $this->assertStringContainsString(
            "'project_entries' => \$entries",
            $controller
        );

        $this->assertStringContainsString(
            "\$barangay['project_entries']",
            $view
        );

        $this->assertStringContainsString(
            'Exact barangay beneficiary allocation',
            $view
        );

        $this->assertStringContainsString(
            'Legacy project-level beneficiary coverage',
            $view
        );
    }
}
