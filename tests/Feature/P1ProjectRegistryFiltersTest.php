<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1ProjectRegistryFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $albay;
    private Province $masbate;
    private Municipality $legazpi;
    private Municipality $masbateCity;
    private Barangay $rawis;
    private Barangay $bapor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->albay = $this->province('Albay', '050500000');
        $this->masbate = $this->province('Masbate', '054100000');
        $this->legazpi = $this->municipality($this->albay, 'Legazpi City', '050506000');
        $this->masbateCity = $this->municipality($this->masbate, 'Masbate City', '054101000');
        $this->rawis = $this->barangay($this->legazpi, 'Rawis', '050506001');
        $this->bapor = $this->barangay($this->masbateCity, 'Bapor', '054101001');
    }

    public function test_registry_searches_project_title_project_code_and_adl_number(): void
    {
        $alpha = $this->project(
            province: $this->albay,
            municipality: $this->legazpi,
            barangay: $this->rawis,
            title: 'ALPHA COMMUNITY PROJECT',
            dateReceived: '2026-03-10',
            status: ProjectStatus::APPROVED,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 25,
            cost: '125000.00',
            adlNumber: 'ADL-P1R-ALPHA',
        );

        ProjectApproval::query()->create([
            'project_id' => $alpha->id,
            'approval_date' => '2026-03-15',
            'project_code' => 'TUPAD-V-ALB-2026-001',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $beta = $this->project(
            province: $this->masbate,
            municipality: $this->masbateCity,
            barangay: $this->bapor,
            title: 'BETA COASTAL PROJECT',
            dateReceived: '2026-04-10',
            status: ProjectStatus::ONGOING_PROFILING,
            mode: ImplementationMode::THROUGH_ACP,
            beneficiaries: 15,
            cost: '75000.00',
            adlNumber: 'ADL-P1R-BETA',
        );

        $this->actingAs($this->admin)
            ->get(route('projects.index', ['q' => 'ALPHA COMMUNITY']))
            ->assertOk()
            ->assertSee($alpha->project_title)
            ->assertDontSee($beta->project_title);

        $this->actingAs($this->admin)
            ->get(route('projects.index', ['q' => 'TUPAD-V-ALB-2026-001']))
            ->assertOk()
            ->assertSee($alpha->project_title)
            ->assertDontSee($beta->project_title);

        $this->actingAs($this->admin)
            ->get(route('projects.index', ['q' => 'ADL-P1R-BETA']))
            ->assertOk()
            ->assertSee($beta->project_title)
            ->assertDontSee($alpha->project_title);
    }

    public function test_registry_combines_status_mode_location_and_fiscal_year_filters(): void
    {
        $matching = $this->project(
            province: $this->albay,
            municipality: $this->legazpi,
            barangay: $this->rawis,
            title: 'MATCHING FILTER PROJECT',
            dateReceived: '2026-06-20',
            status: ProjectStatus::APPROVED,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 30,
            cost: '180000.00',
            adlNumber: 'ADL-P1R-MATCH',
        );

        $wrongStatus = $this->project(
            province: $this->albay,
            municipality: $this->legazpi,
            barangay: $this->rawis,
            title: 'WRONG STATUS PROJECT',
            dateReceived: '2026-06-21',
            status: ProjectStatus::TSSD_EVALUATION,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 20,
            cost: '100000.00',
            adlNumber: 'ADL-P1R-WRONG-STATUS',
        );

        $wrongLocationAndYear = $this->project(
            province: $this->masbate,
            municipality: $this->masbateCity,
            barangay: $this->bapor,
            title: 'WRONG LOCATION PROJECT',
            dateReceived: '2025-06-20',
            status: ProjectStatus::APPROVED,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 50,
            cost: '250000.00',
            adlNumber: 'ADL-P1R-WRONG-LOCATION',
        );

        $response = $this->actingAs($this->admin)
            ->get(route('projects.index', [
                'status' => ProjectStatus::APPROVED->value,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION->value,
                'province_id' => $this->albay->id,
                'municipality_id' => $this->legazpi->id,
                'fiscal_year' => 2026,
            ]));

        $response
            ->assertOk()
            ->assertSee($matching->project_title)
            ->assertDontSee($wrongStatus->project_title)
            ->assertDontSee($wrongLocationAndYear->project_title)
            ->assertSee('Active filters')
            ->assertSee('FY 2026');
    }

    public function test_registry_sorting_changes_project_order_and_filter_controls_are_rendered(): void
    {
        $low = $this->project(
            province: $this->albay,
            municipality: $this->legazpi,
            barangay: $this->rawis,
            title: 'LOW BENEFICIARY PROJECT',
            dateReceived: '2026-01-10',
            status: ProjectStatus::ONGOING_PROFILING,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 10,
            cost: '50000.00',
            adlNumber: 'ADL-P1R-LOW',
        );

        $high = $this->project(
            province: $this->albay,
            municipality: $this->legazpi,
            barangay: $this->rawis,
            title: 'HIGH BENEFICIARY PROJECT',
            dateReceived: '2026-01-11',
            status: ProjectStatus::ONGOING_PROFILING,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 90,
            cost: '450000.00',
            adlNumber: 'ADL-P1R-HIGH',
        );

        $response = $this->actingAs($this->admin)
            ->get(route('projects.index', ['sort' => 'beneficiaries_desc']));

        $response
            ->assertOk()
            ->assertSeeInOrder([$high->project_title, $low->project_title])
            ->assertSee('Registry Filters')
            ->assertSee('name="q"', false)
            ->assertSee('name="status"', false)
            ->assertSee('name="implementation_mode"', false)
            ->assertSee('name="province_id"', false)
            ->assertSee('name="municipality_id"', false)
            ->assertSee('name="fiscal_year"', false)
            ->assertSee('name="sort"', false);
    }

    public function test_tc_registry_is_locked_to_assigned_province_and_rejects_foreign_province_filter(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $masbateProject = $this->project(
            province: $this->masbate,
            municipality: $this->masbateCity,
            barangay: $this->bapor,
            title: 'MASBATE REGISTRY PROJECT',
            dateReceived: '2026-07-01',
            status: ProjectStatus::ONGOING_PROFILING,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 30,
            cost: '150000.00',
            adlNumber: 'ADL-P1R-MASBATE',
        );

        $albayProject = $this->project(
            province: $this->albay,
            municipality: $this->legazpi,
            barangay: $this->rawis,
            title: 'ALBAY FOREIGN REGISTRY PROJECT',
            dateReceived: '2026-07-02',
            status: ProjectStatus::ONGOING_PROFILING,
            mode: ImplementationMode::DIRECT_ADMINISTRATION,
            beneficiaries: 30,
            cost: '150000.00',
            adlNumber: 'ADL-P1R-ALBAY',
        );

        $this->actingAs($tc)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Assigned Province: Masbate')
            ->assertSee($masbateProject->project_title)
            ->assertDontSee($albayProject->project_title)
            ->assertSee('Masbate City')
            ->assertDontSee('Legazpi City');

        $this->actingAs($tc)
            ->get(route('projects.index', ['province_id' => $this->albay->id]))
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

    private function municipality(Province $province, string $name, string $code): Municipality
    {
        return Municipality::query()->create([
            'province_id' => $province->id,
            'name' => $name,
            'code' => $code,
            'district' => '1st District',
            'income_class' => 'Component City',
            'is_city' => true,
            'is_active' => true,
        ]);
    }

    private function barangay(Municipality $municipality, string $name, string $code): Barangay
    {
        return Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function project(
        Province $province,
        Municipality $municipality,
        Barangay $barangay,
        string $title,
        string $dateReceived,
        ProjectStatus $status,
        ImplementationMode $mode,
        int $beneficiaries,
        string $cost,
        string $adlNumber,
    ): Project {
        $adl = Adl::query()->create([
            'adl_number' => $adlNumber,
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'location' => $municipality->name.', '.$province->name,
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'amount' => '1000000.00',
            'grant_amount' => '1000000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);

        $project = Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => $dateReceived,
            'project_title' => $title,
            'nature_of_work' => 'P1 project registry filter test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'project_series' => 'P1 Registry',
            'tevs_date_verified' => $dateReceived,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'income_class' => $municipality->income_class,
            'implementation_mode' => $mode,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => intdiv($beneficiaries, 2),
            'wage_rate' => '500.00',
            'wages_total' => '50000.00',
            'ppe_total' => '0.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => $beneficiaries,
            'insurance_total' => '500.00',
            'total_project_cost' => $cost,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);

        $location = ProjectLocation::query()->create([
            'project_id' => $project->id,
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'district' => $municipality->district,
            'sort_order' => 1,
        ]);

        $location->barangays()->attach($barangay->id, [
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => intdiv($beneficiaries, 2),
        ]);

        return $project;
    }
}
