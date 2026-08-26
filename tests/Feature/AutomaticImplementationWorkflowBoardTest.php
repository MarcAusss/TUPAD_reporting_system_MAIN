<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectImplementation;
use App\Models\ProjectInsuranceEnrollment;
use App\Models\ProjectNoticeToProceed;
use App\Models\ProjectOrientation;
use App\Models\ProjectPpeDelivery;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticImplementationWorkflowBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(
                2026,
                8,
                26,
                8,
                0,
                0,
                'Asia/Manila'
            )
        );

        $this->tc =
            User::factory()->create([
                'role' =>
                    UserRole::TC,

                'is_active' =>
                    true,
            ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_project_without_work_period_stays_in_for_implementation_column(): void
    {
        $project =
            $this->createProject(
                'No Work Period Project',
                ProjectStatus::APPROVED
            );

        $response =
            $this
                ->actingAs(
                    $this->tc
                )
                ->get(
                    route(
                        'project-workflow.index',
                        [
                            'queue' =>
                                'implementation',
                        ]
                    )
                );

        $response->assertOk();

        $response->assertSee(
            'For Implementation'
        );

        $response->assertSee(
            $project->project_title
        );

        $response->assertSee(
            'Set Work Period'
        );
    }

    public function test_future_start_date_stays_for_implementation(): void
    {
        $project =
            $this->createPreparedProject(
                'Future Project',
                '2026-08-27',
                '2026-09-16',
                ProjectStatus::FOR_IMPLEMENTATION
            );

        $this
            ->actingAs(
                $this->tc
            )
            ->get(
                route(
                    'project-workflow.index',
                    [
                        'queue' =>
                            'implementation',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $project->project_title
            );

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project
                ->fresh()
                ->status
        );
    }

    public function test_project_moves_to_ongoing_on_start_date(): void
    {
        $project =
            $this->createPreparedProject(
                'Starts Today Project',
                '2026-08-26',
                '2026-09-15',
                ProjectStatus::FOR_IMPLEMENTATION
            );

        $this
            ->actingAs(
                $this->tc
            )
            ->get(
                route(
                    'project-workflow.index',
                    [
                        'queue' =>
                            'implementation',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $project->project_title
            );

        $this->assertSame(
            ProjectStatus::ONGOING_IMPLEMENTATION,
            $project
                ->fresh()
                ->status
        );
    }

    public function test_project_moves_to_post_docs_on_end_date(): void
    {
        $project =
            $this->createPreparedProject(
                'Ended Today Project',
                '2026-08-06',
                '2026-08-26',
                ProjectStatus::ONGOING_IMPLEMENTATION
            );

        $this
            ->actingAs(
                $this->tc
            )
            ->get(
                route(
                    'project-workflow.index',
                    [
                        'queue' =>
                            'implementation',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $project->project_title
            )
            ->assertSee(
                'Submit Post Documents'
            );

        $this->assertSame(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            $project
                ->fresh()
                ->status
        );
    }

    private function createPreparedProject(
        string $title,
        string $startDate,
        string $endDate,
        ProjectStatus $status
    ): Project {
        $project =
            $this->createProject(
                $title,
                $status
            );

        ProjectInsuranceEnrollment::create([
            'project_id' =>
                $project->id,

            'date_enrolled' =>
                '2026-08-01',

            'beneficiary_count' =>
                50,

            'amount' =>
                2500,

            'payment_mode' =>
                'voucher',

            'recorded_by' =>
                $this->tc->id,
        ]);

        ProjectPpeDelivery::create([
            'project_id' =>
                $project->id,

            'delivery_receipt_date' =>
                '2026-08-01',

            'ppe_provided' =>
                'Safety vest',

            'recorded_by' =>
                $this->tc->id,
        ]);

        ProjectNoticeToProceed::create([
            'project_id' =>
                $project->id,

            'date_issued' =>
                '2026-08-01',

            'date_released' =>
                '2026-08-01',

            'recorded_by' =>
                $this->tc->id,
        ]);

        ProjectOrientation::create([
            'project_id' =>
                $project->id,

            'orientation_date' =>
                '2026-08-01',

            'recorded_by' =>
                $this->tc->id,
        ]);

        ProjectImplementation::create([
            'project_id' =>
                $project->id,

            'start_date' =>
                $startDate,

            'end_date' =>
                $endDate,

            'recorded_by' =>
                $this->tc->id,
        ]);

        return $project;
    }

    private function createProject(
        string $title,
        ProjectStatus $status
    ): Project {
        $focal =
            User::factory()->create([
                'role' =>
                    UserRole::FOCAL,

                'is_active' =>
                    true,
            ]);

        $adl =
            Adl::create([
                'adl_number' =>
                    'ADL-PHASE6-'
                    . str_pad(
                        (string) random_int(
                            1,
                            99999
                        ),
                        5,
                        '0',
                        STR_PAD_LEFT
                    ),

                'grants' =>
                    1000000,

                'admin_cost' =>
                    0,

                'total' =>
                    1000000,

                'created_by' =>
                    $focal->id,
            ]);

        $allocation =
            AdlAllocation::create([
                'adl_id' =>
                    $adl->id,

                'location' =>
                    'Albay',

                'amount' =>
                    1000000,

                'created_by' =>
                    $focal->id,
            ]);

        return Project::create([
            'adl_allocation_id' =>
                $allocation->id,

            'date_received' =>
                '2026-08-01',

            'project_title' =>
                $title,

            'nature_of_work' =>
                'Community clean-up',

            'fund_sponsor' =>
                'DOLE Regional Office V',

            'partner' =>
                'LGU Albay',

            'project_series' =>
                'Regular TUPAD 2026',

            'tevs_date_verified' =>
                '2026-08-01',

            'province' =>
                'Albay',

            'district' =>
                '2nd District',

            'municipality' =>
                'Legazpi City',

            'barangay' =>
                'Rawis',

            'implementation_mode' =>
                'direct_administration',

            'number_of_days' =>
                20,

            'term' =>
                'short_term',

            'beneficiaries_total' =>
                50,

            'beneficiaries_female' =>
                25,

            'wage_rate' =>
                455,

            'wages_total' =>
                455000,

            'ppe_total' =>
                0,

            'insurance_rate' =>
                50,

            'insurance_total' =>
                2500,

            'total_project_cost' =>
                457500,

            'status' =>
                $status,

            'created_by' =>
                $this->tc->id,
        ]);
    }
}
