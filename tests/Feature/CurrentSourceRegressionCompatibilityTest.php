<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CurrentSourceRegressionCompatibilityTest extends TestCase
{
    #[Test]
    public function province_summary_blade_keeps_valid_current_labels_and_project_code_relation(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/projects/province-summary.blade.php'
            )
        );

        $this->assertStringContainsString(
            ':title="$province->name . \' Province Summary\'"',
            $view
        );

        $this->assertStringContainsString(
            'description="{{ $province->name }} Project Summary',
            $view
        );

        $this->assertStringContainsString(
            'Barangay beneficiary figures are coverage totals',
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
        $view = file_get_contents(
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
