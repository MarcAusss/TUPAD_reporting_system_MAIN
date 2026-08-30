<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Reports\GeographicDistributionMap;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Services\Mapping\BicolMapDataService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GeographicMappingPhase5CoordinatorScopeTest extends TestCase
{
    use RefreshDatabase;

    private Province $assignedProvince;
    private Province $foreignProvince;
    private Municipality $assignedMunicipality;
    private Municipality $foreignMunicipality;
    private User $tc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignedProvince = Province::query()->create([
            'code' => '054100000',
            'name' => 'Masbate',
            'is_active' => true,
        ]);

        $this->foreignProvince = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->assignedMunicipality = Municipality::query()->create([
            'province_id' => $this->assignedProvince->id,
            'code' => '054101000',
            'name' => 'Masbate Test Municipality',
            'district' => 'Test District',
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->foreignMunicipality = Municipality::query()->create([
            'province_id' => $this->foreignProvince->id,
            'code' => '050501000',
            'name' => 'Albay Test Municipality',
            'district' => 'Test District',
            'is_city' => false,
            'is_active' => true,
        ]);

        Barangay::query()->create([
            'municipality_id' => $this->assignedMunicipality->id,
            'code' => '054101001',
            'name' => 'Masbate Test Barangay',
            'is_active' => true,
        ]);

        Barangay::query()->create([
            'municipality_id' => $this->foreignMunicipality->id,
            'code' => '050501001',
            'name' => 'Albay Test Barangay',
            'is_active' => true,
        ]);

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->assignedProvince->id,
        ]);
    }

    public function test_tc_workspace_starts_directly_at_assigned_province(): void
    {
        $this->actingAs($this->tc)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('TUPAD Distribution Map')
            ->assertSee('MASBATE MAP')
            ->assertSee('BENEFICIARIES BY MUNICIPALITY / CITY')
            ->assertSee('Assigned Province Scope')
            ->assertDontSee('Back to Region');

        Livewire::actingAs($this->tc)
            ->test(GeographicDistributionMap::class)
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $this->assignedProvince->id)
            ->assertSet('selectedMunicipalityId', null)
            ->assertSee('MASBATE MAP')
            ->assertSee('Assigned Province Scope');
    }

    public function test_tc_can_drill_into_a_municipality_inside_assigned_province(): void
    {
        Livewire::actingAs($this->tc)
            ->test(GeographicDistributionMap::class)
            ->call('selectMunicipality', $this->assignedMunicipality->id)
            ->assertSet('mapLevel', 'municipality')
            ->assertSet('selectedProvinceId', $this->assignedProvince->id)
            ->assertSet('selectedMunicipalityId', $this->assignedMunicipality->id)
            ->assertSee('MASBATE TEST MUNICIPALITY — BENEFICIARIES BY BARANGAY')
            ->assertSee('Back to Municipalities')
            ->call('showProvince')
            ->assertSet('mapLevel', 'province')
            ->assertSet('selectedProvinceId', $this->assignedProvince->id)
            ->assertSet('selectedMunicipalityId', null);
    }

    public function test_tc_cannot_open_region_view_from_livewire(): void
    {
        Livewire::actingAs($this->tc)
            ->test(GeographicDistributionMap::class)
            ->call('showRegion')
            ->assertForbidden();
    }

    public function test_tc_cannot_select_another_province_from_livewire(): void
    {
        Livewire::actingAs($this->tc)
            ->test(GeographicDistributionMap::class)
            ->call('selectProvince', $this->foreignProvince->id)
            ->assertForbidden();
    }

    public function test_tc_cannot_select_a_municipality_from_another_province(): void
    {
        Livewire::actingAs($this->tc)
            ->test(GeographicDistributionMap::class)
            ->call('selectMunicipality', $this->foreignMunicipality->id)
            ->assertNotFound();
    }

    public function test_map_data_service_rejects_region_and_foreign_province_payloads_for_tc(): void
    {
        $service = app(BicolMapDataService::class);

        try {
            $service->regionPayload($this->tc);
            $this->fail('TC regional payload should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        try {
            $service->provincePayload($this->tc, $this->foreignProvince->id);
            $this->fail('TC foreign-province payload should have been rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $payload = $service->provincePayload(
            $this->tc,
            $this->assignedProvince->id,
        );

        $this->assertSame('province', $payload['map_level']);
        $this->assertSame($this->assignedProvince->id, $payload['selected_province']['id']);
        $this->assertSame('province', $payload['viewer_scope']['type']);
        $this->assertFalse($payload['viewer_scope']['can_view_region']);
        $this->assertSame($this->assignedProvince->id, $payload['viewer_scope']['province_id']);
    }

    public function test_http_query_tampering_with_foreign_province_is_forbidden(): void
    {
        $this->actingAs($this->tc)
            ->get(route('reports.workspace.geographic-mapping', [
                'province_id' => $this->foreignProvince->id,
            ]))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->get(route('reports.workspace.geographic-mapping', [
                'province_id' => $this->assignedProvince->id,
            ]))
            ->assertOk()
            ->assertSee('MASBATE MAP');
    }

    public function test_tc_without_assigned_province_cannot_mount_interactive_map(): void
    {
        $unassigned = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => null,
        ]);

        Livewire::actingAs($unassigned)
            ->test(GeographicDistributionMap::class)
            ->assertForbidden();
    }
}
