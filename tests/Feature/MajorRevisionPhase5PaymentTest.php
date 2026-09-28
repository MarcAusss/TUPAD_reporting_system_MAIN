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
            ->assertSee('Obligation Tranches')
            ->assertSee('Add Tranche')
            ->assertSee('Save Tranches')
            ->assertSee('Complete');
    }

    public function test_tc_cannot_perform_focal_payment_actions(): void
    {
        $project = $this->createForPaymentProject();

        $this->actingAs($this->tc)
            ->get(route('payments.show', $project))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'save',
                'tranches' => [$this->trancheRow('100.00')],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('project_obligations', 0);

        $this->recordTranches($project, [$this->trancheRow('100.00')]);
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

    public function test_tranche_saves_encoded_breakdown_and_calculated_total(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [
            $this->trancheRow('400.00', '20.00', '80.00', beneficiaries: 4, female: 3),
        ])->assertRedirect(route('payments.show', $project));

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'tranche_number' => 1,
            'beneficiaries_total' => 4,
            'beneficiaries_female' => 3,
            'wages_amount' => 400,
            'insurance_amount' => 20,
            'ppe_amount' => 80,
            'amount' => 500,
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
                'intent' => 'save',
                'tranches' => [
                    $this->trancheRow('250.00') + [
                        'adl_number' => 'FORGED-ADL',
                        'fund_sponsor' => 'FORGED SPONSOR',
                        'partner' => 'FORGED PARTNER',
                        'project_location' => 'FORGED LOCATION',
                        'term' => 'FORGED TERM',
                        'tranche_number' => 99,
                    ],
                ],
            ])
            ->assertRedirect(route('payments.show', $project));

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'tranche_number' => 1,
            'adl_number' => $project->allocation->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'term' => $project->term->label(),
        ]);

        $this->assertDatabaseMissing('project_obligations', [
            'project_id' => $project->id,
            'adl_number' => 'FORGED-ADL',
        ]);
    }

    public function test_more_than_five_tranches_can_be_saved_in_one_submit(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches(
            $project,
            array_fill(0, 7, $this->trancheRow('100.00'))
        )->assertSessionHasNoErrors();

        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7],
            $project->obligations()->pluck('tranche_number')->all()
        );

        $this->recordTranches($project, [$this->trancheRow('100.00')])
            ->assertSessionHasNoErrors();

        $this->assertSame(8, $project->obligations()->max('tranche_number'));
    }

    public function test_blank_tranche_rows_are_ignored_on_complete_but_required_on_save(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [$this->blankRow()])
            ->assertSessionHasErrors('tranches');

        $this->assertDatabaseCount('project_obligations', 0);
    }

    public function test_female_beneficiaries_cannot_exceed_tranche_total(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [
            $this->trancheRow('100.00', beneficiaries: 2, female: 3),
        ])->assertSessionHasErrors('tranches.0.beneficiaries_female');

        $this->assertDatabaseCount('project_obligations', 0);
    }

    public function test_tranche_totals_cannot_exceed_total_project_cost(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [$this->trancheRow('700.00')]);
        $this->recordTranches($project, [$this->trancheRow('250.00', '50.00', '0.01')])
            ->assertSessionHasErrors('tranches.0.wages_amount');

        $this->assertEquals(700.0, $project->obligations()->sum('amount'));
    }

    public function test_a_failed_row_rolls_back_the_whole_batch(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [
            $this->trancheRow('600.00'),
            $this->trancheRow('600.00'),
        ])->assertSessionHasErrors('tranches.1.wages_amount');

        $this->assertDatabaseCount('project_obligations', 0);
    }

    public function test_complete_is_allowed_when_tranches_are_below_project_data(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [$this->trancheRow('600.00')]);

        $this->completeTranches($project)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('projects.show', $project));

        $this->assertNotNull($project->fresh()->obligations_completed_at);
    }

    public function test_complete_requires_at_least_one_tranche(): void
    {
        $project = $this->createForPaymentProject();

        $this->completeTranches($project, [$this->blankRow()])
            ->assertSessionHasErrors('tranches');

        $this->assertNull($project->fresh()->obligations_completed_at);
    }

    public function test_each_project_figure_cannot_be_exceeded_across_tranches(): void
    {
        $project = $this->createForPaymentProject();

        // Project: 10 beneficiaries (6 female), ₱800 wages, ₱50 insurance, ₱150 PPE.
        $this->recordTranches($project, [$this->trancheRow('100.00', beneficiaries: 11, female: 0)])
            ->assertSessionHasErrors('tranches.0.beneficiaries_total');

        $this->recordTranches($project, [$this->trancheRow('100.00', beneficiaries: 7, female: 7)])
            ->assertSessionHasErrors('tranches.0.beneficiaries_female');

        $this->recordTranches($project, [$this->trancheRow('100.00', insurance: '50.01')])
            ->assertSessionHasErrors('tranches.0.insurance_amount');

        $this->recordTranches($project, [$this->trancheRow('100.00', ppe: '150.01')])
            ->assertSessionHasErrors('tranches.0.ppe_amount');

        $this->recordTranches($project, [
            $this->trancheRow('100.00', beneficiaries: 6, female: 3),
            $this->trancheRow('100.00', beneficiaries: 5, female: 3),
        ])->assertSessionHasErrors('tranches.1.beneficiaries_total');

        $this->assertDatabaseCount('project_obligations', 0);

        // Below the project data is fine.
        $this->recordTranches($project, [$this->trancheRow('455.00', '25.00', '75.00', beneficiaries: 5, female: 3)])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('project_obligations', 1);
    }

    public function test_tranche_saves_with_only_one_amount_beneficiaries_date_and_payee(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [[
            'beneficiaries_total' => 3,
            'beneficiaries_female' => '',
            'wages_amount' => '',
            'insurance_amount' => '',
            'ppe_amount' => '120.00',
            'obligation_date' => now()->toDateString(),
            'payee' => 'PPE Supplier',
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'beneficiaries_total' => 3,
            'beneficiaries_female' => 0,
            'wages_amount' => 0,
            'insurance_amount' => 0,
            'ppe_amount' => 120,
            'amount' => 120,
            'payee' => 'PPE Supplier',
        ]);
    }

    public function test_formatted_amounts_with_commas_are_accepted(): void
    {
        $project = $this->createForPaymentProject();

        $project->update(['wages_total' => '8000.00', 'total_project_cost' => '8200.00']);

        $this->recordTranches($project, [
            ['wages_amount' => '1,500.50'] + $this->trancheRow('0'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_obligations', [
            'project_id' => $project->id,
            'wages_amount' => 1500.50,
        ]);
    }

    public function test_tranche_without_any_amount_or_required_fields_is_rejected(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [[
            'beneficiaries_total' => 3,
            'wages_amount' => '',
            'insurance_amount' => '',
            'ppe_amount' => '',
            'obligation_date' => '',
            'payee' => '',
        ]])->assertSessionHasErrors([
            'tranches.0.wages_amount',
            'tranches.0.obligation_date',
            'tranches.0.payee',
        ]);

        $this->recordTranches($project, [[
            'beneficiaries_total' => '',
            'wages_amount' => '100.00',
            'obligation_date' => now()->toDateString(),
            'payee' => 'TUPAD Beneficiaries',
        ]])->assertSessionHasErrors('tranches.0.beneficiaries_total');

        $this->assertDatabaseCount('project_obligations', 0);
    }

    public function test_payment_page_shows_wage_formula_and_project_limits(): void
    {
        $project = $this->createForPaymentProject();

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Project Data vs Obligated')
            ->assertSee('beneficiaries × ₱9,100.00', false)
            ->assertSee('Insurance as beneficiaries × ₱50.00 insurance rate', false)
            ->assertSee('data-insurance-per-beneficiary-cents="5000"', false)
            ->assertSee('obligationTrancheLimits', false);
    }

    public function test_complete_saves_entered_rows_locks_tranches_and_redirects_to_project(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [$this->trancheRow('500.00')]);

        $this->completeTranches($project, [
            $this->trancheRow('300.00', '50.00', '150.00'),
            $this->blankRow(),
        ])->assertRedirect(route('projects.show', $project));

        $project->refresh();

        $this->assertNotNull($project->obligations_completed_at);
        $this->assertSame($this->focal->id, $project->obligations_completed_by);
        $this->assertSame(2, $project->obligations()->count());
        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->status);

        $this->recordTranches($project, [$this->trancheRow('1.00')])
            ->assertSessionHasErrors('tranches');

        $this->assertSame(2, $project->obligations()->count());
    }

    public function test_disbursement_belongs_to_the_selected_obligation_tranche(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [
            $this->trancheRow('400.00'),
            $this->trancheRow('400.00'),
        ]);

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

        $this->recordTranches($project, [$this->trancheRow('400.00')]);
        $obligation = $project->obligations()->firstOrFail();

        $this->recordDisbursement(
            $project,
            $obligation,
            '400.01',
            'CHECK-OVER'
        )->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('project_disbursements', 0);
    }

    public function test_full_disbursement_alone_does_not_complete_project(): void
    {
        $project = $this->createForPaymentProject();

        $this->completeTranches($project, [
            $this->trancheRow('800.00', '50.00', '150.00'),
        ]);

        $obligation = $project->obligations()->firstOrFail();

        $this->recordDisbursement(
            $project,
            $obligation,
            '1000.00',
            'LDAP-FULL'
        )->assertSessionHasNoErrors();

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status
        );
    }

    public function test_payment_summary_totals_and_balance_are_calculated_correctly(): void
    {
        $project = $this->createForPaymentProject();

        $this->recordTranches($project, [$this->trancheRow('600.00')]);
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
            ->assertSee('Total Project Cost')
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

        $this->recordTranches($projectOne, [$this->trancheRow('100.00')]);
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

    private function trancheRow(
        string $wages,
        string $insurance = '0.00',
        string $ppe = '0.00',
        int $beneficiaries = 1,
        int $female = 0,
    ): array {
        return [
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => $female,
            'wages_amount' => $wages,
            'insurance_amount' => $insurance,
            'ppe_amount' => $ppe,
            'obligation_date' => now()->toDateString(),
            'payee' => 'TUPAD Beneficiaries',
            'remarks' => 'Phase 5 tranche',
        ];
    }

    private function blankRow(): array
    {
        return [
            'beneficiaries_total' => '',
            'beneficiaries_female' => '',
            'wages_amount' => '',
            'insurance_amount' => '',
            'ppe_amount' => '',
            'obligation_date' => now()->toDateString(),
            'payee' => '',
            'remarks' => '',
        ];
    }

    private function recordTranches(Project $project, array $rows)
    {
        return $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'save',
                'tranches' => $rows,
            ]);
    }

    private function completeTranches(Project $project, array $rows = [])
    {
        return $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'complete',
                'tranches' => $rows,
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

    /**
     * Total Project Cost = ₱800 wages + ₱50 insurance + ₱150 PPE = ₱1,000.
     */
    private function createForPaymentProject(): Project
    {
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
            'wages_total' => '800.00',
            'ppe_total' => '150.00',
            'insurance_rate' => 50,
            'insurance_total' => '50.00',
            'total_project_cost' => '1000.00',
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
