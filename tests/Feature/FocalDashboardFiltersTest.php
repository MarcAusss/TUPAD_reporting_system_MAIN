<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FocalDashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    private Province $albay;

    private Province $masbate;

    private Adl $adlA;

    private Adl $adlB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->albay = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $this->masbate = Province::create(['code' => '054100000', 'name' => 'Masbate', 'is_active' => true]);

        $this->adlA = Adl::create(['adl_number' => 'ADL-F-001', 'date_received' => '2026-01-10', 'grants' => 1000000, 'admin_cost' => 0, 'total' => 1000000, 'created_by' => $this->focal->id]);
        $this->adlB = Adl::create(['adl_number' => 'ADL-F-002', 'date_received' => '2025-03-10', 'grants' => 500000, 'admin_cost' => 0, 'total' => 500000, 'created_by' => $this->focal->id]);

        $this->project('Albay Q1 TSSD', $this->albay, $this->adlA, '2026-02-15', ProjectStatus::TSSD_EVALUATION, 'Sponsor A', 'Partner A', 30);
        $this->project('Albay Q2 Approved', $this->albay, $this->adlA, '2026-05-20', ProjectStatus::APPROVED, 'Sponsor B', 'Partner A', 40);
        $this->project('Masbate Q1 TSSD', $this->masbate, $this->adlB, '2025-01-05', ProjectStatus::TSSD_EVALUATION, 'Sponsor A', 'Partner B', 50);
    }

    public function test_dashboard_shows_every_filter_with_an_all_option(): void
    {
        $response = $this->actingAs($this->focal)->get(route('dashboard'))->assertOk();

        $response->assertSee('data-dashboard-filters', false);

        foreach (['All years', 'All quarters', 'All months', 'All provinces', 'Select a province first', 'All sponsors', 'All partners', 'All ADLs', 'All statuses'] as $allOption) {
            $response->assertSee($allOption);
        }

        $this->assertSame(3, $response->viewData('focalOverview')['status']['total']);
    }

    public function test_project_figures_follow_each_filter(): void
    {
        $total = fn (array $query) => $this->actingAs($this->focal)
            ->get(route('dashboard', $query))
            ->assertOk()
            ->viewData('focalOverview')['status']['total'];

        $this->assertSame(2, $total(['year' => 2026]));
        $this->assertSame(2, $total(['quarter' => 1]));
        $this->assertSame(1, $total(['month' => 5]));
        $this->assertSame(0, $total(['quarter' => 1, 'month' => 5]));
        $this->assertSame(2, $total(['province' => $this->albay->id]));
        $this->assertSame(2, $total(['sponsor' => 'Sponsor A']));
        $this->assertSame(1, $total(['partner' => 'Partner B']));
        $this->assertSame(1, $total(['adl' => $this->adlB->id]));
        $this->assertSame(2, $total(['status' => ProjectStatus::TSSD_EVALUATION->value]));
        $this->assertSame(1, $total(['year' => 2026, 'province' => $this->albay->id, 'status' => ProjectStatus::APPROVED->value]));
    }

    public function test_fund_position_follows_adl_and_year_filters(): void
    {
        $finance = fn (array $query) => $this->actingAs($this->focal)
            ->get(route('dashboard', $query))
            ->viewData('focalOverview')['finance'];

        $this->assertEqualsWithDelta(1500000, $finance([])['budget'], 0.01);
        $this->assertEqualsWithDelta(500000, $finance(['adl' => $this->adlB->id])['budget'], 0.01);
        $this->assertEqualsWithDelta(1000000, $finance(['year' => 2026])['budget'], 0.01);
    }

    public function test_invalid_filter_values_fall_back_to_all(): void
    {
        $response = $this->actingAs($this->focal)
            ->get(route('dashboard', ['year' => 'abc', 'quarter' => 9, 'status' => 'nope', 'municipality' => 5]))
            ->assertOk();

        $this->assertSame([], $response->viewData('dashboardFilters')->toArray());
        $this->assertSame(3, $response->viewData('focalOverview')['status']['total']);
    }

    public function test_implementation_counts_projects_waiting_for_post_docs(): void
    {
        $this->project('Albay Post Docs', $this->albay, $this->adlA, '2026-03-01', ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS, 'Sponsor A', 'Partner A', 10);

        $response = $this->actingAs($this->focal)->get(route('dashboard'))->assertOk();

        $this->assertSame(1, $response->viewData('focalOverview')['implementation']['post_docs']);
        $response->assertSee(ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->label())->assertDontSee('Overdue');
    }

    public function test_focal_can_set_beneficiary_targets_and_see_the_balance(): void
    {
        $response = $this->actingAs($this->focal)->get(route('dashboard', ['year' => 2026]))->assertOk();
        $response->assertSee('Beneficiary Overview')->assertSee('beneficiaryTargetsDialog', false);
        $this->assertNull($response->viewData('focalOverview')['beneficiaries']['targets']['terms']['short_term']['target']);

        $this->actingAs($this->focal)
            ->from(route('dashboard', ['year' => 2026]))
            ->put(route('dashboard.beneficiary-targets.update'), ['fiscal_year' => 2026, 'short_term' => 100, 'long_term' => 20])
            ->assertRedirect(route('dashboard', ['year' => 2026]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('beneficiary_targets', ['fiscal_year' => 2026, 'term' => 'short_term', 'target' => 100, 'updated_by' => $this->focal->id]);

        // FY2026 short-term projects have 30 + 40 beneficiaries.
        $short = $this->actingAs($this->focal)->get(route('dashboard', ['year' => 2026]))
            ->viewData('focalOverview')['beneficiaries']['targets']['terms']['short_term'];

        $this->assertSame(100, $short['target']);
        $this->assertSame(70, $short['achieved']);
        $this->assertSame(30, $short['balance']);
    }

    public function test_beneficiary_targets_are_validated_and_limited_to_focal(): void
    {
        $this->actingAs($this->focal)
            ->put(route('dashboard.beneficiary-targets.update'), ['fiscal_year' => 2026, 'short_term' => -5, 'long_term' => 'x'])
            ->assertSessionHasErrors(['short_term', 'long_term'], null, 'beneficiaryTargets');

        $tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);
        $this->actingAs($tc)
            ->put(route('dashboard.beneficiary-targets.update'), ['fiscal_year' => 2026, 'short_term' => 1, 'long_term' => 1])
            ->assertForbidden();

        $this->assertDatabaseCount('beneficiary_targets', 0);
    }

    public function test_tc_dashboard_matches_focal_with_the_province_locked(): void
    {
        $tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true, 'assigned_province_id' => $this->albay->id]);

        $response = $this->actingAs($tc)->get(route('dashboard'))->assertOk();
        $response->assertSee('data-dashboard-filters', false)
            ->assertSee('data-focal-status-chart', false)
            ->assertSee('Beneficiary Overview')
            ->assertSee('Your assigned province', false)
            ->assertDontSee('All provinces')
            ->assertDontSee('beneficiaryTargetsDialog', false)
            ->assertSee('data-tc-aging-summary', false);

        // Only Albay projects (2 of 3), and Fund Position is Albay's allocations.
        $this->assertSame(2, $response->viewData('focalOverview')['status']['total']);
        $this->assertEqualsWithDelta(200000, $response->viewData('focalOverview')['finance']['budget'], 0.01);

        // The province cannot be switched from the URL; other filters still work.
        $other = $this->actingAs($tc)->get(route('dashboard', ['province' => $this->masbate->id, 'status' => ProjectStatus::APPROVED->value]));
        $this->assertSame(1, $other->viewData('focalOverview')['status']['total']);
        $this->assertSame(['status' => ProjectStatus::APPROVED->value], $other->viewData('dashboardFilters')->toArray());
    }

    private function project(string $title, Province $province, Adl $adl, string $date, ProjectStatus $status, string $sponsor, string $partner, int $beneficiaries): Project
    {
        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => $sponsor,
            'partner' => $partner,
            'province' => $province->name,
            'location' => $province->name,
            'amount' => 100000,
            'created_by' => $this->focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => $date,
            'project_title' => $title,
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => $sponsor,
            'partner' => $partner,
            'project_series' => 'Regular TUPAD',
            'tevs_date_verified' => $date,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Test Municipality',
            'barangay' => 'Test Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => 10,
            'wage_rate' => 455,
            'wages_total' => 182000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_beneficiaries' => $beneficiaries,
            'insurance_total' => 1000,
            'total_project_cost' => 183000,
            'status' => $status,
            'created_by' => $this->focal->id,
        ]);
    }
}
