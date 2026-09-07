<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundStatusSheet2TemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $albay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->albay = Province::query()->create([
            'name' => 'Albay',
            'code' => '050500000',
            'is_active' => true,
        ]);

        [$project, $allocation] = $this->project();

        ProjectObligation::query()->create([
            'project_id' => $project->id,
            'tranche_number' => 1,
            'adl_number' => $allocation->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'project_location' => 'Legazpi City, 2nd District, Albay',
            'term' => ProjectTerm::SHORT_TERM->value,
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 11,
            'amount' => '60000.00',
            'obligation_date' => '2026-08-10',
            'month' => 'August',
            'payee' => 'TUPAD Workers',
            'recorded_by' => $this->admin->id,
        ]);
    }

    public function test_fund_status_index_uses_sheet_two_table_structures(): void
    {
        $utilization = $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status'))
            ->assertOk()
            ->assertSee('Fund Utilization Report')
            ->assertSee('Summary per ADL')
            ->assertSee('Summary per Province')
            ->assertSee('TUPAD Allocation')
            ->assertSee('Accomplishment')
            ->assertSee('Amount')
            ->assertSee('Ben.')
            ->assertSee('Balance')
            ->assertSee('TOTAL');

        $template = $utilization->viewData('report')['fund_status_template'];
        $this->assertSame('utilization', $template['kind']);
        $this->assertSame(10000000, $template['totals']['allocation_cents']);
        $this->assertSame(6000000, $template['totals']['accomplishment_cents']);
        $this->assertSame(60.0, $template['totals']['accomplishment_rate']);
        $this->assertSame(20, $template['totals']['accomplishment_beneficiaries']);
        $this->assertSame(4000000, $template['totals']['balance_cents']);

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status', ['view' => 'adl']))
            ->assertOk()
            ->assertSee('Summary per ADL')
            ->assertSee('ADL No.')
            ->assertSee('Fund Sponsor')
            ->assertSee('Partner')
            ->assertSee('Municipality')
            ->assertSee('District')
            ->assertSee('Province')
            ->assertSee('Sub-total');

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status', ['view' => 'province']))
            ->assertOk()
            ->assertSee('Summary per Province')
            ->assertSee('Sub-total');
    }

    public function test_fund_status_print_contains_only_letterhead_type_date_and_sheet_two_table(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.print', [
                'report_type' => 'fund_status',
                'group_by' => 'overall',
            ]))
            ->assertOk()
            ->assertSee('DEPARTMENT OF LABOR AND EMPLOYMENT')
            ->assertSee('Report Type:')
            ->assertSee('Fund Utilization Report')
            ->assertSee('Date:')
            ->assertSee('TUPAD Allocation')
            ->assertSee('Accomplishment')
            ->assertSee('Ben.')
            ->assertSee('Balance')
            ->assertSee('TOTAL');

        $response
            ->assertDontSee('<section class="criteria">', false)
            ->assertDontSee('<section class="summary">', false)
            ->assertDontSee('PREPARED BY')
            ->assertDontSee('REVIEWED BY')
            ->assertDontSee('APPROVED BY')
            ->assertDontSee('reporting row(s)');
    }

    public function test_adl_and_province_prints_use_sheet_two_breakdown_and_subtotals(): void
    {
        foreach ([
            'adl' => 'Summary per ADL',
            'province' => 'Summary per Province',
        ] as $groupBy => $title) {
            $this->actingAs($this->admin)
                ->get(route('reports.print', [
                    'report_type' => 'fund_status',
                    'group_by' => $groupBy,
                ]))
                ->assertOk()
                ->assertSee($title)
                ->assertSee('ADL No.')
                ->assertSee('Fund Sponsor')
                ->assertSee('Partner')
                ->assertSee('Location')
                ->assertSee('Municipality')
                ->assertSee('District')
                ->assertSee('Province')
                ->assertSee('Sub-total')
                ->assertSee('TOTAL')
                ->assertDontSee('PREPARED BY');
        }
    }

    /** @return array{0: Project, 1: AdlAllocation} */
    private function project(): array
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-SHEET2-001',
            'grants' => '100000.00',
            'admin_cost' => '0.00',
            'total' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi City',
            'local_chief_executive_partylist' => 'City Mayor',
            'location' => 'Legazpi City, Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        $project = Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Sheet 2 Report Project',
            'nature_of_work' => 'Community work',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi City',
            'province_id' => $this->albay->id,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 11,
            'wage_rate' => '500.00',
            'wages_total' => '10000.00',
            'ppe_total' => '500.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 20,
            'insurance_total' => '1000.00',
            'total_project_cost' => '11500.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->admin->id,
        ]);

        return [$project, $allocation];
    }
}
