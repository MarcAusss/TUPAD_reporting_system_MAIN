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

class GeographicMappingPhase3ProvinceDrillDownTest extends TestCase
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

    public function test_focal_can_drill_from_region_to_province_and_back(): void
    {
        $albay = $this->references['050500000']['province'];

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->assertSet('mapLevel', 'region')
            ->assertSet('selectedProvinceId', null)
            ->call('selectProvince', $albay->id)
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $albay->id)
            ->assertSee('ALBAY MAP')
            ->assertSee('BENEFICIARIES BY MUNICIPALITY / CITY')
            ->assertSee('Back to Region')
            ->assertDispatched('tupad-map-data-updated')
            ->call('showRegion')
            ->assertSet('mapLevel', 'region')
            ->assertSet('selectedProvinceId', null)
            ->assertSee('BICOL REGION MAP')
            ->assertSee('BENEFICIARIES BY PROVINCE');
    }

    public function test_selected_province_payload_uses_only_its_municipality_rows_and_lazy_geojson_path(): void
    {
        $albay = $this->references['050500000'];

        $secondMunicipality = Municipality::query()->create([
            'province_id' => $albay['province']->id,
            'code' => '050502000',
            'name' => 'Second Albay Municipality',
            'district' => 'Second District',
            'is_city' => false,
            'is_active' => true,
        ]);
        $secondBarangay = Barangay::query()->create([
            'municipality_id' => $secondMunicipality->id,
            'code' => '050502001',
            'name' => 'Second Albay Barangay',
            'is_active' => true,
        ]);

        $ongoing = $this->project($albay['province'], [
            'status' => ProjectStatus::ONGOING_IMPLEMENTATION,
            'project_title' => 'Albay Ongoing Mapping Project',
        ]);
        $this->exactLocation($ongoing, $albay['municipality'], $albay['barangay'], 12, 7);

        $completed = $this->project($albay['province'], [
            'status' => ProjectStatus::COMPLETED,
            'project_title' => 'Albay Completed Mapping Project',
        ]);
        $this->exactLocation($completed, $secondMunicipality, $secondBarangay, 7, 4);

        // A foreign province project must not leak into the selected-province payload.
        $masbate = $this->references['054100000'];
        $foreign = $this->project($masbate['province'], [
            'status' => ProjectStatus::ONGOING_IMPLEMENTATION,
            'project_title' => 'Masbate Foreign Mapping Project',
        ]);
        $this->exactLocation($foreign, $masbate['municipality'], $masbate['barangay'], 99, 40);

        $payload = app(BicolMapDataService::class)->provincePayload(
            $this->focal,
            $albay['province']->id,
            ['fiscal_year' => 2026],
        );

        $this->assertSame('province', $payload['map_level']);
        $this->assertSame($albay['province']->id, $payload['selected_province']['id']);
        $this->assertSame('Albay', $payload['selected_province']['name']);
        $this->assertSame('properties.psgc_code', $payload['boundary']['join_key']);
        $this->assertStringEndsWith('geojson/bicol/municipalities/albay.geojson', $payload['boundary']['path']);
        $this->assertSame([], $payload['provinces']);
        $this->assertCount(2, $payload['municipalities']);
        $this->assertSame(19, $payload['summary']['beneficiaries']);
        $this->assertSame(2, $payload['summary']['projects']);
        $this->assertSame(1, $payload['summary']['ongoing_projects']);
        $this->assertSame(1, $payload['summary']['completed_projects']);

        $first = collect($payload['municipalities'])->firstWhere('id', $albay['municipality']->id);
        $second = collect($payload['municipalities'])->firstWhere('id', $secondMunicipality->id);

        $this->assertSame(12, $first['beneficiaries']);
        $this->assertSame(1, $first['projects']);
        $this->assertSame(1, $first['ongoing_projects']);
        $this->assertSame(0, $first['completed_projects']);
        $this->assertFalse($first['allocation_available']);
        $this->assertNull($first['allocation_cents']);

        $this->assertSame(7, $second['beneficiaries']);
        $this->assertSame(0, $second['ongoing_projects']);
        $this->assertSame(1, $second['completed_projects']);
    }

    public function test_non_bicol_province_id_cannot_be_selected_through_livewire_payload(): void
    {
        $outside = Province::query()->create([
            'code' => '010000000',
            'name' => 'Outside Region V',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->call('selectProvince', $outside->id)
            ->assertNotFound();
    }

    public function test_filter_refresh_preserves_selected_province_scope(): void
    {
        $albay = $this->references['050500000']['province'];

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->call('selectProvince', $albay->id)
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $albay->id)
            ->set('fiscalYear', '2026')
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $albay->id)
            ->assertSee('Albay')
            ->assertSee('BENEFICIARIES BY MUNICIPALITY / CITY')
            ->assertDispatched('tupad-map-data-updated');
    }

    public function test_phase_three_javascript_drills_on_province_click_and_swaps_boundary_lazily(): void
    {
        $javascript = File::get(resource_path('js/geographic-mapping.js'));

        $this->assertStringContainsString('tupad-map-select-province', $javascript);
        $this->assertStringContainsString('Click to view municipalities and cities', $javascript);
        $this->assertStringContainsString('state.boundaryUrl !== nextBoundaryUrl', $javascript);
        $this->assertStringContainsString("payload?.map_level !== 'region'", $javascript);
        $this->assertStringContainsString('fitBounds(layer.getBounds()', $javascript);
    }

    private function project(Province $province, array $overrides = []): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-MAP-P3-'.$province->id.'-'.uniqid(),
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
            'project_title' => 'Mapping Phase 3 Project '.uniqid(),
            'nature_of_work' => 'Interactive Region V province drill-down test.',
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
