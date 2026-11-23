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

    public function test_current_seed_creates_sixty_tssd_evaluation_projects_without_approval_codes(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $this->assertDatabaseCount('projects', 60);
        $this->assertDatabaseCount('project_approvals', 0);
        $this->assertSame(
            60,
            Project::query()->where('status', ProjectStatus::TSSD_EVALUATION->value)->count(),
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

    public function test_seeded_projects_have_ppe_items_that_add_up_to_their_ppe_total(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $projects = Project::query()->with('ppeItems')->get();

        foreach ($projects as $project) {
            $itemsCents = (int) $project->ppeItems->sum(fn ($item): int => (int) round((float) $item->total_amount * 100));

            $this->assertSame((int) round((float) $project->ppe_total * 100), $itemsCents, $project->project_title.' PPE items do not match its PPE total.');

            foreach ($project->ppeItems as $item) {
                $this->assertLessThanOrEqual($project->beneficiaries_total, $item->beneficiary_count);
                $this->assertSame(
                    (int) round((float) $item->total_amount * 100),
                    (int) round($item->beneficiary_count * (float) $item->unit_amount * $item->quantity * 100),
                );
            }
        }

        // Standard sets: Shirt + Hat for everyone; hazardous work adds Mask, Gloves, Rubber Boots.
        $this->assertTrue($projects->filter(fn ($project) => $project->ppe_total > 0)->every(fn ($project) => $project->ppeItems->contains('product', 'TUPAD Shirt')));
        $this->assertTrue($projects->contains(fn ($project) => $project->ppeItems->contains('product', 'Rubber Boots')));
        $this->assertSame(0, \App\Models\ProjectPpeItem::query()->where('product', 'Other PPE')->count());
    }

    public function test_seeded_projects_carry_an_insurance_budget_and_reach_the_gsis_enrollment_officer_in_evaluation(): void
    {
        $this->seed(Fy2025TupadProjectSeeder::class);

        $gpai = app(\App\Services\Gsis\GpaiEnrollmentService::class);

        // Every sample project has a reconciled insurance budget.
        Project::query()->get()->each(function (Project $project): void {
            $this->assertGreaterThan(0, (int) $project->insurance_beneficiaries);
            $this->assertSame(
                round((int) $project->insurance_beneficiaries * (float) $project->insurance_rate, 2),
                round((float) $project->insurance_total, 2),
            );
        });

        // All 60 are in TSSD Evaluation and already wait for GPAI enrollment (before approval).
        $this->assertSame(60, $gpai->eligibleProjectsQuery()->count());

        $project = Project::query()->firstOrFail();
        $annie = \App\Models\User::query()->where('username', 'Annie')->firstOrFail();
        $this->actingAs($annie)
            ->get(route('gsis.enrollments.create'))
            ->assertOk()
            ->assertSee($project->project_title);
    }
}
