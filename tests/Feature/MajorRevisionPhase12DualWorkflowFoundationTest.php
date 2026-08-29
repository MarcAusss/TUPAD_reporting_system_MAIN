<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectObligation;
use App\Models\User;
use App\Services\Payments\ProjectPaymentService;
use App\Services\Projects\ProjectWorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MajorRevisionPhase12DualWorkflowFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function authoritative_happy_paths_match_both_implementation_modes(): void
    {
        $workflow = app(ProjectWorkflowDefinition::class);

        $this->assertSame([
            ProjectStatus::ONGOING_PROFILING,
            ProjectStatus::TSSD_EVALUATION,
            ProjectStatus::FOR_APPROVAL,
            ProjectStatus::APPROVED,
            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION,
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            ProjectStatus::FOR_PAYMENT,
            ProjectStatus::COMPLETED,
        ], $workflow->happyPathFor(ImplementationMode::DIRECT_ADMINISTRATION));

        $this->assertSame([
            ProjectStatus::ONGOING_PROFILING,
            ProjectStatus::TSSD_EVALUATION,
            ProjectStatus::FOR_APPROVAL,
            ProjectStatus::APPROVED,
            ProjectStatus::FOR_PAYMENT,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            ProjectStatus::FOR_IMPLEMENTATION,
            ProjectStatus::ONGOING_IMPLEMENTATION,
            ProjectStatus::FOR_LIQUIDATION,
            ProjectStatus::PARTIALLY_LIQUIDATED,
            ProjectStatus::COMPLETED,
        ], $workflow->happyPathFor(ImplementationMode::THROUGH_ACP));
    }

    #[Test]
    public function new_through_acp_statuses_have_official_labels(): void
    {
        $this->assertSame(
            'For Release of Check to Proponent',
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT->label(),
        );
        $this->assertSame(
            'For Liquidation',
            ProjectStatus::FOR_LIQUIDATION->label(),
        );
        $this->assertSame(
            'Partially Liquidated',
            ProjectStatus::PARTIALLY_LIQUIDATED->label(),
        );
    }

    #[Test]
    public function through_acp_cannot_use_direct_administration_wage_payment_interface(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_PAYMENT,
            ImplementationMode::THROUGH_ACP,
        );

        $this->actingAs($this->admin)
            ->get(route('payments.show', $project))
            ->assertForbidden();
    }

    #[Test]
    public function fully_disbursed_through_acp_project_is_not_completed_by_da_payment_service(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_PAYMENT,
            ImplementationMode::THROUGH_ACP,
        );

        $obligation = $this->addObligation($project, '1000.00');
        $this->addDisbursement($obligation, '1000.00');

        $completed = app(ProjectPaymentService::class)
            ->synchronizeCompletion($project, $this->admin->id);

        $this->assertFalse($completed);
        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status,
        );
    }

    #[Test]
    public function through_acp_cannot_submit_direct_administration_post_documents(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            ImplementationMode::THROUGH_ACP,
        );

        $this->actingAs($this->admin)
            ->post(route('projects.post-documents.store', $project), [])
            ->assertForbidden();
    }

    private function createProject(
        ProjectStatus $status,
        ImplementationMode $implementationMode,
    ): Project {
        $this->sequence++;

        $adl = Adl::create([
            'adl_number' => sprintf('ADL-PHASE12A-%03d', $this->sequence),
            'grants' => 100000,
            'admin_cost' => 0,
            'total' => 100000,
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'amount' => 100000,
            'created_by' => $this->admin->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-29',
            'project_title' => sprintf(
                'Phase 12A Dual Workflow Project %03d',
                $this->sequence,
            ),
            'nature_of_work' => 'Community work',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => $implementationMode,
            'number_of_days' => 10,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => 10,
            'wages_total' => 1000,
            'ppe_total' => 0,
            'insurance_rate' => 10,
            'insurance_beneficiaries' => 10,
            'insurance_total' => 100,
            'total_project_cost' => 1100,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);
    }

    private function addObligation(
        Project $project,
        string $amount,
    ): ProjectObligation {
        return ProjectObligation::create([
            'project_id' => $project->id,
            'tranche_number' => 1,
            'adl_number' => $project->allocation->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'project_location' => $project->full_location,
            'term' => $project->term->value,
            'beneficiaries_total' => $project->beneficiaries_total,
            'beneficiaries_female' => $project->beneficiaries_female,
            'amount' => $amount,
            'obligation_date' => '2026-08-29',
            'month' => 'August',
            'payee' => 'ACP Proponent',
            'recorded_by' => $this->admin->id,
        ]);
    }

    private function addDisbursement(
        ProjectObligation $obligation,
        string $amount,
    ): ProjectDisbursement {
        return ProjectDisbursement::create([
            'project_obligation_id' => $obligation->id,
            'amount' => $amount,
            'date_disbursed' => '2026-08-29',
            'ldap_check_number' => 'ACP-GUARD-001',
            'recorded_by' => $this->admin->id,
        ]);
    }
}
