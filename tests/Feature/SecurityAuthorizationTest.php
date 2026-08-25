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
use App\Models\ProjectPostDocument;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tc;
    private User $focal;
    private User $gip;

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
            'name' => 'Security Administrator',
            'username' => 'security-admin',
            'email' => 'security-admin@example.test',
            'position' => 'Administrator',
            'role' => UserRole::ADMIN,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->tc = User::create([
            'name' => 'Security TC',
            'username' => 'security-tc',
            'email' => 'security-tc@example.test',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->focal = User::create([
            'name' => 'Security Focal',
            'username' => 'security-focal',
            'email' => 'security-focal@example.test',
            'position' => 'TUPAD Focal',
            'role' => UserRole::FOCAL,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->gip = User::create([
            'name' => 'Security GIP',
            'username' => 'security-gip',
            'email' => 'security-gip@example.test',
            'position' => 'GIP',
            'role' => UserRole::GIP,
            'is_active' => true,
            'supervisor_tc_id' => $this->tc->id,
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

        $adl = Adl::create([
            'adl_number' => 'SEC-ADL-001',
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
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE',
            'partner' => 'LGU Virac',
            'location' => 'Virac, Catanduanes',
            'amount' => 2_000_000,
            'remarks' => 'Security test allocation.',
            'created_by' => $this->focal->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Project Helper
    |--------------------------------------------------------------------------
    */

    private function createProject(
        ProjectStatus $status = ProjectStatus::ONGOING_PROFILING
    ): Project {
        $beneficiaries = 2;
        $days = 20;
        $wageRate = 455;
        $insuranceRate = 50;

        $wages =
            $beneficiaries
            * $days
            * $wageRate;

        $insurance =
            $beneficiaries
            * $insuranceRate;

        return Project::create([
            'adl_allocation_id' =>
                $this->allocation->id,

            'date_received' =>
                now()->toDateString(),

            'project_title' =>
                'Security Test Project ' . uniqid(),

            'nature_of_work' =>
                'Security authorization testing.',

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
                $status,

            'created_by' =>
                $this->tc->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_guest_is_redirected_from_protected_project_routes(): void
    {
        $response = $this->get(
            route('projects.index')
        );

        $response->assertRedirect(
            route('login')
        );
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'username' => 'inactive-user',
            'email' => 'inactive@example.test',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => false,
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(
            route('login.store'),
            [
                'username' =>
                    $inactiveUser->username,

                'password' =>
                    'password',
            ]
        );

        $this->assertGuest();

        $response->assertSessionHasErrors();
    }

    /*
    |--------------------------------------------------------------------------
    | Official Project Creation
    |--------------------------------------------------------------------------
    */

    public function test_gip_cannot_access_official_project_creation(): void
    {
        $response = $this
            ->actingAs($this->gip)
            ->get(
                route('projects.create')
            );

        $response->assertForbidden();
    }

    public function test_focal_cannot_access_official_project_creation(): void
    {
        $response = $this
            ->actingAs($this->focal)
            ->get(
                route('projects.create')
            );

        $response->assertForbidden();
    }

    public function test_tc_can_access_official_project_creation(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->get(
                route('projects.create')
            );

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | ADL Access
    |--------------------------------------------------------------------------
    */

    public function test_tc_cannot_access_adl_management(): void
    {
        $response = $this
            ->actingAs($this->tc)
            ->get(
                route('adl.index')
            );

        $response->assertForbidden();
    }

    public function test_focal_can_access_adl_management(): void
    {
        $response = $this
            ->actingAs($this->focal)
            ->get(
                route('adl.index')
            );

        $response->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Individual Beneficiary Registry Removed
    |--------------------------------------------------------------------------
    */

    public function test_individual_beneficiary_routes_are_not_registered(): void
    {
        $routeNames = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->values();

        foreach ([
            'projects.beneficiaries.index',
            'projects.beneficiaries.store',
            'projects.beneficiaries.edit',
            'projects.beneficiaries.update',
            'projects.beneficiaries.destroy',
        ] as $routeName) {
            $this->assertFalse($routeNames->contains($routeName));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Post-Document Protection
    |--------------------------------------------------------------------------
    */

    public function test_post_document_from_another_project_cannot_be_downloaded_through_wrong_project(): void
    {
        Storage::fake('local');

        $projectOne = $this->createProject(
            ProjectStatus::FOR_PAYMENT
        );

        $projectTwo = $this->createProject(
            ProjectStatus::FOR_PAYMENT
        );

        $path =
            "projects/{$projectOne->id}/post-docs/test.pdf";

        Storage::disk('local')->put(
            $path,
            'fake pdf content'
        );

        $document = ProjectPostDocument::create([
            'project_id' =>
                $projectOne->id,

            'date_received' =>
                now()->toDateString(),

            'document_type' =>
                'Accomplishment Report',

            'attachment_path' =>
                $path,

            'date_forwarded_to_imsd' =>
                null,

            'remarks' =>
                null,

            'recorded_by' =>
                $this->tc->id,
        ]);

        $response = $this
            ->actingAs($this->tc)
            ->get(
                route(
                    'projects.post-documents.download',
                    [
                        'project' =>
                            $projectTwo,

                        'projectPostDocument' =>
                            $document,
                    ]
                )
            );

        $response->assertNotFound();
    }

    public function test_focal_cannot_download_document_from_non_payment_project(): void
    {
        Storage::fake('local');

        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        );

        $path =
            "projects/{$project->id}/post-docs/test.pdf";

        Storage::disk('local')->put(
            $path,
            'fake pdf content'
        );

        $document = ProjectPostDocument::create([
            'project_id' =>
                $project->id,

            'date_received' =>
                now()->toDateString(),

            'document_type' =>
                'Accomplishment Report',

            'attachment_path' =>
                $path,

            'recorded_by' =>
                $this->tc->id,
        ]);

        $response = $this
            ->actingAs($this->focal)
            ->get(
                route(
                    'projects.post-documents.download',
                    [
                        'project' =>
                            $project,

                        'projectPostDocument' =>
                            $document,
                    ]
                )
            );

        $response->assertForbidden();
    }

    public function test_focal_can_download_document_from_for_payment_project(): void
    {
        Storage::fake('local');

        $project = $this->createProject(
            ProjectStatus::FOR_PAYMENT
        );

        $path =
            "projects/{$project->id}/post-docs/test.pdf";

        Storage::disk('local')->put(
            $path,
            'fake pdf content'
        );

        $document = ProjectPostDocument::create([
            'project_id' =>
                $project->id,

            'date_received' =>
                now()->toDateString(),

            'document_type' =>
                'Accomplishment Report',

            'attachment_path' =>
                $path,

            'recorded_by' =>
                $this->tc->id,
        ]);

        $response = $this
            ->actingAs($this->focal)
            ->get(
                route(
                    'projects.post-documents.download',
                    [
                        'project' =>
                            $project,

                        'projectPostDocument' =>
                            $document,
                    ]
                )
            );

        $response->assertOk();
    }

    public function test_gip_cannot_download_official_project_documents(): void
    {
        Storage::fake('local');

        $project = $this->createProject(
            ProjectStatus::FOR_PAYMENT
        );

        $path =
            "projects/{$project->id}/post-docs/test.pdf";

        Storage::disk('local')->put(
            $path,
            'fake pdf content'
        );

        $document = ProjectPostDocument::create([
            'project_id' =>
                $project->id,

            'date_received' =>
                now()->toDateString(),

            'document_type' =>
                'Accomplishment Report',

            'attachment_path' =>
                $path,

            'recorded_by' =>
                $this->tc->id,
        ]);

        $response = $this
            ->actingAs($this->gip)
            ->get(
                route(
                    'projects.post-documents.download',
                    [
                        'project' =>
                            $project,

                        'projectPostDocument' =>
                            $document,
                    ]
                )
            );

        $response->assertForbidden();
    }
}