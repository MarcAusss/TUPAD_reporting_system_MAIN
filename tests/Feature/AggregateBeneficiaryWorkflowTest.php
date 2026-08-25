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

class AggregateBeneficiaryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_beneficiary_crud_routes_are_removed(): void
    {
        $routeNames = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->values();

        foreach ([
            'projects.beneficiaries.index',
            'projects.beneficiaries.store',
            'projects.beneficiaries.edit',
            'projects.beneficiaries.update',
            'projects.beneficiaries.destroy',
        ] as $routeName) {
            $this->assertFalse($routeNames->contains($routeName));
        }
    }

    public function test_tc_can_submit_project_to_tssd_using_only_aggregate_beneficiary_counts(): void
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
            'adl_number' => 'ADL-R3-001',
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
            'project_title' => 'Aggregate Beneficiary Project',
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

        $this->assertSame(0, $project->beneficiaries()->count());

        $response = $this
            ->actingAs($tc)
            ->post(route('projects.evaluation.start', $project));

        $response->assertRedirect(route('projects.show', $project));

        $this->assertSame(
            ProjectStatus::TSSD_EVALUATION,
            $project->fresh()->status
        );
    }
}
