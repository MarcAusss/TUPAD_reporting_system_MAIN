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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseValidationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;
    private User $focal;

    private Adl $adl;
    private AdlAllocation $allocation;

    private Province $province;
    private Municipality $municipality;
    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */

        $this->tc = User::create([
            'name' => 'Hardening TC',
            'username' => 'hardening-tc',
            'email' => 'hardening-tc@example.test',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->focal = User::create([
            'name' => 'Hardening Focal',
            'username' => 'hardening-focal',
            'email' => 'hardening-focal@example.test',
            'position' => 'TUPAD Focal',
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Location
        |--------------------------------------------------------------------------
        */

        $this->province = Province::create([
            'name' => 'Catanduanes',
            'is_active' => true,
        ]);

        $this->municipality = Municipality::create([
            'province_id' => $this->province->id,
            'name' => 'Virac',
            'district' => 'Lone District',
            'income_class' => null,
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->barangay = Barangay::create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Mabini',
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | ADL
        |--------------------------------------------------------------------------
        */

        $this->adl = Adl::create([
            'adl_number' => 'HARD-ADL-001',
            'grants' => 5_000_000,
            'admin_cost' => 150_000,
            'total' => 5_150_000,
            'created_by' => $this->focal->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Allocation
        |--------------------------------------------------------------------------
        */

        $this->allocation = AdlAllocation::create([
            'adl_id' => $this->adl->id,
            'fund_sponsor' => 'DOLE',
            'partner' => 'LGU Virac',
            'location' => 'Virac, Catanduanes',
            'amount' => 2_000_000,
            'remarks' => 'Validation hardening allocation.',
            'created_by' => $this->focal->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Valid Official Project Payload
    |--------------------------------------------------------------------------
    */

    private function validProjectPayload(): array
    {
        return [
            'adl_allocation_id' =>
                $this->allocation->id,

            'date_received' =>
                now()->toDateString(),

            'project_title' =>
                'Validation Hardening Project',

            'nature_of_work' =>
                'Community clean-up activities.',

            'fund_sponsor' =>
                'Department of Labor and Employment',

            'partner' =>
                'LGU Virac',

            'project_series' =>
                'Regular TUPAD 2026',

            'project_series_remarks' =>
                'Validation test series remarks.',

            'tevs_date_verified' =>
                now()->toDateString(),

            'tevs_remarks' =>
                'TEVS verified.',

            'province_id' =>
                $this->province->id,

            'municipality_id' =>
                $this->municipality->id,

            'barangay_id' =>
                $this->barangay->id,

            'implementation_mode' =>
                ImplementationMode::DIRECT_ADMINISTRATION->value,

            'number_of_days' =>
                20,

            'beneficiaries_total' =>
                10,

            'beneficiaries_female' =>
                5,

            'wage_rate' =>
                455,

            'insurance_rate' =>
                50,

            'ppe_items' =>
                [],

            'remarks' =>
                null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Location Hierarchy
    |--------------------------------------------------------------------------
    */

    public function test_project_cannot_use_municipality_from_another_province(): void
    {
        $otherProvince = Province::create([
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $otherMunicipality = Municipality::create([
            'province_id' => $otherProvince->id,
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'income_class' => null,
            'is_city' => true,
            'is_active' => true,
        ]);

        $payload =
            $this->validProjectPayload();

        $payload['municipality_id'] =
            $otherMunicipality->id;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertNotFound();

        $this->assertDatabaseMissing(
            'projects',
            [
                'project_title' =>
                    'Validation Hardening Project',
            ]
        );
    }

    public function test_project_cannot_use_barangay_from_another_municipality(): void
    {
        $otherMunicipality = Municipality::create([
            'province_id' =>
                $this->province->id,

            'name' =>
                'Caramoran',

            'district' =>
                'Lone District',

            'income_class' =>
                null,

            'is_city' =>
                false,

            'is_active' =>
                true,
        ]);

        $otherBarangay = Barangay::create([
            'municipality_id' =>
                $otherMunicipality->id,

            'name' =>
                'Mabini',

            'is_active' =>
                true,
        ]);

        $payload =
            $this->validProjectPayload();

        $payload['barangay_id'] =
            $otherBarangay->id;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertNotFound();

        $this->assertDatabaseMissing(
            'projects',
            [
                'project_title' =>
                    'Validation Hardening Project',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Beneficiary Validation
    |--------------------------------------------------------------------------
    */

    public function test_female_beneficiaries_cannot_exceed_total_beneficiaries(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['beneficiaries_total'] =
            10;

        $payload['beneficiaries_female'] =
            11;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'beneficiaries_female'
        );

        $this->assertDatabaseMissing(
            'projects',
            [
                'project_title' =>
                    'Validation Hardening Project',
            ]
        );
    }

    public function test_project_requires_at_least_one_beneficiary(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['beneficiaries_total'] =
            0;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'beneficiaries_total'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Duration Validation
    |--------------------------------------------------------------------------
    */

    public function test_project_rejects_duration_below_minimum(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['number_of_days'] =
            9;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'number_of_days'
        );
    }

    public function test_project_rejects_duration_above_maximum(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['number_of_days'] =
            91;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'number_of_days'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Validation
    |--------------------------------------------------------------------------
    */

    public function test_wage_rate_must_be_greater_than_zero(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['wage_rate'] =
            0;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'wage_rate'
        );
    }

    public function test_insurance_rate_cannot_be_negative(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['insurance_rate'] =
            -1;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'insurance_rate'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Allocation Budget Protection
    |--------------------------------------------------------------------------
    */

    public function test_project_cost_cannot_exceed_remaining_allocation_budget(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Existing project consumes most of the allocation
        |--------------------------------------------------------------------------
        */

        Project::create([
            'adl_allocation_id' =>
                $this->allocation->id,

            'date_received' =>
                now()->toDateString(),

            'project_title' =>
                'Existing Expensive Project',

            'nature_of_work' =>
                'Existing allocation consumption.',

            'province_id' =>
                $this->province->id,

            'municipality_id' =>
                $this->municipality->id,

            'barangay_id' =>
                $this->barangay->id,

            'province' =>
                $this->province->name,

            'district' =>
                $this->municipality->district,

            'municipality' =>
                $this->municipality->name,

            'barangay' =>
                $this->barangay->name,

            'income_class' =>
                null,

            'implementation_mode' =>
                ImplementationMode::DIRECT_ADMINISTRATION,

            'number_of_days' =>
                20,

            'term' =>
                ProjectTerm::SHORT_TERM,

            'beneficiaries_total' =>
                1,

            'beneficiaries_female' =>
                0,

            'wage_rate' =>
                455,

            'wages_total' =>
                1_900_000,

            'ppe_total' =>
                0,

            'insurance_rate' =>
                0,

            'insurance_total' =>
                0,

            'total_project_cost' =>
                1_900_000,

            'status' =>
                ProjectStatus::ONGOING_PROFILING,

            'created_by' =>
                $this->tc->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | New project requires more than the remaining ₱100,000
        |--------------------------------------------------------------------------
        */

        $payload =
            $this->validProjectPayload();

        $payload['beneficiaries_total'] =
            20;

        $payload['beneficiaries_female'] =
            10;

        $payload['number_of_days'] =
            20;

        $payload['wage_rate'] =
            455;

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'adl_allocation_id'
        );

        $this->assertDatabaseMissing(
            'projects',
            [
                'project_title' =>
                    'Validation Hardening Project',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PPE Validation
    |--------------------------------------------------------------------------
    */

    public function test_ppe_beneficiary_count_cannot_exceed_project_beneficiaries(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['beneficiaries_total'] =
            10;

        $payload['ppe_items'] = [
            [
                'ppe_type' =>
                    'non_hazardous',

                'product' =>
                    'Gloves',

                'beneficiary_count' =>
                    11,

                'unit_amount' =>
                    50,
            ],
        ];

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'ppe_items.0.beneficiary_count'
        );
    }

    public function test_negative_ppe_unit_amount_is_rejected(): void
    {
        $payload =
            $this->validProjectPayload();

        $payload['ppe_items'] = [
            [
                'ppe_type' =>
                    'non_hazardous',

                'product' =>
                    'Gloves',

                'beneficiary_count' =>
                    10,

                'unit_amount' =>
                    -50,
            ],
        ];

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route('projects.store'),
                $payload
            );

        $response->assertSessionHasErrors(
            'ppe_items.0.unit_amount'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approval Uniqueness
    |--------------------------------------------------------------------------
    */

    public function test_project_cannot_have_duplicate_approval_record(): void
    {
        $project =
            $this->createProjectForApproval();

        $this->actingAs($this->tc)
            ->post(
                route(
                    'projects.approval.store',
                    $project
                ),
                [
                    'approval_date' =>
                        now()->toDateString(),

                    'project_code' =>
                        'HARD-CODE-001',

                    'remarks' =>
                        null,
                ]
            )
            ->assertRedirect();

        /*
        |--------------------------------------------------------------------------
        | Force project back to For Approval to simulate a manipulated state.
        |--------------------------------------------------------------------------
        */

        $project->updateQuietly([
            'status' =>
                ProjectStatus::FOR_APPROVAL,
        ]);

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.approval.store',
                    $project
                ),
                [
                    'approval_date' =>
                        now()->toDateString(),

                    'project_code' =>
                        'HARD-CODE-002',

                    'remarks' =>
                        null,
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Application must not silently create a second approval.
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            1,
            $project
                ->approval()
                ->count()
        );

        $this->assertDatabaseMissing(
            'project_approvals',
            [
                'project_id' =>
                    $project->id,

                'project_code' =>
                    'HARD-CODE-002',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Unique Project Code
    |--------------------------------------------------------------------------
    */

    public function test_project_code_cannot_be_reused_by_another_project(): void
    {
        $projectOne =
            $this->createProjectForApproval();

        $projectTwo =
            $this->createProjectForApproval();

        $this->actingAs($this->tc)
            ->post(
                route(
                    'projects.approval.store',
                    $projectOne
                ),
                [
                    'approval_date' =>
                        now()->toDateString(),

                    'project_code' =>
                        'UNIQUE-CODE-001',

                    'remarks' =>
                        null,
                ]
            )
            ->assertRedirect();

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.approval.store',
                    $projectTwo
                ),
                [
                    'approval_date' =>
                        now()->toDateString(),

                    'project_code' =>
                        'UNIQUE-CODE-001',

                    'remarks' =>
                        null,
                ]
            );

        $response->assertSessionHasErrors(
            'project_code'
        );

        $this->assertSame(
            1,
            \App\Models\ProjectApproval::query()
                ->where(
                    'project_code',
                    'UNIQUE-CODE-001'
                )
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function createProjectForApproval(): Project
    {
        return Project::create([
            'adl_allocation_id' =>
                $this->allocation->id,

            'date_received' =>
                now()->toDateString(),

            'project_title' =>
                'Approval Hardening ' . uniqid(),

            'nature_of_work' =>
                'Approval hardening test.',

            'province_id' =>
                $this->province->id,

            'municipality_id' =>
                $this->municipality->id,

            'barangay_id' =>
                $this->barangay->id,

            'province' =>
                $this->province->name,

            'district' =>
                $this->municipality->district,

            'municipality' =>
                $this->municipality->name,

            'barangay' =>
                $this->barangay->name,

            'income_class' =>
                null,

            'implementation_mode' =>
                ImplementationMode::DIRECT_ADMINISTRATION,

            'number_of_days' =>
                20,

            'term' =>
                ProjectTerm::SHORT_TERM,

            'beneficiaries_total' =>
                2,

            'beneficiaries_female' =>
                1,

            'wage_rate' =>
                455,

            'wages_total' =>
                18_200,

            'ppe_total' =>
                0,

            'insurance_rate' =>
                50,

            'insurance_total' =>
                100,

            'total_project_cost' =>
                18_300,

            'status' =>
                ProjectStatus::FOR_APPROVAL,

            'created_by' =>
                $this->tc->id,
        ]);
    }
}