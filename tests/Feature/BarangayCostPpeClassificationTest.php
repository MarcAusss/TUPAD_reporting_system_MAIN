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
use App\Models\ProjectBarangayPpeItemCount;
use App\Models\ProjectBarangayPpeProfile;
use App\Models\ProjectBeneficiaryAddress;
use App\Models\ProjectPpeItem;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\BarangayCostBreakdownService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayCostPpeClassificationTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;
    private Municipality $municipality;
    private Barangay $mabini;
    private Barangay $rizal;

    private Province $otherProvince;
    private Municipality $otherMunicipality;
    private Barangay $otherBarangay;

    private User $admin;
    private User $tc;
    private User $focal;
    private User $otherTc;

    private AdlAllocation $allocation;

    private static int $adlSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->province = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->municipality = Municipality::create([
            'province_id' => $this->province->id,
            'code' => '050501000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $this->mabini = Barangay::create([
            'municipality_id' => $this->municipality->id,
            'code' => '050501001',
            'name' => 'Mabini',
            'is_active' => true,
        ]);

        $this->rizal = Barangay::create([
            'municipality_id' => $this->municipality->id,
            'code' => '050501002',
            'name' => 'Rizal',
            'is_active' => true,
        ]);

        $this->otherProvince = Province::create([
            'code' => '051700000',
            'name' => 'Camarines Sur',
            'is_active' => true,
        ]);

        $this->otherMunicipality = Municipality::create([
            'province_id' => $this->otherProvince->id,
            'code' => '051701000',
            'name' => 'Naga City',
            'district' => '3rd District',
            'income_class' => '1st Class',
            'is_city' => true,
            'is_active' => true,
        ]);

        $this->otherBarangay = Barangay::create([
            'municipality_id' => $this->otherMunicipality->id,
            'code' => '051701001',
            'name' => 'Concepcion',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->province->id,
        ]);
        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->otherTc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->otherProvince->id,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-BCPPE-'.(++self::$adlSequence),
            'date_received' => '2026-01-05',
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);

        $this->allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => '1000000.00',
            'grant_amount' => '1000000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixture helpers
    |--------------------------------------------------------------------------
    */

    private function createProject(array $overrides = []): Project
    {
        $project = Project::create(array_merge([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-01-10',
            'project_title' => 'Barangay Cost Test Project',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Mabini',
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->mabini->id,
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 10,
            'term' => 'short_term',
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,
            'wage_rate' => '455.00',
            'wages_total' => '227500.00',
            'ppe_total' => '15100.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 50,
            'insurance_total' => '2500.00',
            'total_project_cost' => '245100.00',
            'status' => ProjectStatus::APPROVED,
            'created_by' => $this->tc->id,
        ], $overrides));

        ProjectApproval::create([
            'project_id' => $project->id,
            'approval_date' => $project->date_received,
            'project_code' => 'TUPAD-RO5-TEST-'.$project->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        return $project;
    }

    private function addPpeItem(
        Project $project,
        string $type,
        string $product,
        int $beneficiaryCount,
        string $unitAmount,
        int $quantity = 1,
    ): ProjectPpeItem {
        $totalAmount = number_format($beneficiaryCount * $quantity * (float) $unitAmount, 2, '.', '');

        return ProjectPpeItem::create([
            'project_id' => $project->id,
            'ppe_type' => $type,
            'product' => $product,
            'beneficiary_count' => $beneficiaryCount,
            'quantity' => $quantity,
            'unit_amount' => $unitAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Adds the acceptance scenario's four PPE items (Gloves/Boots/Mask
     * hazardous, TUPAD Shirt non-hazardous given-to-everyone) and returns
     * them keyed by product name.
     *
     * @return array<string, ProjectPpeItem>
     */
    private function addAcceptancePpeItems(Project $project): array
    {
        return [
            'gloves' => $this->addPpeItem($project, 'hazardous', 'Gloves', 14, '100.00'),
            'boots' => $this->addPpeItem($project, 'hazardous', 'Rubber Boots', 9, '350.00'),
            'mask' => $this->addPpeItem($project, 'hazardous', 'Mask', 11, '50.00'),
            'shirt' => $this->addPpeItem($project, 'non_hazardous', 'TUPAD Shirt', 50, '200.00'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $barangays
     */
    private function updateAddresses(User $actor, Project $project, array $barangays, ?int $provinceId = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)->put(
            route('projects.beneficiary-addresses.update', $project),
            [
                'province_id' => $provinceId ?? $this->province->id,
                'beneficiary_addresses' => [
                    [
                        'municipality_id' => $this->municipality->id,
                        'barangays' => $barangays,
                    ],
                ],
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Acceptance scenario
    |--------------------------------------------------------------------------
    */

    public function test_acceptance_scenario_reconciles_exactly(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $response = $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $breakdown = app(BarangayCostBreakdownService::class)->forProject($project->fresh());

        $this->assertSame('exact', $breakdown['basis']);
        $this->assertFalse($breakdown['status']['distribution_invalid']);
        $this->assertFalse($breakdown['status']['addresses_out_of_date']);

        $rows = collect($breakdown['rows'])->keyBy('barangay');

        $mabini = $rows['Mabini'];
        $this->assertSame(13650000, $mabini['wages_cents']);
        $this->assertSame(150000, $mabini['insurance_cents']);
        $this->assertSame(935000, $mabini['ppe_cents']);
        $this->assertSame(14735000, $mabini['total_cents']);
        $this->assertSame(491167, $mabini['average_per_beneficiary_cents']);
        $this->assertSame(13, $mabini['hazardous']);
        $this->assertSame(17, $mabini['non_hazardous']);

        $rizal = $rows['Rizal'];
        $this->assertSame(9100000, $rizal['wages_cents']);
        $this->assertSame(100000, $rizal['insurance_cents']);
        $this->assertSame(575000, $rizal['ppe_cents']);
        $this->assertSame(9775000, $rizal['total_cents']);
        $this->assertSame(488750, $rizal['average_per_beneficiary_cents']);
        $this->assertSame(7, $rizal['hazardous']);
        $this->assertSame(13, $rizal['non_hazardous']);

        $this->assertSame(0, $breakdown['reconciliation']['total']['difference_cents']);
        $this->assertSame(0, $breakdown['reconciliation']['wages']['difference_cents']);
        $this->assertSame(0, $breakdown['reconciliation']['insurance']['difference_cents']);
        $this->assertSame(0, $breakdown['reconciliation']['ppe']['difference_cents']);
        $this->assertSame(24510000, $breakdown['totals']['total_cents']);
    }

    /*
    |--------------------------------------------------------------------------
    | Additional scenarios
    |--------------------------------------------------------------------------
    */

    public function test_single_barangay_is_exact(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 25,
                'hazardous_workers' => 14,
                'ppe_items' => [
                    $items['gloves']->id => 14,
                    $items['boots']->id => 9,
                    $items['mask']->id => 11,
                    $items['shirt']->id => 50,
                ],
            ],
        ])->assertSessionDoesntHaveErrors();

        $breakdown = app(BarangayCostBreakdownService::class)->forProject($project->fresh());

        $this->assertSame('exact', $breakdown['basis']);
        $this->assertSame(0, $breakdown['reconciliation']['total']['difference_cents']);
    }

    public function test_estimated_fallback_before_distribution_is_encoded_still_reconciles(): void
    {
        $project = $this->createProject();
        $this->addAcceptancePpeItems($project);

        ProjectBeneficiaryAddress::create([
            'project_id' => $project->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->mabini->id,
            'beneficiaries_total' => 30,
            'beneficiaries_female' => 15,
            'encoded_by' => $this->tc->id,
        ]);

        ProjectBeneficiaryAddress::create([
            'project_id' => $project->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->rizal->id,
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 10,
            'encoded_by' => $this->tc->id,
        ]);

        $breakdown = app(BarangayCostBreakdownService::class)->forProject($project->fresh());

        $this->assertSame('estimated', $breakdown['basis']);
        $this->assertSame(0, $breakdown['reconciliation']['total']['difference_cents']);
        $this->assertSame(0, $breakdown['reconciliation']['wages']['difference_cents']);
        $this->assertSame(0, $breakdown['reconciliation']['ppe']['difference_cents']);
    }

    public function test_insurance_beneficiaries_less_than_total_is_apportioned_by_weight(): void
    {
        $project = $this->createProject([
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,
            'insurance_beneficiaries' => 40,
            'insurance_total' => '2000.00',
            'ppe_total' => '0.00',
            'total_project_cost' => '229500.00',
        ]);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
            ],
        ])->assertSessionDoesntHaveErrors();

        $breakdown = app(BarangayCostBreakdownService::class)->forProject($project->fresh());
        $rows = collect($breakdown['rows'])->keyBy('barangay');

        // 40 insured apportioned 30:20 -> floor(40*30/50)=24, floor(40*20/50)=16, remainders 0/0.
        $this->assertSame(24, $rows['Mabini']['insured']);
        $this->assertSame(16, $rows['Rizal']['insured']);
        $this->assertSame(40, $rows['Mabini']['insured'] + $rows['Rizal']['insured']);
    }

    public function test_long_term_quantity_multiplies_ppe_amount(): void
    {
        $project = $this->createProject([
            'number_of_days' => 45,
            'term' => 'long_term',
            'wages_total' => number_format(50 * 455 * 45, 2, '.', ''),
            'ppe_total' => number_format(50 * 100 * 2, 2, '.', ''),
            'total_project_cost' => number_format(50 * 455 * 45 + 50 * 100 * 2 + 2500, 2, '.', ''),
        ]);

        $item = $this->addPpeItem($project, 'non_hazardous', 'TUPAD Shirt', 50, '100.00', quantity: 2);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 25,
            ],
        ])->assertSessionDoesntHaveErrors();

        $breakdown = app(BarangayCostBreakdownService::class)->forProject($project->fresh());
        $row = $breakdown['rows'][0];
        $ppeItemRow = collect($row['ppe_items'])->firstWhere('item_id', $item->id);

        // 50 recipients (given-to-everyone) x 100.00 unit x quantity 2.
        $this->assertSame(1000000, $ppeItemRow['amount_cents']);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation rules (a)-(e)
    |--------------------------------------------------------------------------
    */

    public function test_rule_a_rejects_recipients_not_summing_to_beneficiary_count(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 4, // should be 5 to total 14
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ])->assertSessionHasErrors('beneficiary_addresses');

        $this->assertSame(0, ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->count());
    }

    public function test_rule_b_rejects_recipients_exceeding_barangay_total(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 10,
                'beneficiaries_female' => 5,
                'hazardous_workers' => 10,
                'ppe_items' => [
                    // 14 gloves recipients in a 10-beneficiary barangay: exceeds the barangay total.
                    $items['gloves']->id => 14,
                    $items['boots']->id => 9,
                    $items['mask']->id => 10,
                    $items['shirt']->id => 10,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 40,
                'beneficiaries_female' => 20,
                'hazardous_workers' => 1,
                'ppe_items' => [
                    $items['gloves']->id => 0,
                    $items['boots']->id => 0,
                    $items['mask']->id => 1,
                    $items['shirt']->id => 40,
                ],
            ],
        ])->assertSessionHasErrors('beneficiary_addresses');

        $this->assertSame(0, ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->count());
    }

    public function test_rule_c_rejects_hazardous_workers_below_the_largest_item_recipient_count(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 5, // below gloves recipients of 9
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ])->assertSessionHasErrors('beneficiary_addresses');
    }

    public function test_rule_c_rejects_nonzero_hazardous_workers_when_project_has_no_hazardous_items(): void
    {
        $project = $this->createProject([
            'ppe_total' => '10000.00',
            'total_project_cost' => '240000.00',
        ]);
        $shirt = $this->addPpeItem($project, 'non_hazardous', 'TUPAD Shirt', 50, '200.00');

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 2,
                'ppe_items' => [
                    $shirt->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 0,
                'ppe_items' => [
                    $shirt->id => 20,
                ],
            ],
        ])->assertSessionHasErrors('beneficiary_addresses');
    }

    public function test_rule_d_rejects_complete_set_workers_above_the_smallest_item_recipient_count(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'complete_set_workers' => 8, // exceeds smallest recipient count (mask=7)
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ])->assertSessionHasErrors('beneficiary_addresses');
    }

    public function test_rule_e_rejects_a_ppe_item_id_that_does_not_belong_to_the_project(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $foreignProject = $this->createProject(['project_title' => 'Foreign Project']);
        $foreignItem = $this->addPpeItem($foreignProject, 'hazardous', 'Foreign Gloves', 5, '100.00');

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $foreignItem->id => 1,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                ],
            ],
        ])->assertSessionHasErrors('beneficiary_addresses');
    }

    /*
    |--------------------------------------------------------------------------
    | Preservation / removal behaviour
    |--------------------------------------------------------------------------
    */

    public function test_saving_addresses_without_ppe_fields_keeps_existing_distribution(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ])->assertSessionDoesntHaveErrors();

        $beforeProfiles = ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->get()->keyBy('barangay_id');
        $beforeCounts = ProjectBarangayPpeItemCount::query()->where('project_id', $project->id)->count();

        // Beneficiary Replacement flow's optional address step: no PPE fields at all.
        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
            ],
        ])->assertSessionDoesntHaveErrors();

        $afterProfiles = ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->get()->keyBy('barangay_id');
        $afterCounts = ProjectBarangayPpeItemCount::query()->where('project_id', $project->id)->count();

        $this->assertSame($beforeProfiles->get($this->mabini->id)->hazardous_workers, $afterProfiles->get($this->mabini->id)->hazardous_workers);
        $this->assertSame($beforeProfiles->get($this->rizal->id)->hazardous_workers, $afterProfiles->get($this->rizal->id)->hazardous_workers);
        $this->assertSame($beforeCounts, $afterCounts);
    }

    public function test_removing_a_barangay_deletes_its_distribution_rows(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ])->assertSessionDoesntHaveErrors();

        // Re-save without PPE fields, dropping Rizal from the allocation entirely.
        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 25,
            ],
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(
            0,
            ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->where('barangay_id', $this->rizal->id)->count(),
        );
        $this->assertSame(
            0,
            ProjectBarangayPpeItemCount::query()->where('project_id', $project->id)->where('barangay_id', $this->rizal->id)->count(),
        );
        $this->assertSame(
            1,
            ProjectBarangayPpeProfile::query()->where('project_id', $project->id)->where('barangay_id', $this->mabini->id)->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Integration with neighbouring workflows / roles
    |--------------------------------------------------------------------------
    */

    public function test_beneficiary_replacement_workflow_still_works(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tc)->post(
            route('projects.beneficiary-replacements.store', $project),
            [
                'reason' => 'Replacement due to relocation.',
                'removed_new' => [
                    ['first_name' => 'Pedro', 'last_name' => 'Reyes', 'sex' => 'male'],
                ],
                'added' => [
                    ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'sex' => 'male'],
                ],
            ],
        );

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
    }

    public function test_focal_sees_the_card_read_only(): void
    {
        $project = $this->createProject([
            'status' => ProjectStatus::FOR_PAYMENT,
        ]);
        $this->addAcceptancePpeItems($project);

        $response = $this->actingAs($this->focal)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('Barangay Cost &amp; PPE Classification', false);
        $response->assertDontSee('id="beneficiaryAddressForm"', false);
    }

    public function test_tc_from_another_province_cannot_update(): void
    {
        $project = $this->createProject();
        $this->addAcceptancePpeItems($project);

        $response = $this->updateAddresses($this->otherTc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 50,
                'beneficiaries_female' => 25,
            ],
        ], provinceId: $this->otherProvince->id);

        // EnforceCoordinatorProvinceScope rejects this before the controller
        // is ever reached, since the project is outside the acting TC's
        // assigned province.
        $response->assertForbidden();
        $this->assertSame(0, ProjectBeneficiaryAddress::query()->where('project_id', $project->id)->count());
    }

    public function test_empty_state_renders_when_there_are_no_addresses(): void
    {
        $project = $this->createProject();
        $this->addAcceptancePpeItems($project);

        $response = $this->actingAs($this->tc)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('Save the Beneficiary Mapping Source above to see the cost per barangay.');
    }

    public function test_full_breakdown_card_renders_on_the_project_page_with_real_data(): void
    {
        $project = $this->createProject();
        $items = $this->addAcceptancePpeItems($project);

        $this->updateAddresses($this->tc, $project, [
            [
                'barangay_id' => $this->mabini->id,
                'beneficiaries_total' => 30,
                'beneficiaries_female' => 15,
                'hazardous_workers' => 13,
                'ppe_items' => [
                    $items['gloves']->id => 9,
                    $items['boots']->id => 6,
                    $items['mask']->id => 7,
                    $items['shirt']->id => 30,
                ],
            ],
            [
                'barangay_id' => $this->rizal->id,
                'beneficiaries_total' => 20,
                'beneficiaries_female' => 10,
                'hazardous_workers' => 7,
                'ppe_items' => [
                    $items['gloves']->id => 5,
                    $items['boots']->id => 3,
                    $items['mask']->id => 4,
                    $items['shirt']->id => 20,
                ],
            ],
        ])->assertSessionDoesntHaveErrors();

        $response = $this->actingAs($this->admin)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('Barangay Cost &amp; PPE Classification', false);
        $response->assertSee('Mabini');
        $response->assertSee('Rizal');
        $response->assertSee('₱147,350.00', false);
        $response->assertSee('₱97,750.00', false);
        $response->assertSee('Matches Total Project Cost ₱245,100.00', false);
        $response->assertDontSee('Estimated');
    }
}
