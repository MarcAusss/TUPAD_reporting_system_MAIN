<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\ProjectWorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MajorRevisionPhase12EFinalReleaseVerificationTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_final_workflow_definitions_and_acp_release_schema_are_present(): void
    {
        $workflow = app(ProjectWorkflowDefinition::class);

        $this->assertSame(
            [
                ProjectStatus::ONGOING_PROFILING,
                ProjectStatus::TSSD_EVALUATION,
                ProjectStatus::FOR_APPROVAL,
                ProjectStatus::APPROVED,
                ProjectStatus::FOR_IMPLEMENTATION,
                ProjectStatus::ONGOING_IMPLEMENTATION,
                ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                ProjectStatus::FOR_PAYMENT,
                ProjectStatus::COMPLETED,
            ],
            $workflow->happyPathFor(ImplementationMode::DIRECT_ADMINISTRATION),
        );

        $this->assertSame(
            [
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
            ],
            $workflow->happyPathFor(ImplementationMode::THROUGH_ACP),
        );

        $this->assertTrue(Schema::hasColumns('project_acp_payments', [
            'project_id', 'amount', 'payment_date', 'payee', 'recorded_by',
        ]));
        $this->assertTrue(Schema::hasColumns('project_acp_check_releases', [
            'project_id', 'check_number', 'check_date', 'amount', 'released_date', 'released_to', 'recorded_by',
        ]));
        $this->assertTrue(Schema::hasColumns('project_acp_liquidations', [
            'project_id', 'liquidation_date', 'amount', 'recorded_by',
        ]));
    }

    public function test_release_verifier_passes_for_consistent_through_acp_transaction_chain(): void
    {
        ['project' => $project, 'tc' => $tc, 'focal' => $focal] = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::COMPLETED,
            '5000.00',
        );

        $project->acpPayment()->create([
            'amount' => '5000.00',
            'payment_date' => '2026-08-10',
            'payee' => 'Authorized ACP Proponent',
            'payment_reference' => 'MR12E-DV-001',
            'recorded_by' => $focal->id,
        ]);

        $project->acpCheckRelease()->create([
            'check_number' => 'MR12E-CHECK-001',
            'check_date' => '2026-08-11',
            'amount' => '5000.00',
            'released_date' => '2026-08-12',
            'released_to' => 'Authorized ACP Proponent',
            'recorded_by' => $focal->id,
        ]);

        $project->implementation()->create([
            'start_date' => '2026-08-13',
            'end_date' => '2026-08-23',
            'recorded_by' => $tc->id,
        ]);

        $project->acpLiquidations()->create([
            'liquidation_date' => '2026-08-24',
            'amount' => '5000.00',
            'liquidation_reference' => 'MR12E-LIQ-001',
            'recorded_by' => $focal->id,
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Release verification PASSED')
            ->assertExitCode(0);
    }

    public function test_release_verifier_blocks_acp_payment_that_does_not_match_approved_project_cost(): void
    {
        ['project' => $project, 'focal' => $focal] = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            '5000.00',
        );

        $project->acpPayment()->create([
            'amount' => '4500.00',
            'payment_date' => '2026-08-10',
            'payee' => 'Authorized ACP Proponent',
            'recorded_by' => $focal->id,
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('does not match the project\'s approved total project cost')
            ->assertExitCode(1);
    }

    public function test_release_verifier_blocks_liquidation_above_released_check_amount(): void
    {
        ['project' => $project, 'tc' => $tc, 'focal' => $focal] = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::PARTIALLY_LIQUIDATED,
            '5000.00',
        );

        $project->acpPayment()->create([
            'amount' => '5000.00',
            'payment_date' => '2026-08-10',
            'payee' => 'Authorized ACP Proponent',
            'recorded_by' => $focal->id,
        ]);

        $project->acpCheckRelease()->create([
            'check_number' => 'MR12E-CHECK-OVER',
            'check_date' => '2026-08-11',
            'amount' => '5000.00',
            'released_date' => '2026-08-12',
            'released_to' => 'Authorized ACP Proponent',
            'recorded_by' => $focal->id,
        ]);

        $project->implementation()->create([
            'start_date' => '2026-08-13',
            'end_date' => '2026-08-23',
            'recorded_by' => $tc->id,
        ]);

        $project->acpLiquidations()->create([
            'liquidation_date' => '2026-08-24',
            'amount' => '5100.00',
            'recorded_by' => $focal->id,
        ]);

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('liquidation records exceeding the released check amount')
            ->assertExitCode(1);
    }

    public function test_release_verifier_blocks_cross_mode_only_statuses(): void
    {
        $this->createProject(
            ImplementationMode::DIRECT_ADMINISTRATION,
            ProjectStatus::FOR_LIQUIDATION,
            '5000.00',
        );

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Direct Administration project(s) use a Through ACP-only workflow status')
            ->assertExitCode(1);
    }

    /**
     * @return array{project: Project, tc: User, focal: User}
     */
    private function createProject(
        ImplementationMode $mode,
        ProjectStatus $status,
        string $totalProjectCost,
    ): array {
        $this->sequence++;

        $tc = User::factory()->create([
            'name' => 'Phase 12E TC '.$this->sequence,
            'username' => 'phase12e-tc-'.$this->sequence,
            'email' => 'phase12e-tc-'.$this->sequence.'@example.test',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('safe-test-password'),
        ]);

        $focal = User::factory()->create([
            'name' => 'Phase 12E Focal '.$this->sequence,
            'username' => 'phase12e-focal-'.$this->sequence,
            'email' => 'phase12e-focal-'.$this->sequence.'@example.test',
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'password' => Hash::make('safe-test-password'),
        ]);

        $province = Province::create([
            'code' => '05'.str_pad((string) $this->sequence, 7, '0', STR_PAD_LEFT),
            'name' => 'Phase 12E Province '.$this->sequence,
            'is_active' => true,
        ]);

        $municipality = Municipality::create([
            'province_id' => $province->id,
            'code' => '050'.str_pad((string) $this->sequence, 6, '0', STR_PAD_LEFT),
            'name' => 'Phase 12E Municipality '.$this->sequence,
            'district' => '1st District',
            'is_city' => false,
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'code' => '0500'.str_pad((string) $this->sequence, 5, '0', STR_PAD_LEFT),
            'name' => 'Phase 12E Barangay '.$this->sequence,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-P12E-'.str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT),
            'grants' => '10000.00',
            'admin_cost' => '0.00',
            'total' => '10000.00',
            'created_by' => $tc->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Phase 12E Partner '.$this->sequence,
            'location' => $municipality->name.', '.$province->name,
            'province' => $province->name,
            'municipality' => $municipality->name,
            'amount' => '10000.00',
            'grant_amount' => '10000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '10000.00',
            'created_by' => $tc->id,
        ]);

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 12E Project '.$this->sequence,
            'nature_of_work' => 'Release verification project.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Phase 12E Partner '.$this->sequence,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'implementation_mode' => $mode,
            'number_of_days' => 10,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => '400.00',
            'wages_total' => $mode === ImplementationMode::DIRECT_ADMINISTRATION ? $totalProjectCost : '4000.00',
            'ppe_total' => $mode === ImplementationMode::THROUGH_ACP ? '500.00' : '0.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => $mode === ImplementationMode::THROUGH_ACP ? '500.00' : '0.00',
            'total_project_cost' => $totalProjectCost,
            'status' => $status,
            'created_by' => $tc->id,
        ]);

        $location = ProjectLocation::create([
            'project_id' => $project->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'district' => '1st District',
            'sort_order' => 1,
        ]);

        $location->barangays()->sync([
            $barangay->id => [
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 6,
            ],
        ]);

        return compact('project', 'tc', 'focal');
    }
}
