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

class ProjectFormDetailUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_create_has_section_navigation_and_clear_save_action(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(route('projects.create'));

        $response->assertOk();
        $response->assertSee('Project Sections');
        $response->assertSee('Encoding Guide');
        $response->assertSee('Save Official Project');
        $response->assertSee('href="#allocation"', false);
        $response->assertSee('href="#costing"', false);
    }

    public function test_project_detail_has_recommended_next_action_and_section_navigation(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R11-001',
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

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => 'R11 UX Project',
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
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $tc->id,
        ]);

        $response = $this
            ->actingAs($tc)
            ->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('Recommended Next Action');
        $response->assertSee(
            'Complete profiling, then submit the project to TSSD Evaluation.'
        );
        $response->assertSee('Ongoing Profiling');
        $response->assertSee('Submit to TSSD Evaluation');
        $response->assertSee('Project Snapshot');
        $response->assertSee('href="#overview"', false);
        $response->assertSee('href="#evaluation"', false);
        $response->assertSee('href="#history"', false);
    }
}
