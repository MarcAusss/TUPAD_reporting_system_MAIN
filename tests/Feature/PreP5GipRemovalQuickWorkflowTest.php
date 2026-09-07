<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreP5GipRemovalQuickWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_user_roles_are_admin_focal_and_tc_only(): void
    {
        $this->assertSame(
            ['admin', 'focal', 'tc'],
            array_map(static fn (UserRole $role): string => $role->value, UserRole::assignable()),
        );
        $this->assertNull(UserRole::tryFrom('gip'));

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('Administrator')
            ->assertSee('Focal')
            ->assertSee('TUPAD Coordinator')
            ->assertDontSee('GIP Encoder')
            ->assertDontSee('Retired Account');
    }

    public function test_historical_retired_account_cannot_keep_using_an_existing_session(): void
    {
        $retired = User::factory()->create([
            'role' => UserRole::RETIRED,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($retired)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username']);

        $this->assertGuest();
    }

    public function test_gip_draft_routes_and_schema_are_retired(): void
    {
        foreach ([
            'project-drafts.index',
            'project-drafts.create',
            'project-drafts.store',
            'project-drafts.show',
            'project-drafts.edit',
            'project-drafts.update',
            'project-drafts.submit',
            'project-draft-reviews.index',
            'project-draft-reviews.show',
            'project-draft-reviews.return',
            'project-draft-reviews.confirm',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), $routeName);
        }

        $this->assertFalse(Schema::hasTable('project_drafts'));
        $this->assertFalse(Schema::hasTable('project_draft_ppe_items'));
        $this->assertFalse(Schema::hasColumn('users', 'supervisor_tc_id'));
    }

    public function test_new_project_starts_in_ongoing_profiling_and_can_advance_from_top_quick_action(): void
    {
        [$tc, $allocation, $province, $municipality, $barangay] = $this->projectReferences();

        $this->actingAs($tc)
            ->post(route('projects.store'), [
                'adl_allocation_id' => $allocation->id,
                'date_received' => now()->toDateString(),
                'project_title' => 'Quick Workflow Test Project',
                'nature_of_work' => 'Community clean-up',
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Legazpi',
                'project_series' => 'Regular TUPAD 2026',
                'project_series_remarks' => 'Pre-P5 workflow regression.',
                'tevs_date_verified' => now()->toDateString(),
                'tevs_remarks' => 'Verified for profiling.',
                'province_id' => $province->id,
                'municipality_id' => $municipality->id,
                'barangay_id' => $barangay->id,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION->value,
                'number_of_days' => 20,
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 5,
                'wage_rate' => 455,
                'insurance_rate' => 50,
                'ppe_items' => [],
                'remarks' => null,
            ])
            ->assertRedirect();

        $project = Project::query()->where('project_title', 'Quick Workflow Test Project')->firstOrFail();

        $this->assertSame(ProjectStatus::ONGOING_PROFILING, $project->status);

        $this->actingAs($tc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Next Workflow Action')
            ->assertSee('Ongoing Profiling')
            ->assertSee('TSSD Evaluation')
            ->assertSee('Continue Workflow')
            ->assertSee('Quick Workflow Progression')
            ->assertSee('Submit to TSSD Evaluation')
            ->assertSee(route('projects.evaluation.start', $project), false);

        $this->actingAs($tc)
            ->post(route('projects.evaluation.start', $project), [
                'quick_action' => '1',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame(ProjectStatus::TSSD_EVALUATION, $project->fresh()->status);
    }

    /** @return array{0:User,1:AdlAllocation,2:Province,3:Municipality,4:Barangay} */
    private function projectReferences(): array
    {
        $province = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'code' => '050501001',
            'name' => 'Rawis',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $province->id,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::query()->create([
            'adl_number' => 'ADL-PRE-P5-001',
            'grants' => 2_000_000,
            'admin_cost' => 0,
            'total' => 2_000_000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => null,
            'partner' => null,
            'location' => 'Legazpi City, Albay',
            'amount' => 1_000_000,
            'grant_amount' => 1_000_000,
            'admin_cost_amount' => 0,
            'total_amount' => 1_000_000,
            'created_by' => $focal->id,
        ]);

        return [$tc, $allocation, $province, $municipality, $barangay];
    }
}
