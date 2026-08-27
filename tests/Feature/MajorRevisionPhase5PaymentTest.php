<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase5PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;
    private User $tc;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);
    }

    public function test_focal_can_access_payment_of_wages(): void
    {
        $project = $this->createForPaymentProject();

        $this->actingAs($this->focal)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee($project->project_title);

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Project Payment Summary')
            ->assertSee('Add First Obligation');
    }

    public function test_tc_cannot_perform_focal_payment_actions(): void
    {
        $project = $this->createForPaymentProject();

        $this->actingAs($this->tc)
            ->get(route('payments.show', $project))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->post(route('projects.payment.store', $project), [
                'tranche_number' => 1,
                'amount' => '100.00',
                'obligation_date' => now()->toDateString(),
                'payee' => 'Unauthorized Payee',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('project_obligations', 0);

        $this->recordObligation($project, 1, '100.00');
        $obligation = $project->obligations()->firstOrFail();

        $this->actingAs($this->tc)
            ->post(
                route(
                    'projects.payment.disbursements.store',
                    [$project, $obligation]
                ),
                [
                    'amount' => '100.00',
                    'date_disbursed' => now()->toDateString(),
                    'ldap_check_number' => 'TC-NOT-ALLOWED',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount('project_disbursements', 0);
    }

    public function test_first_obligation_tranche_saves_with_audit_ownership(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '400.00')
            ->assertRedirect(route('payments.show', $project));

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'tranche_number' => 1,
            'amount' => 400,
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->focal->id,
        ]);

        $obligation = ProjectObligation::firstOrFail();

        $this->assertNotNull($obligation->created_at);
        $this->assertNotNull($obligation->updated_at);
    }

    public function test_official_project_and_adl_references_cannot_be_manipulated(): void
    {
        $project = $this->createForPaymentProject();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'tranche_number' => 1,
                'amount' => '250.00',
                'obligation_date' => now()->toDateString(),
                'payee' => 'TUPAD Beneficiaries',
                'adl_number' => 'FORGED-ADL',
                'fund_sponsor' => 'FORGED SPONSOR',
                'partner' => 'FORGED PARTNER',
                'project_location' => 'FORGED LOCATION',
                'term' => 'FORGED TERM',
                'beneficiaries_total' => 999999,
                'beneficiaries_female' => 999999,
            ])
            ->assertRedirect(route('payments.show', $project));

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'adl_number' => $project->allocation->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'term' => $project->term->label(),
            'beneficiaries_total' => $project->beneficiaries_total,
            'beneficiaries_female' => $project->beneficiaries_female,
        ]);

        $this->assertDatabaseMissing('project_obligations', [
            'project_id' => $project->id,
            'adl_number' => 'FORGED-ADL',
        ]);
    }

    public function test_multiple_obligation_tranches_can_be_created(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '400.00');
        $this->recordObligation($project, 2, '600.00');

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'tranche_number' => 1,
            'amount' => 400,
        ]);
        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'tranche_number' => 2,
            'amount' => 600,
        ]);
        $this->assertSame(2, $project->obligations()->count());
    }

    public function test_maximum_five_tranches_is_enforced(): void
    {
        $project = $this->createForPaymentProject('600.00');

        foreach (range(1, 4) as $tranche) {
            $this->recordObligation(
                $project,
                $tranche,
                '100.00'
            );
        }

        $this->recordObligation($project, 5, '200.00');

        $this->recordObligation($project, 6, '100.00')
            ->assertSessionHasErrors('tranche_number');

        $this->assertSame(5, $project->obligations()->count());
    }

    public function test_duplicate_tranche_number_is_rejected(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '400.00');
        $this->recordObligation($project, 1, '100.00')
            ->assertSessionHasErrors('tranche_number');

        $this->assertSame(1, $project->obligations()->count());
    }

    public function test_obligation_total_cannot_exceed_project_payable_wages(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '700.00');
        $this->recordObligation($project, 2, '300.01')
            ->assertSessionHasErrors('amount');

        $this->assertEquals(700.0, $project->obligations()->sum('amount'));
    }

    public function test_disbursement_belongs_to_the_selected_obligation_tranche(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '400.00');
        $this->recordObligation($project, 2, '600.00');

        $first = $project->obligations()
            ->where('tranche_number', 1)
            ->firstOrFail();

        $this->recordDisbursement(
            $project,
            $first,
            '400.00',
            'LDAP-TRANCHE-1'
        );

        $this->assertDatabaseHas('project_disbursements', [
            'project_obligation_id' => $first->id,
            'amount' => 400,
            'ldap_check_number' => 'LDAP-TRANCHE-1',
            'recorded_by' => $this->focal->id,
        ]);
    }

    public function test_disbursement_cannot_exceed_tranche_obligation(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '400.00');
        $obligation = $project->obligations()->firstOrFail();

        $this->recordDisbursement(
            $project,
            $obligation,
            '400.01',
            'CHECK-OVER'
        )->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('project_disbursements', 0);
    }

    public function test_partial_disbursement_does_not_prematurely_complete_project(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '500.00');
        $this->recordObligation($project, 2, '500.00');

        $first = $project->obligations()
            ->where('tranche_number', 1)
            ->firstOrFail();

        $this->recordDisbursement(
            $project,
            $first,
            '500.00',
            'LDAP-PARTIAL'
        );

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status
        );
    }

    public function test_full_disbursement_automatically_completes_project(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '400.00');
        $this->recordObligation($project, 2, '600.00');

        $obligations = $project->obligations()
            ->orderBy('tranche_number')
            ->get();

        $this->recordDisbursement(
            $project,
            $obligations[0],
            '400.00',
            'LDAP-FULL-1'
        );

        $this->recordDisbursement(
            $project,
            $obligations[1],
            '600.00',
            'CHECK-FULL-2'
        );

        $project->refresh();

        $this->assertSame(ProjectStatus::COMPLETED, $project->status);
        $this->assertSame($this->focal->id, $project->updated_by);
    }

    public function test_payment_summary_totals_and_balance_are_calculated_correctly(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordObligation($project, 1, '600.00');
        $obligation = $project->obligations()->firstOrFail();
        $this->recordDisbursement(
            $project,
            $obligation,
            '250.00',
            'LDAP-SUMMARY'
        );

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Project Payable Amount')
            ->assertSee('₱1,000.00')
            ->assertSee('Total Obligated')
            ->assertSee('₱600.00')
            ->assertSee('Total Disbursed')
            ->assertSee('₱250.00')
            ->assertSee('Remaining Balance')
            ->assertSee('₱750.00')
            ->assertSee('25%');
    }

    public function test_disbursement_cannot_use_an_obligation_from_another_project(): void
    {
        $projectOne = $this->createForPaymentProject();
        $projectTwo = $this->createForPaymentProject();

        $this->recordObligation($projectOne, 1, '100.00');
        $foreignObligation = $projectOne->obligations()->firstOrFail();

        $this->actingAs($this->focal)
            ->post(
                route(
                    'projects.payment.disbursements.store',
                    [$projectTwo, $foreignObligation]
                ),
                [
                    'amount' => '100.00',
                    'date_disbursed' => now()->toDateString(),
                    'ldap_check_number' => 'WRONG-PROJECT',
                ]
            )
            ->assertNotFound();

        $this->assertDatabaseCount('project_disbursements', 0);
    }

    private function recordObligation(
        Project $project,
        int $tranche,
        string $amount
    ) {
        return $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'tranche_number' => $tranche,
                'amount' => $amount,
                'obligation_date' => now()->toDateString(),
                'payee' => 'TUPAD Beneficiaries',
                'remarks' => "Phase 5 Tranche {$tranche}",
            ]);
    }

    private function recordDisbursement(
        Project $project,
        ProjectObligation $obligation,
        string $amount,
        string $reference
    ) {
        return $this->actingAs($this->focal)
            ->post(
                route(
                    'projects.payment.disbursements.store',
                    [$project, $obligation]
                ),
                [
                    'amount' => $amount,
                    'date_disbursed' => now()->toDateString(),
                    'ldap_check_number' => $reference,
                ]
            );
    }

    private function createForPaymentProject(
        string $wagesTotal = '1000.00'
    ): Project {
        $this->sequence++;

        $adl = Adl::create([
            'adl_number' => 'ADL-P5-'.str_pad(
                (string) $this->sequence,
                4,
                '0',
                STR_PAD_LEFT
            ),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => "Phase 5 Payment Project {$this->sequence}",
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => now()->toDateString(),
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => 455,
            'wages_total' => $wagesTotal,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 0,
            'total_project_cost' => $wagesTotal,
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->tc->id,
        ]);

        $project->approval()->create([
            'approval_date' => now()->toDateString(),
            'project_code' => 'P5-CODE-'.$this->sequence,
            'approved_by' => $this->tc->id,
            'approved_at' => now(),
        ]);

        return $project;
    }
}
