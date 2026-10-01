<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectApprovalCodeAssignmentUxTest extends TestCase
{
    #[Test]
    public function project_approval_form_has_no_manual_project_code_input(): void
    {
        $view = $this->projectShowSource();

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
        $view = $this->projectShowSource();

        $this->assertStringContainsString(
            '$project->approval->project_code',
            $view
        );
    }

    private function projectShowSource(): string
    {
        $files = array_merge(
            [resource_path('views/projects/show.blade.php')],
            glob(resource_path('views/projects/partials/show/*.blade.php')) ?: [],
        );

        return implode("\n", array_map('file_get_contents', $files));
    }
}
