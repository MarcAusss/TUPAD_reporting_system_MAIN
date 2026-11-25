<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectBeneficiaryAddress;
use App\Models\ProjectImplementation;
use App\Models\ProjectInsuranceEnrollment;
use App\Models\ProjectNoticeToProceed;
use App\Models\ProjectObligation;
use App\Models\ProjectOrientation;
use App\Models\ProjectPostDocument;
use App\Models\ProjectPpeDelivery;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Long-term projects: per-tranche post-documents (TC) → payment (Focal) →
 * release (TC), open while implementation is ongoing; completion still waits
 * for the end of the work period.
 */
class LongTermTrancheWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    private User $focal;

    private User $admin;

    private Province $province;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 26, 9, 0, 0, 'Asia/Manila'));

        $this->province = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->province->id,
        ]);
        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tc_records_a_tranche_post_document_while_implementation_is_ongoing(): void
    {
        $project = $this->createOngoingLongTermProject();

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertOk()
            ->assertSee('Long-Term · Per Tranche')
            ->assertSee('Date Covered – Start')
            ->assertSee('Save Tranche 1 Post-Documents');

        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_post_documents', [
            'project_id' => $project->id,
            'tranche_number' => 1,
            'beneficiaries_total' => 100,
            'beneficiaries_female' => 60,
        ]);

        // Implementation is still ongoing: no automatic move to payment.
        $this->assertSame(ProjectStatus::ONGOING_IMPLEMENTATION, $project->fresh()->status);

        $this->recordTranche($project, beneficiaries: 98, female: 58, start: '2026-08-31', end: '2026-09-29')
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2], $project->postDocuments()->orderBy('tranche_number')->pluck('tranche_number')->all());
    }

    public function test_tranche_post_document_rules(): void
    {
        $project = $this->createOngoingLongTermProject();

        $this->recordTranche($project, beneficiaries: 101, female: 60, start: '2026-08-01', end: '2026-08-30')
            ->assertSessionHasErrors('beneficiaries_total');
        $this->recordTranche($project, beneficiaries: 50, female: 51, start: '2026-08-01', end: '2026-08-30')
            ->assertSessionHasErrors('beneficiaries_female');
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-10', end: '2026-08-01')
            ->assertSessionHasErrors('coverage_end_date');
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-07-15', end: '2026-08-30')
            ->assertSessionHasErrors('coverage_start_date');

        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30')
            ->assertSessionHasNoErrors();

        // Overlaps tranche 1.
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-30', end: '2026-09-10')
            ->assertSessionHasErrors('coverage_start_date');

        $this->assertDatabaseCount('project_post_documents', 1);
    }

    public function test_short_term_projects_keep_the_original_post_document_stage(): void
    {
        $project = $this->createOngoingLongTermProject(term: 'short_term', days: 30);

        $this->actingAs($this->tc)
            ->post(route('projects.post-documents.store', $project), [
                'date_received' => '2026-08-26',
                'date_forwarded_to_imsd' => '2026-08-26',
            ])
            ->assertForbidden();

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertForbidden();
    }

    public function test_focal_sees_tc_tranche_prefilled_and_may_edit_beneficiaries(): void
    {
        $project = $this->createOngoingLongTermProject();
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30');
        $document = $project->postDocuments()->firstOrFail();

        $this->actingAs($this->focal)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee(route('projects.show', $project), false);

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Long-term project · paid per tranche')
            ->assertDontSee('<span aria-hidden="true">+</span> Add Tranche', false)
            ->assertDontSee('value="complete"', false)
            ->assertViewHas('pendingTranches', function (array $rows) use ($document): bool {
                return count($rows) === 1
                    && $rows[0]['post_document_id'] === $document->id
                    && $rows[0]['beneficiaries_total'] === 100
                    && $rows[0]['beneficiaries_female'] === 60
                    && $rows[0]['coverage_days'] === 30
                    && $rows[0]['balance_days'] === 4
                    // 100 beneficiaries × ₱500 × 30 days.
                    && $rows[0]['wages_amount'] === '1500000.00';
            });

        // The Focal lowers the TC's count (two beneficiaries were not paid).
        $this->saveTranches($project, [
            $this->trancheRow($document, beneficiaries: 98, female: 59, wages: '1470000.00'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'project_post_document_id' => $document->id,
            'tranche_number' => 1,
            'beneficiaries_total' => 98,
            'beneficiaries_female' => 59,
            'payee' => 'LGU Albay',
        ]);
    }

    public function test_each_tranche_may_pay_the_same_beneficiaries_and_headcount_is_not_added_up(): void
    {
        $project = $this->createOngoingLongTermProject();
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30');
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-31', end: '2026-09-29');
        [$first, $second] = $project->postDocuments()->orderBy('tranche_number')->get()->all();

        $this->saveTranches($project, [
            $this->trancheRow($first, beneficiaries: 100, female: 60, wages: '1500000.00'),
            $this->trancheRow($second, beneficiaries: 100, female: 60, wages: '1500000.00'),
        ])->assertSessionHasNoErrors();

        $this->assertSame([100, 60], ProjectObligation::paidBeneficiaryCounts($project->obligations()->get()));
    }

    public function test_tranche_limits_per_tranche_beneficiaries_and_cumulative_wages(): void
    {
        $project = $this->createOngoingLongTermProject();
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30');
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-31', end: '2026-09-29');
        [$first, $second] = $project->postDocuments()->orderBy('tranche_number')->get()->all();

        $this->saveTranches($project, [$this->trancheRow($first, beneficiaries: 101, female: 60, wages: '1000.00')])
            ->assertSessionHasErrors('tranches.0.beneficiaries_total');

        $this->saveTranches($project, [
            $this->trancheRow($first, beneficiaries: 100, female: 60, wages: '2000000.00'),
            $this->trancheRow($second, beneficiaries: 100, female: 60, wages: '1000000.01'),
        ])->assertSessionHasErrors('tranches.1.wages_amount');

        // A tranche must come from the TC's submission.
        $this->saveTranches($project, [[
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wages_amount' => '1000.00',
            'obligation_date' => '2026-08-26',
            'payee' => 'LGU Albay',
        ]])->assertSessionHasErrors('tranches.0.post_document_id');

        $this->assertDatabaseCount('project_obligations', 0);

        // The same submission cannot be obligated twice.
        $this->saveTranches($project, [$this->trancheRow($first, beneficiaries: 100, female: 60, wages: '1000.00')])
            ->assertSessionHasNoErrors();
        $this->saveTranches($project, [$this->trancheRow($first, beneficiaries: 100, female: 60, wages: '1000.00')])
            ->assertSessionHasErrors('tranches.0.post_document_id');
    }

    public function test_payment_and_release_proceed_during_implementation_but_completion_waits_for_end_date(): void
    {
        $project = $this->createOngoingLongTermProject();
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30');
        $first = $project->postDocuments()->firstOrFail();

        $this->saveTranches($project, [$this->trancheRow($first, beneficiaries: 100, female: 60, wages: '1500000.00')]);
        $obligation = $project->obligations()->firstOrFail();

        // Completing (locking) the tranches waits for the end of the work period.
        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), ['intent' => 'complete', 'tranches' => []])
            ->assertSessionHasErrors('tranches');
        $this->assertNull($project->fresh()->obligations_completed_at);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.disbursements.store', [$project, $obligation]), [
                'amount' => '1500000.00',
                'date_disbursed' => '2026-08-26',
                'ldap_check_number' => 'LDAP-001',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', [$project, $obligation]), [
                'payout_mode' => '(Actual) Cash Payout',
                'payout_date' => '2026-08-26',
                'venue' => 'Barangay Hall',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($obligation->fresh()->release_date);
        $this->assertSame(ProjectStatus::ONGOING_IMPLEMENTATION, $project->fresh()->status);

        // Work period ends: post-documents are on record, so it moves to For Payment.
        Carbon::setTestNow(Carbon::create(2026, 9, 29, 9, 0, 0, 'Asia/Manila'));
        app(ProjectStatusEngine::class)->synchronize($project);
        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);

        // The TC records the final tranche, the Focal pays it and completes.
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-31', end: '2026-09-29')
            ->assertSessionHasNoErrors();
        $second = $project->postDocuments()->where('tranche_number', 2)->firstOrFail();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'complete',
                'tranches' => [$this->trancheRow($second, beneficiaries: 100, female: 60, wages: '1500000.00')],
            ])
            ->assertSessionHasNoErrors();

        $secondObligation = $project->obligations()->where('tranche_number', 2)->firstOrFail();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.disbursements.store', [$project, $secondObligation]), [
                'amount' => '1500000.00',
                'date_disbursed' => '2026-09-29',
                'ldap_check_number' => 'LDAP-002',
            ]);

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', [$project, $secondObligation]), [
                'payout_mode' => '(Actual) Cash Payout',
                'payout_date' => '2026-09-29',
                'venue' => 'Barangay Hall',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectStatus::COMPLETED, $project->fresh()->status);
        // Same 100 people over two tranches: the actual beneficiaries stay 100.
        $this->assertSame(100, $project->fresh()->actualBeneficiaries());
    }

    public function test_focal_cannot_complete_while_a_tc_tranche_is_still_unpaid(): void
    {
        $project = $this->createOngoingLongTermProject();
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30');
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-31', end: '2026-09-29');
        $first = $project->postDocuments()->where('tranche_number', 1)->firstOrFail();

        Carbon::setTestNow(Carbon::create(2026, 9, 29, 9, 0, 0, 'Asia/Manila'));
        app(ProjectStatusEngine::class)->synchronize($project);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'complete',
                'tranches' => [$this->trancheRow($first, beneficiaries: 100, female: 60, wages: '1500000.00')],
            ])
            ->assertSessionHasErrors('tranches');

        $this->assertNull($project->fresh()->obligations_completed_at);
        $this->assertDatabaseCount('project_obligations', 0);
    }

    public function test_only_tc_or_admin_edits_the_coverage_dates(): void
    {
        $project = $this->createOngoingLongTermProject();
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-01', end: '2026-08-30');
        $this->recordTranche($project, beneficiaries: 100, female: 60, start: '2026-08-31', end: '2026-09-29');
        $first = $project->postDocuments()->where('tranche_number', 1)->firstOrFail();

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertSee(route('projects.post-documents.coverage.update', [$project, $first]), false);

        $this->actingAs($this->focal)
            ->put(route('projects.post-documents.coverage.update', [$project, $first]), [
                'coverage_start_date' => '2026-08-01',
                'coverage_end_date' => '2026-08-20',
            ])
            ->assertForbidden();

        // Cannot overlap tranche 2.
        $this->actingAs($this->tc)
            ->put(route('projects.post-documents.coverage.update', [$project, $first]), [
                'coverage_start_date' => '2026-08-01',
                'coverage_end_date' => '2026-09-05',
            ])
            ->assertSessionHasErrors('coverage_start_date', null, 'coverage_'.$first->id);

        $this->actingAs($this->tc)
            ->put(route('projects.post-documents.coverage.update', [$project, $first]), [
                'coverage_start_date' => '2026-08-01',
                'coverage_end_date' => '2026-08-20',
            ])
            ->assertSessionHasNoErrors();

        $first->refresh();
        $this->assertSame('2026-08-20', $first->coverage_end_date->toDateString());
        $this->assertSame($this->tc->id, $first->coverage_updated_by);
    }

    public function test_balance_days_count_the_days_left_in_the_coverage(): void
    {
        $document = new ProjectPostDocument([
            'tranche_number' => 1,
            'coverage_start_date' => '2026-12-01',
            'coverage_end_date' => '2026-12-10',
        ]);

        $this->assertSame(10, $document->coverageDays());
        $this->assertSame(9, $document->balanceDays(Carbon::create(2026, 12, 1, 0, 0, 0, 'Asia/Manila')));
        $this->assertSame(10, $document->balanceDays(Carbon::create(2026, 11, 1, 0, 0, 0, 'Asia/Manila')));
        $this->assertSame(0, $document->balanceDays(Carbon::create(2026, 12, 10, 0, 0, 0, 'Asia/Manila')));
        $this->assertSame(0, $document->balanceDays(Carbon::create(2026, 12, 25, 0, 0, 0, 'Asia/Manila')));
    }

    public function test_project_registry_shows_the_beneficiary_mapping_status(): void
    {
        $municipality = Municipality::create(['province_id' => $this->province->id, 'code' => '050506000', 'name' => 'Legazpi City', 'is_active' => true]);
        $rawis = Barangay::create(['municipality_id' => $municipality->id, 'code' => '050506001', 'name' => 'Rawis', 'is_active' => true]);

        $notMapped = $this->createOngoingLongTermProject();
        $partial = $this->createOngoingLongTermProject();
        $completed = $this->createOngoingLongTermProject();

        foreach ([[$partial, 40], [$completed, 100]] as [$project, $count]) {
            ProjectBeneficiaryAddress::create([
                'project_id' => $project->id,
                'province_id' => $this->province->id,
                'municipality_id' => $municipality->id,
                'barangay_id' => $rawis->id,
                'beneficiaries_total' => $count,
                'beneficiaries_female' => 10,
                'encoded_by' => $this->tc->id,
                'updated_by' => $this->tc->id,
            ]);
        }

        $this->assertSame('not_mapped', $notMapped->beneficiaryMappingStatus()['key']);
        $this->assertSame('partial', $partial->beneficiaryMappingStatus()['key']);
        $this->assertSame('completed', $completed->beneficiaryMappingStatus()['key']);

        $this->actingAs($this->admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Beneficiary Mapping')
            ->assertSee('Not Yet Mapped')
            ->assertSee('Partially Mapped')
            ->assertSee('40 / 100 mapped')
            ->assertSee('100 / 100 mapped');
    }

    private function recordTranche(Project $project, int $beneficiaries, int $female, string $start, string $end)
    {
        return $this->actingAs($this->tc)->post(route('projects.post-documents.store', $project), [
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => $female,
            'coverage_start_date' => $start,
            'coverage_end_date' => $end,
            'date_received' => '2026-08-26',
            'document_type' => 'Payroll, DTR',
            'date_forwarded_to_imsd' => '2026-08-26',
        ]);
    }

    private function saveTranches(Project $project, array $rows)
    {
        return $this->actingAs($this->focal)->post(route('projects.payment.store', $project), [
            'intent' => 'save',
            'tranches' => $rows,
        ]);
    }

    private function trancheRow(ProjectPostDocument $document, int $beneficiaries, int $female, string $wages): array
    {
        return [
            'post_document_id' => $document->id,
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => $female,
            'wages_amount' => $wages,
            'obligation_date' => '2026-08-26',
            'payee' => 'LGU Albay',
        ];
    }

    /** 60-day project, 100 beneficiaries (60 female), ₱500/day; work period Aug 1 – Sep 29, 2026. */
    private function createOngoingLongTermProject(string $term = 'long_term', int $days = 60): Project
    {
        $this->sequence++;

        $adl = Adl::create([
            'adl_number' => sprintf('ADL-LT-%03d', $this->sequence),
            'grants' => 10000000,
            'admin_cost' => 0,
            'total' => 10000000,
            'created_by' => $this->focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'amount' => 10000000,
            'created_by' => $this->focal->id,
        ]);

        $wages = 500 * 100 * $days;

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-07-01',
            'project_title' => sprintf('Long-Term Tranche Project %03d', $this->sequence),
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'province_id' => $this->province->id,
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => $days,
            'term' => $term,
            'beneficiaries_total' => 100,
            'beneficiaries_female' => 60,
            'wage_rate' => 500,
            'wages_total' => $wages,
            'ppe_total' => 0,
            'insurance_rate' => 0,
            'insurance_beneficiaries' => 100,
            'insurance_total' => 0,
            'total_project_cost' => $wages,
            'status' => ProjectStatus::ONGOING_IMPLEMENTATION,
            'created_by' => $this->tc->id,
        ]);

        $project->approval()->create([
            'approval_date' => '2026-07-15',
            'project_code' => 'LT-CODE-'.$this->sequence,
            'approved_by' => $this->tc->id,
            'approved_at' => now(),
        ]);

        ProjectInsuranceEnrollment::create([
            'project_id' => $project->id,
            'date_enrolled' => '2026-07-20',
            'beneficiary_count' => 100,
            'amount' => 0,
            'payment_mode' => 'voucher',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectPpeDelivery::create([
            'project_id' => $project->id,
            'delivery_receipt_date' => '2026-07-20',
            'ppe_provided' => 'Safety vest',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectNoticeToProceed::create([
            'project_id' => $project->id,
            'date_issued' => '2026-07-25',
            'date_released' => '2026-07-25',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectOrientation::create([
            'project_id' => $project->id,
            'orientation_date' => '2026-07-30',
            'recorded_by' => $this->tc->id,
        ]);

        ProjectImplementation::create([
            'project_id' => $project->id,
            'start_date' => '2026-08-01',
            'end_date' => $term === 'long_term' ? '2026-09-29' : '2026-08-30',
            'recorded_by' => $this->tc->id,
        ]);

        return $project;
    }
}
