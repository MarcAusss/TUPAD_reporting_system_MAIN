<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Services\Mapping\BicolGeographicFoundation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeographicMappingPhase1FoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $testPublicPath = 'geojson/testing-bicol-phase1';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tupad_mapping.public_path', $this->testPublicPath);
        File::deleteDirectory(public_path($this->testPublicPath));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->testPublicPath));
        parent::tearDown();
    }

    public function test_existing_location_code_columns_are_the_mapping_psgc_join_and_no_new_geo_tables_are_required(): void
    {
        $foundation = app(BicolGeographicFoundation::class);

        $this->assertSame('050000000', $foundation->regionCode());
        $this->assertSame([
            '050500000',
            '051600000',
            '051700000',
            '052000000',
            '054100000',
            '056200000',
        ], array_keys($foundation->provinceDefinitions()));

        $this->assertSame('050500000', $foundation->normalizePsgcCode('050500000'));
        $this->assertSame('050500000', $foundation->normalizePsgcCode('0500500000'));
        $this->assertSame('050500000', $foundation->normalizePsgcCode('500500000'));

        $this->assertTrue(\Schema::hasColumn('provinces', 'code'));
        $this->assertTrue(\Schema::hasColumn('municipalities', 'code'));
        $this->assertTrue(\Schema::hasColumn('barangays', 'code'));
        $this->assertTrue(\Schema::hasColumn('project_location_barangay', 'beneficiaries_total'));
        $this->assertTrue(\Schema::hasColumn('users', 'assigned_province_id'));
    }

    public function test_foundation_fails_closed_when_an_active_bicol_reference_is_missing_psgc_code(): void
    {
        $this->seedMinimalBicolReferences(missingMunicipalityCode: true);

        $issues = app(BicolGeographicFoundation::class)->validationIssues();

        $this->assertNotEmpty($issues);
        $this->assertTrue(
            collect($issues)->contains(fn (string $issue): bool => str_contains($issue, 'without PSGC code'))
        );
    }

    public function test_boundary_sync_writes_only_region_v_and_per_province_municipality_files_keyed_by_psgc(): void
    {
        $references = $this->seedMinimalBicolReferences();
        $regionUrl = (string) config('tupad_mapping.boundary_source.region_url');
        $urlPattern = (string) config('tupad_mapping.boundary_source.municipality_url_pattern');

        $regionFeatures = [];
        $fakes = [];

        foreach (config('tupad_mapping.provinces') as $provinceCode => $definition) {
            $regionFeatures[] = $this->feature(
                $this->toTenDigit($provinceCode),
                $definition['name'],
                'ADM2_PCODE',
                'ADM2_EN',
            );

            $municipality = $references[$provinceCode]['municipality'];
            $url = sprintf($urlPattern, $definition['source_code']);
            $fakes[$url] = Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    $this->feature(
                        $this->toTenDigit($municipality->code),
                        $municipality->name,
                        'ADM3_PCODE',
                        'ADM3_EN',
                    ),
                ],
            ], 200);
        }

        $fakes[$regionUrl] = Http::response([
            'type' => 'FeatureCollection',
            'features' => $regionFeatures,
        ], 200);

        Http::fake($fakes);

        $this->artisan('tupad:mapping-sync-boundaries')
            ->assertSuccessful();

        $root = public_path($this->testPublicPath);
        $this->assertFileExists($root.'/provinces.geojson');
        $this->assertFileExists($root.'/manifest.json');

        $provinceCollection = json_decode(File::get($root.'/provinces.geojson'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(6, $provinceCollection['features']);
        $this->assertSame(
            '050500000',
            $provinceCollection['features'][0]['properties']['psgc_code'],
        );

        foreach (config('tupad_mapping.provinces') as $provinceCode => $definition) {
            $file = $root.'/municipalities/'.$definition['slug'].'.geojson';
            $this->assertFileExists($file);

            $collection = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(1, $collection['features']);
            $this->assertSame(
                $references[$provinceCode]['municipality']->code,
                $collection['features'][0]['properties']['psgc_code'],
            );
            $this->assertSame(
                $provinceCode,
                $collection['features'][0]['properties']['province_psgc_code'],
            );
        }

        $manifest = json_decode(File::get($root.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('properties.psgc_code', $manifest['join_key']);
        $this->assertSame(6, $manifest['files']['provinces.geojson']['feature_count']);
    }

    public function test_boundary_sync_does_not_replace_local_files_when_source_cannot_match_current_psgc_references(): void
    {
        $this->seedMinimalBicolReferences();
        $root = public_path($this->testPublicPath);
        File::ensureDirectoryExists($root);
        File::put($root.'/existing-marker.txt', 'keep');

        Http::fake([
            (string) config('tupad_mapping.boundary_source.region_url') => Http::response([
                'type' => 'FeatureCollection',
                'features' => [],
            ], 200),
            '*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [],
            ], 200),
        ]);

        $this->artisan('tupad:mapping-sync-boundaries')
            ->assertFailed();

        $this->assertFileExists($root.'/existing-marker.txt');
        $this->assertFileDoesNotExist($root.'/provinces.geojson');
    }

    /** @return array<string, array{province:Province,municipality:Municipality,barangay:Barangay}> */
    private function seedMinimalBicolReferences(bool $missingMunicipalityCode = false): array
    {
        $result = [];
        $index = 1;

        foreach (config('tupad_mapping.provinces') as $provinceCode => $definition) {
            $province = Province::query()->create([
                'code' => $provinceCode,
                'name' => $definition['name'],
                'is_active' => true,
            ]);

            $municipalityCode = substr($provinceCode, 0, 4).sprintf('%02d', $index).substr($provinceCode, 6, 3);
            // Keep a canonical 9-digit correspondence code that remains unique
            // within this isolated fixture.
            $municipalityCode = substr($provinceCode, 0, 4).sprintf('%02d', $index).'000';

            $municipality = Municipality::query()->create([
                'province_id' => $province->id,
                'code' => $missingMunicipalityCode && $index === 1 ? null : $municipalityCode,
                'name' => $definition['name'].' Test Municipality',
                'district' => 'Test District',
                'income_class' => null,
                'is_city' => false,
                'is_active' => true,
            ]);

            $barangay = Barangay::query()->create([
                'municipality_id' => $municipality->id,
                'code' => substr($municipalityCode, 0, 6).'001',
                'name' => 'Test Barangay '.$index,
                'is_active' => true,
            ]);

            $result[$provinceCode] = compact('province', 'municipality', 'barangay');
            $index++;
        }

        return $result;
    }

    private function feature(string $code, string $name, string $codeKey, string $nameKey): array
    {
        return [
            'type' => 'Feature',
            'properties' => [
                $codeKey => $code,
                $nameKey => $name,
            ],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [123.0, 13.0],
                    [123.1, 13.0],
                    [123.1, 13.1],
                    [123.0, 13.1],
                    [123.0, 13.0],
                ]],
            ],
        ];
    }

    private function toTenDigit(string $correspondenceCode): string
    {
        return substr($correspondenceCode, 0, 2).'0'.substr($correspondenceCode, 2);
    }
}
