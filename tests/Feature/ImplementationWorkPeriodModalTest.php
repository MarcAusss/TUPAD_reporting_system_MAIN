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

class ImplementationWorkPeriodModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_implementation_board_renders_set_work_period_modal_for_project_without_period(): void
    {
        $tc =
            User::factory()->create([
                'role' =>
                    UserRole::TC,

                'is_active' =>
                    true,
            ]);

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
                    'ADL-PHASE7-001',

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

        $project =
            Project::create([
                'adl_allocation_id' =>
                    $allocation->id,

                'date_received' =>
                    '2026-08-01',

                'project_title' =>
                    'Phase 7 Modal Project',

                'project_code' =>
                    'PH7-001',

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
                    ProjectStatus::APPROVED,

                'created_by' =>
                    $tc->id,
            ]);

        $response =
            $this
                ->actingAs($tc)
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
            'Set Work Period'
        );

        $response->assertSee(
            'id="workPeriodModal"',
            false
        );

        $response->assertSee(
            'data-project-title="Phase 7 Modal Project"',
            false
        );

        $response->assertSee(
            'data-duration="20"',
            false
        );

        $response->assertSee(
            route(
                'projects.implementation.period',
                $project
            ),
            false
        );

        $response->assertSee(
            'End Date is calculated automatically'
        );
    }
}
