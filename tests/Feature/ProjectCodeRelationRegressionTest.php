<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCodeRelationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_province_summary_uses_project_approval_code_instead_of_projects_column(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-CODE-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-26',
            'project_title' => 'Project Code Relation Test',
            'nature_of_work' => 'Community activity',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD',
            'tevs_date_verified' => '2026-08-26',
            'province_id' => $province->id,
            'province' => 'Albay',
            'district' => '1st District',
            'municipality' => 'Test Municipality',
            'barangay' => 'Test Barangay',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 25,
            'beneficiaries_female' => 10,
            'wage_rate' => 455,
            'wages_total' => 227500,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 1250,
            'total_project_cost' => 228750,
            'status' => ProjectStatus::APPROVED,
            'created_by' => $tc->id,
        ]);

        ProjectApproval::create([
            'project_id' => $project->id,
            'approval_date' => '2026-08-26',
            'project_code' => 'TUPAD-ALB-001',
            'approved_by' => $tc->id,
            'approved_at' => now(),
        ]);

        $this
            ->actingAs($tc)
            ->get(
                route(
                    'projects.summary',
                    $project
                )
            )
            ->assertOk()
            ->assertSee('TUPAD-ALB-001');
    }
}
