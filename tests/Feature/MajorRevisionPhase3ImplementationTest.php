<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase3ImplementationTest extends TestCase
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
                9,
                0,
                0,
                'Asia/Manila'
            )
        );

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_complete_insurance_ppe_and_ntp_auto_moves_approved_da_project_to_for_implementation(): void
    {
        $project = $this->createProject(
            'R3 Pre-Implementation Project',
            ProjectStatus::APPROVED,
            ImplementationMode::DIRECT_ADMINISTRATION
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.requirements',
                    $project
                ),
                $this->requirementsPayload()
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project->fresh()->status
        );

        $this->assertDatabaseHas(
            'project_insurance_enrollments',
            [
                'project_id' => $project->id,
                'beneficiary_count' => 50,
                'amount' => 2500,
                'payment_mode' => 'voucher',
                'or_number' => 'OR-R3-001',
                'policy_number' => 'POL-R3-001',
            ]
        );

        $this->assertDatabaseHas(
            'project_ppe_deliveries',
            [
                'project_id' => $project->id,
                'ppe_provided' => 'Safety vest, gloves, boots',
            ]
        );

        $this->assertDatabaseHas(
            'project_notice_to_proceeds',
            [
                'project_id' => $project->id,
            ]
        );
    }

    public function test_orientation_and_work_period_are_locked_until_project_reaches_for_implementation(): void
    {
        $project = $this->createProject(
            'R3 Locked Scheduling Project',
            ProjectStatus::APPROVED,
            ImplementationMode::DIRECT_ADMINISTRATION
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.orientation',
                    $project
                ),
                [
                    'orientation_date' => '2026-08-26',
                ]
            )
            ->assertForbidden();

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $project
                ),
                [
                    'start_date' => '2026-08-26',
                ]
            )
            ->assertForbidden();

        $response = $this
            ->actingAs($this->tc)
            ->get(route('projects.show', $project));

        $response
            ->assertOk()
            ->assertSee(
                'Orientation and Work Period are not open yet'
            )
            ->assertSee(
                'Complete Insurance, PPE, and Notice to Proceed first.'
            );
    }

    public function test_orientation_and_work_period_auto_move_project_to_ongoing_when_start_date_is_today(): void
    {
        $project = $this->createProject(
            'R3 Ongoing Project',
            ProjectStatus::APPROVED,
            ImplementationMode::DIRECT_ADMINISTRATION
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.requirements',
                    $project
                ),
                $this->requirementsPayload()
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project->fresh()->status
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.orientation',
                    $project
                ),
                [
                    'orientation_date' => '2026-08-26',
                    'remarks' => 'Orientation completed.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project->fresh()->status
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $project
                ),
                [
                    'start_date' => '2026-08-26',
                ]
            )
            ->assertRedirect();

        $project->refresh();

        $this->assertSame(
            ProjectStatus::ONGOING_IMPLEMENTATION,
            $project->status
        );

        $this->assertSame(
            '2026-09-15',
            $project->implementation->end_date->toDateString()
        );
    }

    public function test_work_period_auto_moves_project_to_post_docs_when_end_date_has_already_passed(): void
    {
        $project = $this->createProject(
            'R3 Ended Project',
            ProjectStatus::APPROVED,
            ImplementationMode::DIRECT_ADMINISTRATION
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.requirements',
                    $project
                ),
                $this->requirementsPayload()
            )
            ->assertRedirect();

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.orientation',
                    $project
                ),
                [
                    'orientation_date' => '2026-07-20',
                ]
            )
            ->assertRedirect();

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.period',
                    $project
                ),
                [
                    'start_date' => '2026-07-20',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            $project->fresh()->status
        );
    }

    public function test_da_implementation_forms_do_not_apply_to_through_acp_projects(): void
    {
        $project = $this->createProject(
            'R3 Through ACP Project',
            ProjectStatus::APPROVED,
            ImplementationMode::THROUGH_ACP
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.requirements',
                    $project
                ),
                $this->requirementsPayload()
            )
            ->assertForbidden();

        $this
            ->actingAs($this->tc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Through ACP Project')
            ->assertSee(
                'apply only to Direct Administration projects.'
            );

        $this
            ->actingAs($this->tc)
            ->get(
                route(
                    'project-workflow.index',
                    [
                        'queue' => 'implementation',
                    ]
                )
            )
            ->assertOk()
            ->assertDontSee('R3 Through ACP Project');
    }

    public function test_implementation_board_requires_pre_requirements_before_work_period_action(): void
    {
        $project = $this->createProject(
            'R3 Board Requirements Project',
            ProjectStatus::APPROVED,
            ImplementationMode::DIRECT_ADMINISTRATION
        );

        $this
            ->actingAs($this->tc)
            ->get(
                route(
                    'project-workflow.index',
                    [
                        'queue' => 'implementation',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('R3 Board Requirements Project')
            ->assertSee('Complete Requirements');

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.requirements',
                    $project
                ),
                $this->requirementsPayload()
            )
            ->assertRedirect();

        $this
            ->actingAs($this->tc)
            ->get(
                route(
                    'project-workflow.index',
                    [
                        'queue' => 'implementation',
                    ]
                )
            )
            ->assertOk()
            ->assertSee('R3 Board Requirements Project')
            ->assertSee('Set Work Period');
    }

    private function requirementsPayload(): array
    {
        return [
            'insurance' => [
                'date_enrolled' => '2026-08-26',
                'payment_mode' => 'voucher',
                'or_number' => 'OR-R3-001',
                'policy_number' => 'POL-R3-001',
                'remarks' => 'Insurance enrolled.',
            ],
            'ppe' => [
                'delivery_receipt_date' => '2026-08-26',
                'ppe_provided' => 'Safety vest, gloves, boots',
                'remarks' => 'PPE delivered.',
            ],
            'ntp' => [
                'date_issued' => '2026-08-26',
                'date_released' => '2026-08-26',
                'remarks' => 'NTP released.',
            ],
        ];
    }

    private function createProject(
        string $title,
        ProjectStatus $status,
        ImplementationMode $mode
    ): Project {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R3-' . uniqid(),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => $title,
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => '2026-08-01',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => $mode,
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,
            'insurance_beneficiaries' => 50,
            'wage_rate' => 455,
            'wages_total' => 455000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 2500,
            'total_project_cost' => 457500,
            'status' => $status,
            'created_by' => $this->tc->id,
        ]);
    }
}
