<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectObligation;
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
            'release-of-assistance',
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

    public function test_release_queue_only_shows_for_payment_projects_with_obligation_and_without_payout(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $ready = $this->createProject(
            $tc,
            ProjectStatus::FOR_PAYMENT,
            'Ready for Release'
        );

        $notReady = $this->createProject(
            $tc,
            ProjectStatus::FOR_PAYMENT,
            'Waiting for Payment'
        );

        ProjectObligation::create([
            'project_id' =>
                $ready->id,

            /*
            |--------------------------------------------------------------------------
            | Payment Snapshot
            |--------------------------------------------------------------------------
            |
            | Match the actual project_obligations schema used by the system.
            |
            */

            'adl_number' =>
                $ready->allocation->adl->adl_number,

            'fund_sponsor' =>
                $ready->fund_sponsor,

            'partner' =>
                $ready->partner,

            'project_location' =>
                collect([
                    $ready->barangay,
                    $ready->municipality,
                    $ready->province,
                ])
                    ->filter()
                    ->implode(', '),

            'term' =>
                $ready->term?->value
                ?? (string) $ready->term,

            'beneficiaries_total' =>
                $ready->beneficiaries_total,

            'beneficiaries_female' =>
                $ready->beneficiaries_female,

            'amount' =>
                $ready->total_project_cost,

            'obligation_date' =>
                now()->toDateString(),

            'month' =>
                now()->format('F Y'),

            'payee' =>
                'TUPAD Beneficiaries',

            'remarks' =>
                'R7 release queue test obligation.',

            'recorded_by' =>
                $tc->id,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(
                route(
                    'project-workflow.index',
                    ['queue' => 'release-of-assistance']
                )
            );

        $response->assertOk();
        $response->assertSee($ready->project_title);
        $response->assertDontSee($notReady->project_title);
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
