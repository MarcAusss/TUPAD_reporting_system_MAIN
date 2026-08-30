<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\ReportDimension;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase14CFundStatusReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $masbate;
    private Province $albay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->masbate = $this->province('Masbate', '054100000');
        $this->albay = $this->province('Albay', '050500000');
    }

    public function test_fund_status_workspace_exposes_all_eight_requested_views_and_export_actions(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status'))
            ->assertOk()
            ->assertSee('Fund Utilization Report')
            ->assertSee('Report ADL')
            ->assertSee('Report Province')
            ->assertSee('Report Status')
            ->assertSee('Report Sponsor')
            ->assertSee('Report NGA')
            ->assertSee('Report District')
            ->assertSee('Report LCE')
            ->assertSee('TUPAD Allocation')
            ->assertSee('Accomplishment (Obligated)')
            ->assertSee('Balance (Unobligated)')
            ->assertSee('Detailed Generator')
            ->assertSee('Print')
            ->assertSee('PDF')
            ->assertSee('Excel')
            ->assertSee('CSV');

        $response->assertSee(route('reports.workspace.fund-status', ['view' => 'lce']), false);
    }

    public function test_fund_utilization_uses_existing_allocation_obligation_and_disbursement_semantics(): void
    {
        [$project, $allocation] = $this->project($this->masbate, [
            'allocation_amount' => '100000.00',
            'lce' => 'Mayor Juliana Santos',
        ]);

        $obligation = ProjectObligation::query()->create([
            'project_id' => $project->id,
            'tranche_number' => 1,
            'adl_number' => $allocation->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'project_location' => 'Masbate',
            'term' => ProjectTerm::SHORT_TERM->value,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'amount' => '60000.00',
            'obligation_date' => '2026-08-10',
            'month' => 'August',
            'payee' => 'TUPAD Workers',
            'recorded_by' => $this->admin->id,
        ]);

        ProjectDisbursement::query()->create([
            'project_obligation_id' => $obligation->id,
            'amount' => '40000.00',
            'date_disbursed' => '2026-08-15',
            'ldap_check_number' => 'CHK-14C-001',
            'recorded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status'))
            ->assertOk();

        $row = $response->viewData('overallRow');
        $this->assertSame(10000000, (int) $row['allocation_cents']);
        $this->assertSame(6000000, (int) $row['obligated_cents']);
        $this->assertSame(4000000, (int) $row['unobligated_balance_cents']);
        $this->assertSame(4000000, (int) $row['disbursed_cents']);
        $this->assertSame(2000000, (int) $row['undisbursed_obligation_cents']);
        $this->assertSame(6000000, (int) $row['balance_cents']);
        $this->assertSame(60.0, $response->viewData('utilization')['obligation_rate']);
        $this->assertSame(40.0, $response->viewData('utilization')['disbursement_rate']);
    }

    public function test_lce_report_uses_authoritative_adl_allocation_field_and_includes_allocation_without_project(): void
    {
        $adl = $this->adl('ADL-LCE-ONLY');
        AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'local_chief_executive_partylist' => 'Mayor Elena Dela Cruz',
            'location' => 'Masbate',
            'province' => 'Masbate',
            'district' => '1st District',
            'municipality' => 'Masbate City',
            'amount' => '75000.00',
            'grant_amount' => '75000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '75000.00',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status', ['view' => 'lce']))
            ->assertOk()
            ->assertSee('Mayor Elena Dela Cruz');

        $this->assertSame(ReportDimension::LCE, $response->viewData('report')['dimension']);
        $row = $response->viewData('report')['rows']->firstWhere('label', 'Mayor Elena Dela Cruz');
        $this->assertNotNull($row);
        $this->assertSame(7500000, (int) $row['allocation_cents']);
        $this->assertSame(0, (int) $row['project_count']);
    }

    public function test_district_report_does_not_fabricate_financial_allocation(): void
    {
        $this->project($this->masbate, ['district' => '2nd District']);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.fund-status', ['view' => 'district']))
            ->assertOk()
            ->assertSee('District financial integrity')
            ->assertSee('do not divide or guess project money');

        foreach ($response->viewData('report')['rows'] as $row) {
            $this->assertNull($row['allocation_cents']);
            $this->assertNull($row['obligated_cents']);
            $this->assertNull($row['disbursed_cents']);
        }
    }

    public function test_generic_phase_nine_exports_accept_lce_only_for_fund_status(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.index', [
                'report_type' => 'fund_status',
                'group_by' => 'lce',
            ]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('reports.index', [
                'report_type' => 'physical_financial',
                'group_by' => 'lce',
            ]))
            ->assertSessionHasErrors('group_by');
    }

    public function test_coordinator_fund_status_is_locked_to_assigned_province(): void
    {
        $this->project($this->masbate, ['project_title' => 'MASBATE FUND']);
        $this->project($this->albay, ['project_title' => 'ALBAY FUND']);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $response = $this->actingAs($tc)
            ->get(route('reports.workspace.fund-status', ['view' => 'province']))
            ->assertOk();

        $this->assertSame($this->masbate->id, (int) $response->viewData('filters')['province_id']);
        $this->assertTrue($response->viewData('provinceLocked'));
        $this->assertSame(1, $response->viewData('report')['rows']->count());
        $this->assertSame('Masbate', $response->viewData('report')['rows']->first()['label']);

        $this->actingAs($tc)
            ->get(route('reports.workspace.fund-status', [
                'province_id' => $this->albay->id,
            ]))
            ->assertForbidden();
    }

    private function province(string $name, string $code): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function adl(string $number): Adl
    {
        return Adl::query()->create([
            'adl_number' => $number,
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @return array{0: Project, 1: AdlAllocation} */
    private function project(Province $province, array $overrides = []): array
    {
        $adl = $this->adl('ADL-P14C-'.$province->id.'-'.uniqid());
        $allocationAmount = $overrides['allocation_amount'] ?? '100000.00';
        $lce = $overrides['lce'] ?? 'Mayor '.$province->name;
        unset($overrides['allocation_amount'], $overrides['lce']);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'local_chief_executive_partylist' => $lce,
            'location' => $province->name,
            'province' => $province->name,
            'district' => $overrides['district'] ?? '1st District',
            'municipality' => 'Sample Municipality',
            'amount' => $allocationAmount,
            'grant_amount' => $allocationAmount,
            'admin_cost_amount' => '0.00',
            'total_amount' => $allocationAmount,
            'created_by' => $this->admin->id,
        ]);

        $project = Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 14C Project',
            'nature_of_work' => 'Fund status report test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'barangay' => 'Sample Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wage_rate' => '500.00',
            'wages_total' => '5000.00',
            'ppe_total' => '500.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '500.00',
            'total_project_cost' => '6000.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->admin->id,
        ], $overrides));

        return [$project, $allocation];
    }
}
