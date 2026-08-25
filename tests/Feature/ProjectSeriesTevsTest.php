<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSeriesTevsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_can_store_project_series_and_tevs_verification_details(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R2-001',
            'grants' => 2_000_000,
            'admin_cost' => 0,
            'total' => 2_000_000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => null,
            'partner' => null,
            'location' => 'Legazpi City, Albay',
            'amount' => 1_000_000,
            'created_by' => $focal->id,
        ]);

        $province = Province::create([
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $municipality = Municipality::create([
            'province_id' => $province->id,
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Rawis',
            'is_active' => true,
        ]);

        $verified = now()->toDateString();

        $response = $this
            ->actingAs($tc)
            ->post(route('projects.store'), [
                'adl_allocation_id' => $allocation->id,
                'date_received' => now()->toDateString(),
                'project_title' => 'R2 Project Series and TEVS Project',
                'nature_of_work' => 'Community clean-up',
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Legazpi',
                'project_series' => 'Regular TUPAD 2026',
                'project_series_remarks' => 'First series for the allocation.',
                'tevs_date_verified' => $verified,
                'tevs_remarks' => 'Verified and qualified for implementation.',
                'province_id' => $province->id,
                'municipality_id' => $municipality->id,
                'barangay_id' => $barangay->id,
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION->value,
                'number_of_days' => 20,
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 5,
                'wage_rate' => 455,
                'insurance_rate' => 50,
                'ppe_items' => [],
                'remarks' => null,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'project_title' => 'R2 Project Series and TEVS Project',
            'project_series' => 'Regular TUPAD 2026',
            'project_series_remarks' => 'First series for the allocation.',
            'tevs_remarks' => 'Verified and qualified for implementation.',
        ]);

        $project = \App\Models\Project::query()
            ->where(
                'project_title',
                'R2 Project Series and TEVS Project'
            )
            ->firstOrFail();

        $this->assertSame(
            $verified,
            $project->tevs_date_verified->toDateString()
        );
    }

    public function test_project_series_and_tevs_date_are_required_for_new_official_project(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->post(route('projects.store'), []);

        $response->assertSessionHasErrors([
            'project_series',
            'tevs_date_verified',
        ]);
    }
}
