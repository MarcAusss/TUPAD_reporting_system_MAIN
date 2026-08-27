<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinalProjectWorkflowTerminologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_release_of_assistance_action_is_deprecated(): void
    {
        $this->assertFalse(Route::has('projects.payout.store'));

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $this->actingAs($tc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Release of Assistance');
    }

    public function test_focal_obligation_and_full_disbursement_complete_project(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $project = $this->createForPaymentProject($tc, $focal);

        $this->actingAs($focal)
            ->post(route('projects.payment.store', $project), [
                'tranche_number' => 1,
                'amount' => '455000.00',
                'obligation_date' => now()->toDateString(),
                'payee' => 'TUPAD Beneficiaries',
            ])
            ->assertRedirect(route('payments.show', $project));

        $obligation = $project->obligations()->firstOrFail();

        $this->actingAs($focal)
            ->post(
                route(
                    'projects.payment.disbursements.store',
                    [$project, $obligation]
                ),
                [
                    'amount' => '455000.00',
                    'date_disbursed' => now()->toDateString(),
                    'ldap_check_number' => 'LDAP-FINAL-WORKFLOW',
                ]
            )
            ->assertRedirect(route('payments.show', $project));

        $this->assertDatabaseHas('project_disbursements', [
            'project_obligation_id' => $obligation->id,
            'amount' => 455000,
            'ldap_check_number' => 'LDAP-FINAL-WORKFLOW',
        ]);

        $this->assertSame(
            ProjectStatus::COMPLETED,
            $project->fresh()->status
        );
    }

    private function createForPaymentProject(
        User $tc,
        User $focal
    ): Project {
        $adl = Adl::create([
            'adl_number' => 'ADL-R6-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => 'R6 Final Workflow Project',
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
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $tc->id,
        ]);
    }
}
