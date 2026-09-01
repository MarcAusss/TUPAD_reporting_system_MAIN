<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectLaborMarketReferral;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveLaborMarketOfficialPrintMatrixLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->province = Province::query()->create([
            'name' => 'Masbate',
            'code' => '054100000',
            'is_active' => true,
        ]);
    }

    public function test_active_labor_market_official_print_uses_supplied_seven_column_matrix_and_populates_existing_data(): void
    {
        $project = $this->project();

        ProjectLaborMarketReferral::query()->create([
            'project_id' => $project->id,
            'reporting_month' => '2026-08-01',
            'program' => LaborMarketProgram::SKILLS_TRAINING,
            'interested_referred_total' => 12,
            'interested_referred_female' => 7,
            'provided_intervention_total' => 8,
            'provided_intervention_female' => 5,
            'amount_released' => '25000.00',
            'services_availed' => 'TESDA skills assessment',
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ProjectLaborMarketReferral::query()->create([
            'project_id' => $project->id,
            'reporting_month' => '2026-09-01',
            'program' => LaborMarketProgram::EMPLOYMENT_FACILITATION_SERVICES,
            'interested_referred_total' => 4,
            'interested_referred_female' => 3,
            'provided_intervention_total' => 2,
            'provided_intervention_female' => 2,
            'amount_released' => '1500.00',
            'services_availed' => 'Job matching and referral',
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.periodic.print', [
                'form' => 'labor-market',
                'fiscal_year' => 2026,
                'quarter' => 3,
                'province_id' => $this->province->id,
            ]))
            ->assertOk()
            ->assertSee('Number of TUPAD Beneficiaries Referred to Active Labor Market')
            ->assertSee('No. of Interested TUPAD Beneficiaries Reffered')
            ->assertSee('No. of Reffered TUPAD Beneficiaries Provided with Intervention')
            ->assertSee('Amount Released under the Intervention')
            ->assertSee('Types of Skills Training/Livelihood/Employment Services Availed')
            ->assertSee('Skills Training (TESDA)')
            ->assertSee('DOLE Integrated Livelihood Program')
            ->assertSee('Employment Facilitation Services')
            ->assertSee('TESDA skills assessment')
            ->assertSee('Job matching and referral')
            ->assertSee('₱25,000.00')
            ->assertSee('₱1,500.00');

        $response
            ->assertSee('data-labor-program="skills_training"', false)
            ->assertSee('data-labor-program="dole_integrated_livelihood_program"', false)
            ->assertSee('data-labor-program="employment_facilitation_services"', false)
            ->assertSee('data-labor-has-data="0"', false)
            ->assertSee('data-labor-has-data="1"', false);
    }

    public function test_active_labor_market_print_respects_selected_quarter_and_leaves_program_without_data_blank(): void
    {
        $project = $this->project();

        ProjectLaborMarketReferral::query()->create([
            'project_id' => $project->id,
            'reporting_month' => '2026-10-01',
            'program' => LaborMarketProgram::DOLE_INTEGRATED_LIVELIHOOD_PROGRAM,
            'interested_referred_total' => 99,
            'interested_referred_female' => 50,
            'provided_intervention_total' => 75,
            'provided_intervention_female' => 40,
            'amount_released' => '99999.00',
            'services_availed' => 'Outside selected quarter',
            'recorded_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('reports.periodic.print', [
                'form' => 'labor-market',
                'fiscal_year' => 2026,
                'quarter' => 3,
                'province_id' => $this->province->id,
            ]))
            ->assertOk()
            ->assertSee('DOLE Integrated Livelihood Program')
            ->assertDontSee('Outside selected quarter')
            ->assertDontSee('₱99,999.00');
    }

    private function project(): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-LABOR-'.uniqid(),
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'location' => 'Masbate',
            'province' => 'Masbate',
            'district' => '1st District',
            'municipality' => 'Masbate City',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Active Labor Market Print Test',
            'nature_of_work' => 'Community work',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Masbate',
            'province_id' => $this->province->id,
            'province' => 'Masbate',
            'district' => '1st District',
            'municipality' => 'Masbate City',
            'barangay' => 'Sample Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 12,
            'wage_rate' => '500.00',
            'wages_total' => '100000.00',
            'ppe_total' => '5000.00',
            'insurance_rate' => '100.00',
            'insurance_beneficiaries' => 20,
            'insurance_total' => '2000.00',
            'total_project_cost' => '107000.00',
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }
}
