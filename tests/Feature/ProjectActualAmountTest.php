<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reports use the actual amount (total of all obligation tranches once the
 * Focal completes them, or the ACP payment) instead of the proposed Total
 * Project Cost.
 */
class ProjectActualAmountTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
    }

    public function test_direct_administration_uses_proposed_amount_until_obligations_are_completed(): void
    {
        $project = $this->createProject(ImplementationMode::DIRECT_ADMINISTRATION);
        $this->addObligation($project, 1, '100000.00');

        $this->assertSame(457500.0, $project->fresh()->proposedAmount());
        $this->assertNull($project->fresh()->actualAmount());
        $this->assertSame(457500.0, $project->fresh()->reportAmount());
    }

    public function test_direct_administration_uses_total_of_all_tranches_after_completion(): void
    {
        $project = $this->createProject(ImplementationMode::DIRECT_ADMINISTRATION);
        $this->addObligation($project, 1, '200000.50');
        $this->addObligation($project, 2, '150000.25');
        $project->forceFill(['obligations_completed_at' => now(), 'obligations_completed_by' => $this->focal->id])->save();

        $fresh = $project->fresh();

        $this->assertSame(457500.0, $fresh->proposedAmount());
        $this->assertSame(350000.75, $fresh->actualAmount());
        $this->assertSame(350000.75, $fresh->reportAmount());

        // Beneficiaries follow the tranches too (2 tranches × 10 / 5 female).
        $this->assertSame(20, $fresh->actualBeneficiaries());
        $this->assertSame(20, $fresh->reportBeneficiaries());
        $this->assertSame(10, $fresh->reportFemaleBeneficiaries());
    }

    public function test_beneficiaries_use_approved_counts_until_obligations_are_completed(): void
    {
        $project = $this->createProject(ImplementationMode::DIRECT_ADMINISTRATION);
        $this->addObligation($project, 1, '100000.00');

        $fresh = $project->fresh();

        $this->assertNull($fresh->actualBeneficiaries());
        $this->assertSame(50, $fresh->reportBeneficiaries());
        $this->assertSame(25, $fresh->reportFemaleBeneficiaries());
    }

    public function test_through_acp_uses_acp_payment_amount_once_recorded(): void
    {
        $project = $this->createProject(ImplementationMode::THROUGH_ACP);

        $this->assertSame(457500.0, $project->fresh()->reportAmount());

        $project->acpPayment()->create([
            'amount' => '400000.00',
            'payment_date' => '2026-09-01',
            'payee' => 'ACP Proponent',
            'recorded_by' => $this->focal->id,
        ]);

        $this->assertSame(400000.0, $project->fresh()->actualAmount());
        $this->assertSame(400000.0, $project->fresh()->reportAmount());
    }

    private function addObligation(Project $project, int $tranche, string $amount): void
    {
        $project->obligations()->create([
            'tranche_number' => $tranche,
            'adl_number' => 'ADL-AA-001',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_location' => 'Rawis, Legazpi City, Albay',
            'term' => 'Short Term',
            'month' => 'September 2026',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 5,
            'wages_amount' => $amount,
            'amount' => $amount,
            'obligation_date' => '2026-09-01',
            'payee' => 'Payee '.$tranche,
            'recorded_by' => $this->focal->id,
        ]);
    }

    private function createProject(ImplementationMode $mode): Project
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-AA-'.uniqid(),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Actual Amount Project',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => '2026-08-01',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => $mode,
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 50,
            'beneficiaries_female' => 25,
            'insurance_beneficiaries' => 50,
            'wage_rate' => 455,
            'wages_total' => 455000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_total' => 2500,
            'total_project_cost' => 457500,
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->focal->id,
        ]);
    }
}
