<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleProjectSummaryScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_summary_contains_only_selected_project_even_when_same_province_has_other_projects(): void
    {
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $tabaco = $this->municipality(
            $province,
            '050517000',
            'Tabaco City',
            '1st District',
            true
        );

        $daraga = $this->municipality(
            $province,
            '050505000',
            'Daraga',
            '2nd District'
        );

        $legazpi = $this->municipality(
            $province,
            '050506000',
            'Legazpi City',
            '2nd District',
            true
        );

        $tabacoBarangay = $this->barangay(
            $tabaco,
            '050517001',
            'San Vicente'
        );

        $daragaBarangay = $this->barangay(
            $daraga,
            '050505001',
            'Busay'
        );

        $legazpiBarangay = $this->barangay(
            $legazpi,
            '050506001',
            'Bagumbayan'
        );

        $adl = Adl::create([
            'adl_number' => 'ADL-SINGLE-SUMMARY',
            'grants' => 5000000,
            'admin_cost' => 0,
            'total' => 5000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'amount' => 5000000,
            'created_by' => $focal->id,
        ]);

        $selectedProject = $this->project(
            $allocation,
            $province,
            $tabaco,
            $tabacoBarangay,
            $tc,
            'Selected Multi-Municipality Project',
            80
        );

        ProjectApproval::create([
            'project_id' => $selectedProject->id,
            'approval_date' => '2026-08-26',
            'project_code' => 'ALB-ONE-001',
            'approved_by' => $tc->id,
            'approved_at' => now(),
        ]);

        $locationOne = ProjectLocation::create([
            'project_id' => $selectedProject->id,
            'province_id' => $province->id,
            'municipality_id' => $tabaco->id,
            'district' => '1st District',
            'sort_order' => 1,
        ]);
        $locationOne->barangays()->sync([$tabacoBarangay->id]);

        $locationTwo = ProjectLocation::create([
            'project_id' => $selectedProject->id,
            'province_id' => $province->id,
            'municipality_id' => $daraga->id,
            'district' => '2nd District',
            'sort_order' => 2,
        ]);
        $locationTwo->barangays()->sync([$daragaBarangay->id]);

        $otherProject = $this->project(
            $allocation,
            $province,
            $legazpi,
            $legazpiBarangay,
            $tc,
            'Different Albay Project',
            40
        );

        ProjectApproval::create([
            'project_id' => $otherProject->id,
            'approval_date' => '2026-08-26',
            'project_code' => 'ALB-TWO-002',
            'approved_by' => $tc->id,
            'approved_at' => now(),
        ]);

        $otherLocation = ProjectLocation::create([
            'project_id' => $otherProject->id,
            'province_id' => $province->id,
            'municipality_id' => $legazpi->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $otherLocation->barangays()->sync([$legazpiBarangay->id]);

        $projectResponse = $this
            ->actingAs($tc)
            ->get(
                route(
                    'projects.summary',
                    $selectedProject
                )
            );

        $projectResponse
            ->assertOk()
            ->assertSee('Selected Project')
            ->assertSee('ALB-ONE-001')
            ->assertSee('Selected Multi-Municipality Project')
            ->assertSee('Tabaco City')
            ->assertSee('Daraga')
            ->assertSee('1st District')
            ->assertSee('2nd District')
            ->assertDontSee('ALB-TWO-002')
            ->assertDontSee('Different Albay Project')
            ->assertDontSee('Legazpi City');

        /*
         * The sidebar Province Summary intentionally remains province-wide.
         */
        $this
            ->actingAs($tc)
            ->get(
                route(
                    'project-summary.province',
                    $province
                )
            )
            ->assertOk()
            ->assertSee('ALB-ONE-001')
            ->assertSee('ALB-TWO-002');
    }

    private function municipality(
        Province $province,
        string $code,
        string $name,
        string $district,
        bool $isCity = false
    ): Municipality {
        return Municipality::create([
            'province_id' => $province->id,
            'code' => $code,
            'name' => $name,
            'district' => $district,
            'income_class' => $isCity ? 'Component City' : 'Municipality',
            'is_city' => $isCity,
            'is_active' => true,
        ]);
    }

    private function barangay(
        Municipality $municipality,
        string $code,
        string $name
    ): Barangay {
        return Barangay::create([
            'municipality_id' => $municipality->id,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function project(
        AdlAllocation $allocation,
        Province $province,
        Municipality $municipality,
        Barangay $barangay,
        User $creator,
        string $title,
        int $beneficiaries
    ): Project {
        $wages = $beneficiaries * 20 * 435;
        $insurance = $beneficiaries * 50;
        $ppe = $beneficiaries * 350;

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-26',
            'project_title' => $title,
            'nature_of_work' => 'Community work',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => '2026-08-26',
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => $province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => intdiv($beneficiaries, 2),
            'wage_rate' => 435,
            'wages_total' => $wages,
            'ppe_total' => $ppe,
            'insurance_rate' => 50,
            'insurance_beneficiaries' => $beneficiaries,
            'insurance_total' => $insurance,
            'total_project_cost' => $wages + $ppe + $insurance,
            'status' => ProjectStatus::APPROVED,
            'created_by' => $creator->id,
        ]);
    }
}
