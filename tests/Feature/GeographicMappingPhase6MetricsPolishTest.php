<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Reports\GeographicDistributionMap;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Services\Mapping\BicolMapDataService;
use App\Services\Mapping\BicolMapMetricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class GeographicMappingPhase6MetricsPolishTest extends TestCase
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

    public function test_metric_presentation_supports_beneficiaries_projects_and_region_allocation(): void
    {
        $service = app(BicolMapMetricService::class);
        $payload = $this->sampleRegionPayload();

        $beneficiaries = $service->apply($payload, BicolMapMetricService::BENEFICIARIES);
        $this->assertSame('beneficiaries', $beneficiaries['metric']['key']);
        $this->assertSame(20, $beneficiaries['areas'][0]['value']);
        $this->assertSame('20', $beneficiaries['areas'][0]['value_display']);

        $projects = $service->apply($payload, BicolMapMetricService::PROJECTS);
        $this->assertSame('projects', $projects['metric']['key']);
        $this->assertSame(4, $projects['areas'][0]['value']);
        $this->assertSame('4', $projects['areas'][0]['value_display']);

        $allocation = $service->apply($payload, BicolMapMetricService::ALLOCATION);
        $this->assertSame('allocation', $allocation['metric']['key']);
        $this->assertSame(2_500_000, $allocation['areas'][0]['value']);
        $this->assertSame('₱25,000', $allocation['areas'][0]['value_display']);
        $this->assertTrue(collect($allocation['metric_options'])->firstWhere('key', 'allocation')['available']);
        $this->assertCount(5, $allocation['legend']);
    }

    public function test_allocation_falls_back_below_region_without_inventing_municipality_values(): void
    {
        $payload = [
            'map_level' => 'province',
            'areas' => [[
                'id' => 1,
                'psgc_code' => '050501000',
                'name' => 'Municipality A',
                'beneficiaries' => 12,
                'projects' => 2,
                'allocation_cents' => null,
                'allocation_available' => false,
            ]],
            'provinces' => [],
            'municipalities' => [[
                'id' => 1,
                'psgc_code' => '050501000',
                'name' => 'Municipality A',
                'beneficiaries' => 12,
                'projects' => 2,
                'allocation_cents' => null,
                'allocation_available' => false,
            ]],
            'barangays' => [],
            'summary' => [
                'beneficiaries' => 12,
                'projects' => 2,
                'allocation_cents' => 5_000_000,
            ],
            'data_note' => 'Base note.',
        ];

        $result = app(BicolMapMetricService::class)->apply(
            $payload,
            BicolMapMetricService::ALLOCATION,
        );

        $this->assertSame('beneficiaries', $result['metric']['key']);
        $this->assertSame('allocation', $result['metric']['requested_key']);
        $this->assertSame(12, $result['areas'][0]['value']);
        $this->assertNotNull($result['metric_notice']);
        $this->assertFalse(collect($result['metric_options'])->firstWhere('key', 'allocation')['available']);
    }

    public function test_livewire_supports_project_metric_and_quarter_month_filter_controls(): void
    {
        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->set('fiscalYear', '2026')
            ->set('reportingPeriod', 'q3')
            ->set('mapMetric', 'projects')
            ->assertSet('fiscalYear', '2026')
            ->assertSet('reportingPeriod', 'q3')
            ->assertSet('mapMetric', 'projects')
            ->assertSee('PROJECTS BY PROVINCE')
            ->assertSee('Quarter 3 (Jul–Sep)')
            ->assertDispatched('tupad-map-data-updated');
    }

    public function test_allocation_metric_reverts_to_beneficiaries_when_drilling_into_province(): void
    {
        $albay = $this->references['050500000'];

        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->set('mapMetric', 'allocation')
            ->assertSet('mapMetric', 'allocation')
            ->assertSee('ALLOCATION BY PROVINCE')
            ->call('selectProvince', $albay['province']->id)
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $albay['province']->id)
            ->assertSet('mapMetric', 'beneficiaries')
            ->assertSee('BENEFICIARIES BY MUNICIPALITY / CITY');
    }

    public function test_period_cannot_be_applied_without_fiscal_year(): void
    {
        Livewire::actingAs($this->focal)
            ->test(GeographicDistributionMap::class)
            ->set('reportingPeriod', 'm08')
            ->assertSet('reportingPeriod', null)
            ->assertHasErrors(['reportingPeriod']);
    }


    public function test_workspace_period_query_is_validated_preserved_and_applied_to_map_payload(): void
    {
        $response = $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping', [
                'fiscal_year' => 2026,
                'quarter' => 3,
            ]))
            ->assertOk();

        $this->assertSame(2026, (int) $response->viewData('filters')['fiscal_year']);
        $this->assertSame(3, (int) $response->viewData('filters')['quarter']);
        $this->assertArrayNotHasKey('month', array_filter(
            $response->viewData('filters'),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        ));
        $this->assertSame(3, (int) $response->viewData('commonQuery')['quarter']);
        $this->assertSame(3, (int) $response->viewData('exportQuery')['quarter']);

        $payload = app(BicolMapDataService::class)->regionPayload($this->focal, [
            'fiscal_year' => 2026,
            'quarter' => 3,
        ]);

        $this->assertSame(2026, $payload['filters']['fiscal_year']);
        $this->assertSame(3, $payload['filters']['quarter']);
        $this->assertNull($payload['filters']['month']);
    }

    public function test_workspace_rejects_ambiguous_or_unbounded_reporting_period_queries(): void
    {
        $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping', [
                'fiscal_year' => 2026,
                'quarter' => 3,
                'month' => 8,
            ]))
            ->assertSessionHasErrors(['quarter', 'month']);

        $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping', [
                'month' => 8,
            ]))
            ->assertSessionHasErrors(['fiscal_year']);
    }

    public function test_phase_six_frontend_has_dynamic_metric_formatting_empty_state_and_label_density_controls(): void
    {
        $javascript = File::get(resource_path('js/geographic-mapping.js'));
        $css = File::get(resource_path('css/app.css'));
        $blade = File::get(resource_path('views/livewire/reports/geographic-distribution-map.blade.php'));
        $workspace = File::get(resource_path('views/reports/geographic-mapping/index.blade.php'));

        $this->assertStringContainsString('function metricValueText', $javascript);
        $this->assertStringContainsString('function metricAxisText', $javascript);
        $this->assertStringContainsString('function syncLabelVisibility', $javascript);
        $this->assertStringContainsString("map.on('zoomend'", $javascript);
        $this->assertStringContainsString('.tupad-map-label-hidden', $css);
        $this->assertStringContainsString('wire:model.live="mapMetric"', $blade);
        $this->assertStringContainsString('wire:model.live="reportingPeriod"', $blade);
        $this->assertStringContainsString("empty_state.has_values", $blade);
        $this->assertStringContainsString(':quarter=', $workspace);
        $this->assertStringContainsString(':month=', $workspace);
    }

    /** @return array<string,mixed> */
    private function sampleRegionPayload(): array
    {
        $rows = [
            [
                'id' => 1,
                'psgc_code' => '050500000',
                'name' => 'Albay',
                'beneficiaries' => 20,
                'projects' => 4,
                'allocation_cents' => 2_500_000,
                'allocation_available' => true,
            ],
            [
                'id' => 2,
                'psgc_code' => '051600000',
                'name' => 'Camarines Norte',
                'beneficiaries' => 10,
                'projects' => 2,
                'allocation_cents' => 1_000_000,
                'allocation_available' => true,
            ],
        ];

        return [
            'map_level' => 'region',
            'areas' => $rows,
            'provinces' => $rows,
            'municipalities' => [],
            'barangays' => [],
            'summary' => [
                'beneficiaries' => 30,
                'projects' => 6,
                'allocation_cents' => 3_500_000,
            ],
            'data_note' => 'Base beneficiary note.',
        ];
    }
}
