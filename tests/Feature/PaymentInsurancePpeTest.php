<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use App\Services\Payments\ProjectPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payment of Wages → Insurance and PPE.
 *
 * Project: 100 beneficiaries, ₱455 × 1 day wages, ₱50 insurance, and PPE
 * T-shirt ₱120, Rubber Boots ₱350, Gloves ₱60 (one each per beneficiary).
 * Paying only 90 beneficiaries deducts 10 from every component.
 */
class PaymentInsurancePpeTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;
    private User $tc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);
    }

    public function test_insurance_and_each_ppe_item_deduct_the_beneficiaries_not_included(): void
    {
        $project = $this->project();

        $computed = app(ProjectPaymentService::class)->insurancePpeComputation($project, 90);

        $this->assertSame(10, $computed['beneficiaries_deducted']);
        $this->assertSame(50000, $computed['insurance']['deducted_cents']);       // 10 × ₱50
        $this->assertSame(450000, $computed['insurance']['amount_cents']);        // ₱4,500
        $this->assertSame(
            [1080000, 3150000, 540000],                                           // ₱10,800 · ₱31,500 · ₱5,400
            array_column($computed['ppe_items'], 'amount_cents'),
        );
        $this->assertSame(530000, $computed['ppe']['deducted_cents']);            // 10 × (120 + 350 + 60)
        $this->assertSame(4770000, $computed['ppe']['amount_cents']);
    }

    public function test_insurance_and_ppe_form_saves_server_computed_amounts_separately_from_tranches(): void
    {
        $project = $this->project();
        $this->saveTranche($project, 90);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), [
                'beneficiaries_paid' => 90,
                'insurance_ppe_remarks' => 'Ten beneficiaries did not report.',
                // No amounts posted: everything is auto-computed.
            ])
            ->assertRedirect(route('payments.show', $project).'#insurance-ppe')
            ->assertSessionHasNoErrors();

        $record = $project->fresh()->paymentInsurancePpe;

        $this->assertSame(90, $record->beneficiaries_paid);
        $this->assertSame(10, $record->beneficiaries_deducted);
        $this->assertSame('4500.00', $record->insurance_amount);
        $this->assertSame('47700.00', $record->ppe_amount);
        $this->assertSame('5300.00', $record->ppe_deducted);
        $this->assertCount(3, $record->ppe_breakdown);
        $this->assertSame('Ten beneficiaries did not report.', $record->remarks);

        // The tranche itself still carries wages only.
        $obligation = $project->obligations()->first();
        $this->assertSame('40950.00', $obligation->wages_amount);
        $this->assertSame('0.00', $obligation->insurance_amount);
        $this->assertSame('0.00', $obligation->ppe_amount);
    }

    public function test_auto_computed_values_can_be_edited_but_not_above_the_project_plan(): void
    {
        $project = $this->project();
        $this->saveTranche($project, 90);
        [$tshirt, $boots] = $project->ppeItems->values()->all();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), [
                'beneficiaries_paid' => 90,
                'insurance_beneficiaries' => 92,
                'insurance_amount' => '4,600.00',
                'ppe' => [
                    $tshirt->id => ['beneficiaries' => 95, 'amount' => '11,400.00'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $record = $project->fresh()->paymentInsurancePpe;
        $breakdown = collect($record->ppe_breakdown)->keyBy('ppe_item_id');

        $this->assertSame(92, $record->insurance_beneficiaries);
        $this->assertSame('4600.00', $record->insurance_amount);
        $this->assertSame(1140000, $breakdown[$tshirt->id]['amount_cents']);
        $this->assertSame(5, $breakdown[$tshirt->id]['beneficiaries_deducted']);
        // Not edited → still auto-computed (90 × ₱350).
        $this->assertSame(3150000, $breakdown[$boots->id]['amount_cents']);
        $this->assertSame('48300.00', $record->ppe_amount);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), [
                'beneficiaries_paid' => 90,
                'insurance_amount' => '5,000.01',
                'ppe' => [$boots->id => ['amount' => '35,000.01']],
            ])
            ->assertSessionHasErrors(['insurance_amount']);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), [
                'beneficiaries_paid' => 90,
                'ppe' => [$boots->id => ['amount' => '35,000.01']],
            ])
            ->assertSessionHasErrors(["ppe.{$boots->id}.amount"]);
    }

    public function test_completing_keeps_edited_values_saved_for_the_same_beneficiaries(): void
    {
        $project = $this->project();
        $this->saveTranche($project, 90);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), [
                'beneficiaries_paid' => 90,
                'insurance_amount' => '4,600.00',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), ['intent' => 'complete', 'tranches' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame('4600.00', $project->fresh()->paymentInsurancePpe->insurance_amount);
    }

    public function test_form_stays_disabled_when_no_beneficiary_is_deducted(): void
    {
        $project = $this->project();
        $this->saveTranche($project, 100);

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('No beneficiaries deducted')
            ->assertSee('<fieldset data-ip-fields disabled', false);

        // Nothing deducted → nothing to save.
        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), ['beneficiaries_paid' => 100])
            ->assertSessionHasErrors('beneficiaries_paid');

        $this->assertNull($project->fresh()->paymentInsurancePpe);
    }

    public function test_completing_with_every_beneficiary_paid_keeps_insurance_and_ppe_as_planned(): void
    {
        $project = $this->project();

        // Saved while 10 were deducted, then the tranches end up covering all 100.
        $this->saveTranche($project, 90);
        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), ['beneficiaries_paid' => 90])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'complete',
                'tranches' => [$this->trancheRow(10)],
            ])
            ->assertSessionHasNoErrors();

        $project->refresh();

        $this->assertNull($project->paymentInsurancePpe);
        // Wages ₱45,500 + planned insurance ₱5,000 + planned PPE ₱53,000.
        $this->assertSame(103500.0, $project->actualAmount());
    }

    public function test_form_counts_tranche_beneficiaries_still_being_entered(): void
    {
        $project = $this->project();
        $this->saveTranche($project, 40);

        // 40 saved + 50 typed in the tranche form but not yet saved.
        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), ['beneficiaries_paid' => 90])
            ->assertSessionHasNoErrors();

        $this->assertSame(90, $project->fresh()->paymentInsurancePpe->beneficiaries_paid);

        // Never fewer than the beneficiaries already saved in tranches.
        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), ['beneficiaries_paid' => 30])
            ->assertSessionHasErrors('beneficiaries_paid');

        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), ['beneficiaries_paid' => 101])
            ->assertSessionHasErrors('beneficiaries_paid');
    }

    public function test_completing_tranches_syncs_insurance_and_ppe_and_actual_amount_includes_both(): void
    {
        $project = $this->project();

        // Saved earlier for 95, but the tranches finish at 90.
        $this->saveTranche($project, 50);
        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), [
                'beneficiaries_paid' => 95,
                'insurance_ppe_remarks' => 'Keep me',
            ]);

        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'complete',
                'tranches' => [$this->trancheRow(40)],
            ])
            ->assertSessionHasNoErrors();

        $project->refresh();
        $record = $project->paymentInsurancePpe;

        $this->assertSame(90, $record->beneficiaries_paid);
        $this->assertSame('4500.00', $record->insurance_amount);
        $this->assertSame('47700.00', $record->ppe_amount);
        $this->assertSame('Keep me', $record->remarks);

        // Wages 90 × ₱455 + insurance ₱4,500 + PPE ₱47,700.
        $this->assertSame(93150.0, $project->actualAmount());

        // Locked once the tranches are complete.
        $this->actingAs($this->focal)
            ->post(route('projects.payment.insurance-ppe.store', $project), ['beneficiaries_paid' => 90])
            ->assertSessionHasErrors('beneficiaries_paid');
    }

    public function test_payment_page_shows_the_insurance_and_ppe_form(): void
    {
        $project = $this->project();
        $this->saveTranche($project, 90);

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Insurance and PPE')
            ->assertSee('Save Insurance and PPE')
            ->assertSee('name="beneficiaries_paid"', false)
            ->assertSee('name="insurance_amount"', false)
            ->assertSee('value="4,500.00"', false)       // 90 × ₱50
            ->assertSee('value="10,800.00"', false)      // T-shirt 90 × ₱120
            ->assertSee('value="31,500.00"', false)      // Rubber Boots 90 × ₱350
            ->assertSee('value="5,400.00"', false)       // Gloves 90 × ₱60
            ->assertSee('T-shirt')
            ->assertSee('Rubber Boots')
            ->assertSee('Gloves');
    }

    private function saveTranche(Project $project, int $beneficiaries): void
    {
        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'save',
                'tranches' => [$this->trancheRow($beneficiaries)],
            ])
            ->assertSessionHasNoErrors();
    }

    private function trancheRow(int $beneficiaries): array
    {
        return [
            'beneficiaries_total' => $beneficiaries,
            'beneficiaries_female' => 0,
            'wages_amount' => number_format($beneficiaries * 455, 2, '.', ''),
            'obligation_date' => now()->toDateString(),
            'payee' => 'TUPAD Beneficiaries',
        ];
    }

    private function project(): Project
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-IPPE-0001',
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
            'project_title' => 'Insurance and PPE Project',
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
            'number_of_days' => 1,
            'term' => 'short_term',
            'beneficiaries_total' => 100,
            'beneficiaries_female' => 0,
            'wage_rate' => 455,
            'wages_total' => '45500.00',
            'ppe_total' => '53000.00',
            'insurance_rate' => 50,
            'insurance_beneficiaries' => 100,
            'insurance_total' => '5000.00',
            'total_project_cost' => '103500.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->tc->id,
        ]);

        foreach ([['T-shirt', 120], ['Rubber Boots', 350], ['Gloves', 60]] as [$product, $unit]) {
            $project->ppeItems()->create([
                'ppe_type' => 'non_hazardous',
                'product' => $product,
                'beneficiary_count' => 100,
                'quantity' => 1,
                'unit_amount' => $unit,
                'total_amount' => $unit * 100,
            ]);
        }

        $project->approval()->create([
            'approval_date' => now()->toDateString(),
            'project_code' => 'IPPE-CODE-1',
            'approved_by' => $this->tc->id,
            'approved_at' => now(),
        ]);

        return $project;
    }
}
