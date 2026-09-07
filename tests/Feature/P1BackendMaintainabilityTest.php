<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Controllers\ProjectController;
use App\Http\Requests\ProjectRegistryRequest;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\ProjectCreateReferenceService;
use App\Services\Projects\ProjectRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P1BackendMaintainabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_registry_and_create_support_are_resolved_as_dedicated_services(): void
    {
        $this->assertInstanceOf(
            ProjectRegistryService::class,
            app(ProjectRegistryService::class),
        );

        $this->assertInstanceOf(
            ProjectCreateReferenceService::class,
            app(ProjectCreateReferenceService::class),
        );

        $this->assertInstanceOf(
            ProjectRegistryRequest::class,
            new ProjectRegistryRequest(),
        );
    }

    public function test_tc_create_reference_service_keeps_region_and_allocation_scope(): void
    {
        $albay = $this->province('Albay', '050500000');
        $masbate = $this->province('Masbate', '054100000');
        $outsideRegion = $this->province('Cebu', '072200000');

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $masbate->id,
        ]);

        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P1-MAINT-001',
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $tc->id,
        ]);

        AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'province' => 'Masbate',
            'location' => 'Masbate',
            'amount' => '250000.00',
            'grant_amount' => '250000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '250000.00',
            'created_by' => $tc->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Masbate Provincial Government',
        ]);

        AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'province' => 'Albay',
            'location' => 'Albay',
            'amount' => '250000.00',
            'grant_amount' => '250000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '250000.00',
            'created_by' => $tc->id,
            'fund_sponsor' => 'Foreign Sponsor',
            'partner' => 'Foreign Partner',
        ]);

        $data = app(ProjectCreateReferenceService::class)->viewData($tc);

        $this->assertSame(
            [$masbate->id],
            $data['provinces']->pluck('id')->all(),
        );
        $this->assertFalse($data['provinces']->contains('id', $albay->id));
        $this->assertFalse($data['provinces']->contains('id', $outsideRegion->id));
        $this->assertCount(1, $data['allocations']);
        $this->assertSame('Masbate', $data['allocations']->first()->province);
        $this->assertSame(['DOLE Regional Office V'], $data['fundSponsorOptions']->all());
        $this->assertSame(['Masbate Provincial Government'], $data['partnerOptions']->all());
    }

    public function test_project_controller_delegates_registry_and_create_reference_assembly(): void
    {
        $source = file_get_contents((new \ReflectionClass(ProjectController::class))->getFileName());

        $this->assertIsString($source);
        $this->assertStringContainsString('ProjectRegistryService $registry', $source);
        $this->assertStringContainsString('ProjectCreateReferenceService $references', $source);
        $this->assertStringNotContainsString('BICOL_PROVINCE_NAMES', $source);
        $this->assertStringNotContainsString("Rule::in([\n                    'newest'", $source);
    }

    private function province(string $name, string $code): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }
}
