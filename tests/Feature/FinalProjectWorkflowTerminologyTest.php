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

class FinalProjectWorkflowTerminologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_of_wages_must_exist_before_release_of_assistance(): void
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

        $response = $this
            ->actingAs($tc)
            ->post(
                route(
                    'projects.payout.store',
                    $project
                ),
                [
                    'payout_date' => now()->toDateString(),
                    'payout_mode' => 'Cash Card',
                    'venue' => 'Legazpi City',
                ]
            );

        $response->assertSessionHasErrors([
            'payout_date',
        ]);

        $this->assertDatabaseCount(
            'project_payouts',
            0
        );
    }

    public function test_focal_records_payment_then_tc_records_release_and_completes_project(): void
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
            ->post(
                route(
                    'projects.payment.store',
                    $project
                ),
                [
                    'amount' => 457500,
                    'obligation_date' => now()->toDateString(),
                    'month' => now()->format('F Y'),
                    'payee' => 'TUPAD Beneficiaries',
                    'remarks' => 'Payment of Wages processed.',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'project_obligations',
            [
                'project_id' => $project->id,
                'amount' => 457500,
                'payee' => 'TUPAD Beneficiaries',
            ]
        );

        $this->actingAs($tc)
            ->post(
                route(
                    'projects.payout.store',
                    $project
                ),
                [
                    'payout_date' => now()->toDateString(),
                    'payout_mode' => 'Cash Card',
                    'venue' => 'Legazpi City',
                    'remarks' => 'Release of Assistance completed.',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'project_payouts',
            [
                'project_id' => $project->id,
                'payout_mode' => 'Cash Card',
                'venue' => 'Legazpi City',
            ]
        );

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
            'fund_sponsor' => null,
            'partner' => null,
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
