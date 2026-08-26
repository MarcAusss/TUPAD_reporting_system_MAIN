<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase2ComplianceApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;
    private AdlAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tc =
            User::factory()->create([
                'role' => UserRole::TC,
                'is_active' => true,
            ]);

        $focal =
            User::factory()->create([
                'role' => UserRole::FOCAL,
                'is_active' => true,
            ]);

        $adl =
            Adl::create([
                'adl_number' =>
                    'ADL-MR2-001',
                'grants' =>
                    2000000,
                'admin_cost' =>
                    0,
                'total' =>
                    2000000,
                'created_by' =>
                    $focal->id,
            ]);

        $this->allocation =
            AdlAllocation::create([
                'adl_id' =>
                    $adl->id,
                'fund_sponsor' =>
                    'DOLE RO V',
                'partner' =>
                    'LGU Albay',
                'location' =>
                    'Albay',
                'amount' =>
                    2000000,
                'created_by' =>
                    $focal->id,
            ]);
    }

    public function test_compliance_save_records_date_and_moves_directly_to_for_approval(): void
    {
        $project =
            $this->createProject(
                'MR2 Compliance Project',
                ProjectStatus::TSSD_EVALUATION
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $project
                ),
                [
                    'result' =>
                        'for_compliance',
                    'findings' =>
                        'Missing signed certification.',
                    'required_documents' =>
                        'Signed certification',
                    'remarks' =>
                        'Return for compliance.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_COMPLIANCE,
            $project->fresh()->status
        );

        $response =
            $this
                ->actingAs($this->tc)
                ->get(
                    route(
                        'projects.show',
                        $project
                    )
                );

        $response
            ->assertOk()
            ->assertSee(
                'Project for Compliance'
            )
            ->assertSee('Aging')
            ->assertSee(
                'Date of Compliance'
            )
            ->assertSee(
                'Save Compliance'
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.compliance.store',
                    $project
                ),
                [
                    'compliance_date' =>
                        now()->toDateString(),
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_APPROVAL,
            $project->fresh()->status
        );

        $evaluation =
            $project
                ->evaluations()
                ->where(
                    'result',
                    'for_compliance'
                )
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            now()->toDateString(),
            $evaluation
                ->compliance_date
                ->toDateString()
        );

        $this->assertSame(
            $this->tc->id,
            $evaluation->complied_by
        );
    }

    public function test_compliance_date_cannot_precede_the_tssd_finding_date(): void
    {
        $project =
            $this->createProject(
                'MR2 Compliance Date Project',
                ProjectStatus::TSSD_EVALUATION
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $project
                ),
                [
                    'result' =>
                        'for_compliance',
                    'findings' =>
                        'Finding',
                    'required_documents' =>
                        'Requirement',
                ]
            )
            ->assertRedirect();

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.compliance.store',
                    $project
                ),
                [
                    'compliance_date' =>
                        now()
                            ->subDay()
                            ->toDateString(),
                ]
            )
            ->assertSessionHasErrors(
                'compliance_date'
            );

        $this->assertSame(
            ProjectStatus::FOR_COMPLIANCE,
            $project->fresh()->status
        );
    }

    public function test_project_under_for_compliance_cannot_skip_compliance_date_by_re_evaluating(): void
    {
        $project =
            $this->createProject(
                'MR2 No Bypass Project',
                ProjectStatus::FOR_COMPLIANCE
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $project
                ),
                [
                    'result' =>
                        'for_approval',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            ProjectStatus::FOR_COMPLIANCE,
            $project->fresh()->status
        );
    }

    public function test_approval_normalizes_project_code_and_auto_updates_status_to_approved(): void
    {
        $project =
            $this->createProject(
                'MR2 Approval Project',
                ProjectStatus::FOR_APPROVAL
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.approval.store',
                    $project
                ),
                [
                    'approval_date' =>
                        now()->toDateString(),
                    'project_code' =>
                        '  tupad-alb-2026-099  ',
                    'remarks' =>
                        'Approved.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ProjectStatus::APPROVED,
            $project->fresh()->status
        );

        $this->assertDatabaseHas(
            'project_approvals',
            [
                'project_id' =>
                    $project->id,
                'project_code' =>
                    'TUPAD-ALB-2026-099',
            ]
        );
    }

    public function test_project_code_uniqueness_is_checked_after_normalization(): void
    {
        $first =
            $this->createProject(
                'First Approved Project',
                ProjectStatus::APPROVED
            );

        ProjectApproval::create([
            'project_id' =>
                $first->id,
            'approval_date' =>
                now()->toDateString(),
            'project_code' =>
                'TUPAD-ALB-2026-100',
            'remarks' =>
                null,
            'approved_by' =>
                $this->tc->id,
            'approved_at' =>
                now(),
        ]);

        $second =
            $this->createProject(
                'Second Approval Project',
                ProjectStatus::FOR_APPROVAL
            );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.approval.store',
                    $second
                ),
                [
                    'approval_date' =>
                        now()->toDateString(),
                    'project_code' =>
                        'tupad-alb-2026-100',
                ]
            )
            ->assertSessionHasErrors(
                'project_code'
            );

        $this->assertDatabaseMissing(
            'project_approvals',
            [
                'project_id' =>
                    $second->id,
            ]
        );
    }

    private function createProject(
        string $title,
        ProjectStatus $status
    ): Project {
        return Project::create([
            'adl_allocation_id' =>
                $this->allocation->id,
            'date_received' =>
                now()->toDateString(),
            'project_title' =>
                $title,
            'nature_of_work' =>
                'Community clean-up',
            'fund_sponsor' =>
                'DOLE RO V',
            'partner' =>
                'LGU Albay',
            'project_series' =>
                'Regular TUPAD 2026',
            'tevs_date_verified' =>
                now()->toDateString(),
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
