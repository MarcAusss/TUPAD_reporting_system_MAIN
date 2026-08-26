<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectApprovalCodeAssignmentUxTest extends TestCase
{
    #[Test]
    public function project_approval_form_explicitly_assigns_one_official_project_code(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/projects/show.blade.php'
            )
        );

        $this->assertStringContainsString(
            'Official Project Code',
            $view
        );

        $this->assertStringContainsString(
            'name="project_code"',
            $view
        );

        $this->assertStringContainsString(
            'One project receives one Project Code',
            $view
        );

        $this->assertStringContainsString(
            'single official Project Code for this project',
            $view
        );
    }
}
