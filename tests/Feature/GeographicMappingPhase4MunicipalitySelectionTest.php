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
use App\Services\Mapping\BicolBarangayLabelSyncService;
use App\Services\Mapping\BicolGeographicFoundation;
use App\Services\Mapping\BicolMapDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GeographicMappingPhase4MunicipalitySelectionTest extends TestCase
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

    public function test_focal_can_select_municipality_show_barangay_ranking_and_back_to_municipalities(): void
    {
        $albay = $this->references['050500000'];

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->call('selectProvince', $albay['province']->id)
            ->call('selectMunicipality', $albay['municipality']->id)
            ->assertSet('mapLevel', 'municipality')
            ->assertSet('selectedProvinceId', $albay['province']->id)
            ->assertSet('selectedMunicipalityId', $albay['municipality']->id)
            ->assertSee(strtoupper($albay['municipality']->name).' — BENEFICIARIES BY BARANGAY')
            ->assertSee('Back to Municipalities')
            ->assertDispatched('tupad-map-data-updated')
            ->call('showProvince')
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $albay['province']->id)
            ->assertSet('selectedMunicipalityId', null)
            ->assertSee('BENEFICIARIES BY MUNICIPALITY / CITY');
    }

    public function test_selected_municipality_payload_uses_exact_barangays_but_keeps_province_municipality_polygons(): void
    {
        $albay = $this->references['050500000'];
        $secondBarangay = Barangay::query()->create([
            'municipality_id' => $albay['municipality']->id,
            'code' => substr($albay['municipality']->code, 0, 6).'002',
            'name' => 'Second Selected Barangay',
            'is_active' => true,
        ]);
        $secondMunicipality = Municipality::query()->create([
            'province_id' => $albay['province']->id,
            'code' => '050502000',
            'name' => 'Second Albay Municipality',
            'district' => 'Second District',
            'is_city' => false,
            'is_active' => true,
        ]);
        $foreignBarangay = Barangay::query()->create([
            'municipality_id' => $secondMunicipality->id,
            'code' => '050502001',
            'name' => 'Foreign Municipality Barangay',
            'is_active' => true,
        ]);

        $selectedProject = $this->project($albay['province'], [
            'status' => ProjectStatus::ONGOING_IMPLEMENTATION,
            'project_title' => 'Selected Municipality Project',
        ]);
        $this->exactLocation($selectedProject, $albay['municipality'], $albay['barangay'], 14, 8);
        $this->exactLocation($selectedProject, $albay['municipality'], $secondBarangay, 6, 3);

        $otherProject = $this->project($albay['province'], [
            'status' => ProjectStatus::COMPLETED,
            'project_title' => 'Other Municipality Project',
        ]);
        $this->exactLocation($otherProject, $secondMunicipality, $foreignBarangay, 50, 25);

        $payload = app(BicolMapDataService::class)->municipalityPayload(
            $this->focal,
            $albay['province']->id,
            $albay['municipality']->id,
            ['fiscal_year' => 2026],
        );

        $this->assertSame('municipality', $payload['map_level']);
        $this->assertSame($albay['municipality']->id, $payload['selected_municipality']['id']);
        $this->assertCount(2, $payload['municipalities']);
        $this->assertCount(2, $payload['barangays']);
        $this->assertSame($payload['barangays'], $payload['areas']);
        $this->assertSame(20, $payload['summary']['beneficiaries']);
        $this->assertSame(1, $payload['summary']['projects']);
        $this->assertSame(1, $payload['summary']['ongoing_projects']);
        $this->assertSame(0, $payload['summary']['completed_projects']);
        $this->assertFalse($payload['summary']['allocation_available']);
        $this->assertNull($payload['summary']['allocation_cents']);
        $this->assertStringEndsWith('geojson/bicol/municipalities/albay.geojson', $payload['boundary']['path']);
        $this->assertStringEndsWith(
            'geojson/bicol/barangay-labels/'.$albay['municipality']->code.'.geojson',
            $payload['label_boundary']['path'],
        );

        $first = collect($payload['barangays'])->firstWhere('id', $albay['barangay']->id);
        $second = collect($payload['barangays'])->firstWhere('id', $secondBarangay->id);
        $this->assertSame(14, $first['beneficiaries']);
        $this->assertSame(6, $second['beneficiaries']);
        $this->assertNull(collect($payload['barangays'])->firstWhere('id', $foreignBarangay->id));
    }

    public function test_municipality_selection_is_validated_against_selected_province(): void
    {
        $albay = $this->references['050500000'];
        $masbateMunicipality = $this->references['054100000']['municipality'];

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->call('selectProvince', $albay['province']->id)
            ->call('selectMunicipality', $masbateMunicipality->id)
            ->assertNotFound();
    }

    public function test_filter_refresh_preserves_selected_municipality_scope(): void
    {
        $albay = $this->references['050500000'];

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->call('selectProvince', $albay['province']->id)
            ->call('selectMunicipality', $albay['municipality']->id)
            ->set('fiscalYear', '2026')
            ->assertSet('mapLevel', 'municipality')
            ->assertSet('selectedProvinceId', $albay['province']->id)
            ->assertSet('selectedMunicipalityId', $albay['municipality']->id)
            ->assertDispatched('tupad-map-data-updated');
    }

    public function test_phase_four_javascript_has_municipality_and_barangay_labels_and_distinct_chart_rows(): void
    {
        $javascript = File::get(resource_path('js/geographic-mapping.js'));
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('tupad-map-select-municipality', $javascript);
        $this->assertStringContainsString('function mapRows(payload)', $javascript);
        $this->assertStringContainsString('function chartRows(payload)', $javascript);
        $this->assertStringContainsString('payload?.label_boundary?.ready', $javascript);
        $this->assertStringContainsString('tupad-municipality-label', $javascript);
        $this->assertStringContainsString('tupad-barangay-label', $javascript);
        $this->assertStringContainsString('.tupad-municipality-label-selected', $css);
        $this->assertStringContainsString('.tupad-barangay-label', $css);
    }

    public function test_barangay_label_sync_generates_lightweight_points_by_municipality(): void
    {
        $relativePath = 'geojson/testing-phase4-'.uniqid();
        config(['tupad_mapping.public_path' => $relativePath]);

        $foundation = app(BicolGeographicFoundation::class);
        $bySourceMunicipality = collect($this->references)
            ->mapWithKeys(fn (array $reference): array => [
                $foundation->sourceMunicipalityCode($reference['municipality']->code) => $reference,
            ]);

        Http::fake(function (Request $request) use ($bySourceMunicipality) {
            preg_match('/bgysubmuns-municity-(\d+)\.0\.01\.json$/', $request->url(), $matches);
            $reference = $bySourceMunicipality->get($matches[1] ?? '');

            if (! $reference) {
                return Http::response(['message' => 'not found'], 404);
            }

            $sourceBarangayCode = ltrim(
                substr($reference['barangay']->code, 0, 2).'0'.substr($reference['barangay']->code, 2),
                '0',
            );

            return Http::response([
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [
                        'adm4_psgc' => (int) $sourceBarangayCode,
                        'adm4_en' => $reference['barangay']->name,
                    ],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [123.0, 13.0],
                            [123.2, 13.0],
                            [123.2, 13.2],
                            [123.0, 13.2],
                            [123.0, 13.0],
                        ]],
                    ],
                ]],
            ], 200);
        });

        try {
            $result = app(BicolBarangayLabelSyncService::class)->sync();
            $this->assertSame(6, $result['municipalities']);
            $this->assertSame(6, $result['barangay_labels']);

            $albay = $this->references['050500000'];
            $path = $foundation->barangayLabelPath($albay['municipality']->code);
            $this->assertFileExists($path);

            $json = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('Point', $json['features'][0]['geometry']['type']);
            $this->assertSame([123.1, 13.1], $json['features'][0]['geometry']['coordinates']);
            $this->assertSame($albay['barangay']->code, $json['features'][0]['properties']['psgc_code']);
        } finally {
            File::deleteDirectory(public_path($relativePath));
        }
    }

    public function test_barangay_label_sync_skips_matched_psgc_with_null_geometry_without_inventing_coordinates(): void
    {
        $relativePath = 'geojson/testing-phase4-null-geometry-'.uniqid();
        config(['tupad_mapping.public_path' => $relativePath]);

        $foundation = app(BicolGeographicFoundation::class);
        $bySourceMunicipality = collect($this->references)
            ->mapWithKeys(fn (array $reference): array => [
                $foundation->sourceMunicipalityCode($reference['municipality']->code) => $reference,
            ]);
        $albayCode = $this->references['050500000']['municipality']->code;

        Http::fake(function (Request $request) use ($bySourceMunicipality, $albayCode) {
            preg_match('/bgysubmuns-municity-(\d+)\.0\.01\.json$/', $request->url(), $matches);
            $reference = $bySourceMunicipality->get($matches[1] ?? '');

            if (! $reference) {
                return Http::response(['message' => 'not found'], 404);
            }

            $sourceBarangayCode = ltrim(
                substr($reference['barangay']->code, 0, 2).'0'.substr($reference['barangay']->code, 2),
                '0',
            );

            $geometry = $reference['municipality']->code === $albayCode
                ? null
                : [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [123.0, 13.0],
                        [123.2, 13.0],
                        [123.2, 13.2],
                        [123.0, 13.2],
                        [123.0, 13.0],
                    ]],
                ];

            return Http::response([
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [
                        'adm4_psgc' => (int) $sourceBarangayCode,
                        'adm4_en' => $reference['barangay']->name,
                    ],
                    'geometry' => $geometry,
                ]],
            ], 200);
        });

        try {
            $result = app(BicolBarangayLabelSyncService::class)->sync();

            $this->assertSame(6, $result['municipalities']);
            $this->assertSame(5, $result['barangay_labels']);
            $this->assertSame(1, $result['unavailable_labels']);
            $this->assertSame(
                [$this->references['050500000']['barangay']->code],
                $result['unavailable_psgc_codes'],
            );

            $albayPath = $foundation->barangayLabelPath($albayCode);
            $albayJson = json_decode(File::get($albayPath), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame([], $albayJson['features']);

            $manifest = json_decode(
                File::get(public_path($relativePath.'/barangay-labels/manifest.json')),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $this->assertSame(
                $this->references['050500000']['barangay']->code,
                $manifest['unavailable_geometry'][0]['psgc_code'],
            );
        } finally {
            File::deleteDirectory(public_path($relativePath));
        }
    }

    public function test_foundation_derives_reviewed_barangay_source_filename_code(): void
    {
        $foundation = app(BicolGeographicFoundation::class);

        $this->assertSame('500501000', $foundation->sourceMunicipalityCode('050501000'));
        $this->assertStringEndsWith(
            'barangay-labels/050501000.geojson',
            str_replace('\\', '/', $foundation->barangayLabelRelativePath('050501000')),
        );
    }

    private function project(Province $province, array $overrides = []): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-MAP-P4-'.$province->id.'-'.uniqid(),
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
            'project_title' => 'Mapping Phase 4 Project '.uniqid(),
            'nature_of_work' => 'Interactive Region V municipality selection test.',
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

    private function exactLocation(
        Project $project,
        Municipality $municipality,
        Barangay $barangay,
        int $total,
        int $female,
    ): void {
        $location = ProjectLocation::query()->firstOrCreate(
            [
                'project_id' => $project->id,
                'municipality_id' => $municipality->id,
            ],
            [
                'province_id' => $municipality->province_id,
                'district' => $municipality->district,
                'sort_order' => ProjectLocation::query()
                    ->where('project_id', $project->id)
                    ->count() + 1,
            ],
        );

        $location->barangays()->syncWithoutDetaching([
            $barangay->id => [
                'beneficiaries_total' => $total,
                'beneficiaries_female' => $female,
            ],
        ]);
    }
}
