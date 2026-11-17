<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectMonitoringDetail;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CqprFollowsSprsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $masbate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->masbate = Province::query()->create(['code' => '054100000', 'name' => 'Masbate', 'is_active' => true]);
    }

    public function test_cqpr_quarter_comes_from_the_sprs_date_not_the_cqpr_date(): void
    {
        // SPRS in February (Q1); a stale CQPR date in August (Q3) must be ignored.
        $project = $this->project('Follows SPRS', '2026-01-20');
        ProjectMonitoringDetail::query()->create([
            'project_id' => $project->id,
            'sprs_date' => '2026-02-10',
            'cqpr_date' => '2026-08-15',
            'updated_by' => $this->admin->id,
        ]);

        // No SPRS date: falls back to the obligation completion date (May, Q2), like the SPRS does.
        $this->project('Falls Back To Obligation', '2026-05-04');

        $q1 = $this->cqpr(1);
        $this->assertSame(['Follows SPRS'], $q1);

        $this->assertSame([], $this->cqpr(3));
        $this->assertSame(['Falls Back To Obligation'], $this->cqpr(2));
    }

    public function test_all_quarters_is_one_table_ordered_by_quarter(): void
    {
        $this->project('Second Quarter Project', '2026-05-04');
        $this->project('First Quarter Project', '2026-01-20');

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.quarterly', ['view' => 'cqpr', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSeeInOrder(['First Quarter Project', 'Second Quarter Project', 'Sub-Total (Masbate)', 'TOTAL']);

        $sections = $response->viewData('cqpr')['sections'];
        $this->assertCount(1, $sections);
        $this->assertNull($sections[0]['quarter']);
        $this->assertSame(2, collect($sections[0]['groups'])->sum(fn (array $group) => count($group['rows'])));

        $this->actingAs($this->admin)
            ->get(route('reports.periodic.print', ['form' => 'cqpr', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertDontSee('cqpr-section-title">', false)
            ->assertSeeInOrder(['First Quarter Project', 'Second Quarter Project', 'TOTAL']);
    }

    /** @return list<string> project titles in that CQPR quarter */
    private function cqpr(int $quarter): array
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.quarterly', ['view' => 'cqpr', 'fiscal_year' => 2026, 'quarter' => $quarter]))
            ->assertOk();

        return collect($response->viewData('cqpr')['sections'])
            ->flatMap(fn (array $section) => collect($section['groups'])->flatMap(fn (array $group) => $group['rows']))
            ->pluck('project')
            ->values()
            ->all();
    }

    private function project(string $title, string $obligationsCompletedAt): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-CQPR-'.uniqid(),
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'location' => 'Masbate',
            'province' => 'Masbate',
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-01-05',
            'project_title' => $title,
            'nature_of_work' => 'CQPR test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'province_id' => $this->masbate->id,
            'province' => 'Masbate',
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'barangay' => 'Sample Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => '500.00',
            'wages_total' => '50000.00',
            'ppe_total' => '5000.00',
            'insurance_rate' => '100.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '1000.00',
            'total_project_cost' => '56000.00',
            'status' => ProjectStatus::FOR_IMPLEMENTATION,
            'obligations_completed_at' => $obligationsCompletedAt,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }
}
