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
use App\Services\Monitoring\PerAdlSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorPartnerOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_tc_encodes_sponsor_and_partner_on_project_and_focal_monitoring_reads_them(): void
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
            'adl_number' => 'ADL-R1-001',
            'grants' => 2_000_000,
            'admin_cost' => 0,
            'total' => 2_000_000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => null,
            'partner' => null,
            'location' => 'Virac, Catanduanes',
            'amount' => 1_000_000,
            'grant_amount' => 1_000_000,
            'admin_cost_amount' => 0,
            'total_amount' => 1_000_000,
            'created_by' => $focal->id,
        ]);

        $province = Province::create([
            'name' => 'Catanduanes',
            'is_active' => true,
        ]);

        $municipality = Municipality::create([
            'province_id' => $province->id,
            'name' => 'Virac',
            'district' => 'Lone District',
            'income_class' => '1st Class',
            'is_city' => false,
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Rawis',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($tc)
            ->post(route('projects.store'), [
                'adl_allocation_id' => $allocation->id,
                'date_received' => now()->toDateString(),
                'project_title' => 'R1 Sponsor Ownership Project',
                'nature_of_work' => 'Community clean-up',
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Virac',
                'project_series' => 'Regular TUPAD 2026',
                'project_series_remarks' => 'R1/R2 regression.',
                'tevs_date_verified' => now()->toDateString(),
                'tevs_remarks' => 'Verified for implementation.',
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
            'project_title' => 'R1 Sponsor Ownership Project',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Virac',
        ]);

        $this->assertDatabaseHas('adl_allocations', [
            'id' => $allocation->id,
            'fund_sponsor' => null,
            'partner' => null,
        ]);

        $row = app(PerAdlSummaryService::class)
            ->rowsForAdl($adl->fresh())
            ->first();

        $this->assertSame('DOLE Regional Office V', $row['fund_sponsor']);
        $this->assertSame('LGU Virac', $row['partner']);
    }
}
