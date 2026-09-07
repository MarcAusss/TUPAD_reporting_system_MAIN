<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectApproval;
use Database\Seeders\Fy2025TupadProjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentSystemDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_seed_creates_sixty_ongoing_profiling_projects_without_approval_codes(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $this->assertDatabaseCount('projects', 60);
        $this->assertDatabaseCount('project_approvals', 0);
        $this->assertSame(
            60,
            Project::query()->where('status', ProjectStatus::ONGOING_PROFILING->value)->count(),
        );
        $this->assertSame(0, ProjectApproval::query()->whereNotNull('project_code')->count());
    }

    public function test_current_seed_covers_all_bicol_provinces_with_ten_projects_each(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $provinces = [
            'Albay',
            'Camarines Norte',
            'Camarines Sur',
            'Catanduanes',
            'Masbate',
            'Sorsogon',
        ];

        $this->assertEqualsCanonicalizing(
            $provinces,
            Project::query()->distinct()->pluck('province')->all(),
        );

        foreach ($provinces as $province) {
            $this->assertSame(10, Project::query()->where('province', $province)->count());
        }
    }

    public function test_source_project_codes_are_kept_as_traceability_not_as_premature_approval_records(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $traceable = Project::query()
            ->where('remarks', 'like', '%Source project code:%')
            ->count();

        $this->assertGreaterThan(0, $traceable);
        $this->assertDatabaseCount('project_approvals', 0);
    }
}
