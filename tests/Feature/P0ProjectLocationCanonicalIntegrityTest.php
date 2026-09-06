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
use App\Models\Province;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Projects\ProjectLocationCanonicalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class P0ProjectLocationCanonicalIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_location_synchronizes_legacy_snapshot_and_prevents_eloquent_conflicts(): void
    {
        [$actor, $project, $albay, $legazpi, $rawis, $masbate] = $this->projectFixture();

        $service = app(ProjectLocationCanonicalService::class);
        $service->createSingleCanonicalLocation($project, $albay, $legazpi, $rawis);

        $project->refresh();

        $this->assertSame($albay->id, $project->province_id);
        $this->assertSame($legazpi->id, $project->municipality_id);
        $this->assertSame($rawis->id, $project->barangay_id);
        $this->assertSame('Albay', $project->province);
        $this->assertSame('Legazpi City', $project->municipality);
        $this->assertSame('Rawis', $project->barangay);

        $project->update([
            'province_id' => $masbate->id,
            'province' => 'Masbate',
        ]);

        $project->refresh();

        $this->assertSame($albay->id, $project->province_id);
        $this->assertSame('Albay', $project->province);
        $service->assertProjectIntegrity($project);
    }

    public function test_coordinator_authorization_uses_canonical_location_even_if_legacy_snapshot_is_tampered_directly(): void
    {
        [$actor, $project, $albay, $legazpi, $rawis, $masbate] = $this->projectFixture();

        app(ProjectLocationCanonicalService::class)
            ->createSingleCanonicalLocation($project, $albay, $legazpi, $rawis);

        DB::table('projects')
            ->where('id', $project->id)
            ->update([
                'province_id' => $masbate->id,
                'province' => 'Masbate',
            ]);

        $albayTc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $albay->id,
        ]);

        $masbateTc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $masbate->id,
        ]);

        $access = app(ProvinceAccessService::class);

        $this->assertTrue($access->scopeProjects(Project::query(), $albayTc)->whereKey($project->id)->exists());
        $this->assertFalse($access->scopeProjects(Project::query(), $masbateTc)->whereKey($project->id)->exists());
    }

    public function test_project_location_rejects_municipality_from_another_province(): void
    {
        [, $project, $albay, , , $masbate] = $this->projectFixture();

        $masbateMunicipality = Municipality::query()->create([
            'province_id' => $masbate->id,
            'code' => '054101000',
            'name' => 'Masbate City',
            'district' => '1st District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $this->expectException(LogicException::class);

        $project->projectLocations()->create([
            'province_id' => $albay->id,
            'municipality_id' => $masbateMunicipality->id,
            'district' => '1st District',
            'sort_order' => 1,
        ]);
    }

    public function test_project_location_rejects_barangay_from_another_municipality(): void
    {
        [, $project, $albay, $legazpi, , $masbate] = $this->projectFixture();

        $location = $project->projectLocations()->create([
            'province_id' => $albay->id,
            'municipality_id' => $legazpi->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);

        $masbateMunicipality = Municipality::query()->create([
            'province_id' => $masbate->id,
            'code' => '054101000',
            'name' => 'Masbate City',
            'district' => '1st District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $foreignBarangay = Barangay::query()->create([
            'municipality_id' => $masbateMunicipality->id,
            'code' => '054101001',
            'name' => 'Bapor',
            'is_active' => true,
        ]);

        $this->expectException(LogicException::class);

        $location->barangays()->attach($foreignBarangay->id, [
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 4,
        ]);
    }

    /** @return array{User, Project, Province, Municipality, Barangay, Province} */
    private function projectFixture(): array
    {
        $actor = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($actor);

        $albay = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $masbate = Province::query()->create([
            'code' => '054100000',
            'name' => 'Masbate',
            'is_active' => true,
        ]);

        $legazpi = Municipality::query()->create([
            'province_id' => $albay->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $rawis = Barangay::query()->create([
            'municipality_id' => $legazpi->id,
            'code' => '050501001',
            'name' => 'Rawis',
            'is_active' => true,
        ]);

        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P0-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $actor->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => 1000000,
            'grant_amount' => 1000000,
            'admin_cost_amount' => 0,
            'total_amount' => 1000000,
            'created_by' => $actor->id,
        ]);

        $project = Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-09-05',
            'project_title' => 'Canonical Location Test Project',
            'nature_of_work' => 'Community work',
            'province' => 'Masbate',
            'district' => '1st District',
            'municipality' => 'Legacy Municipality',
            'barangay' => 'Legacy Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 4,
            'wage_rate' => 400,
            'wages_total' => 40000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 500,
            'total_project_cost' => 40500,
            'status' => ProjectStatus::ONGOING_PROFILING,
            'province_id' => $masbate->id,
            'created_by' => $actor->id,
        ]);

        return [$actor, $project, $albay, $legazpi, $rawis, $masbate];
    }
}
