<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Services\Mapping\BicolMapDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GeographicMappingFinalReleaseVerificationTest extends TestCase
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
                'name' => $definition['name'].' Release Municipality',
                'district' => 'Release District',
                'is_city' => false,
                'is_active' => true,
            ]);

            $barangay = Barangay::query()->create([
                'municipality_id' => $municipality->id,
                'code' => substr($municipality->code, 0, 6).'001',
                'name' => $definition['name'].' Release Barangay',
                'is_active' => true,
            ]);

            $this->references[$provinceCode] = compact('province', 'municipality', 'barangay');
            $index++;
        }
    }

    public function test_release_route_keeps_auth_role_and_province_scope_middleware(): void
    {
        $route = Route::getRoutes()->getByName('reports.workspace.geographic-mapping');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('province.scope', $route->gatherMiddleware());
        $this->assertContains('role:admin,tc,focal', $route->gatherMiddleware());
    }

    public function test_release_workspace_preserves_authoritative_quarter_filter_across_report_navigation(): void
    {
        $response = $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping', [
                'view' => 'projects',
                'level' => 'province',
                'fiscal_year' => 2026,
                'quarter' => 3,
            ]))
            ->assertOk()
            ->assertSee('TUPAD Distribution Map');

        $this->assertSame(2026, (int) $response->viewData('filters')['fiscal_year']);
        $this->assertSame(3, (int) $response->viewData('filters')['quarter']);
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

    public function test_release_workspace_rejects_invalid_period_combinations(): void
    {
        $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping', [
                'fiscal_year' => 2026,
                'quarter' => 2,
                'month' => 5,
            ]))
            ->assertSessionHasErrors(['quarter', 'month']);

        $this->actingAs($this->focal)
            ->get(route('reports.workspace.geographic-mapping', [
                'quarter' => 2,
            ]))
            ->assertSessionHasErrors(['fiscal_year']);
    }

    public function test_release_tc_is_forced_to_assigned_province_and_foreign_query_is_denied(): void
    {
        $assigned = $this->references['054100000']['province'];
        $foreign = $this->references['050500000']['province'];

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $assigned->id,
        ]);

        $response = $this->actingAs($tc)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('Assigned Province Scope')
            ->assertSee('MASBATE MAP');

        $this->assertSame(
            $assigned->id,
            (int) $response->viewData('filters')['province_id'],
        );

        $this->actingAs($tc)
            ->get(route('reports.workspace.geographic-mapping', [
                'province_id' => $foreign->id,
            ]))
            ->assertForbidden();
    }

    public function test_release_frontend_keeps_drilldown_metrics_labels_and_lazy_boundary_architecture(): void
    {
        $javascript = File::get(resource_path('js/geographic-mapping.js'));
        $blade = File::get(resource_path('views/livewire/reports/geographic-distribution-map.blade.php'));

        $this->assertStringContainsString("root.closest('[data-mapping-phase=\"2\"]')", $javascript);
        $this->assertStringContainsString('tupad-map-select-province', $javascript);
        $this->assertStringContainsString('tupad-map-select-municipality', $javascript);
        $this->assertStringContainsString('getBarangayLabelGeoJson', $javascript);
        $this->assertStringContainsString("map.on('zoomend'", $javascript);
        $this->assertStringContainsString('wire:model.live="mapMetric"', $blade);
        $this->assertStringContainsString('wire:model.live="reportingPeriod"', $blade);
        $this->assertStringContainsString('Assigned Province Scope', $blade);
        $this->assertStringContainsString('Back to Municipalities', $blade);
    }
}
