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

class ConditionalTssdEvaluationTest extends TestCase
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
            'adl_number' => 'ADL-R4-001',
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
            'project_title' => 'R4 Conditional Evaluation Project',
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
            'status' => ProjectStatus::TSSD_EVALUATION,
            'created_by' => $this->tc->id,
        ]);
    }

    public function test_for_compliance_requires_findings_and_required_documents(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $this->project
                ),
                [
                    'result' => 'for_compliance',
                    'remarks' => 'Needs compliance.',
                ]
            );

        $response->assertSessionHasErrors([
            'findings',
            'required_documents',
        ]);

        $this->assertDatabaseCount(
            'project_evaluations',
            0
        );
    }

    public function test_for_compliance_stores_findings_and_required_documents(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $this->project
                ),
                [
                    'result' => 'for_compliance',
                    'findings' => 'Missing documentary requirement.',
                    'required_documents' => 'Submit signed certification.',
                    'remarks' => 'Return for compliance.',
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'project_evaluations',
            [
                'project_id' => $this->project->id,
                'findings' => 'Missing documentary requirement.',
                'required_documents' => 'Submit signed certification.',
            ]
        );
    }

    public function test_for_approval_does_not_require_findings_or_required_documents(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $this->project
                ),
                [
                    'result' => 'for_approval',
                    'remarks' => 'Recommended for approval.',
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'project_evaluations',
            [
                'project_id' => $this->project->id,
                'findings' => null,
                'required_documents' => null,
            ]
        );

        $this->assertSame(
            ProjectStatus::FOR_APPROVAL,
            $this->project->fresh()->status
        );
    }

    public function test_for_approval_discards_stale_findings_and_required_documents(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $this->project
                ),
                [
                    'result' => 'for_approval',
                    'findings' => 'Stale browser value',
                    'required_documents' => 'Stale browser value',
                    'remarks' => 'Recommended for approval.',
                ]
            );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'project_evaluations',
            [
                'project_id' => $this->project->id,
                'findings' => null,
                'required_documents' => null,
            ]
        );
    }
}
