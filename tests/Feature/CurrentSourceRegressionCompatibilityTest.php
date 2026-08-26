<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrentSourceRegressionCompatibilityTest extends TestCase
{
    #[Test]
    public function province_summary_keeps_current_single_project_and_exact_allocation_labels(): void
    {
        $view =
            file_get_contents(
                resource_path(
                    'views/projects/province-summary.blade.php'
                )
            );

        $this->assertStringContainsString(
            '$summaryPageTitle',
            $view
        );

        $this->assertStringContainsString(
            'Only this project is included in this report.',
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

        $this->assertStringContainsString(
            '$project->approval?->project_code',
            $view
        );
    }

    #[Test]
    public function minimal_implementation_board_preserves_explicit_post_document_action(): void
    {
        $view =
            file_get_contents(
                resource_path(
                    'views/project-workflow/index.blade.php'
                )
            );

        $this->assertStringContainsString(
            'Project Code:',
            $view
        );

        $this->assertStringContainsString(
            'Beneficiaries:',
            $view
        );

        $this->assertStringContainsString(
            'Status:',
            $view
        );

        $this->assertStringContainsString(
            'Submit Post Documents',
            $view
        );
    }
}
