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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage for the automatic TUPAD-RO5 Project Code generator:
 * province/municipality mapping, per-scope series continuation and
 * independence, and the monthly reset — see
 * App\Services\Projects\ProjectCodeGenerator.
 */
class ProjectCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private User $camSurTc;

    private User $albayTc;

    private AdlAllocation $allocation;

    private Province $camSur;

    private Province $albay;

    private Municipality $cabusao;

    private Municipality $lupi;

    private Municipality $ragay;

    private Municipality $bacacay;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->camSur = Province::create([
            'code' => '051700000',
            'name' => 'Camarines Sur',
            'is_active' => true,
        ]);

        $this->albay = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->camSurTc = User::create([
            'name' => 'Camarines Sur Coordinator',
            'username' => 'cs-coordinator',
            'email' => 'cs-coordinator@example.test',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('password'),
            'assigned_province_id' => $this->camSur->id,
        ]);

        $this->albayTc = User::create([
            'name' => 'Albay Coordinator',
            'username' => 'alb-coordinator',
            'email' => 'alb-coordinator@example.test',
            'position' => 'TUPAD Coordinator',
            'role' => UserRole::TC,
            'is_active' => true,
            'password' => Hash::make('password'),
            'assigned_province_id' => $this->albay->id,
        ]);

        $this->cabusao = Municipality::create([
            'province_id' => $this->camSur->id,
            'name' => 'Cabusao',
            'district' => '1st District',
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->lupi = Municipality::create([
            'province_id' => $this->camSur->id,
            'name' => 'Lupi',
            'district' => '4th District',
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->ragay = Municipality::create([
            'province_id' => $this->camSur->id,
            'name' => 'Ragay',
            'district' => '4th District',
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->bacacay = Municipality::create([
            'province_id' => $this->albay->id,
            'name' => 'Bacacay',
            'district' => '2nd District',
            'is_city' => false,
            'is_active' => true,
        ]);

        $this->barangay = Barangay::create([
            'municipality_id' => $this->cabusao->id,
            'name' => 'Poblacion',
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-CODEGEN-001',
            'grants' => 5_000_000,
            'admin_cost' => 0,
            'total' => 5_000_000,
            'created_by' => $this->camSurTc->id,
        ]);

        $this->allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE',
            'partner' => 'LGU',
            'location' => 'Region V',
            'amount' => 5_000_000,
            'created_by' => $this->camSurTc->id,
        ]);
    }

    private function createProject(
        User $coordinator,
        Municipality $municipality,
        ProjectStatus $status,
    ): Project {
        static $sequence = 0;
        $sequence++;

        return Project::create([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => 'Code Generator Test Project '.$sequence,
            'nature_of_work' => 'Community activity',
            'province_id' => $municipality->province_id,
            'municipality_id' => $municipality->id,
            'barangay_id' => $this->barangay->id,
            'province' => $municipality->province->name,
            'district' => $municipality->district,
            'municipality' => $municipality->name,
            'barangay' => $this->barangay->name,
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 2,
            'beneficiaries_female' => 1,
            'wage_rate' => 455,
            'wages_total' => 18_200,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 100,
            'total_project_cost' => 18_300,
            'status' => $status,
            'created_by' => $coordinator->id,
        ]);
    }

    private function approve(
        User $coordinator,
        Project $project,
        string $approvalDate,
    ): void {
        $this->actingAs($coordinator)
            ->post(
                route('projects.approval.store', $project),
                ['approval_date' => $approvalDate],
            )
            ->assertRedirect();
    }

    #[Test]
    public function projects_have_no_project_code_before_they_are_approved(): void
    {
        foreach ([
            ProjectStatus::ONGOING_PROFILING,
            ProjectStatus::TSSD_EVALUATION,
            ProjectStatus::FOR_COMPLIANCE,
            ProjectStatus::FOR_APPROVAL,
        ] as $status) {
            $project = $this->createProject($this->camSurTc, $this->cabusao, $status);

            $this->assertNull($project->fresh()->approval);
        }
    }

    #[Test]
    public function the_for_approval_page_has_no_manual_project_code_field_and_the_approved_page_shows_the_generated_code_read_only(): void
    {
        $project = $this->createProject($this->camSurTc, $this->cabusao, ProjectStatus::FOR_APPROVAL);

        $this->actingAs($this->camSurTc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('name="project_code"', false);

        $this->approve($this->camSurTc, $project, '2026-09-16');

        $this->actingAs($this->camSurTc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('TUPAD-RO5-CSPO-CABU-26-09-01')
            ->assertDontSee('name="project_code"', false);
    }

    #[Test]
    public function approval_derives_the_project_code_from_the_coordinators_province_and_the_project_municipality(): void
    {
        $project = $this->createProject($this->camSurTc, $this->cabusao, ProjectStatus::FOR_APPROVAL);

        $this->approve($this->camSurTc, $project, '2026-09-16');

        $this->assertSame(
            'TUPAD-RO5-CSPO-CABU-26-09-01',
            $project->fresh()->approval->project_code,
        );
    }

    #[Test]
    public function series_continues_across_different_municipalities_within_the_same_province_and_month(): void
    {
        $cabusaoProject = $this->createProject($this->camSurTc, $this->cabusao, ProjectStatus::FOR_APPROVAL);
        $lupiProject = $this->createProject($this->camSurTc, $this->lupi, ProjectStatus::FOR_APPROVAL);
        $ragayProject = $this->createProject($this->camSurTc, $this->ragay, ProjectStatus::FOR_APPROVAL);

        $this->approve($this->camSurTc, $cabusaoProject, '2026-09-16');
        $this->approve($this->camSurTc, $lupiProject, '2026-09-16');
        $this->approve($this->camSurTc, $ragayProject, '2026-09-16');

        $this->assertSame('TUPAD-RO5-CSPO-CABU-26-09-01', $cabusaoProject->fresh()->approval->project_code);
        $this->assertSame('TUPAD-RO5-CSPO-LUPI-26-09-02', $lupiProject->fresh()->approval->project_code);
        $this->assertSame('TUPAD-RO5-CSPO-RAGY-26-09-03', $ragayProject->fresh()->approval->project_code);
    }

    #[Test]
    public function each_province_has_an_independent_series_in_the_same_month(): void
    {
        $camSurProject = $this->createProject($this->camSurTc, $this->cabusao, ProjectStatus::FOR_APPROVAL);
        $anotherCamSurProject = $this->createProject($this->camSurTc, $this->lupi, ProjectStatus::FOR_APPROVAL);
        $albayProject = $this->createProject($this->albayTc, $this->bacacay, ProjectStatus::FOR_APPROVAL);

        $this->approve($this->camSurTc, $camSurProject, '2026-09-16');
        $this->approve($this->camSurTc, $anotherCamSurProject, '2026-09-16');
        $this->approve($this->albayTc, $albayProject, '2026-09-16');

        $this->assertSame('TUPAD-RO5-CSPO-CABU-26-09-01', $camSurProject->fresh()->approval->project_code);
        $this->assertSame('TUPAD-RO5-CSPO-LUPI-26-09-02', $anotherCamSurProject->fresh()->approval->project_code);

        // Albay's series starts at 01 independently, unaffected by Camarines Sur's count.
        $this->assertSame('TUPAD-RO5-APO-BAC-26-09-01', $albayProject->fresh()->approval->project_code);
    }

    #[Test]
    public function the_series_resets_automatically_when_the_approval_month_changes(): void
    {
        $septemberProject = $this->createProject($this->camSurTc, $this->cabusao, ProjectStatus::FOR_APPROVAL);
        $alsoSeptemberProject = $this->createProject($this->camSurTc, $this->lupi, ProjectStatus::FOR_APPROVAL);
        $octoberProject = $this->createProject($this->camSurTc, $this->ragay, ProjectStatus::FOR_APPROVAL);

        $this->approve($this->camSurTc, $septemberProject, '2026-09-16');
        $this->approve($this->camSurTc, $alsoSeptemberProject, '2026-09-20');
        $this->approve($this->camSurTc, $octoberProject, '2026-10-01');

        $this->assertSame('TUPAD-RO5-CSPO-CABU-26-09-01', $septemberProject->fresh()->approval->project_code);
        $this->assertSame('TUPAD-RO5-CSPO-LUPI-26-09-02', $alsoSeptemberProject->fresh()->approval->project_code);

        // October is a new province/month scope, so the series restarts at 01.
        $this->assertSame('TUPAD-RO5-CSPO-RAGY-26-10-01', $octoberProject->fresh()->approval->project_code);
    }

    #[Test]
    public function the_year_and_month_in_the_code_come_from_the_approval_date_not_the_current_date(): void
    {
        $project = $this->createProject($this->camSurTc, $this->cabusao, ProjectStatus::FOR_APPROVAL);

        $this->approve($this->camSurTc, $project, '2025-01-05');

        $this->assertSame('TUPAD-RO5-CSPO-CABU-25-01-01', $project->fresh()->approval->project_code);
    }
}
