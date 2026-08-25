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

class ApprovedInsuranceLockTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R5-001',
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

        $this->project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => 'R5 Approved Insurance Lock Project',
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
            'status' => ProjectStatus::APPROVED,
            'created_by' => $this->tc->id,
        ]);
    }

    public function test_insurance_form_renders_locked_beneficiary_and_amount_values(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->get(
                route(
                    'projects.show',
                    $this->project
                )
            );

        $response->assertOk();

        /*
        |--------------------------------------------------------------------------
        | Locked Insurance UI
        |--------------------------------------------------------------------------
        */

        $response->assertSee(
            'Approved project values are locked'
        );

        $response->assertSee(
            'Uses the approved project beneficiary count.'
        );

        $response->assertSee(
            'Uses the approved project insurance amount.'
        );

        /*
        |--------------------------------------------------------------------------
        | Beneficiary Count Must Not Be Editable
        |--------------------------------------------------------------------------
        |
        | beneficiary_count is specific to the Insurance Enrollment form,
        | so checking that it does not exist anywhere on the page is safe.
        |
        */

        $response->assertDontSee(
            'name="beneficiary_count"',
            false
        );

        /*
        |--------------------------------------------------------------------------
        | Locked Values Are Displayed
        |--------------------------------------------------------------------------
        */

        $response->assertSee(
            number_format(
                $this->project->beneficiaries_total
            )
        );

        $response->assertSee(
            '₱' . number_format(
                $this->project->insurance_total,
                2
            )
        );
    }

    public function test_operational_insurance_details_can_still_be_updated_without_changing_locked_values(): void
    {
        $this->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.insurance',
                    $this->project
                ),
                [
                    'date_enrolled' => now()->toDateString(),
                    'payment_mode' => 'voucher',
                    'or_number' => 'OR-OLD',
                    'policy_number' => 'POL-OLD',
                    'remarks' => 'Initial enrollment.',
                ]
            )
            ->assertRedirect();

        $this->actingAs($this->tc)
            ->post(
                route(
                    'projects.implementation.insurance',
                    $this->project
                ),
                [
                    'date_enrolled' => now()->addDay()->toDateString(),

                    // Attempt to change locked values.
                    'beneficiary_count' => 999,
                    'amount' => 999999,

                    'payment_mode' => 'ca',
                    'or_number' => 'OR-UPDATED',
                    'policy_number' => 'POL-UPDATED',
                    'remarks' => 'Operational details updated.',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'project_insurance_enrollments',
            [
                'project_id' => $this->project->id,
                'beneficiary_count' => 50,
                'amount' => 2500,
                'payment_mode' => 'ca',
                'or_number' => 'OR-UPDATED',
                'policy_number' => 'POL-UPDATED',
                'remarks' => 'Operational details updated.',
            ]
        );
    }


}
