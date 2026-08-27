<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectApproval;
use Database\Seeders\CurrentSystemDemoSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsBicolTestLocations;
use Tests\TestCase;

class CurrentSystemDemoSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsBicolTestLocations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBicolTestLocations();
        $this->seed(UserSeeder::class);
    }

    public function test_seeder_creates_one_code_per_approved_project_and_multi_district_project(): void
    {
        $this->seed(CurrentSystemDemoSeeder::class);

        $project = Project::query()
            ->where('project_title', 'Albay Multi-District Community Works')
            ->firstOrFail();

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project->status
        );

        $this->assertSame(
            'TUPAD-ALB-2026-001',
            $project->approval?->project_code
        );

        $this->assertSame(
            1,
            ProjectApproval::query()
                ->where('project_id', $project->id)
                ->count()
        );

        $this->assertSame(
            3,
            $project->projectLocations()->count()
        );

        $this->assertEqualsCanonicalizing(
            [
                '1st District',
                '2nd District',
                '3rd District',
            ],
            $project->projectLocations()
                ->pluck('district')
                ->all()
        );

        $this->assertSame(
            1,
            ProjectApproval::query()
                ->where('project_code', 'TUPAD-ALB-2026-001')
                ->count()
        );
    }

    public function test_for_approval_demo_project_has_no_code_until_user_approves_it(): void
    {
        $this->seed(CurrentSystemDemoSeeder::class);

        $project = Project::query()
            ->where('project_title', 'Camarines Sur Community Rehabilitation')
            ->firstOrFail();

        $this->assertSame(
            ProjectStatus::FOR_APPROVAL,
            $project->status
        );

        $this->assertFalse(
            $project->approval()->exists()
        );
    }

    public function test_demo_projects_cover_all_bicol_provinces_and_keep_unique_project_codes(): void
    {
        $this->seed(CurrentSystemDemoSeeder::class);

        $this->assertEqualsCanonicalizing(
            [
                'Albay',
                'Camarines Norte',
                'Camarines Sur',
                'Catanduanes',
                'Masbate',
                'Sorsogon',
            ],
            Project::query()
                ->distinct()
                ->pluck('province')
                ->all()
        );

        $codes = ProjectApproval::query()
            ->whereNotNull('project_code')
            ->pluck('project_code')
            ->all();

        $this->assertContains('TUPAD-ALB-2026-001', $codes);
        $this->assertContains('TUPAD-CAN-2026-001', $codes);
        $this->assertContains('TUPAD-CAT-2026-001', $codes);
        $this->assertContains('TUPAD-MAS-2026-001', $codes);
        $this->assertContains('TUPAD-SOR-2026-001', $codes);

        $this->assertCount(
            count(array_unique($codes)),
            $codes
        );
    }
}
