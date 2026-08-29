<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectAcpCheckReleaseAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MajorRevisionPhase12BAcpPaymentCheckReleaseTest extends TestCase
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

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);
        $this->gip = User::factory()->create(['role' => UserRole::GIP, 'is_active' => true]);

        $adl = Adl::create([
            'adl_number' => 'ADL-MR12B-001',
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

    public function test_approved_through_acp_project_moves_to_for_payment_after_approval(): void
    {
        $project = $this->createProject(ProjectStatus::FOR_APPROVAL);

        $this->actingAs($this->tc)
            ->post(route('projects.approval.store', $project), [
                'approval_date' => '2026-08-29',
                'project_code' => 'ACP-ALB-2026-001',
            ])
            ->assertRedirect();

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'to_status' => ProjectStatus::APPROVED->value,
        ]);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'to_status' => ProjectStatus::FOR_PAYMENT->value,
        ]);
    }

    public function test_focal_and_admin_can_access_acp_payment_but_tc_and_gip_cannot(): void
    {
        $project = $this->createProject(ProjectStatus::FOR_PAYMENT);

        $this->actingAs($this->admin)
            ->get(route('acp-payments.show', $project))
            ->assertOk()
            ->assertSee('Record Through ACP Payment');

        $this->actingAs($this->focal)
            ->get(route('acp-payments.show', $project))
            ->assertOk();

        $this->actingAs($this->tc)
            ->get(route('acp-payments.show', $project))
            ->assertForbidden();

        $this->actingAs($this->gip)
            ->get(route('acp-payments.show', $project))
            ->assertForbidden();
    }

    public function test_acp_payment_uses_approved_project_cost_and_ignores_browser_amount(): void
    {
        $project = $this->createProject(ProjectStatus::FOR_PAYMENT, '5000.00');

        $this->actingAs($this->focal)
            ->post(route('projects.acp-payment.store', $project), [
                'payment_date' => '2026-08-29',
                'payee' => 'Authorized ACP Proponent',
                'payment_reference' => 'DV-ACP-001',
                'amount' => '1.00',
            ])
            ->assertRedirect(route('acp-payments.show', $project));

        $this->assertDatabaseHas('project_acp_payments', [
            'project_id' => $project->id,
            'amount' => 5000,
            'payee' => 'Authorized ACP Proponent',
            'recorded_by' => $this->focal->id,
        ]);

        $this->assertSame(
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            $project->fresh()->status,
        );
    }

    public function test_direct_administration_project_cannot_use_acp_payment(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_PAYMENT,
            '5000.00',
            ImplementationMode::DIRECT_ADMINISTRATION,
        );

        $this->actingAs($this->focal)
            ->post(route('projects.acp-payment.store', $project), [
                'payment_date' => '2026-08-29',
                'payee' => 'Wrong Workflow',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('project_acp_payments', 0);
    }

    public function test_check_release_requires_proof_attachment_and_valid_release_date(): void
    {
        $project = $this->createProject(ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT);
        $this->createPayment($project);

        $this->actingAs($this->focal)
            ->post(route('projects.acp-check-release.store', $project), [
                'check_number' => 'ACP-CHECK-001',
                'check_date' => '2026-08-29',
                'released_date' => '2026-08-28',
                'released_to' => 'ACP Proponent',
            ])
            ->assertSessionHasErrors(['released_date', 'attachments']);

        $this->assertDatabaseCount('project_acp_check_releases', 0);
        $this->assertSame(
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            $project->fresh()->status,
        );
    }

    public function test_check_release_uses_payment_amount_stores_attachments_and_moves_to_for_implementation(): void
    {
        Storage::fake('local');

        $project = $this->createProject(ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT, '5000.00');
        $this->createPayment($project, '5000.00');

        $this->actingAs($this->admin)
            ->post(route('projects.acp-check-release.store', $project), [
                'check_number' => ' acp-check-002 ',
                'check_date' => '2026-08-29',
                'released_date' => '2026-08-29',
                'released_to' => 'Authorized ACP Proponent',
                'amount' => '1.00',
                'attachments' => [
                    UploadedFile::fake()->create('acknowledgement.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('check-photo.jpg', 80, 'image/jpeg'),
                ],
            ])
            ->assertRedirect(route('acp-payments.show', $project));

        $this->assertDatabaseHas('project_acp_check_releases', [
            'project_id' => $project->id,
            'check_number' => 'ACP-CHECK-002',
            'amount' => 5000,
            'released_to' => 'Authorized ACP Proponent',
            'recorded_by' => $this->admin->id,
        ]);
        $this->assertDatabaseCount('project_acp_check_release_attachments', 2);
        $this->assertSame(ProjectStatus::FOR_IMPLEMENTATION, $project->fresh()->status);

        foreach (ProjectAcpCheckReleaseAttachment::all() as $attachment) {
            Storage::disk('local')->assertExists($attachment->attachment_path);
        }
    }

    public function test_check_number_is_unique_after_normalization(): void
    {
        Storage::fake('local');

        $first = $this->createProject(ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT);
        $this->createPayment($first);
        $this->releaseCheck($first, 'ACP-UNIQUE-001');

        $second = $this->createProject(ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT);
        $this->createPayment($second);

        $this->actingAs($this->focal)
            ->post(route('projects.acp-check-release.store', $second), [
                'check_number' => ' acp-unique-001 ',
                'check_date' => '2026-08-29',
                'released_date' => '2026-08-29',
                'released_to' => 'Second Proponent',
                'attachments' => [UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf')],
            ])
            ->assertSessionHasErrors('check_number');

        $this->assertDatabaseCount('project_acp_check_releases', 1);
    }

    public function test_check_release_attachment_download_is_scoped_and_role_protected(): void
    {
        Storage::fake('local');

        $project = $this->createProject(ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT);
        $this->createPayment($project);
        $this->releaseCheck($project, 'ACP-DOWNLOAD-001');
        $attachment = ProjectAcpCheckReleaseAttachment::firstOrFail();

        $this->actingAs($this->focal)
            ->get(route('projects.acp-check-release.attachments.download', [$project, $attachment]))
            ->assertOk();

        $this->actingAs($this->tc)
            ->get(route('projects.acp-check-release.attachments.download', [$project, $attachment]))
            ->assertForbidden();

        $other = $this->createProject(ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT);
        $this->actingAs($this->admin)
            ->get(route('projects.acp-check-release.attachments.download', [$other, $attachment]))
            ->assertNotFound();
    }

    private function createProject(
        ProjectStatus $status,
        string $totalProjectCost = '5000.00',
        ImplementationMode $mode = ImplementationMode::THROUGH_ACP,
    ): Project {
        $this->sequence++;

        return Project::create([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-08-29',
            'project_title' => 'Phase 12B ACP Project '.$this->sequence,
            'nature_of_work' => 'Community livelihood support',
            'fund_sponsor' => 'DOLE RO V',
            'partner' => 'ACP Proponent '.$this->sequence,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => $mode,
            'number_of_days' => 10,
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

    private function createPayment(Project $project, string $amount = '5000.00'): void
    {
        $project->acpPayment()->create([
            'amount' => $amount,
            'payment_date' => '2026-08-29',
            'payee' => 'ACP Proponent',
            'payment_reference' => 'DV-TEST',
            'recorded_by' => $this->focal->id,
        ]);
    }

    private function releaseCheck(Project $project, string $checkNumber): void
    {
        $this->actingAs($this->focal)
            ->post(route('projects.acp-check-release.store', $project), [
                'check_number' => $checkNumber,
                'check_date' => '2026-08-29',
                'released_date' => '2026-08-29',
                'released_to' => 'ACP Proponent',
                'attachments' => [
                    UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('acp-payments.show', $project));
    }
}
