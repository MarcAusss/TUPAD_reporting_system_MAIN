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

class MergedImplementationRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tc =
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
                    'ADL-PHASE5-001',

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

        $this->project =
            Project::create([
                'adl_allocation_id' =>
                    $allocation->id,

                'date_received' =>
                    now()->toDateString(),

                'project_title' =>
                    'Phase 5 Combined Requirements Project',

                'nature_of_work' =>
                    'Community clean-up',

                'fund_sponsor' =>
                    'DOLE Regional Office V',

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

                'insurance_beneficiaries' =>
                    35,

                'wage_rate' =>
                    455,

                'wages_total' =>
                    455000,

                'ppe_total' =>
                    0,

                'insurance_rate' =>
                    50,

                'insurance_total' =>
                    1750,

                'total_project_cost' =>
                    456750,

                'status' =>
                    ProjectStatus::APPROVED,

                'created_by' =>
                    $this->tc->id,
            ]);
    }

    public function test_project_page_uses_one_submit_for_three_implementation_requirements(): void
    {
        $response =
            $this
                ->actingAs(
                    $this->tc
                )
                ->get(
                    route(
                        'projects.show',
                        $this->project
                    )
                );

        $response->assertOk();

        $response->assertSee(
            'Save Implementation Requirements'
        );

        $response->assertSee(
            route(
                'projects.implementation.requirements',
                $this->project
            ),
            false
        );

        $response->assertDontSee(
            'Save Insurance'
        );

        $response->assertDontSee(
            'Save PPE Delivery'
        );

        $response->assertDontSee(
            'Save Notice to Proceed'
        );
    }

    public function test_one_submission_saves_insurance_ppe_and_notice_to_proceed(): void
    {
        $this
            ->actingAs(
                $this->tc
            )
            ->post(
                route(
                    'projects.implementation.requirements',
                    $this->project
                ),
                [
                    'insurance' => [
                        'date_enrolled' =>
                            '2026-08-26',

                        'payment_mode' =>
                            'voucher',

                        'or_number' =>
                            'OR-P5-001',

                        'policy_number' =>
                            'POL-P5-001',

                        'remarks' =>
                            'Insurance complete.',
                    ],

                    'ppe' => [
                        'delivery_receipt_date' =>
                            '2026-08-26',

                        'ppe_provided' =>
                            'Safety vest, gloves, and boots',

                        'remarks' =>
                            'PPE delivered.',
                    ],

                    'ntp' => [
                        'date_issued' =>
                            '2026-08-26',

                        'date_released' =>
                            '2026-08-26',

                        'remarks' =>
                            'NTP released.',
                    ],
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'project_insurance_enrollments',
            [
                'project_id' =>
                    $this->project->id,

                'beneficiary_count' =>
                    35,

                'amount' =>
                    1750,

                'payment_mode' =>
                    'voucher',
            ]
        );

        $this->assertDatabaseHas(
            'project_ppe_deliveries',
            [
                'project_id' =>
                    $this->project->id,

                'ppe_provided' =>
                    'Safety vest, gloves, and boots',
            ]
        );

        $this->assertDatabaseHas(
            'project_notice_to_proceeds',
            [
                'project_id' =>
                    $this->project->id,

                'date_issued' =>
                    '2026-08-26 00:00:00',

                'date_released' =>
                    '2026-08-26 00:00:00',
            ]
        );
    }

    public function test_invalid_requirement_prevents_all_three_records_from_being_saved(): void
    {
        $this
            ->actingAs(
                $this->tc
            )
            ->post(
                route(
                    'projects.implementation.requirements',
                    $this->project
                ),
                [
                    'insurance' => [
                        'date_enrolled' =>
                            '2026-08-26',

                        'payment_mode' =>
                            'voucher',
                    ],

                    'ppe' => [
                        'delivery_receipt_date' =>
                            '2026-08-26',

                        'ppe_provided' =>
                            'Safety vest',
                    ],

                    'ntp' => [
                        'date_issued' =>
                            '2026-08-27',

                        // Invalid: released before issued.
                        'date_released' =>
                            '2026-08-26',
                    ],
                ]
            )
            ->assertSessionHasErrors([
                'ntp.date_released',
            ]);

        $this->assertDatabaseCount(
            'project_insurance_enrollments',
            0
        );

        $this->assertDatabaseCount(
            'project_ppe_deliveries',
            0
        );

        $this->assertDatabaseCount(
            'project_notice_to_proceeds',
            0
        );
    }
}
