<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Livewire\Reports\GeographicDistributionMap;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use App\Services\Mapping\BicolMapDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class GeographicMappingPhase2FocalRegionViewTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    /** @var array<string,array{province:Province,municipality:Municipality,barangay:Barangay}> */
    private array $references = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $index = 1;
        foreach (config('tupad_mapping.provinces') as $provinceCode => $definition) {
            $province = Province::query()->create([
                'code' => $provinceCode,
                'name' => $definition['name'],
                'is_active' => true,
            ]);
            $municipality = Municipality::query()->create([
                'province_id' => $province->id,
                'code' => substr($provinceCode, 0, 4).sprintf('%02d', $index).'000',
                'name' => $definition['name'].' Test Municipality',
                'district' => 'Test District',
                'is_city' => false,
                'is_active' => true,
            ]);
            $barangay = Barangay::query()->create([
                'municipality_id' => $municipality->id,
                'code' => substr($municipality->code, 0, 6).'001',
                'name' => $definition['name'].' Test Barangay',
                'is_active' => true,
            ]);

            $this->references[$provinceCode] = compact('province', 'municipality', 'barangay');
            $index++;
        }
    }

    public function test_focal_default_geographic_workspace_renders_the_interactive_region_shell(): void
    {
        $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('TUPAD Distribution Map')
            ->assertSee('BICOL REGION MAP')
            ->assertSee('BENEFICIARIES BY PROVINCE')
            ->assertSee('data-tupad-region-map', false)
            ->assertSee('Project Mapping')
            ->assertSee('Beneficiary Mapping');
    }

    public function test_nested_component_accepts_null_default_filters_and_all_filters_can_be_cleared(): void
    {
        $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('TUPAD Distribution Map')
            ->assertSee('All fiscal years')
            ->assertSee('All statuses')
            ->assertSee('All modes');

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class, [
                'fiscalYear' => null,
                'status' => null,
                'implementationMode' => null,
            ])
            ->assertSet('fiscalYear', null)
            ->assertSet('status', null)
            ->assertSet('implementationMode', null)
            ->set('fiscalYear', '2026')
            ->assertSet('fiscalYear', '2026')
            ->set('fiscalYear', '')
            ->assertSet('fiscalYear', null)
            ->set('status', ProjectStatus::ONGOING_IMPLEMENTATION->value)
            ->assertSet('status', ProjectStatus::ONGOING_IMPLEMENTATION->value)
            ->set('status', '')
            ->assertSet('status', null)
            ->set('implementationMode', ImplementationMode::DIRECT_ADMINISTRATION->value)
            ->assertSet('implementationMode', ImplementationMode::DIRECT_ADMINISTRATION->value)
            ->set('implementationMode', '')
            ->assertSet('implementationMode', null);
    }

    public function test_region_payload_has_all_six_psgc_provinces_and_uses_exact_beneficiary_allocations(): void
    {
        $masbate = $this->references['054100000'];
        $project = $this->project($masbate['province'], [
            'beneficiaries_total' => 25,
            'beneficiaries_female' => 15,
            'status' => ProjectStatus::ONGOING_IMPLEMENTATION,
        ]);
        $this->exactLocation($project, $masbate['municipality'], $masbate['barangay'], 18, 11);

        $payload = app(BicolMapDataService::class)->regionPayload($this->focal, [
            'fiscal_year' => 2026,
        ]);

        $this->assertSame('region', $payload['map_level']);
        $this->assertCount(6, $payload['provinces']);
        $this->assertSame('properties.psgc_code', $payload['boundary']['join_key']);

        $masbateRow = collect($payload['provinces'])->firstWhere('psgc_code', '054100000');
        $this->assertNotNull($masbateRow);
        $this->assertSame(18, $masbateRow['beneficiaries']);
        $this->assertSame(1, $masbateRow['projects']);
        $this->assertSame(1, $masbateRow['ongoing_projects']);
        $this->assertSame(18, $payload['summary']['beneficiaries']);
        $this->assertSame(1, $payload['summary']['projects']);
    }

    public function test_livewire_filter_refresh_keeps_map_and_chart_on_one_server_payload(): void
    {
        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->assertSet('mapLevel', 'region')
            ->assertSet('selectedProvinceId', null)
            ->set('fiscalYear', '2026')
            ->assertDispatched('tupad-map-data-updated')
            ->assertSee('BICOL REGION MAP')
            ->assertSee('BENEFICIARIES BY PROVINCE');
    }

    public function test_phase_two_javascript_resolves_chart_from_the_shared_mapping_shell(): void
    {
        $javascript = File::get(resource_path('js/geographic-mapping.js'));

        $this->assertStringContainsString(
            'root.closest(\'[data-mapping-phase="2"]\')',
            $javascript,
        );
        $this->assertStringContainsString(
            "shell?.querySelector('[data-map-chart]')",
            $javascript,
        );
    }

    public function test_tc_receives_the_interactive_shell_at_the_assigned_province_scope(): void
    {
        $masbate = $this->references['054100000']['province'];
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $masbate->id,
        ]);

        $this->actingAs($tc)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('TUPAD Distribution Map')
            ->assertSee('MASBATE MAP')
            ->assertSee('Assigned Province Scope');

        Livewire::actingAs($tc)
            ->test(GeographicDistributionMap::class)
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $masbate->id)
            ->assertSee('MASBATE MAP')
            ->assertDontSee('Back to Region');
    }

    private function project(Province $province, array $overrides = []): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-MAP-P2-'.$province->id.'-'.uniqid(),
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $this->focal->id,
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
            'created_by' => $this->focal->id,
        ]);

        return Project::query()->create(array_merge([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Mapping Phase 2 Project '.uniqid(),
            'nature_of_work' => 'Interactive Region V mapping test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => 'Test District',
            'municipality' => 'Test Municipality',
            'barangay' => 'Test Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 25,
            'beneficiaries_female' => 15,
            'wage_rate' => '500.00',
            'wages_total' => '125000.00',
            'ppe_total' => '5000.00',
            'insurance_rate' => '100.00',
            'insurance_beneficiaries' => 25,
            'insurance_total' => '2500.00',
            'total_project_cost' => '132500.00',
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $this->focal->id,
            'updated_by' => $this->focal->id,
        ], $overrides));
    }

    private function exactLocation(Project $project, Municipality $municipality, Barangay $barangay, int $total, int $female): void
    {
        $location = ProjectLocation::query()->create([
            'project_id' => $project->id,
            'province_id' => $municipality->province_id,
            'municipality_id' => $municipality->id,
            'district' => $municipality->district,
            'sort_order' => 1,
        ]);
        $location->barangays()->attach($barangay->id, [
            'beneficiaries_total' => $total,
            'beneficiaries_female' => $female,
        ]);
    }
}
