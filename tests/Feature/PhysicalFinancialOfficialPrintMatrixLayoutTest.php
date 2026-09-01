<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalFinancialOfficialPrintMatrixLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_uses_three_level_province_target_accomplishment_balance_header_with_real_data(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $province = Province::query()->create([
            'name' => 'Albay',
            'code' => '050500000',
            'is_active' => true,
        ]);

        $short = $this->project($admin, $province, [
            'project_title' => 'Short Completed Project',
            'term' => ProjectTerm::SHORT_TERM,
            'number_of_days' => 20,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wages_total' => '5000.00',
            'ppe_total' => '500.00',
            'insurance_total' => '500.00',
            'total_project_cost' => '6000.00',
            'status' => ProjectStatus::COMPLETED,
        ]);

        $this->project($admin, $province, [
            'project_title' => 'Long Pending Project',
            'term' => ProjectTerm::LONG_TERM,
            'number_of_days' => 60,
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 10,
            'wages_total' => '10000.00',
            'ppe_total' => '1000.00',
            'insurance_total' => '1000.00',
            'total_project_cost' => '12000.00',
            'status' => ProjectStatus::APPROVED,
        ]);

        $obligation = ProjectObligation::query()->create([
            'project_id' => $short->id,
            'tranche_number' => 1,
            'adl_number' => $short->allocation->adl->adl_number,
            'fund_sponsor' => $short->fund_sponsor,
            'partner' => $short->partner,
            'project_location' => 'Albay',
            'term' => ProjectTerm::SHORT_TERM->value,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'amount' => '5000.00',
            'obligation_date' => '2026-08-20',
            'month' => 'August',
            'payee' => 'Short Project Payee',
            'recorded_by' => $admin->id,
        ]);

        ProjectDisbursement::query()->create([
            'project_obligation_id' => $obligation->id,
            'amount' => '2500.00',
            'date_disbursed' => '2026-08-25',
            'ldap_check_number' => 'CHK-PF-001',
            'recorded_by' => $admin->id,
        ]);

        $report = app(ReportGenerationService::class)->generate(
            ReportType::PHYSICAL_FINANCIAL,
            ReportDimension::OVERALL,
            new ReportFilters(),
        );

        $matrix = $report['physical_financial_print_matrix'];
        $row = $matrix['rows']->firstWhere('province', 'Albay');

        $this->assertNotNull($row);
        $this->assertSame(10, $row['target_short_physical']);
        $this->assertSame(600000, $row['target_short_financial_cents']);
        $this->assertSame(10, $row['accomplishment_short_physical']);
        $this->assertSame(250000, $row['accomplishment_short_financial_cents']);
        $this->assertSame(0, $row['balance_short_physical']);
        $this->assertSame(350000, $row['balance_short_financial_cents']);

        $this->assertSame(20, $row['target_long_physical']);
        $this->assertSame(1200000, $row['target_long_financial_cents']);
        $this->assertSame(0, $row['accomplishment_long_physical']);
        $this->assertSame(0, $row['accomplishment_long_financial_cents']);
        $this->assertSame(20, $row['balance_long_physical']);
        $this->assertSame(1200000, $row['balance_long_financial_cents']);

        $this->actingAs($admin)
            ->get(route('reports.print', [
                'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
                'group_by' => ReportDimension::OVERALL->value,
            ]))
            ->assertOk()
            ->assertSee('rowspan="3"', false)
            ->assertSee('colspan="4"', false)
            ->assertSee('Reformulated Target')
            ->assertSee('Accomplishment')
            ->assertSee('Balance')
            ->assertSee('Short Term')
            ->assertSee('Long Term')
            ->assertSee('Physical')
            ->assertSee('Financial')
            ->assertSee('Albay')
            ->assertSee('₱6,000.00')
            ->assertSee('₱2,500.00')
            ->assertSee('₱3,500.00')
            ->assertSee('TOTAL');
    }

    private function project(
        User $admin,
        Province $province,
        array $overrides,
    ): Project {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-PF-'.uniqid(),
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'location' => $province->name,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'amount' => '1000000.00',
            'grant_amount' => '1000000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '1000000.00',
            'created_by' => $admin->id,
        ]);

        return Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Physical Financial Print Project',
            'nature_of_work' => 'Print matrix test.',
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
            'status' => ProjectStatus::APPROVED,
            'created_by' => $admin->id,
        ], $overrides));
    }
}
