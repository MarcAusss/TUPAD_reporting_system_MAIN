<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_can_open_each_project_workflow_queue(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        foreach ([
            'tssd-evaluation',
            'for-compliance',
            'for-approval',
            'implementation',
            'post-documents',
        ] as $queue) {
            $this
                ->actingAs($tc)
                ->get(
                    route(
                        'project-workflow.index',
                        ['queue' => $queue]
                    )
                )
                ->assertOk();
        }
    }

    public function test_focal_cannot_access_tc_project_workflow_queues(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $this
            ->actingAs($focal)
            ->get(
                route(
                    'project-workflow.index',
                    ['queue' => 'tssd-evaluation']
                )
            )
            ->assertForbidden();
    }

    public function test_tssd_queue_only_shows_projects_waiting_for_tssd_evaluation(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $evaluation = $this->createProject(
            $tc,
            ProjectStatus::TSSD_EVALUATION,
            'Evaluation Project'
        );

        $compliance = $this->createProject(
            $tc,
            ProjectStatus::FOR_COMPLIANCE,
            'Compliance Project'
        );

        $response = $this
            ->actingAs($tc)
            ->get(
                route(
                    'project-workflow.index',
                    ['queue' => 'tssd-evaluation']
                )
            );

        $response->assertOk();
        $response->assertSee(
            $evaluation->project_title
        );
        $response->assertDontSee(
            $compliance->project_title
        );
    }

    public function test_for_compliance_queue_only_shows_projects_waiting_for_compliance(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $evaluation = $this->createProject(
            $tc,
            ProjectStatus::TSSD_EVALUATION,
            'Evaluation Project'
        );

        $compliance = $this->createProject(
            $tc,
            ProjectStatus::FOR_COMPLIANCE,
            'Compliance Project'
        );

        $response = $this
            ->actingAs($tc)
            ->get(
                route(
                    'project-workflow.index',
                    ['queue' => 'for-compliance']
                )
            );

        $response->assertOk();
        $response->assertSee(
            $compliance->project_title
        );
        $response->assertDontSee(
            $evaluation->project_title
        );
        $response->assertSee('Aging');
    }

    public function test_release_of_assistance_queue_is_deprecated(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $this->actingAs($tc)
            ->get('/project-workflow/release-of-assistance')
            ->assertNotFound();
    }
    private function createProject(
        User $tc,
        ProjectStatus $status,
        string $title
    ): Project {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' =>
                'ADL-R7-'.str_pad(
                    (string) random_int(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                ),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => null,
            'partner' => null,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => $title,
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => now()->toDateString(),
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,
            'wage_rate' => 455,
            'wages_total' => 455000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 2500,
            'total_project_cost' => 457500,
            'status' => $status,
            'created_by' => $tc->id,
        ]);
    }
}
