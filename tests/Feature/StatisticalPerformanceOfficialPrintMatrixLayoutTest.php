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

class StatisticalPerformanceOfficialPrintMatrixLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $albay;
    private Province $masbate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->albay = $this->province('Albay', '050500000');
        $this->masbate = $this->province('Masbate', '054100000');
    }

    public function test_sprs_browser_print_matches_supplied_province_month_matrix_and_contains_data(): void
    {
        $january = $this->project($this->albay, [
            'project_title' => 'January Albay SPRS',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
        ]);

        ProjectMonitoringDetail::query()->create([
            'project_id' => $january->id,
            'sprs_date' => '2026-01-18',
            'monitoring_remarks' => 'January encoded report.',
            'updated_by' => $this->admin->id,
        ]);

        $august = $this->project($this->masbate, [
            'project_title' => 'August Masbate SPRS',
            'beneficiaries_total' => 7,
            'beneficiaries_female' => 4,
        ]);

        ProjectMonitoringDetail::query()->create([
            'project_id' => $august->id,
            'sprs_date' => '2026-08-20',
            'monitoring_remarks' => 'August encoded report.',
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.periodic.print', [
                'form' => 'sprs',
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('Statistical Performance Reporting System (SPRS)')
            ->assertSee('Province/', false)
            ->assertSee('Overall')
            ->assertSee('ALBAY')
            ->assertSee('CAMARINES NORTE')
            ->assertSee('CAMARINES SUR')
            ->assertSee('CATANDUANES')
            ->assertSee('MASBATE')
            ->assertSee('SORSOGON')
            ->assertSee('Date', false)
            ->assertSee('Accomplished')
            ->assertSee('Remarks')
            ->assertSee('JANUARY')
            ->assertSee('1ST QUARTER')
            ->assertSee('2ND QUARTER')
            ->assertSee('3RD QUARTER')
            ->assertSee('4TH QUARTER')
            ->assertSee('GRAND TOTAL')
            ->assertSee('data-sprs-row="september"', false)
            ->assertSee('data-sprs-included="0"', false);

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="january-overall-total">\s*10\s*</',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="january-overall-female">\s*6\s*</',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="january-albay-total">\s*10\s*</',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="august-masbate-total">\s*7\s*</',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="august-masbate-female">\s*4\s*</',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="grand-total-overall-total">\s*17\s*</',
            $html,
        );
        $this->assertStringContainsString('January encoded report.', $html);
        $this->assertStringContainsString('August encoded report.', $html);
    }

    public function test_sprs_print_matrix_populates_legacy_imported_project_data_when_sprs_date_is_not_encoded(): void
    {
        $legacy = $this->project($this->albay, [
            'project_title' => 'FY2025 Imported Albay Project',
            'date_received' => '2025-03-13',
            'beneficiaries_total' => 25,
            'beneficiaries_female' => 14,
        ]);

        ProjectMonitoringDetail::query()->create([
            'project_id' => $legacy->id,
            'receipt_month' => 'March',
            'receipt_datetime' => '2025-03-13 00:00:00',
            'sprs_date' => null,
            'monitoring_remarks' => 'Imported FY2025 source row.',
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.periodic.print', [
                'form' => 'sprs',
                'fiscal_year' => 2025,
                'month' => 12,
            ]))
            ->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="march-overall-total">\s*25\s*</',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="march-overall-female">\s*14\s*</',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="march-albay-total">\s*25\s*</',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '/data-sprs-cell="grand-total-overall-total">\s*25\s*</',
            $html,
        );

        $this->assertStringContainsString(
            'Imported FY2025 source row.',
            $html,
        );
    }

    public function test_sprs_print_cutoff_does_not_include_future_month_data(): void
    {
        $september = $this->project($this->masbate, [
            'project_title' => 'September Future SPRS',
            'beneficiaries_total' => 99,
            'beneficiaries_female' => 50,
        ]);

        ProjectMonitoringDetail::query()->create([
            'project_id' => $september->id,
            'sprs_date' => '2026-09-05',
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.periodic.print', [
                'form' => 'sprs',
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk();

        $html = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/data-sprs-row="september"[^>]*data-sprs-included="0"/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-sprs-cell="grand-total-overall-total">\s*99\s*</',
            $html,
        );
    }

    private function province(string $name, string $code): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function project(Province $province, array $overrides = []): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-SPRS-'.$province->id.'-'.uniqid(),
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'location' => $province->name,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => 'Sample Municipality',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-01-01',
            'project_title' => 'SPRS Matrix Project '.uniqid(),
            'nature_of_work' => 'SPRS matrix layout test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
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
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $overrides));
    }
}
