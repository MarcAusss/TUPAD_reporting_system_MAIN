<?php

namespace Tests\Feature;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectBeneficiarySector;
use App\Models\Province;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Exports\XlsxTableWriter;
use App\Services\Reports\ReportGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MajorRevisionPhase14GFinalReportsReleaseVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_phase_fourteen_report_routes_are_registered_once_and_keep_security_middleware(): void
    {
        $routeNames = [
            'reports.index',
            'reports.workspace.physical-financial',
            'reports.workspace.fund-status',
            'reports.workspace.monthly',
            'reports.workspace.quarterly',
            'reports.workspace.geographic-mapping',
            'reports.print',
            'reports.export.pdf',
            'reports.export.excel',
            'reports.export.csv',
            'reports.periodic.print',
            'reports.periodic.export.pdf',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Expected [{$routeName}] to be registered.");
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('province.scope', $route->gatherMiddleware());
            $this->assertContains('role:admin,tc,focal', $route->gatherMiddleware());
        }

        $routeSource = file_get_contents(base_path('routes/web.php'));
        $this->assertIsString($routeSource);
        $this->assertSame(1, substr_count($routeSource, "Route::get('/reports/periodic/print'"));
        $this->assertSame(1, substr_count($routeSource, "Route::get('/reports/periodic/export/pdf'"));
    }

    public function test_sector_group_filter_keeps_mapping_print_pdf_csv_and_excel_on_the_same_cohort(): void
    {
        $admin = $this->admin();
        $province = $this->province();
        $project = $this->project($admin, $province);

        $this->sector($project, $admin, BeneficiarySectorCategory::PERSONS_WITH_DISABILITIES, 6, 4);
        $this->sector($project, $admin, BeneficiarySectorCategory::TRANSPORT_WORKERS, 5, 2);

        $query = [
            'view' => 'sectors',
            'sector_group' => BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
            'province_id' => $province->id,
        ];

        $screen = $this->actingAs($admin)
            ->get(route('reports.workspace.geographic-mapping', $query))
            ->assertOk();

        $rows = $screen->viewData('rows');
        $this->assertNotEmpty($rows);
        $this->assertTrue($rows->every(
            fn (array $row): bool =>
                $row['sector_group'] === BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE
        ));
        $this->assertSame(
            BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
            $screen->viewData('exportQuery')['sector_group'],
        );

        $exportQuery = $screen->viewData('exportQuery');

        $this->actingAs($admin)
            ->get(route('reports.print', $exportQuery))
            ->assertOk()
            ->assertSee('Priority / Vulnerable Sectors')
            ->assertSee('Persons with Disabilities')
            ->assertDontSee('Transport Workers');

        $pdf = $this->actingAs($admin)
            ->get(route('reports.export.pdf', $exportQuery))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('Persons with Disabilities', $pdf->getContent());
        $this->assertStringNotContainsString('Transport Workers', $pdf->getContent());

        $csv = $this->actingAs($admin)
            ->get(route('reports.export.csv', $exportQuery))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();
        $this->assertStringContainsString('Persons with Disabilities', $csv);
        $this->assertStringNotContainsString('Transport Workers', $csv);

        $report = app(ReportGenerationService::class)->generate(
            ReportType::BENEFICIARY_SECTORS,
            ReportDimension::SECTOR,
            ReportFilters::fromArray([
                'province_id' => $province->id,
                'sector_group' => BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
            ]),
        );
        $path = app(XlsxTableWriter::class)->write($report);

        try {
            $xlsx = file_get_contents($path);
            $this->assertIsString($xlsx);
            $this->assertStringContainsString('Persons with Disabilities', $xlsx);
            $this->assertStringNotContainsString('Transport Workers', $xlsx);
        } finally {
            @unlink($path);
        }
    }

    public function test_sector_group_is_server_validated_and_cannot_conflict_with_selected_sector(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('reports.index'))
            ->get(route('reports.index', [
                'report_type' => ReportType::BENEFICIARY_SECTORS->value,
                'group_by' => ReportDimension::SECTOR->value,
                'sector_group' => 'not-a-sector-group',
            ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('sector_group');

        $this->actingAs($admin)
            ->from(route('reports.index'))
            ->get(route('reports.index', [
                'report_type' => ReportType::BENEFICIARY_SECTORS->value,
                'group_by' => ReportDimension::SECTOR->value,
                'sector_group' => BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
                'sector' => BeneficiarySectorCategory::TRANSPORT_WORKERS->value,
            ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('sector');
    }

    public function test_lce_fund_status_screen_and_all_export_formats_use_the_same_allocation_only_row(): void
    {
        $admin = $this->admin();

        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P14G-LCE-001',
            'grants' => '150000.00',
            'admin_cost' => '0.00',
            'total' => '150000.00',
            'created_by' => $admin->id,
        ]);

        AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Phase 14G',
            'local_chief_executive_partylist' => 'Mayor Phase 14G',
            'location' => 'Bicol Region',
            'province' => 'Albay',
            'amount' => '150000.00',
            'grant_amount' => '150000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '150000.00',
            'created_by' => $admin->id,
        ]);

        $query = [
            'report_type' => ReportType::FUND_STATUS->value,
            'group_by' => ReportDimension::LCE->value,
        ];

        $this->actingAs($admin)
            ->get(route('reports.index', $query))
            ->assertOk()
            ->assertSee('Mayor Phase 14G')
            ->assertSee('PHP 150,000.00');

        $this->actingAs($admin)
            ->get(route('reports.print', $query))
            ->assertOk()
            ->assertSee('Mayor Phase 14G');

        $pdf = $this->actingAs($admin)
            ->get(route('reports.export.pdf', $query))
            ->assertOk();
        $this->assertStringContainsString('Mayor Phase 14G', $pdf->getContent());

        $csv = $this->actingAs($admin)
            ->get(route('reports.export.csv', $query))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('Mayor Phase 14G', $csv);

        $report = app(ReportGenerationService::class)->generate(
            ReportType::FUND_STATUS,
            ReportDimension::LCE,
            new ReportFilters(),
        );
        $path = app(XlsxTableWriter::class)->write($report);

        try {
            $xlsx = file_get_contents($path);
            $this->assertIsString($xlsx);
            $this->assertStringContainsString('Mayor Phase 14G', $xlsx);
        } finally {
            @unlink($path);
        }

        $this->assertTrue(ReportType::FUND_STATUS->allows(ReportDimension::LCE));
        $this->assertFalse(ReportType::PHYSICAL_FINANCIAL->allows(ReportDimension::LCE));
    }

    public function test_periodic_browser_print_and_pdf_share_the_same_form_period_and_header_identity(): void
    {
        $admin = $this->admin();
        $query = [
            'form' => 'sprs',
            'fiscal_year' => 2026,
            'month' => 8,
        ];

        $this->actingAs($admin)
            ->get(route('reports.periodic.print', $query))
            ->assertOk()
            ->assertSee('TUPAD Reporting System')
            ->assertSee('Statistical Performance Reporting System (SPRS)')
            ->assertSee('August 2026');

        $pdf = $this->actingAs($admin)
            ->get(route('reports.periodic.export.pdf', $query))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $content = $pdf->getContent();
        $this->assertStringContainsString('TUPAD Reporting System', $content);
        $this->assertStringContainsString('Statistical Performance Reporting System \\(SPRS\\)', $content);
        $this->assertStringContainsString('August 2026', $content);
    }

    public function test_release_verifier_enforces_phase_fourteen_reporting_schema_routes_and_print_asset(): void
    {
        $this->assertTrue(Schema::hasColumn('adl_allocations', 'local_chief_executive_partylist'));
        $this->assertTrue(Schema::hasColumn('project_orientations', 'alkansssya_conducted'));
        $this->assertTrue(Schema::hasColumn('project_orientations', 'yakap_conducted'));
        $this->assertTrue(Schema::hasColumn('project_labor_market_referrals', 'services_availed'));
        $this->assertFileExists(public_path('images/tupad-print-brand.jpg'));

        $this->artisan('tupad:release-verify')
            ->expectsOutputToContain('Release verification PASSED')
            ->assertExitCode(0);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);
    }

    private function province(): Province
    {
        return Province::query()->create([
            'name' => 'Masbate',
            'code' => '054100000',
            'is_active' => true,
        ]);
    }

    private function project(User $admin, Province $province): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P14G-SECTOR-001',
            'grants' => '100000.00',
            'admin_cost' => '0.00',
            'total' => '100000.00',
            'created_by' => $admin->id,
        ]);
        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'location' => 'Masbate',
            'province' => 'Masbate',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $admin->id,
        ]);

        return Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 14G Sector Export Project',
            'nature_of_work' => 'Final reporting regression.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '2nd District',
            'municipality' => 'Masbate City',
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
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    private function sector(
        Project $project,
        User $admin,
        BeneficiarySectorCategory $category,
        int $total,
        int $female,
    ): void {
        ProjectBeneficiarySector::query()->create([
            'project_id' => $project->id,
            'sector_group' => $category->group(),
            'sector_key' => $category,
            'beneficiaries_total' => $total,
            'beneficiaries_female' => $female,
            'recorded_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }
}
