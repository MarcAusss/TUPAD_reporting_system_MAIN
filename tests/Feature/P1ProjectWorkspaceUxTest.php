<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectWorkspacePresenter;
use Tests\TestCase;

class P1ProjectWorkspaceUxTest extends TestCase
{
    public function test_direct_compliance_opens_the_workflow_tab_and_evaluation_stage(): void
    {
        $project = $this->project(
            ImplementationMode::DIRECT_ADMINISTRATION,
            ProjectStatus::FOR_COMPLIANCE,
        );

        $workspace = app(ProjectWorkspacePresenter::class)
            ->present($project, $this->user(UserRole::TC));

        $this->assertSame('workflow', $workspace['default_tab']);
        $this->assertSame(1, $workspace['current_stage_index']);
        $this->assertSame('Record Compliance', $workspace['action']['label']);
        $this->assertSame('evaluation', $workspace['action']['anchor']);
        $this->assertFalse($workspace['action']['external']);
        $this->assertSame('current', $workspace['stages'][1]['state']);
        $this->assertSame('upcoming', $workspace['stages'][2]['state']);
    }

    public function test_focal_direct_payment_receives_the_authorized_payment_workspace_link(): void
    {
        $project = $this->project(
            ImplementationMode::DIRECT_ADMINISTRATION,
            ProjectStatus::FOR_PAYMENT,
            77,
        );

        $workspace = app(ProjectWorkspacePresenter::class)
            ->present($project, $this->user(UserRole::FOCAL));

        $this->assertSame('financial', $workspace['default_tab']);
        $this->assertSame(6, $workspace['current_stage_index']);
        $this->assertTrue($workspace['action']['external']);
        $this->assertSame('Manage Payment of Wages', $workspace['action']['label']);
        $this->assertSame(route('payments.show', $project), $workspace['action']['href']);
    }

    public function test_completed_project_opens_overview_and_marks_every_stage_complete(): void
    {
        $project = $this->project(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::COMPLETED,
        );

        $workspace = app(ProjectWorkspacePresenter::class)
            ->present($project, $this->user(UserRole::ADMIN));

        $this->assertSame('overview', $workspace['default_tab']);
        $this->assertSame(100, $workspace['progress_percent']);
        $this->assertSame(7, $workspace['current_stage_index']);
        $this->assertNotEmpty($workspace['stages']);

        foreach ($workspace['stages'] as $stage) {
            $this->assertSame('complete', $stage['state']);
        }
    }

    public function test_project_detail_source_is_grouped_into_workspace_tabs_and_uses_module_javascript(): void
    {
        $blade = file_get_contents(resource_path('views/projects/show.blade.php'));
        $component = file_get_contents(resource_path('views/components/project-workspace-header.blade.php'));
        $appJs = file_get_contents(resource_path('js/app.js'));
        $workspaceJs = file_get_contents(resource_path('js/project-workspace.js'));

        $this->assertStringContainsString('data-project-workspace', $blade);
        $this->assertStringContainsString('data-workspace-panel="overview"', $blade);
        $this->assertStringContainsString('data-workspace-panel="beneficiaries"', $blade);
        $this->assertStringContainsString('data-workspace-panel="workflow"', $blade);
        $this->assertStringContainsString('data-workspace-panel="financial"', $blade);
        $this->assertStringContainsString('data-workspace-panel="history"', $blade);
        $this->assertStringContainsString('Project Progress', $component);
        $this->assertStringContainsString('data-workspace-tab-target', $component);
        $this->assertStringContainsString("import { initializeProjectWorkspace } from './project-workspace';", $appJs);
        $this->assertStringContainsString('initializeWorkspaceTabs', $workspaceJs);
        $this->assertStringNotContainsString("document.addEventListener('DOMContentLoaded', function ()", $blade);
    }

    private function project(
        ImplementationMode $mode,
        ProjectStatus $status,
        int $id = 1,
    ): Project {
        $project = new Project();
        $project->forceFill([
            'id' => $id,
            'implementation_mode' => $mode,
            'status' => $status,
        ]);
        $project->exists = true;

        return $project;
    }

    private function user(UserRole $role): User
    {
        $user = new User();
        $user->forceFill([
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }
}
