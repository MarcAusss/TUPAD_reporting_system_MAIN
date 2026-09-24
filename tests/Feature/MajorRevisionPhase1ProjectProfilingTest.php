<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MajorRevisionPhase1ProjectProfilingTest extends TestCase
{
    #[Test]
    public function project_create_ui_reflects_revised_profiling_requirements(): void
    {
        $view = file_get_contents(
            resource_path('views/projects/create.blade.php')
        );

        $this->assertStringContainsString('Regional Wage Rate', $view);
        $this->assertStringContainsString("old('wage_rate', 455)", $view);
        $this->assertStringContainsString('Wage Rate × Beneficiaries × Number of Days', $view);
        $this->assertStringContainsString('Non-Hazardous or Hazardous PPE', $view);
        $this->assertStringContainsString('Project Creation Completion', $view);
        $this->assertStringContainsString('moves it directly to <strong>TSSD Evaluation</strong> status', $view);
    }

    #[Test]
    public function official_project_store_uses_tssd_evaluation_as_initial_authoritative_status(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/ProjectController.php')
        );

        $this->assertStringContainsString(
            "'status' =>\n                    ProjectStatus::TSSD_EVALUATION",
            $controller
        );

        $this->assertStringContainsString(
            'Project profile saved successfully and moved directly to TSSD Evaluation status.',
            $controller
        );
    }
}
