<?php

namespace Tests\Feature;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\ImplementationMode;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectBeneficiarySector;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MajorRevisionPhase14EGeographicMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $masbate;
    private Province $albay;
    private Municipality $masbateCity;
    private Municipality $mobo;
    private Barangay $barangayA;
    private Barangay $barangayB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);
        $this->masbate = $this->province('Masbate', '054100000');
        $this->albay = $this->province('Albay', '050500000');

        $this->masbateCity = Municipality::query()->create([
            'province_id' => $this->masbate->id,
            'code' => '054111000',
            'name' => 'City of Masbate',
            'district' => '2nd District',
            'income_class' => '4th Class',
            'is_city' => true,
            'is_active' => true,
        ]);
        $this->mobo = Municipality::query()->create([
            'province_id' => $this->masbate->id,
            'code' => '054112000',
            'name' => 'Mobo',
            'district' => '2nd District',
            'income_class' => '3rd Class',
            'is_city' => false,
            'is_active' => true,
        ]);
        $this->barangayA = Barangay::query()->create([
            'municipality_id' => $this->masbateCity->id,
            'code' => '054111001',
            'name' => 'Barangay A',
            'is_active' => true,
        ]);
        $this->barangayB = Barangay::query()->create([
            'municipality_id' => $this->mobo->id,
            'code' => '054112001',
            'name' => 'Barangay B',
            'is_active' => true,
        ]);
    }

    public function test_dedicated_geographic_mapping_route_keeps_security_and_four_requested_families(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('Project Mapping')
            ->assertSee('Beneficiary Mapping')
            ->assertSee('Sector Mapping')
            ->assertSee('Intervention-Focus Mapping')
            ->assertSee('Province')
            ->assertSee('District')
            ->assertSee('Municipality');

        $route = Route::getRoutes()->getByName('reports.workspace.geographic-mapping');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('province.scope', $route->gatherMiddleware());
        $this->assertContains('role:admin,tc,focal', $route->gatherMiddleware());
    }

    public function test_project_mapping_supports_province_district_and_municipality_without_inventing_financial_splits(): void
    {
        $project = $this->project($this->masbate, [
            'project_title' => 'Multi Location Mapping Project',
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 12,
        ]);
        $this->exactLocation($project, $this->masbateCity, $this->barangayA, 12, 7);
        $this->exactLocation($project, $this->mobo, $this->barangayB, 8, 5);

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'projects',
                'level' => 'municipality',
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('City of Masbate')
            ->assertSee('Mobo')
            ->assertSee('Multi-location integrity')
            ->assertSee('does not divide or infer project money');

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'projects',
                'level' => 'barangay',
            ]))
            ->assertSessionHasErrors('level');
    }

    public function test_beneficiary_mapping_uses_exact_allocations_and_flags_legacy_unallocated_rows(): void
    {
        $exact = $this->project($this->masbate, [
            'project_title' => 'Exact Beneficiary Mapping',
            'beneficiaries_total' => 15,
            'beneficiaries_female' => 9,
        ]);
        $this->exactLocation($exact, $this->masbateCity, $this->barangayA, 15, 9);

        $legacy = $this->project($this->masbate, [
            'project_title' => 'Legacy Beneficiary Mapping',
            'beneficiaries_total' => 40,
            'beneficiaries_female' => 20,
        ]);
        $location = ProjectLocation::query()->create([
            'project_id' => $legacy->id,
            'province_id' => $this->masbate->id,
            'municipality_id' => $this->mobo->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $location->barangays()->attach($this->barangayB->id, [
            'beneficiaries_total' => null,
            'beneficiaries_female' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'beneficiaries',
                'level' => 'barangay',
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('Barangay A')
            ->assertSee('Barangay B')
            ->assertSee('Exact beneficiary mapping')
            ->assertSee('Includes legacy unallocated records');

        $rows = $response->viewData('rows');
        $this->assertSame(15, $rows->firstWhere('key', (string) $this->barangayA->id)['beneficiaries_total']);
        $this->assertSame(0, $rows->firstWhere('key', (string) $this->barangayB->id)['beneficiaries_total']);
        $this->assertFalse($rows->firstWhere('key', (string) $this->barangayB->id)['has_complete_exact_allocation']);
    }

    public function test_sector_mapping_preserves_both_requested_sector_families_and_overlap_warning(): void
    {
        $project = $this->project($this->masbate);
        ProjectBeneficiarySector::query()->create([
            'project_id' => $project->id,
            'sector_group' => BeneficiarySectorCategory::PERSONS_WITH_DISABILITIES->group(),
            'sector_key' => BeneficiarySectorCategory::PERSONS_WITH_DISABILITIES,
            'beneficiaries_total' => 6,
            'beneficiaries_female' => 4,
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        ProjectBeneficiarySector::query()->create([
            'project_id' => $project->id,
            'sector_group' => BeneficiarySectorCategory::TRANSPORT_WORKERS->group(),
            'sector_key' => BeneficiarySectorCategory::TRANSPORT_WORKERS,
            'beneficiaries_total' => 5,
            'beneficiaries_female' => 2,
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'sectors',
                'sector_group' => BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('Priority / Vulnerable Sectors')
            ->assertSee('Occupational / Livelihood Sectors')
            ->assertSee('Persons with Disabilities')
            ->assertSee('Persons Deprived of Liberty')
            ->assertSee('Transport Workers')
            ->assertSee('Overlapping classifications')
            ->assertSee('must not be summed');
    }

    public function test_intervention_focus_mapping_uses_authoritative_primary_focus_categories(): void
    {
        $this->project($this->masbate, [
            'project_title' => 'Environmental Project',
            'intervention_focus' => ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'interventions',
                'province_id' => $this->masbate->id,
            ]))
            ->assertOk()
            ->assertSee('Disaster Risk Reduction and Mitigation')
            ->assertSee('Emergency Preparedness')
            ->assertSee('Environmental Conservation')
            ->assertSee('Early Recovery and Rehabilitation')
            ->assertSee('Administrative, Clerical and Logistical Support');
    }

    public function test_coordinator_mapping_is_forced_to_assigned_province_and_foreign_filter_is_forbidden(): void
    {
        $masbateProject = $this->project($this->masbate, ['project_title' => 'Masbate Map Project']);
        $this->project($this->albay, ['project_title' => 'Albay Map Project']);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $response = $this->actingAs($tc)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'projects',
                'level' => 'province',
            ]))
            ->assertOk()
            ->assertSee('Masbate')
            ->assertDontSee('Albay');

        $this->assertSame($this->masbate->id, (int) $response->viewData('filters')['province_id']);
        $this->assertSame(1, $response->viewData('summary')[0]['value']);
        $this->assertSame($masbateProject->province_id, $this->masbate->id);

        $this->actingAs($tc)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'beneficiaries',
                'level' => 'province',
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

    private function project(Province $province, array $overrides = []): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P14E-'.$province->id.'-'.uniqid(),
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
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 14E Project '.uniqid(),
            'nature_of_work' => 'Geographic mapping test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '2nd District',
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

    private function exactLocation(
        Project $project,
        Municipality $municipality,
        Barangay $barangay,
        int $beneficiaries,
        int $female,
    ): ProjectLocation {
        $location = ProjectLocation::query()->create([
            'project_id' => $project->id,
            'province_id' => $municipality->province_id,
            'municipality_id' => $municipality->id,
            'district' => $municipality->district,
            'sort_order' => $project->projectLocations()->count() + 1,
        ]);
        $location->barangays()->attach($barangay->id, [
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => $female,
        ]);

        return $location;
    }
}
