<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\GpaiCashAdvance;
use App\Models\GpaiEnrollment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GsisGpaiEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $gsis;
    private User $tc;
    private AdlAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->gsis = User::factory()->create([
            'role' => UserRole::GSIS,
            'is_active' => true,
            'name' => 'Gina GSIS Officer',
            'position' => 'GSIS Enrollment Officer',
        ]);
        $this->tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);

        $adl = Adl::create([
            'adl_number' => '2026-02-0093',
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->admin->id,
        ]);

        $this->allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_create_a_gsis_enrollment_officer_account(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('GSIS Enrollment Officer');

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => 'Isaac GSIS',
                'username' => 'isaac.gsis',
                'role' => UserRole::GSIS->value,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $account = User::query()->where('username', 'isaac.gsis')->firstOrFail();
        $this->assertTrue($account->isGsis());
        $this->assertNull($account->assigned_province_id);
        $this->assertSame('GSIS Enrollment Officer', $account->roleLabel());
    }

    public function test_gsis_officer_has_only_the_four_gsis_pages(): void
    {
        $project = $this->project('Oas Project', 51);

        $this->actingAs($this->gsis)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Projects Awaiting GPAI Enrollment')
            ->assertSee('Oas Project')
            ->assertSee(route('gsis.status'), false)
            ->assertSee(route('gsis.cash-advances'), false)
            ->assertSee(route('gsis.summary'), false)
            ->assertSee('Yearly Status')
            ->assertSee('CA (Balance Calculation)')
            ->assertSee('Yearly Summary')
            ->assertDontSee('Project Registry')
            ->assertSee('data-global-search', false);

        foreach (['gsis.status', 'gsis.cash-advances', 'gsis.summary', 'gsis.enrollments.create'] as $route) {
            $this->actingAs($this->gsis)->get(route($route))->assertOk();
        }

        $this->actingAs($this->gsis)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($this->gsis)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($this->gsis)->get(route('search.index', ['q' => 'Oas']))->assertOk()->assertSee('Oas Project')->assertSee('Enroll Project');
        $this->actingAs($this->gsis)->get(route('users.index'))->assertForbidden();
        $this->actingAs($this->gsis)->get(route('adl.index'))->assertForbidden();

        // Other roles cannot open the GSIS pages.
        $this->actingAs($this->tc)->get(route('gsis.status'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('gsis.enrollments.create'))->assertForbidden();
    }

    public function test_projects_with_an_insurance_budget_show_from_evaluation_until_work_starts(): void
    {
        $this->project('Approved Project', 51);
        $this->project('No Insurance Project', 0);
        $this->project('Evaluation Project', 40, ['status' => ProjectStatus::TSSD_EVALUATION]);
        $this->project('Compliance Project', 35, ['status' => ProjectStatus::FOR_COMPLIANCE]);
        $this->project('For Approval Project', 33, ['status' => ProjectStatus::FOR_APPROVAL]);
        $this->project('ACP Evaluation Project', 30, ['implementation_mode' => 'through_acp', 'status' => ProjectStatus::TSSD_EVALUATION]);
        $this->project('ACP Check Release Project', 25, [
            'implementation_mode' => 'through_acp',
            'status' => ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
        ]);
        $this->project('ACP For Implementation Project', 20, [
            'implementation_mode' => 'through_acp',
            'status' => ProjectStatus::FOR_IMPLEMENTATION,
        ]);
        $this->project('Ongoing Project', 18, ['status' => ProjectStatus::ONGOING_IMPLEMENTATION]);
        $this->project('Direct For Payment Project', 16, ['status' => ProjectStatus::FOR_PAYMENT]);

        $this->actingAs($this->gsis)
            ->get(route('gsis.enrollments.create'))
            ->assertOk()
            ->assertSee('Approved Project')
            ->assertSee('Evaluation Project')
            ->assertSee('Compliance Project')
            ->assertSee('For Approval Project')
            ->assertSee('ACP Evaluation Project')
            ->assertSee('ACP Check Release Project')
            ->assertSee('ACP For Implementation Project')
            ->assertDontSee('No Insurance Project')
            ->assertDontSee('Ongoing Project')
            ->assertDontSee('Direct For Payment Project')
            // Live search over project, location, ADL No., status.
            ->assertSee('id="gpaiSearch"', false)
            ->assertSee('data-search="approved project legazpi city albay 2026-02-0093 approved direct administration"', false)
            ->assertSee('No projects match your search.');
    }

    public function test_enrolling_before_approval_keeps_the_project_in_evaluation(): void
    {
        $project = $this->project('Evaluation Project', 40, ['status' => ProjectStatus::TSSD_EVALUATION]);

        $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'project_ids' => [$project->id],
                'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
                'transmittal_date' => '2026-04-14',
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame(ProjectStatus::TSSD_EVALUATION, $project->status);
        $this->assertNotNull($project->insuranceEnrollment);
    }

    public function test_bulk_voucher_enrollment_creates_the_transmittal_and_records_project_insurance(): void
    {
        $oas = $this->project('Oas Project', 51, ['municipality' => 'Oas']);
        $camalig = $this->project('Camalig Project', 48, ['municipality' => 'Camalig']);

        $response = $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'project_ids' => [$oas->id, $camalig->id],
                'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
                'transmittal_date' => '2026-04-14',
            ]);

        $enrollment = GpaiEnrollment::query()->firstOrFail();
        $response->assertRedirect(route('gsis.enrollments.edit', $enrollment));

        $this->assertSame('GPAI V26-0001', $enrollment->gpai_number);
        $this->assertSame(2026, $enrollment->fiscal_year);
        $this->assertSame('2026-04-15', $enrollment->coverage_start->toDateString());
        $this->assertSame('2027-04-14', $enrollment->coverage_end->toDateString());
        $this->assertCount(2, $enrollment->items);
        $this->assertSame(99, $enrollment->beneficiaries());
        $this->assertSame(495000, $enrollment->premiumCents());
        $this->assertSame(20000, $enrollment->docStampCents());
        $this->assertSame(515000, $enrollment->totalCents());
        $this->assertSame('2026-02-0093', $enrollment->items->first()->adl_number);
        $this->assertSame('DOLE TUPAD Program for 51 beneficiaries in Oas, Albay', $enrollment->items->first()->particulars);

        foreach ([$oas, $camalig] as $project) {
            $insurance = $project->fresh()->insuranceEnrollment;
            $this->assertNotNull($insurance);
            $this->assertSame('2026-04-14', $insurance->date_enrolled->toDateString());
            $this->assertSame('voucher', $insurance->payment_mode);
            $this->assertSame($this->gsis->id, $insurance->recorded_by);
        }

        // Enrolled projects leave the awaiting list; numbering continues.
        $this->actingAs($this->gsis)
            ->get(route('gsis.enrollments.create'))
            ->assertDontSee('Oas Project');

        $third = $this->project('Tabaco Project', 10);
        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$third->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => '2026-04-20',
        ]);

        $second = GpaiEnrollment::query()->latest('id')->firstOrFail();
        $this->assertSame('GPAI V26-0002', $second->gpai_number);
        // Doc. stamp default: 20% of a 500.00 premium.
        $this->assertSame(10000, $second->docStampCents());
    }

    public function test_an_enrolled_project_cannot_be_enrolled_twice(): void
    {
        $project = $this->project('Oas Project', 51);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$project->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => '2026-04-14',
        ])->assertRedirect();

        $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'project_ids' => [$project->id],
                'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
                'transmittal_date' => '2026-04-15',
            ])
            ->assertSessionHasErrors('project_ids');

        $this->assertSame(1, GpaiEnrollment::query()->count());
    }

    public function test_cash_advance_enrollment_is_charged_against_the_ca_balance(): void
    {
        $this->actingAs($this->gsis)
            ->post(route('gsis.cash-advances.store'), [
                'cash_advance_date' => '2026-03-08',
                'amount' => '3000.00',
            ])
            ->assertRedirect(route('gsis.cash-advances', ['fiscal_year' => 2026]));

        $cashAdvance = GpaiCashAdvance::query()->firstOrFail();
        $small = $this->project('Small Project', 20);
        $large = $this->project('Large Project', 100);

        // Cash advance is required for CA mode.
        $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'project_ids' => [$small->id],
                'payment_mode' => GpaiEnrollment::MODE_CASH_ADVANCE,
                'transmittal_date' => '2026-03-11',
            ])
            ->assertSessionHasErrors('gpai_cash_advance_id');

        // 100 x 50 = 5,000 + 200 doc stamp exceeds the 3,000 balance.
        $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'project_ids' => [$large->id],
                'payment_mode' => GpaiEnrollment::MODE_CASH_ADVANCE,
                'transmittal_date' => '2026-03-11',
                'gpai_cash_advance_id' => $cashAdvance->id,
            ])
            ->assertSessionHasErrors('gpai_cash_advance_id');

        // 20 x 50 = 1,000 + 200 doc stamp fits.
        $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'project_ids' => [$small->id],
                'payment_mode' => GpaiEnrollment::MODE_CASH_ADVANCE,
                'transmittal_date' => '2026-03-11',
                'gpai_cash_advance_id' => $cashAdvance->id,
            ])
            ->assertRedirect();

        $enrollment = GpaiEnrollment::query()->firstOrFail();
        $this->assertSame('GPAI CA26-001', $enrollment->gpai_number);
        $this->assertSame('ca', $small->fresh()->insuranceEnrollment->payment_mode);
        $this->assertSame(180000, $cashAdvance->fresh()->balanceCents());

        $this->actingAs($this->gsis)
            ->get(route('gsis.cash-advances', ['fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('CA (March 08, 2026)')
            ->assertSee('GPAI CA26-001')
            ->assertSee('₱1,200.00')
            ->assertSee('₱1,800.00');
    }

    public function test_progress_updates_complete_the_transmittal_and_copy_or_and_policy_to_projects(): void
    {
        $project = $this->project('Oas Project', 51);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$project->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => '2026-04-14',
        ]);
        $enrollment = GpaiEnrollment::query()->firstOrFail();
        $this->assertSame('prepared', $enrollment->stage());

        $this->actingAs($this->gsis)
            ->get(route('gsis.enrollments.edit', $enrollment))
            ->assertOk()
            ->assertSee('GSIS Processing')
            ->assertSee('Transmittal Prepared')
            ->assertSee('DOLE TUPAD Program for 51 beneficiaries in Legazpi City, Albay')
            ->assertSee('Print Transmittal');

        $this->actingAs($this->gsis)
            ->put(route('gsis.enrollments.update', $enrollment), [
                'billing_emailed_at' => '2026-04-14T09:38',
                'soa_received_at' => '2026-04-14T09:45',
                'doc_stamp' => '200.00',
            ])
            // Saving lands on the next step to record.
            ->assertRedirect(route('gsis.enrollments.edit', $enrollment).'#step-routing_released_on');
        $this->assertSame('for_routing', $enrollment->fresh()->stage());

        $this->actingAs($this->gsis)
            ->put(route('gsis.enrollments.update', $enrollment), [
                'billing_emailed_at' => '2026-04-14T09:38',
                'soa_received_at' => '2026-04-14T09:45',
                'routing_released_on' => '2026-04-14',
                'or_policy_requested_at' => '2026-04-23T15:40',
                'or_policy_received_on' => '2026-05-05',
                'billing_status' => 'signed_completed',
                'or_number' => '5600010227',
                'policy_id' => 'GPAI V26-007',
                'doc_stamp' => '200.00',
            ])
            ->assertSessionHas('success', 'GPAI V26-0001 is complete. The TUPAD Coordinators were notified that the OR and Policy were received.');

        $this->assertTrue($enrollment->fresh()->isComplete());

        // Distributed to PCs is recorded automatically, with time, when the TCs are notified.
        $this->assertNotNull($enrollment->fresh()->distributed_on);
        $this->assertTrue($enrollment->fresh()->distributed_on->isToday());
        $this->assertNull($enrollment->fresh()->nextStep());

        $this->actingAs($this->gsis)->get(route('gsis.enrollments.edit', $enrollment))
            ->assertOk()
            ->assertSee('data-wizard', false)
            ->assertSee('Next →')
            ->assertSee('The TUPAD Coordinators were notified through the system');
        $insurance = $project->fresh()->insuranceEnrollment;
        $this->assertSame('5600010227', $insurance->or_number);
        $this->assertSame('GPAI V26-007', $insurance->policy_number);

        $this->actingAs($this->gsis)
            ->get(route('gsis.status', ['fiscal_year' => 2026, 'table' => 'ops']))
            ->assertOk()
            ->assertSee('GPAI V26-0001')
            ->assertSee('5600010227')
            ->assertSee('Complete');
    }

    public function test_officer_can_continue_a_transmittal_at_its_next_step_from_the_dashboard_and_status(): void
    {
        $project = $this->project('Oas Project', 51);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$project->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => now()->toDateString(),
        ]);
        $enrollment = GpaiEnrollment::query()->firstOrFail();
        $firstStep = route('gsis.enrollments.edit', $enrollment).'#step-billing_emailed_at';

        $this->assertSame('billing_emailed_at', $enrollment->nextStep());

        $this->actingAs($this->gsis)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Transmittals In Process')
            ->assertSee($firstStep, false)
            ->assertSee('Continue →');

        $this->actingAs($this->gsis)
            ->get(route('gsis.status', ['stage' => 'prepared']))
            ->assertOk()
            ->assertSee('data-row-href="'.$firstStep.'"', false)
            ->assertSee('Continue →')
            ->assertSee('Emailed for Issuance of Insurance Bill');

        $this->actingAs($this->gsis)
            ->get(route('gsis.enrollments.edit', $enrollment))
            ->assertOk()
            ->assertSee('Next step')
            ->assertSee('id="step-billing_emailed_at"', false);

        // After recording billing and SOA, the next step is routing.
        $enrollment->forceFill(['billing_emailed_at' => now(), 'soa_received_at' => now()])->save();
        $this->assertSame('routing_released_on', $enrollment->fresh()->nextStep());

        // A skipped step is not asked for again once a later one is recorded.
        $enrollment->forceFill(['or_policy_requested_at' => now()])->save();
        $this->assertSame('or_policy_received_on', $enrollment->fresh()->nextStep());

        $enrollment->forceFill(['or_policy_received_on' => now(), 'distributed_on' => now()])->save();
        $this->assertNull($enrollment->fresh()->nextStep());

        // Completed transmittals leave the dashboard's in-process list.
        $this->actingAs($this->gsis)
            ->get(route('dashboard'))
            ->assertSee('No transmittals in process');
    }

    public function test_yearly_status_is_split_into_billing_and_on_call_payment_slip_tables(): void
    {
        $voucherProject = $this->project('Oas Project', 51);
        $caProject = $this->project('Ligao Project', 20);
        $cashAdvance = GpaiCashAdvance::query()->create([
            'fiscal_year' => (int) now()->year,
            'cash_advance_date' => now()->toDateString(),
            'amount' => 100000,
            'created_by' => $this->gsis->id,
        ]);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$voucherProject->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => now()->toDateString(),
        ]);
        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$caProject->id],
            'payment_mode' => GpaiEnrollment::MODE_CASH_ADVANCE,
            'gpai_cash_advance_id' => $cashAdvance->id,
            'transmittal_date' => now()->toDateString(),
        ]);
        $voucher = GpaiEnrollment::query()->where('payment_mode', GpaiEnrollment::MODE_VOUCHER)->firstOrFail();
        $ca = GpaiEnrollment::query()->where('payment_mode', GpaiEnrollment::MODE_CASH_ADVANCE)->firstOrFail();

        // Table 1 lists every transmittal with a status dropdown; Table 2 is empty until signed.
        $this->actingAs($this->gsis)->get(route('gsis.status'))
            ->assertOk()
            ->assertSee('1 · Billing &amp; Routing', false)
            ->assertSee('For Transmittal Signing')
            ->assertSee($voucher->gpai_number)
            ->assertSee($ca->gpai_number);
        $this->actingAs($this->gsis)->get(route('gsis.status', ['table' => 'ops']))
            ->assertOk()
            ->assertDontSee($voucher->gpai_number)
            ->assertSee('No Signed/Completed transmittals');

        // Voucher / CA filter.
        $this->actingAs($this->gsis)->get(route('gsis.status', ['mode' => 'ca']))
            ->assertSee($ca->gpai_number)
            ->assertDontSee($voucher->gpai_number);

        // Inline status and remarks; Signed/Completed moves it to Table 2.
        $this->actingAs($this->gsis)->from(route('gsis.status'))
            ->patch(route('gsis.enrollments.status', $voucher), ['billing_status' => 'signed_completed'])
            ->assertRedirect(route('gsis.status'));
        $this->actingAs($this->gsis)
            ->patch(route('gsis.enrollments.status', $voucher), ['remarks' => 'Signed by RD'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->gsis)
            ->patch(route('gsis.enrollments.status', $voucher), ['billing_status' => 'not-a-status'])
            ->assertSessionHasErrors('billing_status');

        // Table 1 remark: received via IMSD or Email.
        $this->actingAs($this->gsis)->get(route('gsis.status'))
            ->assertSee('name="billing_received_via"', false)
            ->assertSee('Received via IMSD');
        $this->actingAs($this->gsis)
            ->patch(route('gsis.enrollments.status', $voucher), ['billing_received_via' => 'email'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->gsis)
            ->patch(route('gsis.enrollments.status', $voucher), ['billing_received_via' => 'fax'])
            ->assertSessionHasErrors('billing_received_via');
        $this->actingAs($this->admin)->get(route('gpai-status.index'))
            ->assertSee('Received via Email');

        $voucher->refresh();
        $this->assertSame('email', $voucher->billing_received_via);
        $this->assertTrue($voucher->isBillingSigned());
        $this->assertSame('Signed by RD', $voucher->remarks);

        $this->actingAs($this->gsis)->get(route('gsis.status', ['table' => 'ops']))
            ->assertSee($voucher->gpai_number)
            ->assertDontSee($ca->gpai_number)
            ->assertSee('For OR only');

        // On-call payment slip details, with a Google Drive link, from the transmittal wizard.
        $this->actingAs($this->gsis)->put(route('gsis.enrollments.update', $voucher), [
            'doc_stamp' => $voucher->doc_stamp,
            'billing_status' => 'signed_completed',
            'remarks' => 'Signed by RD',
            'ops_status' => 'for_or_only',
            'ops_notes' => 'Policy received, OR to follow',
            'ops_document_url' => 'https://drive.google.com/drive/folders/abc123',
        ])->assertSessionHasNoErrors();

        $voucher->refresh();
        $this->assertSame('for_or_only', $voucher->ops_status);
        $this->assertSame('https://drive.google.com/drive/folders/abc123', $voucher->ops_document_url);
        $this->assertFalse($voucher->isComplete());

        // Only Google Drive / Docs links are accepted (inline too).
        $this->actingAs($this->gsis)
            ->patch(route('gsis.enrollments.status', $voucher), ['ops_document_url' => 'https://example.com/file.pdf'])
            ->assertSessionHasErrors('ops_document_url');
        $this->actingAs($this->gsis)
            ->patch(route('gsis.enrollments.status', $voucher), ['ops_document_url' => 'https://docs.google.com/spreadsheets/d/xyz'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://docs.google.com/spreadsheets/d/xyz', $voucher->fresh()->ops_document_url);

        // Read-only viewers see the status as a label, not a dropdown.
        $this->actingAs($this->tc)->get(route('gpai-status.index', ['table' => 'ops']))
            ->assertOk()
            ->assertSee('For OR only')
            ->assertSee('https://docs.google.com/spreadsheets/d/xyz', false)
            ->assertDontSee(route('gsis.enrollments.status', $voucher), false);

        // Other accounts see one row per project, each with its GPAI No. and payment method.
        $this->actingAs($this->admin)->get(route('gpai-status.index'))
            ->assertOk()
            ->assertSeeInOrder([$voucher->gpai_number, 'Voucher', 'Oas Project'])
            ->assertSeeInOrder([$ca->gpai_number, 'Cash Advance', 'Ligao Project'])
            ->assertSee('Total (2 projects)');

        // "Complete" completes the transmittal (OR and Policy received today).
        $this->actingAs($this->gsis)->patch(route('gsis.enrollments.status', $voucher), ['ops_status' => 'complete']);
        $this->assertTrue($voucher->fresh()->isComplete());
    }

    public function test_enrolled_projects_report_splits_voucher_and_ca_and_shows_project_codes_live(): void
    {
        $voucherProject = $this->project('Oas Project', 51);
        $caProject = $this->project('Ligao Project', 20);
        $cashAdvance = GpaiCashAdvance::query()->create([
            'fiscal_year' => 2026,
            'cash_advance_date' => '2026-01-05',
            'amount' => 100000,
            'created_by' => $this->gsis->id,
        ]);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$voucherProject->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => '2026-02-10',
        ]);
        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$caProject->id],
            'payment_mode' => GpaiEnrollment::MODE_CASH_ADVANCE,
            'gpai_cash_advance_id' => $cashAdvance->id,
            'transmittal_date' => '2026-08-03',
        ]);

        // Sidebar link and the annual report: Voucher, CA, Overall; amount = beneficiaries x ₱50.
        $this->actingAs($this->gsis)->get(route('dashboard'))->assertSee(route('gsis.report'), false);

        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('data-report-section="voucher"', false)
            ->assertSee('data-report-section="ca"', false)
            ->assertSee('Oas Project')
            ->assertSee('Ligao Project')
            ->assertSee('₱2,550.00')   // 51 x 50
            ->assertSee('₱1,000.00')   // 20 x 50
            ->assertSee('₱3,550.00')   // overall
            ->assertSee('Not yet assigned')
            ->assertSee('data-enrollment-id=', false)
            ->assertSee('id="enrollmentInfo"', false);

        // The documentary stamp is added once per transmittal, for both Voucher and CA.
        $report = $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026]))->assertSee('Doc. Stamp');
        $voucherStamp = GpaiEnrollment::query()->where('payment_mode', GpaiEnrollment::MODE_VOUCHER)->firstOrFail()->docStampCents();
        $caStamp = GpaiEnrollment::query()->where('payment_mode', GpaiEnrollment::MODE_CASH_ADVANCE)->firstOrFail()->docStampCents();
        $this->assertGreaterThan(0, $voucherStamp);
        $this->assertGreaterThan(0, $caStamp);

        $sections = $report->viewData('sections');
        $this->assertSame($voucherStamp, $sections['voucher']['totals']['doc_stamp_cents']);
        $this->assertSame(255000 + $voucherStamp, $sections['voucher']['totals']['total_cents']);
        $this->assertSame(100000 + $caStamp, $sections['ca']['totals']['total_cents']);
        $this->assertSame(355000 + $voucherStamp + $caStamp, $report->viewData('overall')['total_cents']);

        // A Project Code assigned after enrollment shows up automatically.
        $voucherProject->approval()->create([
            'approval_date' => '2026-03-01',
            'project_code' => 'TUPAD-RO5-ALB-26-0001',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // Period filters: Q1 has only the voucher project; the 2nd semester only the CA one.
        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026, 'period' => 'quarter', 'part' => 1]))
            ->assertOk()
            ->assertSee('TUPAD-RO5-ALB-26-0001')
            ->assertSee('Oas Project')
            ->assertDontSee('Ligao Project');

        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026, 'period' => 'semester', 'part' => 2]))
            ->assertOk()
            ->assertSee('Ligao Project')
            ->assertDontSee('Oas Project');

        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026, 'period' => 'month', 'part' => 8]))
            ->assertOk()
            ->assertSee('August 2026')
            ->assertSee('Ligao Project');

        // "All": the whole year grouped by month / quarter / semester with sub-totals.
        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026, 'period' => 'quarter', 'part' => 'all']))
            ->assertOk()
            ->assertSee('All quarters of 2026')
            ->assertSeeInOrder(['Q1 2026', 'Oas Project', 'Sub-total · Q1 2026'])
            ->assertSeeInOrder(['Q3 2026', 'Ligao Project', 'Sub-total · Q3 2026']);

        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026, 'period' => 'month']))
            ->assertOk()
            ->assertSee('All months of 2026')
            ->assertSee('Sub-total · February 2026')
            ->assertSee('Sub-total · August 2026');

        $this->actingAs($this->gsis)->get(route('gsis.report', ['fiscal_year' => 2026, 'period' => 'semester', 'part' => 'all']))
            ->assertOk()
            ->assertSee('Sub-total · 1st Semester 2026')
            ->assertSee('Sub-total · 2nd Semester 2026');

        // Only the GSIS Enrollment Officer opens it.
        $this->actingAs($this->tc)->get(route('gsis.report'))->assertForbidden();
    }

    public function test_tc_can_request_enrollment_three_times_per_three_hours_and_it_tops_the_officer_list(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $first = $this->project('Aaa Early Project', 10);
        $requested = $this->project('Zzz Requested Project', 51);

        // The button shows on the project before approval (TSSD Evaluation).
        $this->actingAs($this->tc)
            ->get(route('projects.show', $requested))
            ->assertOk()
            ->assertSee('data-gpai-request', false)
            ->assertSee('Request GPAI Enrollment')
            ->assertSee('3 of 3 requests left')
            // Also inside the Evaluation & Approval panel (the Approval progress step).
            ->assertSeeInOrder(['id="evaluation"', 'Evaluation &amp; Approval', 'data-gpai-request'], false);

        foreach ([2, 1, 0] as $left) {
            $this->actingAs($this->tc)
                ->from(route('projects.show', $requested))
                ->post(route('projects.gpai-enrollment-request', $requested))
                ->assertRedirect(route('projects.show', $requested))
                ->assertSessionHas('success', "The GSIS Enrollment Officer was notified to enroll this project. {$left} request(s) left in this 3-hour window.");
            $this->travel(30)->minutes();
        }

        // A 4th press within 3 hours is refused; the limit resets 3 hours after the first press.
        $this->actingAs($this->tc)
            ->post(route('projects.gpai-enrollment-request', $requested))
            ->assertSessionHas('gpai_request_error');
        $this->assertSame(3, $requested->gpaiEnrollmentRequests()->count());

        $this->travelTo(now()->setTime(12, 1));
        $this->actingAs($this->tc)
            ->post(route('projects.gpai-enrollment-request', $requested))
            ->assertSessionHas('success');
        $this->assertSame(4, $requested->gpaiEnrollmentRequests()->count());

        // The officer sees it first, highlighted, and in the bell.
        $this->actingAs($this->gsis)->get(route('gsis.enrollments.create'))
            ->assertOk()
            ->assertSee('Requested by TUPAD Coordinators (1)')
            ->assertSeeInOrder(['Zzz Requested Project', 'Other projects awaiting enrollment', 'Aaa Early Project'])
            ->assertSee('data-gpai-request-badge', false);

        $feed = $this->actingAs($this->gsis)->getJson(route('notifications.feed'))->assertOk()->json();
        $this->assertSame('critical', collect($feed['items'])->firstWhere('key', 'gpai-awaiting')['severity']);
        $item = collect($feed['project_items'])->firstWhere('project_title', 'Zzz Requested Project');
        $this->assertStringContainsString('Enrollment requested by', $item['message']);

        // Once enrolled the button is gone and requests are refused.
        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$requested->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => now()->toDateString(),
        ]);
        $this->actingAs($this->tc)->get(route('projects.show', $requested))->assertDontSee('data-gpai-request', false);
        $this->actingAs($this->tc)
            ->post(route('projects.gpai-enrollment-request', $requested))
            ->assertSessionHas('gpai_request_error');

        // Other roles cannot request.
        $this->actingAs($this->gsis)->post(route('projects.gpai-enrollment-request', $first))->assertForbidden();
    }

    public function test_tc_is_notified_and_their_insurance_form_becomes_read_only(): void
    {
        $project = $this->project('Oas Project', 51);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$project->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => now()->toDateString(),
        ]);
        $enrollment = GpaiEnrollment::query()->firstOrFail();

        $feed = $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->json();

        $item = collect($feed['project_items'])->firstWhere('kind', 'gpai_enrollment');
        $this->assertNotNull($item);
        $this->assertSame($project->id, $item['project_id']);
        $this->assertStringContainsString($enrollment->gpai_number, $item['message']);
        $this->assertNotNull(collect($feed['items'])->firstWhere('key', 'gpai-enrollments'));

        $this->actingAs($this->tc)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('GSIS GPAI Enrollment')
            ->assertSee('Oas Project');

        $this->actingAs($this->tc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Enrolled to GSIS GPAI')
            ->assertSee($enrollment->gpai_number);

        $this->actingAs($this->tc)
            ->post(route('projects.implementation.insurance', $project), [
                'date_enrolled' => '2026-01-01',
                'payment_mode' => 'ca',
            ])
            ->assertForbidden();

        $this->assertSame('voucher', $project->fresh()->insuranceEnrollment->payment_mode);

        // A TC from another province is not notified.
        $otherTc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);
        $otherProvince = \App\Models\Province::query()->create(['code' => '056200000', 'name' => 'Sorsogon', 'is_active' => true]);
        $otherTc->forceFill(['assigned_province_id' => $otherProvince->id])->save();

        $otherFeed = $this->actingAs($otherTc)->getJson(route('notifications.feed'))->json();
        $this->assertNull(collect($otherFeed['project_items'])->firstWhere('kind', 'gpai_enrollment'));
    }

    public function test_gsis_officer_is_reminded_of_projects_awaiting_enrollment(): void
    {
        $this->project('Oas Project', 51);

        $feed = $this->actingAs($this->gsis)->getJson(route('notifications.feed'))->assertOk()->json();

        $this->assertSame(1, collect($feed['items'])->firstWhere('key', 'gpai-awaiting')['count']);

        // The dropdown lists what the bell counts: the awaiting project itself.
        $item = collect($feed['project_items'])->firstWhere('project_title', 'Oas Project');
        $this->assertNotNull($item);
        $this->assertSame(route('gsis.enrollments.create'), $item['url']);
        $this->assertStringContainsString('Ready for GPAI enrollment', $item['message']);
    }

    public function test_summary_and_transmittal_follow_the_gpai_sheets(): void
    {
        $first = $this->project('Oas Project', 51, ['municipality' => 'Oas']);
        $second = $this->project('Sorsogon Project', 15, ['province' => 'Sorsogon', 'municipality' => 'Irosin']);

        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$first->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => '2026-04-14',
        ]);
        $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
            'project_ids' => [$second->id],
            'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
            'transmittal_date' => '2026-10-22',
        ]);

        $this->actingAs($this->gsis)
            ->get(route('gsis.summary', ['fiscal_year' => 2026]))
            ->assertOk()
            ->assertSeeInOrder([
                'GPAI V26-0001', 'DOLE TUPAD Project for 51 beneficiaries in Albay', '2,550.00', '200.00', '2,750.00',
                'Sub-total (1st Semester)',
                'GPAI V26-0002', '15', '750.00', '150.00', '900.00',
                'Sub-total (2nd Semester)',
                'TOTAL', '66', '3,300.00', '350.00', '3,650.00',
            ]);

        $this->actingAs($this->gsis)
            ->get(route('gsis.summary', ['fiscal_year' => 2026, 'print' => 1]))
            ->assertOk()
            ->assertSee('SUMMARY OF ENROLLED TUPAD FY2026 BENEFICIARIES TO GPAI');

        $enrollment = GpaiEnrollment::query()->where('gpai_number', 'GPAI V26-0001')->firstOrFail();

        $this->actingAs($this->gsis)
            ->get(route('gsis.enrollments.transmittal', $enrollment))
            ->assertOk()
            ->assertSee('ENROLLMENT TO GPAI TRANSMITTAL')
            ->assertSee('14 April 2026')
            ->assertSee('April 15, 2026 to April 14, 2027')
            ->assertSee('DOLE TUPAD Program for 51 beneficiaries in Oas, Albay')
            ->assertSee('Add Documentary Stamp Tax')
            ->assertSee('2,750.00')
            ->assertSee('Gina GSIS Officer');
    }

    public function test_tc_admin_and_tc_focal_can_view_the_read_only_enrollment_status(): void
    {
        $albayProject = $this->project('Albay Oas Project', 51);
        $sorsogonProject = $this->project('Sorsogon Irosin Project', 15, ['province' => 'Sorsogon', 'municipality' => 'Irosin']);

        foreach ([$albayProject, $sorsogonProject] as $index => $project) {
            $this->actingAs($this->gsis)->post(route('gsis.enrollments.store'), [
                'project_ids' => [$project->id],
                'payment_mode' => GpaiEnrollment::MODE_VOUCHER,
                'transmittal_date' => '2026-04-1'.($index + 4),
            ])->assertRedirect();
        }

        // TC (Albay): only their province's transmittal and project; no editing.
        $this->actingAs($this->tc)
            ->get(route('dashboard'))
            ->assertSee(route('gpai-status.index'), false)
            ->assertSee('GPAI Enrollment Status');

        $this->actingAs($this->tc)
            ->get(route('gpai-status.index', ['fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('GPAI Enrollment Status 2026')
            ->assertSee('Your Projects')
            ->assertSee('GPAI V26-0001')
            ->assertSee('Albay Oas Project')
            ->assertDontSee('GPAI V26-0002')
            ->assertDontSee('Sorsogon Irosin Project')
            ->assertDontSee('+ Enroll Projects')
            ->assertDontSee(route('gsis.enrollments.edit', GpaiEnrollment::query()->firstOrFail()), false);

        // Admin and TC-Focal see every transmittal.
        $tcFocal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $tcFocal->forceFill(['has_focal_access' => true])->save();

        foreach ([$this->admin, $tcFocal] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('gpai-status.index', ['fiscal_year' => 2026]))
                ->assertOk()
                ->assertSee('GPAI V26-0001')
                ->assertSee('GPAI V26-0002')
                ->assertSee('Sorsogon Irosin Project');
        }

        $this->actingAs($tcFocal)->get(route('dashboard'))->assertSee(route('gpai-status.index'), false);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee(route('gpai-status.index'), false);

        // Plain Focal can view it too (read-only) but gets no GPAI notifications.
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->actingAs($focal)
            ->get(route('gpai-status.index', ['fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('GPAI V26-0002')
            ->assertDontSee('+ Enroll Projects');
        $this->actingAs($focal)->get(route('dashboard'))->assertSee(route('gpai-status.index'), false);

        foreach ([$focal, $tcFocal, $this->admin] as $viewer) {
            $feed = $this->actingAs($viewer)->getJson(route('notifications.feed'))->assertOk()->json();
            $this->assertNull(collect($feed['project_items'])->firstWhere('kind', 'gpai_enrollment'));
            $this->assertEmpty(collect($feed['items'])->filter(fn (array $item): bool => str_starts_with($item['key'], 'gpai')));
        }

        // The GSIS officer uses its own full Yearly Status page instead.
        $this->actingAs($this->gsis)->get(route('gpai-status.index'))->assertForbidden();
    }

    public function test_focal_can_create_and_edit_the_gsis_enrollment_officer_account(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('GSIS Enrollment Officer');

        $this->actingAs($focal)
            ->post(route('users.store'), [
                'name' => 'Annie',
                'username' => 'annie2',
                'role' => UserRole::GSIS->value,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $annie = User::query()->where('username', 'annie2')->firstOrFail();
        $this->assertTrue($annie->isGsis());
        $this->assertNull($annie->assigned_province_id);

        $this->actingAs($focal)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Gina GSIS Officer')
            ->assertSee(route('users.edit', $this->gsis), false);

        $this->actingAs($focal)
            ->get(route('users.edit', $this->gsis))
            ->assertOk()
            ->assertSee('value="gsis" selected', false);

        // Editing without naming a role keeps the account a GSIS Enrollment Officer.
        $this->actingAs($focal)
            ->put(route('users.update', $this->gsis), [
                'name' => 'Gina Renamed',
                'username' => $this->gsis->username,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.edit', $this->gsis));

        $this->assertSame('Gina Renamed', $this->gsis->fresh()->name);
        $this->assertTrue($this->gsis->fresh()->isGsis());

        $this->actingAs($focal)
            ->post(route('users.reset-password', $this->gsis))
            ->assertRedirect()
            ->assertSessionHas('temporary_password');

        // Still no access to Admin or plain Focal accounts.
        $this->actingAs($focal)->get(route('users.edit', $this->admin))->assertNotFound();
    }

    public function test_gsis_search_finds_projects_awaiting_enrollment_and_transmittals(): void
    {
        $awaiting = $this->project('Oas Awaiting Project', 10);
        $this->project('Oas Ongoing Project', 10, ['status' => ProjectStatus::ONGOING_IMPLEMENTATION]);
        $enrolled = $this->project('Daraga Enrolled Project', 12);

        $this->actingAs($this->gsis)
            ->post(route('gsis.enrollments.store'), [
                'payment_mode' => 'voucher',
                'transmittal_date' => '2026-03-05',
                'project_ids' => [$enrolled->id],
            ]);
        $enrollment = \App\Models\GpaiEnrollment::query()->firstOrFail();

        $oas = $this->actingAs($this->gsis)->getJson(route('search.suggest', ['q' => 'Oas']))->assertOk()->json();
        // Projects the officer cannot act on (already past enrollment) are left out.
        $this->assertSame(['Oas Awaiting Project'], array_column($oas['projects'], 'title'));
        $this->assertSame(route('gsis.enrollments.create', ['project' => $awaiting->id]), $oas['projects'][0]['url']);

        $daraga = $this->actingAs($this->gsis)->getJson(route('search.suggest', ['q' => 'Daraga']))->json();
        $this->assertSame(route('gsis.enrollments.edit', $enrollment), $daraga['projects'][0]['url']);
        $this->assertSame($enrollment->gpai_number, $daraga['enrollments'][0]['number']);

        $byNumber = $this->actingAs($this->gsis)->getJson(route('search.suggest', ['q' => $enrollment->gpai_number]))->json();
        $this->assertCount(1, $byNumber['enrollments']);

        // "Enroll Project" link opens New Enrollment with the project already ticked.
        $html = $this->actingAs($this->gsis)
            ->get(route('gsis.enrollments.create', ['project' => $awaiting->id]))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/value="'.$awaiting->id.'"[^>]*checked/s', $html);
    }

    private function project(string $title, int $insured, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-03-01',
            'project_title' => $title,
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => max($insured, 1),
            'beneficiaries_female' => 0,
            'insurance_beneficiaries' => $insured,
            'wage_rate' => 455,
            'wages_total' => 455 * 20 * max($insured, 1),
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => $insured * 50,
            'total_project_cost' => 455 * 20 * max($insured, 1) + $insured * 50,
            'status' => ProjectStatus::APPROVED,
            'created_by' => $this->tc->id,
        ], $overrides));
    }
}
