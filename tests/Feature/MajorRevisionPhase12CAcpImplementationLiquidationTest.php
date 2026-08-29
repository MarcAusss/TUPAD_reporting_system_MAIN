<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectAcpLiquidationAttachment;
use App\Models\User;
use App\Services\Projects\ProjectAcpLiquidationService;
use App\Services\Projects\ProjectStatusEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MajorRevisionPhase12CAcpImplementationLiquidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $focal;
    private User $tc;
    private User $gip;
    private AdlAllocation $allocation;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-29 12:00:00');

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);
        $this->gip = User::factory()->create(['role' => UserRole::GIP, 'is_active' => true]);

        $adl = Adl::create([
            'adl_number' => 'ADL-MR12C-001',
            'grants' => 500000,
            'admin_cost' => 0,
            'total' => 500000,
            'created_by' => $this->focal->id,
        ]);

        $this->allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE RO V',
            'partner' => 'ACP Proponent',
            'location' => 'Albay',
            'amount' => 500000,
            'created_by' => $this->focal->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_and_tc_can_access_acp_implementation_but_focal_and_gip_cannot(): void
    {
        $project = $this->createAcpProject(ProjectStatus::FOR_IMPLEMENTATION);
        $this->createCheckRelease($project);

        $this->actingAs($this->admin)
            ->get(route('acp-implementation.show', $project))
            ->assertOk()
            ->assertSee('Set Through ACP Implementation Period');

        $this->actingAs($this->tc)
            ->get(route('acp-implementation.show', $project))
            ->assertOk();

        $this->actingAs($this->focal)
            ->get(route('acp-implementation.show', $project))
            ->assertForbidden();

        $this->actingAs($this->gip)
            ->get(route('acp-implementation.show', $project))
            ->assertForbidden();
    }

    public function test_acp_implementation_uses_project_duration_ignores_browser_end_date_and_moves_to_ongoing(): void
    {
        $project = $this->createAcpProject(ProjectStatus::FOR_IMPLEMENTATION, '5000.00', 10);
        $this->createCheckRelease($project, '2026-08-20');

        $this->actingAs($this->tc)
            ->post(route('projects.acp-implementation.store', $project), [
                'start_date' => '2026-08-25',
                'end_date' => '2030-01-01',
                'remarks' => 'ACP implementation scheduled.',
            ])
            ->assertRedirect(route('acp-implementation.show', $project));

        $project->refresh();

        $this->assertSame('2026-08-25', $project->implementation->start_date->toDateString());
        $this->assertSame('2026-09-04', $project->implementation->end_date->toDateString());
        $this->assertSame(ProjectStatus::ONGOING_IMPLEMENTATION, $project->status);
    }

    public function test_acp_implementation_cannot_start_before_check_release(): void
    {
        $project = $this->createAcpProject(ProjectStatus::FOR_IMPLEMENTATION);
        $this->createCheckRelease($project, '2026-08-29');

        $this->actingAs($this->tc)
            ->post(route('projects.acp-implementation.store', $project), [
                'start_date' => '2026-08-28',
            ])
            ->assertSessionHasErrors('start_date');

        $this->assertDatabaseMissing('project_implementations', [
            'project_id' => $project->id,
        ]);
    }

    public function test_direct_administration_cannot_use_acp_implementation(): void
    {
        $project = $this->createAcpProject(
            ProjectStatus::FOR_IMPLEMENTATION,
            '5000.00',
            10,
            ImplementationMode::DIRECT_ADMINISTRATION,
        );

        $this->actingAs($this->admin)
            ->post(route('projects.acp-implementation.store', $project), [
                'start_date' => '2026-08-29',
            ])
            ->assertForbidden();
    }

    public function test_status_engine_moves_acp_from_ongoing_implementation_to_for_liquidation_on_end_date(): void
    {
        $project = $this->createAcpProject(ProjectStatus::ONGOING_IMPLEMENTATION);
        $this->createCheckRelease($project, '2026-08-10');
        $project->implementation()->create([
            'start_date' => '2026-08-19',
            'end_date' => '2026-08-29',
            'recorded_by' => $this->tc->id,
        ]);

        $status = app(ProjectStatusEngine::class)->synchronize(
            $project,
            actorId: $this->tc->id,
            today: CarbonImmutable::parse('2026-08-29', 'Asia/Manila'),
        );

        $this->assertSame(ProjectStatus::FOR_LIQUIDATION, $status);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'to_status' => ProjectStatus::FOR_LIQUIDATION->value,
        ]);
    }

    public function test_admin_and_focal_can_access_liquidation_but_tc_and_gip_cannot(): void
    {
        $project = $this->readyForLiquidation();

        $this->actingAs($this->admin)
            ->get(route('acp-liquidations.show', $project))
            ->assertOk()
            ->assertSee('Liquidation Summary');

        $this->actingAs($this->focal)
            ->get(route('acp-liquidations.show', $project))
            ->assertOk();

        $this->actingAs($this->tc)
            ->get(route('acp-liquidations.show', $project))
            ->assertForbidden();

        $this->actingAs($this->gip)
            ->get(route('acp-liquidations.show', $project))
            ->assertForbidden();
    }

    public function test_partial_liquidation_is_audited_and_moves_project_to_partially_liquidated(): void
    {
        Storage::fake('local');
        $project = $this->readyForLiquidation('5000.00');

        $this->actingAs($this->focal)
            ->post(route('projects.acp-liquidations.store', $project), [
                'liquidation_date' => '2026-08-29',
                'amount' => '2000.00',
                'liquidation_reference' => 'LIQ-001',
                'attachments' => [
                    UploadedFile::fake()->create('liquidation-report.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('acp-liquidations.show', $project));

        $this->assertDatabaseHas('project_acp_liquidations', [
            'project_id' => $project->id,
            'amount' => 2000,
            'liquidation_reference' => 'LIQ-001',
            'recorded_by' => $this->focal->id,
        ]);
        $this->assertSame(ProjectStatus::PARTIALLY_LIQUIDATED, $project->fresh()->status);

        $summary = app(ProjectAcpLiquidationService::class)->summary($project->fresh());
        $this->assertSame(200000, $summary['liquidated_cents']);
        $this->assertSame(300000, $summary['remaining_cents']);
        $this->assertSame(40, $summary['progress_percent']);

        $attachment = ProjectAcpLiquidationAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->attachment_path);
    }

    public function test_liquidation_cannot_exceed_remaining_balance(): void
    {
        Storage::fake('local');
        $project = $this->readyForLiquidation('5000.00');
        $this->recordLiquidation($project, '2000.00', 'LIQ-PARTIAL');

        $this->actingAs($this->admin)
            ->post(route('projects.acp-liquidations.store', $project), [
                'liquidation_date' => '2026-08-29',
                'amount' => '3000.01',
                'attachments' => [
                    UploadedFile::fake()->create('too-much.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('project_acp_liquidations', 1);
        $this->assertSame(ProjectStatus::PARTIALLY_LIQUIDATED, $project->fresh()->status);
    }

    public function test_final_liquidation_completes_through_acp_project(): void
    {
        Storage::fake('local');
        $project = $this->readyForLiquidation('5000.00');
        $this->recordLiquidation($project, '2000.00', 'LIQ-001');
        $this->recordLiquidation($project->fresh(), '3000.00', 'LIQ-002');

        $this->assertSame(ProjectStatus::COMPLETED, $project->fresh()->status);

        $summary = app(ProjectAcpLiquidationService::class)->summary($project->fresh());
        $this->assertSame(500000, $summary['liquidated_cents']);
        $this->assertSame(0, $summary['remaining_cents']);
        $this->assertTrue($summary['is_fully_liquidated']);
    }

    public function test_liquidation_date_cannot_precede_implementation_end_date(): void
    {
        Storage::fake('local');
        $project = $this->readyForLiquidation('5000.00', '2026-08-29');

        $this->actingAs($this->focal)
            ->post(route('projects.acp-liquidations.store', $project), [
                'liquidation_date' => '2026-08-28',
                'amount' => '1000.00',
                'attachments' => [
                    UploadedFile::fake()->create('early.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('liquidation_date');

        $this->assertDatabaseCount('project_acp_liquidations', 0);
    }

    public function test_direct_administration_cannot_use_acp_liquidation(): void
    {
        $project = $this->createAcpProject(
            ProjectStatus::FOR_LIQUIDATION,
            '5000.00',
            10,
            ImplementationMode::DIRECT_ADMINISTRATION,
        );

        $this->actingAs($this->admin)
            ->get(route('acp-liquidations.show', $project))
            ->assertForbidden();
    }

    public function test_liquidation_attachment_download_is_scoped_and_financial_role_protected(): void
    {
        Storage::fake('local');
        $project = $this->readyForLiquidation();
        $this->recordLiquidation($project, '1000.00', 'LIQ-DOWNLOAD');
        $attachment = ProjectAcpLiquidationAttachment::firstOrFail();

        $this->actingAs($this->focal)
            ->get(route('projects.acp-liquidations.attachments.download', [$project, $attachment]))
            ->assertOk();

        $this->actingAs($this->tc)
            ->get(route('projects.acp-liquidations.attachments.download', [$project, $attachment]))
            ->assertForbidden();

        $other = $this->readyForLiquidation();

        $this->actingAs($this->admin)
            ->get(route('projects.acp-liquidations.attachments.download', [$other, $attachment]))
            ->assertNotFound();
    }

    private function createAcpProject(
        ProjectStatus $status,
        string $totalProjectCost = '5000.00',
        int $numberOfDays = 10,
        ImplementationMode $mode = ImplementationMode::THROUGH_ACP,
    ): Project {
        $this->sequence++;

        return Project::create([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Phase 12C ACP Project '.$this->sequence,
            'nature_of_work' => 'Community livelihood support',
            'fund_sponsor' => 'DOLE RO V',
            'partner' => 'ACP Proponent '.$this->sequence,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => $mode,
            'number_of_days' => $numberOfDays,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => 400,
            'wages_total' => 4000,
            'ppe_total' => 500,
            'insurance_rate' => 50,
            'insurance_beneficiaries' => 10,
            'insurance_total' => 500,
            'total_project_cost' => $totalProjectCost,
            'status' => $status,
            'created_by' => $this->tc->id,
        ]);
    }

    private function createCheckRelease(
        Project $project,
        string $releasedDate = '2026-08-20',
        string $amount = '5000.00',
    ): void {
        $project->acpCheckRelease()->create([
            'check_number' => 'ACP-12C-'.str_pad((string) $project->id, 4, '0', STR_PAD_LEFT),
            'check_date' => $releasedDate,
            'amount' => $amount,
            'released_date' => $releasedDate,
            'released_to' => 'ACP Proponent',
            'recorded_by' => $this->focal->id,
        ]);
    }

    private function readyForLiquidation(
        string $amount = '5000.00',
        string $endDate = '2026-08-29',
    ): Project {
        $project = $this->createAcpProject(ProjectStatus::FOR_LIQUIDATION, $amount);
        $this->createCheckRelease($project, '2026-08-10', $amount);
        $project->implementation()->create([
            'start_date' => '2026-08-19',
            'end_date' => $endDate,
            'recorded_by' => $this->tc->id,
        ]);

        return $project->fresh();
    }

    private function recordLiquidation(
        Project $project,
        string $amount,
        string $reference,
    ): void {
        $this->actingAs($this->focal)
            ->post(route('projects.acp-liquidations.store', $project), [
                'liquidation_date' => '2026-08-29',
                'amount' => $amount,
                'liquidation_reference' => $reference,
                'attachments' => [
                    UploadedFile::fake()->create($reference.'.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('acp-liquidations.show', $project));
    }
}
