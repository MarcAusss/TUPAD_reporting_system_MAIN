<?php

namespace Tests\Feature;

use App\Enums\BeneficiarySectorCategory;
use App\Enums\LaborMarketProgram;
use App\Enums\ProjectInterventionFocus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLaborMarketReferral;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase7ClassificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tc;
    private User $focal;
    private User $gip;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $this->gip = User::factory()->create([
            'role' => UserRole::GIP,
            'is_active' => true,
        ]);
    }

    public function test_admin_and_tc_can_access_project_classification_page(): void
    {
        $project = $this->createProject();

        foreach ([$this->admin, $this->tc] as $user) {
            $this->actingAs($user)
                ->get(route('projects.classifications.show', $project))
                ->assertOk()
                ->assertSee('Beneficiary Classification & Labor Market')
                ->assertSee($project->project_title);
        }
    }

    public function test_sector_classifications_and_primary_intervention_are_stored_with_audit_ownership(): void
    {
        $project = $this->createProject();
        $payload = $this->classificationPayload();

        // Categories may overlap and intentionally sum beyond the project total.
        $payload['sectors']['youth'] = ['total' => 10, 'female' => 6];
        $payload['sectors']['persons_with_disabilities'] = [
            'total' => 7,
            'female' => 4,
        ];
        $payload['sectors']['transport_workers'] = [
            'total' => 9,
            'female' => 3,
        ];

        $this->actingAs($this->tc)
            ->put(
                route('projects.classifications.update', $project),
                $payload,
            )
            ->assertRedirect(route('projects.classifications.show', $project));

        $this->assertSame(
            ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION,
            $project->fresh()->intervention_focus,
        );

        $this->assertDatabaseCount(
            'project_beneficiary_sectors',
            count(BeneficiarySectorCategory::cases()),
        );

        $this->assertDatabaseHas('project_beneficiary_sectors', [
            'project_id' => $project->id,
            'sector_group' =>
                BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE,
            'sector_key' => 'youth',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->tc->id,
            'module' => 'Beneficiary Sector Classification',
            'action' => 'created',
        ]);

        $this->actingAs($this->tc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Beneficiary Classification & Labor Market')
            ->assertSee('Environmental Conservation')
            ->assertSee('Manage Classification');
    }

    public function test_sector_female_count_cannot_exceed_category_total(): void
    {
        $project = $this->createProject();
        $payload = $this->classificationPayload();
        $payload['sectors']['youth'] = ['total' => 2, 'female' => 3];

        $this->actingAs($this->tc)
            ->put(
                route('projects.classifications.update', $project),
                $payload,
            )
            ->assertSessionHasErrors('sectors.youth.female');

        $this->assertDatabaseCount('project_beneficiary_sectors', 0);
        $this->assertNull($project->fresh()->intervention_focus);
    }

    public function test_all_required_priority_vulnerable_categories_are_supported(): void
    {
        $this->assertSame([
            'females',
            'youth',
            'senior_citizens',
            'persons_with_disabilities',
            'solo_parents',
            'indigenous_peoples',
            'former_rebels',
            'persons_deprived_of_liberty',
            'parolees_and_probationers',
        ], array_map(
            static fn (BeneficiarySectorCategory $category): string =>
                $category->value,
            BeneficiarySectorCategory::priorityVulnerable(),
        ));
    }

    public function test_all_required_occupational_livelihood_categories_are_supported(): void
    {
        $this->assertSame([
            'transport_workers',
            'vendors',
            'crop_growers',
            'homebased_workers',
            'fishers_fisherfolk',
            'livestock_poultry_raisers',
            'small_transport_drivers',
            'laborers',
            'house_helpers',
            'others',
        ], array_map(
            static fn (BeneficiarySectorCategory $category): string =>
                $category->value,
            BeneficiarySectorCategory::occupationalLivelihood(),
        ));
    }

    public function test_all_intervention_focuses_and_labor_market_programs_are_supported(): void
    {
        $this->assertSame([
            'disaster_risk_reduction_and_mitigation',
            'emergency_preparedness',
            'environmental_conservation',
            'early_recovery_and_rehabilitation',
            'administrative_clerical_and_logistical_support',
        ], array_map(
            static fn (ProjectInterventionFocus $focus): string =>
                $focus->value,
            ProjectInterventionFocus::cases(),
        ));

        $this->assertSame([
            'skills_training',
            'dole_integrated_livelihood_program',
            'employment_facilitation_services',
        ], array_map(
            static fn (LaborMarketProgram $program): string =>
                $program->value,
            LaborMarketProgram::cases(),
        ));
    }

    public function test_labor_market_referral_is_stored_and_updated_by_month_and_program(): void
    {
        $project = $this->createProject();
        $payload = $this->referralPayload();

        $this->actingAs($this->tc)
            ->post(
                route('projects.labor-market-referrals.store', $project),
                $payload,
            )
            ->assertRedirect(route('projects.classifications.show', $project));

        $referral = ProjectLaborMarketReferral::query()
            ->where('project_id', $project->id)
            ->where(
                'program',
                LaborMarketProgram::SKILLS_TRAINING->value,
            )
            ->firstOrFail();

        $this->assertSame(
            '2026-08-01',
            $referral->reporting_month->format('Y-m-d'),
        );

        $this->assertDatabaseHas('project_labor_market_referrals', [
            'project_id' => $project->id,
            'program' => LaborMarketProgram::SKILLS_TRAINING->value,
            'interested_referred_total' => 8,
            'interested_referred_female' => 5,
            'provided_intervention_total' => 6,
            'provided_intervention_female' => 4,
            'amount_released' => 12500.50,
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);

        $payload['provided_intervention_total'] = 7;
        $payload['provided_intervention_female'] = 5;

        $this->actingAs($this->admin)
            ->post(
                route('projects.labor-market-referrals.store', $project),
                $payload,
            )
            ->assertRedirect(route('projects.classifications.show', $project));

        $this->assertDatabaseCount('project_labor_market_referrals', 1);
        $this->assertDatabaseHas('project_labor_market_referrals', [
            'project_id' => $project->id,
            'program' => LaborMarketProgram::SKILLS_TRAINING->value,
            'provided_intervention_total' => 7,
            'provided_intervention_female' => 5,
            'recorded_by' => $this->tc->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'module' => 'Active Labor Market Referral',
            'action' => 'updated',
        ]);
    }

    public function test_invalid_labor_market_referral_counts_are_rejected(): void
    {
        $project = $this->createProject();

        $invalidPayloads = [
            [
                'field' => 'interested_referred_female',
                'values' => [
                    'interested_referred_total' => 2,
                    'interested_referred_female' => 3,
                ],
            ],
            [
                'field' => 'provided_intervention_total',
                'values' => [
                    'interested_referred_total' => 2,
                    'interested_referred_female' => 1,
                    'provided_intervention_total' => 3,
                ],
            ],
            [
                'field' => 'provided_intervention_female',
                'values' => [
                    'provided_intervention_total' => 1,
                    'provided_intervention_female' => 2,
                ],
            ],
        ];

        foreach ($invalidPayloads as $invalid) {
            $payload = array_replace(
                $this->referralPayload(),
                $invalid['values'],
            );

            $this->actingAs($this->tc)
                ->post(
                    route('projects.labor-market-referrals.store', $project),
                    $payload,
                )
                ->assertSessionHasErrors($invalid['field']);
        }

        $this->assertDatabaseCount('project_labor_market_referrals', 0);
    }

    public function test_negative_labor_market_amount_is_rejected(): void
    {
        $project = $this->createProject();
        $payload = $this->referralPayload();
        $payload['amount_released'] = '-0.01';

        $this->actingAs($this->tc)
            ->post(
                route('projects.labor-market-referrals.store', $project),
                $payload,
            )
            ->assertSessionHasErrors('amount_released');

        $this->assertDatabaseCount('project_labor_market_referrals', 0);
    }

    public function test_focal_and_gip_cannot_modify_phase7_project_data(): void
    {
        $project = $this->createProject();

        foreach ([$this->focal, $this->gip] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->put(
                    route('projects.classifications.update', $project),
                    $this->classificationPayload(),
                )
                ->assertForbidden();

            $this->actingAs($unauthorizedUser)
                ->post(
                    route('projects.labor-market-referrals.store', $project),
                    $this->referralPayload(),
                )
                ->assertForbidden();
        }

        $this->assertDatabaseCount('project_beneficiary_sectors', 0);
        $this->assertDatabaseCount('project_labor_market_referrals', 0);
    }

    public function test_exact_barangay_allocations_are_unchanged_by_sector_encoding(): void
    {
        $project = $this->createProject();

        $province = Province::create([
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $municipality = Municipality::create([
            'province_id' => $province->id,
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);

        $barangay = Barangay::create([
            'municipality_id' => $municipality->id,
            'name' => 'Rawis',
            'is_active' => true,
        ]);

        $location = $project->projectLocations()->create([
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);

        $location->barangays()->attach($barangay->id, [
            'beneficiaries_total' => 7,
            'beneficiaries_female' => 4,
        ]);

        $this->actingAs($this->tc)
            ->put(
                route('projects.classifications.update', $project),
                $this->classificationPayload(),
            )
            ->assertRedirect(route('projects.classifications.show', $project));

        $this->assertDatabaseHas('project_location_barangay', [
            'project_location_id' => $location->id,
            'barangay_id' => $barangay->id,
            'beneficiaries_total' => 7,
            'beneficiaries_female' => 4,
        ]);

        $this->assertDatabaseCount('project_location_barangay', 1);
    }

    private function classificationPayload(): array
    {
        $sectors = [];

        foreach (BeneficiarySectorCategory::cases() as $category) {
            $sectors[$category->value] = [
                'total' => 0,
                'female' => 0,
            ];
        }

        return [
            'intervention_focus' =>
                ProjectInterventionFocus::ENVIRONMENTAL_CONSERVATION->value,
            'sectors' => $sectors,
        ];
    }

    private function referralPayload(): array
    {
        return [
            'reporting_month' => '2026-08',
            'program' => LaborMarketProgram::SKILLS_TRAINING->value,
            'interested_referred_total' => 8,
            'interested_referred_female' => 5,
            'provided_intervention_total' => 6,
            'provided_intervention_female' => 4,
            'amount_released' => '12500.50',
            'services_availed' => 'Shielded Metal Arc Welding NC II',
        ];
    }

    private function createProject(): Project
    {
        $this->sequence++;

        $adl = Adl::create([
            'adl_number' => sprintf('ADL-P7-%03d', $this->sequence),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => sprintf(
                'Phase 7 Classification Project %03d',
                $this->sequence,
            ),
            'nature_of_work' => 'Environmental rehabilitation',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => 455,
            'wages_total' => 91000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_beneficiaries' => 10,
            'insurance_total' => 500,
            'total_project_cost' => 91500,
            'status' => ProjectStatus::TSSD_EVALUATION,
            'created_by' => $this->tc->id,
        ]);
    }
}
