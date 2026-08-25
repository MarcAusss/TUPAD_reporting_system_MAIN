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

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tc;

    private User $focal;

    private User $gip;

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

        $this->admin = User::create([
            'name' => 'System Administrator',
            'username' => 'test-admin',
            'email' => 'test-admin@example.test',
            'position' => 'Administrator',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->tc = User::create([
            'name' => 'Test TUPAD Coordinator',
            'username' => 'test-tc',
            'email' => 'test-tc@example.test',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->focal = User::create([
            'name' => 'Test Focal',
            'username' => 'test-focal',
            'email' => 'test-focal@example.test',
            'position' => 'TUPAD Focal',
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->gip = User::create([
            'name' => 'Test GIP',
            'username' => 'test-gip',
            'email' => 'test-gip@example.test',
            'position' => 'GIP',
            'role' => UserRole::GIP,
            'is_active' => true,
            'supervisor_tc_id' => $this->tc->id,
            'password' => Hash::make('password'),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Geographic References
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
            'adl_number' => 'TEST-ADL-001',
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
            'remarks' => 'Automated testing allocation.',
            'created_by' => $this->focal->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Create Profiling Project
    |--------------------------------------------------------------------------
    */

    private function createProfilingProject(
        int $beneficiaries = 2
    ): Project {
        $wageRate = 455;
        $days = 20;

        $wages =
            $beneficiaries
            * $days
            * $wageRate;

        $insuranceRate = 50;

        $insurance =
            $beneficiaries
            * $insuranceRate;

        return Project::create([
            'adl_allocation_id' =>
                $this->allocation->id,

            'date_received' =>
                now()->toDateString(),

            'project_title' =>
                'Automated Workflow Test Project',

            'nature_of_work' =>
                'Community maintenance activities.',

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
                $days,

            'term' =>
                ProjectTerm::SHORT_TERM,

            'beneficiaries_total' =>
                $beneficiaries,

            'beneficiaries_female' =>
                0,

            'wage_rate' =>
                $wageRate,

            'wages_total' =>
                $wages,

            'ppe_total' =>
                0,

            'insurance_rate' =>
                $insuranceRate,

            'insurance_total' =>
                $insurance,

            'total_project_cost' =>
                $wages + $insurance,

            'status' =>
                ProjectStatus::ONGOING_PROFILING,

            'created_by' =>
                $this->tc->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Aggregate Beneficiaries / TSSD Evaluation
    |--------------------------------------------------------------------------
    */

    public function test_project_uses_aggregate_beneficiary_counts_without_individual_registry(): void
    {
        $project = $this->createProfilingProject(beneficiaries: 50);

        $this->assertSame(50, (int) $project->beneficiaries_total);
        $this->assertSame(0, (int) $project->beneficiaries_female);
        $this->assertDatabaseCount('project_beneficiaries', 0);
    }

    public function test_project_can_enter_tssd_without_individual_beneficiary_registry(): void
    {
        $project = $this->createProfilingProject(beneficiaries: 2);

        $this->assertSame(0, $project->beneficiaries()->count());

        $response = $this
            ->actingAs($this->tc)
            ->post(route('projects.evaluation.start', $project));

        $response->assertRedirect();

        $this->assertSame(
            ProjectStatus::TSSD_EVALUATION,
            $project->fresh()->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Evaluation Result
    |--------------------------------------------------------------------------
    */

    public function test_tssd_evaluation_can_move_project_to_for_approval(): void
    {
        $project = $this->createProfilingProject();

        $project->update([
            'status' =>
                ProjectStatus::TSSD_EVALUATION,
        ]);

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.evaluation.store',
                    $project
                ),
                [
                    'result' => 'for_approval',
                    'findings' => null,
                    'required_documents' => null,
                    'remarks' => 'Requirements complete.',
                ]
            );

        $response->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_APPROVAL,
            $project->fresh()->status
        );

        $this->assertDatabaseHas(
            'project_evaluations',
            [
                'project_id' => $project->id,
                'result' => 'for_approval',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approval
    |--------------------------------------------------------------------------
    */

    public function test_project_can_be_approved(): void
    {
        $project = $this->createProfilingProject();

        $project->update([
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
                        'TEST-TUPAD-001',

                    'remarks' =>
                        'Approved during automated test.',
                ]
            );

        $response->assertRedirect();

        $this->assertSame(
            ProjectStatus::APPROVED,
            $project->fresh()->status
        );

        $this->assertDatabaseHas(
            'project_approvals',
            [
                'project_id' =>
                    $project->id,

                'project_code' =>
                    'TEST-TUPAD-001',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Role Restrictions
    |--------------------------------------------------------------------------
    */

    public function test_gip_cannot_access_official_project(): void
    {
        $project =
            $this->createProfilingProject();

        $response = $this
            ->actingAs($this->gip)
            ->get(
                route(
                    'projects.show',
                    $project
                )
            );

        $response->assertForbidden();
    }

    public function test_focal_cannot_view_profiling_project(): void
    {
        $project =
            $this->createProfilingProject();

        $response = $this
            ->actingAs($this->focal)
            ->get(
                route(
                    'projects.show',
                    $project
                )
            );

        $response->assertForbidden();
    }

    public function test_focal_can_view_for_payment_project(): void
    {
        $project =
            $this->createProfilingProject();

        $project->update([
            'status' =>
                ProjectStatus::FOR_PAYMENT,
        ]);

        $response = $this
            ->actingAs($this->focal)
            ->get(
                route(
                    'projects.show',
                    $project
                )
            );

        $response->assertOk();
    }

    public function test_gip_cannot_access_official_reports(): void
    {
        $response = $this
            ->actingAs($this->gip)
            ->get(
                route(
                    'reports.index'
                )
            );

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Geographic Hierarchy
    |--------------------------------------------------------------------------
    */

    public function test_same_barangay_name_can_exist_under_different_municipalities(): void
    {
        $secondMunicipality =
            Municipality::create([
                'province_id' =>
                    $this->province->id,

                'name' =>
                    'Caramoran',

                'district' =>
                    'Lone District',

                'is_city' =>
                    false,

                'is_active' =>
                    true,
            ]);

        $secondBarangay =
            Barangay::create([
                'municipality_id' =>
                    $secondMunicipality->id,

                'name' =>
                    'Mabini',

                'is_active' =>
                    true,
            ]);

        $this->assertNotSame(
            $this->barangay->id,
            $secondBarangay->id
        );

        $this->assertSame(
            'Mabini',
            $secondBarangay->name
        );
    }
}