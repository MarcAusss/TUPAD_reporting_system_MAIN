<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectImplementation;
use App\Models\ProjectInsuranceEnrollment;
use App\Models\ProjectNoticeToProceed;
use App\Models\ProjectObligation;
use App\Models\ProjectOrientation;
use App\Models\ProjectPostDocument;
use App\Models\ProjectPpeDelivery;
use App\Models\User;
use App\Services\Projects\ProjectStatusEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MajorRevisionPhase6StatusEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(
            2026,
            8,
            26,
            0,
            5,
            0,
            'Asia/Manila',
        ));

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

    public function test_consolidated_and_compatibility_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('projects:sync-statuses', $commands);
        $this->assertArrayHasKey(
            'projects:sync-implementation-statuses',
            $commands,
        );
    }

    public function test_scheduled_engine_moves_project_to_ongoing_without_opening_it(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_IMPLEMENTATION,
        );

        $this->addCompleteImplementationPreparation(
            $project,
            startDate: '2026-08-26',
            endDate: '2026-09-05',
        );

        $this->artisan('projects:sync-statuses')
            ->expectsOutputToContain('1 project status(es) updated')
            ->assertSuccessful();

        $this->assertSame(
            ProjectStatus::ONGOING_IMPLEMENTATION,
            $project->fresh()->status,
        );

        $history = $project->statusHistory()
            ->where(
                'to_status',
                ProjectStatus::ONGOING_IMPLEMENTATION->value,
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertNull($history->changed_by);
        $this->assertStringContainsString(
            'start date has been reached',
            (string) $history->remarks,
        );
    }

    public function test_end_date_is_inclusive_for_post_document_transition(): void
    {
        $project = $this->createProject(
            ProjectStatus::ONGOING_IMPLEMENTATION,
        );

        $this->addCompleteImplementationPreparation(
            $project,
            startDate: '2026-08-16',
            endDate: '2026-08-26',
        );

        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            $project->fresh()->status,
        );
    }

    public function test_future_project_does_not_advance_and_sync_is_idempotent(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_IMPLEMENTATION,
        );

        $this->addCompleteImplementationPreparation(
            $project,
            startDate: '2026-08-27',
            endDate: '2026-09-06',
        );

        Artisan::call('projects:sync-statuses');
        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project->fresh()->status,
        );

        $this->assertSame(
            0,
            $project->statusHistory()
                ->where(
                    'to_status',
                    ProjectStatus::ONGOING_IMPLEMENTATION->value,
                )
                ->count(),
        );
    }

    public function test_approved_direct_administration_project_advances_when_requirements_are_complete(): void
    {
        $project = $this->createProject(ProjectStatus::APPROVED);

        $this->addPreImplementationRequirements($project);

        app(ProjectStatusEngine::class)->synchronize(
            $project,
            actorId: $this->tc->id,
        );

        $this->assertSame(
            ProjectStatus::FOR_IMPLEMENTATION,
            $project->fresh()->status,
        );

        $history = $project->statusHistory()
            ->where(
                'to_status',
                ProjectStatus::FOR_IMPLEMENTATION->value,
            )
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($this->tc->id, $history->changed_by);
        $this->assertStringContainsString(
            'Insurance, PPE delivery, and Notice to Proceed are complete',
            (string) $history->remarks,
        );
    }

    public function test_through_acp_project_is_not_moved_by_da_status_rules(): void
    {
        $project = $this->createProject(
            ProjectStatus::APPROVED,
            ImplementationMode::THROUGH_ACP,
        );

        // Even inconsistent legacy DA child records must not activate the DA
        // engine for a project officially marked Through ACP.
        $this->addCompleteImplementationPreparation(
            $project,
            startDate: '2026-08-01',
            endDate: '2026-08-20',
        );

        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::APPROVED,
            $project->fresh()->status,
        );
    }

    public function test_complete_post_documents_are_repaired_to_for_payment(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        );

        ProjectPostDocument::create([
            'project_id' => $project->id,
            'date_received' => '2026-08-25',
            'document_type' => 'DTR and Payroll',
            'date_forwarded_to_imsd' => '2026-08-26',
            'recorded_by' => $this->tc->id,
        ]);

        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status,
        );
    }

    public function test_partial_disbursement_does_not_complete_project(): void
    {
        $project = $this->createProject(ProjectStatus::FOR_PAYMENT);
        $obligation = $this->addObligation($project, '1000.00');
        $this->addDisbursement($obligation, '600.00', 'CHK-PARTIAL');

        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status,
        );
    }

    public function test_fully_disbursed_project_is_completed_by_status_engine(): void
    {
        $project = $this->createProject(ProjectStatus::FOR_PAYMENT);
        $obligation = $this->addObligation($project, '1000.00');
        $this->addDisbursement($obligation, '1000.00', 'CHK-FULL');

        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::COMPLETED,
            $project->fresh()->status,
        );

        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'from_status' => ProjectStatus::FOR_PAYMENT->value,
            'to_status' => ProjectStatus::COMPLETED->value,
            'changed_by' => null,
        ]);
    }

    public function test_manual_evaluation_status_is_never_changed_by_engine(): void
    {
        $project = $this->createProject(
            ProjectStatus::TSSD_EVALUATION,
        );

        Artisan::call('projects:sync-statuses');

        $this->assertSame(
            ProjectStatus::TSSD_EVALUATION,
            $project->fresh()->status,
        );
    }

    public function test_project_option_limits_status_synchronization_scope(): void
    {
        $selected = $this->createProject(
            ProjectStatus::ONGOING_IMPLEMENTATION,
        );
        $unselected = $this->createProject(
            ProjectStatus::ONGOING_IMPLEMENTATION,
        );

        $this->addCompleteImplementationPreparation(
            $selected,
            startDate: '2026-08-01',
            endDate: '2026-08-26',
        );
        $this->addCompleteImplementationPreparation(
            $unselected,
            startDate: '2026-08-01',
            endDate: '2026-08-26',
        );

        Artisan::call('projects:sync-statuses', [
            '--project' => [$selected->id],
        ]);

        $this->assertSame(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            $selected->fresh()->status,
        );
        $this->assertSame(
            ProjectStatus::ONGOING_IMPLEMENTATION,
            $unselected->fresh()->status,
        );
    }

    private function createProject(
        ProjectStatus $status,
        ImplementationMode $implementationMode = ImplementationMode::DIRECT_ADMINISTRATION,
    ): Project {
        $this->sequence++;

        $adl = Adl::create([
            'adl_number' => sprintf('ADL-PHASE6-%03d', $this->sequence),
            'grants' => 100000,
            'admin_cost' => 0,
            'total' => 100000,
            'created_by' => $this->tc->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'amount' => 100000,
            'created_by' => $this->tc->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => sprintf(
                'Phase 6 Status Project %03d',
                $this->sequence,
            ),
            'nature_of_work' => 'Community clean-up',
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
            'created_by' => $this->tc->id,
        ]);
    }

    private function addPreImplementationRequirements(Project $project): void
    {
        ProjectInsuranceEnrollment::create([
            'project_id' => $project->id,
            'date_enrolled' => '2026-08-01',
            'beneficiary_count' => 10,
            'amount' => 100,
            'payment_mode' => 'voucher',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectPpeDelivery::create([
            'project_id' => $project->id,
            'delivery_receipt_date' => '2026-08-01',
            'ppe_provided' => 'Safety vest',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectNoticeToProceed::create([
            'project_id' => $project->id,
            'date_issued' => '2026-08-01',
            'date_released' => '2026-08-01',
            'recorded_by' => $this->tc->id,
        ]);
    }

    private function addCompleteImplementationPreparation(
        Project $project,
        string $startDate,
        string $endDate,
    ): void {
        $this->addPreImplementationRequirements($project);

        ProjectOrientation::create([
            'project_id' => $project->id,
            'orientation_date' => '2026-08-01',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectImplementation::create([
            'project_id' => $project->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'recorded_by' => $this->tc->id,
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
            'obligation_date' => '2026-08-25',
            'month' => 'August',
            'payee' => 'Authorized Payee',
            'recorded_by' => $this->tc->id,
        ]);
    }

    private function addDisbursement(
        ProjectObligation $obligation,
        string $amount,
        string $reference,
    ): ProjectDisbursement {
        return ProjectDisbursement::create([
            'project_obligation_id' => $obligation->id,
            'amount' => $amount,
            'date_disbursed' => '2026-08-26',
            'ldap_check_number' => $reference,
            'recorded_by' => $this->tc->id,
        ]);
    }
}
