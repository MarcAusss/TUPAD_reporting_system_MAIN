<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MinimalImplementationBoardUxTest extends TestCase
{
    #[Test]
    public function implementation_board_template_uses_minimal_stage_cards(): void
    {
        $view =
            file_get_contents(
                resource_path(
                    'views/project-workflow/index.blade.php'
                )
            );

        $this->assertStringContainsString(
            'For Implementation',
            $view
        );

        $this->assertStringContainsString(
            'Ongoing Implementation',
            $view
        );

        $this->assertStringContainsString(
            'For Submission of Post Docs',
            $view
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
            'Set Work Period',
            $view
        );

        $this->assertStringContainsString(
            'js-open-work-period-modal',
            $view
        );

        $this->assertStringContainsString(
            'View',
            $view
        );
    }
}
