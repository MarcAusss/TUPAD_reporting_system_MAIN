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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MajorRevisionPhase13DSystemWideProvinceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tc;
    private Province $masbate;
    private Province $albay;
    private Municipality $masbateMunicipality;
    private Municipality $albayMunicipality;
    private Project $masbateProject;
    private Project $albayProject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->masbate = $this->province('Masbate', '054100000');
        $this->albay = $this->province('Albay', '050500000');

        $this->masbateMunicipality = $this->municipality(
            $this->masbate,
            'Masbate City',
            '054101000',
        );
        $this->albayMunicipality = $this->municipality(
            $this->albay,
            'Legazpi City',
            '050506000',
        );

        $masbateBarangay = $this->barangay(
            $this->masbateMunicipality,
            'Bapor',
            '054101001',
        );
        $albayBarangay = $this->barangay(
            $this->albayMunicipality,
            'Rawis',
            '050506001',
        );

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->tc = User::factory()->create([
            'name' => 'Juls Masbate',
            'username' => 'juls.masbate',
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->masbate->id,
        ]);

        $this->masbateProject = $this->project(
            $this->masbate,
            $this->masbateMunicipality,
            $masbateBarangay,
            'MASBATE ONLY PROJECT',
        );
        $this->albayProject = $this->project(
            $this->albay,
            $this->albayMunicipality,
            $albayBarangay,
            'ALBAY HIDDEN PROJECT',
        );
    }

    public function test_tc_project_registry_and_workflow_queue_show_only_assigned_province(): void
    {
        $this->actingAs($this->tc)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($this->masbateProject->project_title)
            ->assertDontSee($this->albayProject->project_title);

        $this->actingAs($this->tc)
            ->get(route('project-workflow.index', ['queue' => 'tssd-evaluation']))
            ->assertOk()
            ->assertSee($this->masbateProject->project_title)
            ->assertDontSee($this->albayProject->project_title);
    }

    public function test_tc_cannot_open_a_foreign_project_by_changing_the_url(): void
    {
        $this->actingAs($this->tc)
            ->get(route('projects.show', $this->albayProject))
            ->assertForbidden();
    }

    public function test_tc_cannot_access_foreign_province_or_location_resources(): void
    {
        $this->actingAs($this->tc)
            ->get(route('project-summary.province', $this->albay))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->get(route('locations.municipalities', $this->albay))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->get(route('locations.barangays', $this->albayMunicipality))
            ->assertForbidden();
    }

    public function test_report_and_executive_filters_reject_foreign_province_tampering(): void
    {
        $this->actingAs($this->tc)
            ->get(route('reports.index', ['province_id' => $this->albay->id]))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->get(route('executive-dashboard.index', ['province_id' => $this->albay->id]))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->get(route('executive-dashboard.presentation', ['province_id' => $this->albay->id]))
            ->assertForbidden();
    }

    public function test_report_family_routes_are_forced_to_the_tc_assigned_province_when_omitted(): void
    {
        Route::middleware(['web', 'auth', 'province.scope'])
            ->get('/_phase13d/report-scope', function (Request $request) {
                return response((string) $request->query('province_id', 'missing'));
            })
            ->name('reports._phase13d-scope');

        Route::middleware(['web', 'auth', 'province.scope'])
            ->get('/_phase13d/executive-scope', function (Request $request) {
                return response((string) $request->query('province_id', 'missing'));
            })
            ->name('executive-dashboard._phase13d-scope');

        $this->actingAs($this->tc)
            ->get('/_phase13d/report-scope')
            ->assertOk()
            ->assertSee((string) $this->masbate->id);

        $this->actingAs($this->tc)
            ->get('/_phase13d/executive-scope')
            ->assertOk()
            ->assertSee((string) $this->masbate->id);
    }

    public function test_project_creation_only_exposes_the_assigned_province_and_scoped_allocations(): void
    {
        $response = $this->actingAs($this->tc)
            ->get(route('projects.create'))
            ->assertOk();

        $this->assertSame(
            [$this->masbate->id],
            $response->viewData('provinces')->pluck('id')->all(),
        );

        $visibleAllocationProvinces = $response->viewData('allocations')
            ->pluck('province')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['Masbate'], $visibleAllocationProvinces);
    }

    public function test_admin_keeps_regional_access(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee($this->masbateProject->project_title)
            ->assertSee($this->albayProject->project_title);
    }

    public function test_unassigned_tc_fails_closed_but_can_still_open_my_account(): void
    {
        $unassigned = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => null,
        ]);

        $this->autoAssignCoordinatorProvinceForTests = false;

        $this->actingAs($unassigned)
            ->get(route('dashboard'))
            ->assertForbidden();

        $this->actingAs($unassigned)
            ->get(route('account.show'))
            ->assertOk();
    }

    private function province(string $name, string $code): Province
    {
        return Province::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function municipality(Province $province, string $name, string $code): Municipality
    {
        return Municipality::query()->create([
            'province_id' => $province->id,
            'name' => $name,
            'code' => $code,
            'district' => '1st District',
            'is_city' => true,
            'is_active' => true,
        ]);
    }

    private function barangay(Municipality $municipality, string $name, string $code): Barangay
    {
        return Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function project(
        Province $province,
        Municipality $municipality,
        Barangay $barangay,
        string $title,
    ): Project {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-P13D-'.str_pad((string) $province->id, 3, '0', STR_PAD_LEFT),
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);

        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'location' => $municipality->name.', '.$province->name,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => $municipality->name,
            'amount' => '1000000.00',
            'grant_amount' => '1000000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '1000000.00',
            'created_by' => $this->admin->id,
        ]);

        return Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-29',
            'project_title' => $title,
            'nature_of_work' => 'Province scoped authorization test project.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'project_series' => 'Phase 13D',
            'tevs_date_verified' => '2026-08-29',
            'province_id' => $province->id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'province' => $province->name,
            'district' => '1st District',
            'municipality' => $municipality->name,
            'barangay' => $barangay->name,
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wage_rate' => '500.00',
            'wages_total' => '50000.00',
            'ppe_total' => '0.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => '500.00',
            'total_project_cost' => '50500.00',
            'status' => ProjectStatus::TSSD_EVALUATION,
            'created_by' => $this->admin->id,
        ]);
    }
}
