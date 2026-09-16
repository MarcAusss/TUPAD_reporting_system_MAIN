<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectApprovalCodeAssignmentUxTest extends TestCase
{
    #[Test]
    public function project_approval_form_has_no_manual_project_code_input(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/projects/show.blade.php'
            )
        );

        $this->assertStringNotContainsString(
            'name="project_code"',
            $view
        );

        $quickWorkflowView = file_get_contents(
            resource_path(
                'views/projects/partials/quick-workflow-action.blade.php'
            )
        );

        $this->assertStringNotContainsString(
            'name="project_code"',
            $quickWorkflowView
        );
    }

    #[Test]
    public function approved_project_displays_the_system_generated_project_code_as_read_only(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/projects/show.blade.php'
            )
        );

        $this->assertStringContainsString(
            '$project->approval->project_code',
            $view
        );
    }
}
