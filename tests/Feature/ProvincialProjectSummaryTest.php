<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvincialProjectSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_and_focal_can_open_project_registry_and_province_summary(): void
    {
        [
            $province,
            $project,
        ] = $this->createAlbayProjectHierarchy();

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

        $this
            ->actingAs($tc)
            ->get(
                route('projects.index')
            )
            ->assertOk()
            ->assertSee(
                'Summary'
            );

        $this
            ->actingAs($focal)
            ->get(
                route('projects.index')
            )
            ->assertOk()
            ->assertSee(
                'Summary'
            )
            ->assertDontSee(
                'Add Project'
            );

        foreach ([$tc, $focal] as $user) {
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'projects.summary',
                        $project
                    )
                )
                ->assertOk()
                ->assertSee(
                    'Albay Province Summary'
                )
                ->assertSee(
                    '1st District'
                )
                ->assertSee(
                    'Tabaco City'
                )
                ->assertSee(
                    'Barangay San Vicente'
                );
        }
    }

    public function test_summary_uses_unique_project_total_for_province_and_full_geographic_tree(): void
    {
        [
            $province,
            $project,
            $barangayOne,
            $barangayTwo,
        ] = $this->createAlbayProjectHierarchy();

        $tc =
            User::factory()->create([
                'role' =>
                    UserRole::TC,

                'is_active' =>
                    true,
            ]);

        $response =
            $this
                ->actingAs($tc)
                ->get(
                    route(
                        'projects.summary',
                        $project
                    )
                );

        $response->assertOk();

        /*
         * Project has 80 beneficiaries and covers two barangays.
         * The province total must remain 80, not 160.
         */
        $response->assertSee(
            '80'
        );

        $response->assertSee(
            '40'
        );

        $response->assertSee(
            'Barangay beneficiary figures are coverage totals'
        );

        $response->assertSee(
            $barangayOne->name
        );

        $response->assertSee(
            $barangayTwo->name
        );

        /*
         * Project-driven summary intentionally excludes areas with no project
         * location coverage.
         */
        $response->assertDontSee(
            'Santo Domingo'
        );

        $response->assertDontSee(
            '2nd District'
        );
    }

    private function createAlbayProjectHierarchy(): array
    {
        $province =
            Province::create([
                'code' =>
                    '050500000',

                'name' =>
                    'Albay',

                'is_active' =>
                    true,
            ]);

        $tabaco =
            Municipality::create([
                'province_id' =>
                    $province->id,

                'code' =>
                    '050517000',

                'name' =>
                    'Tabaco City',

                'district' =>
                    '1st District',

                'income_class' =>
                    'Component City',

                'is_city' =>
                    true,

                'is_active' =>
                    true,
            ]);

        $sanVicente =
            Barangay::create([
                'municipality_id' =>
                    $tabaco->id,

                'code' =>
                    '050517001',

                'name' =>
                    'Barangay San Vicente',

                'is_active' =>
                    true,
            ]);

        $sanRoque =
            Barangay::create([
                'municipality_id' =>
                    $tabaco->id,

                'code' =>
                    '050517002',

                'name' =>
                    'Barangay San Roque',

                'is_active' =>
                    true,
            ]);

        $santoDomingo =
            Municipality::create([
                'province_id' =>
                    $province->id,

                'code' =>
                    '050516000',

                'name' =>
                    'Santo Domingo',

                'district' =>
                    '1st District',

                'income_class' =>
                    'Municipality',

                'is_city' =>
                    false,

                'is_active' =>
                    true,
            ]);

        Barangay::create([
            'municipality_id' =>
                $santoDomingo->id,

            'code' =>
                '050516001',

            'name' =>
                'San Isidro',

            'is_active' =>
                true,
        ]);

        $legazpi =
            Municipality::create([
                'province_id' =>
                    $province->id,

                'code' =>
                    '050506000',

                'name' =>
                    'Legazpi City',

                'district' =>
                    '2nd District',

                'income_class' =>
                    'Component City',

                'is_city' =>
                    true,

                'is_active' =>
                    true,
            ]);

        Barangay::create([
            'municipality_id' =>
                $legazpi->id,

            'code' =>
                '050506001',

            'name' =>
                'Rawis',

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
                    'ADL-PHASE9-001',

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

        $tc =
            User::factory()->create([
                'role' =>
                    UserRole::TC,

                'is_active' =>
                    true,
            ]);

        $project =
            Project::create([
                'adl_allocation_id' =>
                    $allocation->id,

                'date_received' =>
                    '2026-08-01',

                'project_title' =>
                    'Albay Multi-Barangay Project',


                'nature_of_work' =>
                    'Community clean-up',

                'fund_sponsor' =>
                    'DOLE Regional Office V',

                'partner' =>
                    'LGU Tabaco',

                'project_series' =>
                    'Regular TUPAD 2026',

                'tevs_date_verified' =>
                    '2026-08-01',

                'province' =>
                    'Albay',

                'district' =>
                    '1st District',

                'municipality' =>
                    'Tabaco City',

                'barangay' =>
                    'Barangay San Vicente',

                'province_id' =>
                    $province->id,

                'municipality_id' =>
                    $tabaco->id,

                'barangay_id' =>
                    $sanVicente->id,

                'implementation_mode' =>
                    'direct_administration',

                'number_of_days' =>
                    20,

                'term' =>
                    'short_term',

                'beneficiaries_total' =>
                    80,

                'beneficiaries_female' =>
                    40,

                'wage_rate' =>
                    455,

                'wages_total' =>
                    728000,

                'ppe_total' =>
                    0,

                'insurance_rate' =>
                    50,

                'insurance_total' =>
                    4000,

                'total_project_cost' =>
                    732000,

                'status' =>
                    ProjectStatus::APPROVED,

                'created_by' =>
                    $tc->id,
            ]);

        ProjectApproval::create([
            'project_id' =>
                $project->id,

            'approval_date' =>
                '2026-08-01',

            'project_code' =>
                'ALB-SUM-001',

            'approved_by' =>
                $tc->id,

            'approved_at' =>
                now(),
        ]);

        $location =
            ProjectLocation::create([
                'project_id' =>
                    $project->id,

                'province_id' =>
                    $province->id,

                'municipality_id' =>
                    $tabaco->id,

                'district' =>
                    '1st District',

                'sort_order' =>
                    1,
            ]);

        $location
            ->barangays()
            ->sync([
                $sanVicente->id,
                $sanRoque->id,
            ]);

        return [
            $province,
            $project,
            $sanVicente,
            $sanRoque,
        ];
    }
}
