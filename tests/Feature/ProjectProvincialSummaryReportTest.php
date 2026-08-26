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

class ProjectProvincialSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_summary_index_is_available_to_tc_and_focal(): void
    {
        $tc =
            User::factory()->create([
                'role' => UserRole::TC,
                'is_active' => true,
            ]);

        $focal =
            User::factory()->create([
                'role' => UserRole::FOCAL,
                'is_active' => true,
            ]);

        Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        foreach ([$tc, $focal] as $user) {
            $this
                ->actingAs($user)
                ->get(
                    route(
                        'project-summary.index'
                    )
                )
                ->assertOk()
                ->assertSee(
                    'Provincial Summary'
                )
                ->assertSee(
                    'Albay'
                );
        }
    }

    public function test_province_report_has_requested_project_columns_and_only_project_municipalities(): void
    {
        [
            $province,
            $project,
        ] = $this->createHierarchy();

        $tc =
            User::factory()->create([
                'role' => UserRole::TC,
                'is_active' => true,
            ]);

        $response =
            $this
                ->actingAs($tc)
                ->get(
                    route(
                        'project-summary.province',
                        $province
                    )
                );

        $response
            ->assertOk()
            ->assertSee('Project Code')
            ->assertSee('Proponent')
            ->assertSee('Project Title')
            ->assertSee('Total Benefs.')
            ->assertSee('Total Amount Assisted')
            ->assertSee('No. of Days')
            ->assertSee('Wages')
            ->assertSee('(415.00/435.00)')
            ->assertSee('PPEs')
            ->assertSee('(350.00)')
            ->assertSee('Micro-Insurance')
            ->assertSee('(50.00)')
            ->assertSee('Amount Assisted')
            ->assertSee('ALB-001')
            ->assertSee('LGU Tabaco')
            ->assertSee('Tabaco City')
            ->assertSee('Barangay San Vicente')
            ->assertDontSee('Santo Domingo');

        $this
            ->actingAs($tc)
            ->get(
                route(
                    'projects.summary',
                    $project
                )
            )
            ->assertOk()
            ->assertSee(
                'Albay Project Summary'
            );
    }

    private function createHierarchy(): array
    {
        $province =
            Province::create([
                'code' => '050500000',
                'name' => 'Albay',
                'is_active' => true,
            ]);

        $tabaco =
            Municipality::create([
                'province_id' => $province->id,
                'code' => '050517000',
                'name' => 'Tabaco City',
                'district' => '1st District',
                'income_class' => 'Component City',
                'is_city' => true,
                'is_active' => true,
            ]);

        $sanVicente =
            Barangay::create([
                'municipality_id' => $tabaco->id,
                'code' => '050517001',
                'name' => 'Barangay San Vicente',
                'is_active' => true,
            ]);

        $santoDomingo =
            Municipality::create([
                'province_id' => $province->id,
                'code' => '050516000',
                'name' => 'Santo Domingo',
                'district' => '1st District',
                'income_class' => 'Municipality',
                'is_city' => false,
                'is_active' => true,
            ]);

        Barangay::create([
            'municipality_id' => $santoDomingo->id,
            'code' => '050516001',
            'name' => 'San Isidro',
            'is_active' => true,
        ]);

        $focal =
            User::factory()->create([
                'role' => UserRole::FOCAL,
                'is_active' => true,
            ]);

        $adl =
            Adl::create([
                'adl_number' => 'ADL-SUMMARY-001',
                'grants' => 1000000,
                'admin_cost' => 0,
                'total' => 1000000,
                'created_by' => $focal->id,
            ]);

        $allocation =
            AdlAllocation::create([
                'adl_id' => $adl->id,
                'location' => 'Albay',
                'amount' => 1000000,
                'created_by' => $focal->id,
            ]);

        $creator =
            User::factory()->create([
                'role' => UserRole::TC,
                'is_active' => true,
            ]);

        $project =
            Project::create([
                'adl_allocation_id' => $allocation->id,
                'date_received' => '2026-08-01',
                'project_title' => 'Coastal Clean-up',
                'nature_of_work' => 'Clean-up',
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Tabaco',
                'project_series' => 'Regular TUPAD 2026',
                'tevs_date_verified' => '2026-08-01',
                'province' => 'Albay',
                'district' => '1st District',
                'municipality' => 'Tabaco City',
                'barangay' => 'Barangay San Vicente',
                'province_id' => $province->id,
                'municipality_id' => $tabaco->id,
                'barangay_id' => $sanVicente->id,
                'implementation_mode' => 'direct_administration',
                'number_of_days' => 20,
                'term' => 'short_term',
                'beneficiaries_total' => 80,
                'beneficiaries_female' => 40,
                'wage_rate' => 435,
                'wages_total' => 696000,
                'ppe_total' => 28000,
                'insurance_rate' => 50,
                'insurance_total' => 4000,
                'total_project_cost' => 728000,
                'status' => ProjectStatus::APPROVED,
                'created_by' => $creator->id,
            ]);

        ProjectApproval::create([
            'project_id' =>
                $project->id,

            'approval_date' =>
                '2026-08-01',

            'project_code' =>
                'ALB-001',

            'approved_by' =>
                $creator->id,

            'approved_at' =>
                now(),
        ]);

        $location =
            ProjectLocation::create([
                'project_id' => $project->id,
                'province_id' => $province->id,
                'municipality_id' => $tabaco->id,
                'district' => '1st District',
                'sort_order' => 1,
            ]);

        $location
            ->barangays()
            ->sync([
                $sanVicente->id,
            ]);

        return [
            $province,
            $project,
        ];
    }
}
