<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\ProjectBeneficiaryDeductionController;
use App\Http\Controllers\ProjectSectionEditController;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectBeneficiaryAddress;
use App\Models\ProjectEditLog;
use App\Models\ProjectEditRequest;
use App\Models\Province;
use App\Models\User;
use App\Enums\ReportDimension;
use App\Reports\ReportFilters;
use App\Services\Projects\ProjectBeneficiaryAddressService;
use App\Services\Reports\ReportingDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActualBeneficiaryMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;
    private User $tc;
    private Province $province;
    private Municipality $municipality;
    private Barangay $rawis;
    private Barangay $bogtong;
    private Project $project;
    private ProjectBeneficiaryAddress $rawisAddress;
    private ProjectBeneficiaryAddress $bogtongAddress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->province = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $this->municipality = Municipality::create(['province_id' => $this->province->id, 'code' => '050506000', 'name' => 'Legazpi City', 'is_active' => true]);
        $this->rawis = Barangay::create(['municipality_id' => $this->municipality->id, 'code' => '050506001', 'name' => 'Rawis', 'is_active' => true]);
        $this->bogtong = Barangay::create(['municipality_id' => $this->municipality->id, 'code' => '050506002', 'name' => 'Bogtong', 'is_active' => true]);

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true, 'name' => 'Focal Maria']);
        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->province->id,
            'name' => 'Coordinator Juan',
        ]);

        $this->project = $this->createProject();
    }

    public function test_tc_is_notified_when_obligations_complete_with_fewer_beneficiaries(): void
    {
        $this->assertNotInTcFeed();

        $this->completeObligation(beneficiaries: 8, female: 5);

        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonFragment([
                'project_title' => 'Actual Mapping Project',
                'action_label' => 'Beneficiary Deduction',
                'url' => route('projects.show', ['project' => $this->project, 'workspace' => 'overview']).'#section-beneficiary-deduction-'.$this->project->id,
            ]);

        $this->actingAs($this->tc)
            ->get(route('project-workflow.index', ['queue' => 'beneficiary-deduction']))
            ->assertOk()
            ->assertSee('Actual Mapping Project')
            ->assertSee('Record Deduction');

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertOk()
            ->assertSee('Beneficiary Deduction (Actual Beneficiary Mapping)')
            ->assertSee('Not Included (to deduct)')
            ->assertSee('Rawis, Legazpi City')
            ->assertSee('Save Deductions');
    }

    public function test_no_deduction_is_needed_when_obligations_cover_every_beneficiary(): void
    {
        $this->completeObligation(beneficiaries: 10, female: 6);

        $this->assertNotInTcFeed();

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertDontSee('Beneficiary Deduction (Actual Beneficiary Mapping)');

        $this->actingAs($this->tc)
            ->put(route('projects.beneficiary-deductions.update', $this->project), ['deductions' => []])
            ->assertForbidden();
    }

    public function test_deductions_must_match_the_shortfall_and_fit_each_address(): void
    {
        $this->completeObligation(beneficiaries: 8, female: 5);
        $bag = ProjectSectionEditController::errorBag(ProjectBeneficiaryDeductionController::SECTION, $this->project->id);

        // Only 1 of the 2 excluded beneficiaries indicated.
        $this->saveDeductions([$this->rawis->id => ['total' => 1, 'female' => 1]])
            ->assertSessionHasErrors('deductions', null, $bag);

        // More than the address holds.
        $this->saveDeductions([$this->bogtong->id => ['total' => 5, 'female' => 1]])
            ->assertSessionHasErrors("deductions.{$this->bogtong->id}.total", null, $bag);

        // Female deductions must also match (1 female excluded).
        $this->saveDeductions([$this->rawis->id => ['total' => 2, 'female' => 0]])
            ->assertSessionHasErrors('deductions', null, $bag);

        $this->assertDatabaseCount('project_beneficiary_deductions', 0);
        $this->assertNull($this->project->fresh()->beneficiary_deductions_recorded_at);
    }

    public function test_actual_beneficiary_mapping_is_mapping_minus_deductions(): void
    {
        $this->completeObligation(beneficiaries: 8, female: 5);

        $this->saveDeductions([
            $this->rawis->id => ['total' => 1, 'female' => 1],
            $this->bogtong->id => ['total' => 1, 'female' => 0],
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($this->project->fresh()->beneficiary_deductions_recorded_at);
        $this->assertNotInTcFeed();

        $this->assertSame(5, $this->rawisAddress->fresh()->load('deduction')->actualTotal());
        $this->assertSame(3, $this->rawisAddress->fresh()->load('deduction')->actualFemale());
        $this->assertSame(3, $this->bogtongAddress->fresh()->load('deduction')->actualTotal());

        $reporting = app(ReportingDataService::class);

        $mapped = $reporting->beneficiaryGeography(new ReportFilters(), ReportDimension::BARANGAY)->keyBy('label');
        $actual = $reporting->beneficiaryGeography(new ReportFilters(actualBeneficiaries: true), ReportDimension::BARANGAY)->keyBy('label');

        $this->assertSame(10, (int) $mapped->sum('beneficiaries_total'));
        $this->assertSame(8, (int) $actual->sum('beneficiaries_total'));
        $this->assertSame(5, (int) $actual->sum('beneficiaries_female'));

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertSee('Deductions recorded by Coordinator Juan')
            ->assertDontSee('Save Deductions')
            ->assertSee('Request Edit');

        $this->actingAs($this->tc)
            ->get(route('reports.workspace.geographic-mapping', ['view' => 'actual_beneficiaries']))
            ->assertOk()
            ->assertSee('Actual Beneficiary Mapping');
    }

    public function test_changing_recorded_deductions_needs_focal_approval_and_is_noted(): void
    {
        $this->completeObligation(beneficiaries: 8, female: 5);
        $this->saveDeductions([
            $this->rawis->id => ['total' => 2, 'female' => 1],
        ])->assertSessionHasNoErrors();

        $corrected = [
            $this->rawis->id => ['total' => 1, 'female' => 1],
            $this->bogtong->id => ['total' => 1, 'female' => 0],
        ];

        $this->saveDeductions($corrected)->assertForbidden();

        $this->actingAs($this->tc)
            ->post(route('projects.sections.edit-requests.store', [$this->project, ProjectBeneficiaryDeductionController::SECTION, $this->project->id]))
            ->assertSessionHas('success');

        $editRequest = ProjectEditRequest::firstOrFail();

        $this->actingAs($this->focal)
            ->postJson(route('edit-requests.approve', $editRequest))
            ->assertOk();

        $this->saveDeductions($corrected)->assertSessionHasNoErrors();

        $this->assertSame(ProjectEditRequest::USED, $editRequest->fresh()->status);
        $this->assertSame(3, $this->bogtongAddress->fresh()->load('deduction')->actualTotal());

        $log = ProjectEditLog::firstOrFail();
        $this->assertSame($this->focal->id, $log->approved_by);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertSee('Edited by Coordinator Juan')
            ->assertSee('Bogtong, Legazpi City deducted');
    }

    public function test_barangays_come_from_project_locations_when_no_mapping_source_is_encoded(): void
    {
        // Project with only the create-form location allocations (no Beneficiary Mapping Source).
        ProjectBeneficiaryAddress::query()->where('project_id', $this->project->id)->delete();

        $location = $this->project->projectLocations()->create([
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'district' => '2nd District',
            'sort_order' => 1,
        ]);
        $location->barangays()->attach([
            $this->rawis->id => ['beneficiaries_total' => 6, 'beneficiaries_female' => 4],
            $this->bogtong->id => ['beneficiaries_total' => 4, 'beneficiaries_female' => 2],
        ]);

        $this->completeObligation(beneficiaries: 9, female: 6);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertOk()
            ->assertSee('Barangays are taken from the project\'s location allocations', false)
            ->assertSee('Rawis, Legazpi City')
            ->assertSee('Bogtong, Legazpi City')
            ->assertSee('data-deduction-select', false);

        // Unticked barangays submit nothing; only the ticked one is deducted.
        $this->saveDeductions([$this->bogtong->id => ['total' => 5]])
            ->assertSessionHasErrors(
                "deductions.{$this->bogtong->id}.total",
                null,
                ProjectSectionEditController::errorBag(ProjectBeneficiaryDeductionController::SECTION, $this->project->id),
            );

        $this->saveDeductions([$this->bogtong->id => ['total' => 1]])->assertSessionHasNoErrors();

        $this->assertSame(2, ProjectBeneficiaryAddress::query()->where('project_id', $this->project->id)->count());
        $this->assertSame(
            9,
            (int) app(ReportingDataService::class)
                ->beneficiaryGeography(new ReportFilters(actualBeneficiaries: true), ReportDimension::BARANGAY)
                ->sum('beneficiaries_total')
        );
        $this->assertNotInTcFeed();
    }

    public function test_resaving_the_beneficiary_mapping_keeps_the_deductions(): void
    {
        $this->completeObligation(beneficiaries: 8, female: 5);
        $this->saveDeductions([
            $this->rawis->id => ['total' => 1, 'female' => 1],
            $this->bogtong->id => ['total' => 1, 'female' => 0],
        ])->assertSessionHasNoErrors();

        app(ProjectBeneficiaryAddressService::class)->sync(
            $this->project->fresh(),
            $this->tc,
            $this->province->id,
            $this->province->id,
            $this->mappingInput(),
        );

        $this->assertDatabaseCount('project_beneficiary_deductions', 2);
        $this->assertNotNull($this->project->fresh()->beneficiary_deductions_recorded_at);
        $this->assertSame(
            8,
            (int) app(ReportingDataService::class)
                ->beneficiaryGeography(new ReportFilters(actualBeneficiaries: true), ReportDimension::BARANGAY)
                ->sum('beneficiaries_total')
        );
    }

    private function assertNotInTcFeed(): void
    {
        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonMissing(['action_label' => 'Beneficiary Deduction']);
    }

    private function saveDeductions(array $deductions)
    {
        return $this->actingAs($this->tc)
            ->put(route('projects.beneficiary-deductions.update', $this->project), ['deductions' => $deductions]);
    }

    private function completeObligation(int $beneficiaries, int $female): void
    {
        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $this->project), [
                'intent' => 'complete',
                'tranches' => [[
                    'beneficiaries_total' => $beneficiaries,
                    'beneficiaries_female' => $female,
                    'wages_amount' => number_format($beneficiaries * 80, 2, '.', ''),
                    'obligation_date' => now()->toDateString(),
                    'payee' => 'TUPAD Beneficiaries',
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->project->refresh();
    }

    private function mappingInput(): array
    {
        return [[
            'municipality_id' => $this->municipality->id,
            'barangays' => [
                ['barangay_id' => $this->rawis->id, 'beneficiaries_total' => 6, 'beneficiaries_female' => 4],
                ['barangay_id' => $this->bogtong->id, 'beneficiaries_total' => 4, 'beneficiaries_female' => 2],
            ],
        ]];
    }

    private function createProject(): Project
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-ACTUAL-001',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => 'Actual Mapping Project',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => 4,
            'wages_total' => '800.00',
            'ppe_total' => '0.00',
            'insurance_rate' => 0,
            'insurance_total' => '0.00',
            'total_project_cost' => '800.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->tc->id,
        ]);

        $this->rawisAddress = ProjectBeneficiaryAddress::create([
            'project_id' => $project->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->rawis->id,
            'beneficiaries_total' => 6,
            'beneficiaries_female' => 4,
            'encoded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);

        $this->bogtongAddress = ProjectBeneficiaryAddress::create([
            'project_id' => $project->id,
            'province_id' => $this->province->id,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->bogtong->id,
            'beneficiaries_total' => 4,
            'beneficiaries_female' => 2,
            'encoded_by' => $this->tc->id,
            'updated_by' => $this->tc->id,
        ]);

        return $project;
    }
}
