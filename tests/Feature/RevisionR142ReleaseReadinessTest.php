<?php

namespace Tests\Feature;

use App\Models\AdlRealignment;
use App\Models\Project;
use App\Models\ProjectNoticeToProceed;
use App\Services\Projects\ImplementationStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RevisionR142ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_revision_database_columns_exist(): void
    {
        $this->assertTrue(
            Schema::hasColumn(
                'projects',
                'insurance_beneficiaries'
            ),
            'projects.insurance_beneficiaries is missing.'
        );

        $this->assertTrue(
            Schema::hasColumn(
                'adl_realignments',
                'direction'
            ),
            'adl_realignments.direction is missing.'
        );
    }

    public function test_required_revision_routes_are_registered(): void
    {
        $requiredRoutes = [
            'project-workflow.index',
            'projects.implementation.requirements',
            'projects.implementation.period',
            'adl.realignments.store',
        ];

        foreach ($requiredRoutes as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "Required route [{$routeName}] is not registered."
            );
        }
    }

    public function test_project_model_accepts_separate_insurance_beneficiaries(): void
    {
        $project = new Project();

        $this->assertContains(
            'insurance_beneficiaries',
            $project->getFillable()
        );

        $project->insurance_beneficiaries = 60;

        $this->assertSame(
            60,
            $project->insurance_beneficiaries
        );
    }

    public function test_realignment_model_accepts_explicit_direction(): void
    {
        $realignment = new AdlRealignment();

        $this->assertContains(
            'direction',
            $realignment->getFillable()
        );

        $this->assertSame(
            'tupad_to_gip',
            AdlRealignment::DIRECTION_TUPAD_TO_GIP
        );

        $this->assertSame(
            'gip_to_tupad',
            AdlRealignment::DIRECTION_GIP_TO_TUPAD
        );
    }

    public function test_notice_to_proceed_model_uses_existing_table_name(): void
    {
        $notice = new ProjectNoticeToProceed();

        $this->assertSame(
            'project_notice_to_proceeds',
            $notice->getTable()
        );
    }

    public function test_automatic_implementation_stage_service_is_resolvable(): void
    {
        $service = app(
            ImplementationStageService::class
        );

        $this->assertInstanceOf(
            ImplementationStageService::class,
            $service
        );
    }
}
