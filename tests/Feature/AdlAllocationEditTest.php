<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdlAllocationEditTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    private Adl $adl;

    private AdlAllocation $allocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        Province::create(['code' => '054100000', 'name' => 'Masbate', 'is_active' => true]);

        $this->adl = Adl::create(['adl_number' => 'ADL-E-001', 'date_received' => '2026-01-10', 'grants' => 1000000, 'admin_cost' => 50000, 'total' => 1050000, 'created_by' => $this->focal->id]);
        $this->allocation = $this->allocate(300000, 'Albay');
        $this->allocate(500000, 'Masbate');
    }

    public function test_focal_sees_edit_and_updates_an_allocation(): void
    {
        $this->actingAs($this->focal)->get(route('adl.show', $this->adl))
            ->assertOk()
            ->assertSee('data-allocation-edit=', false)
            ->assertSee('id="allocationEditDialog"', false)
            ->assertSee(e(json_encode(route('adl.allocations.update', [$this->adl, $this->allocation]))), false);

        $this->actingAs($this->focal)
            ->put(route('adl.allocations.update', [$this->adl, $this->allocation]), $this->payload([
                'grant_amount' => 400000,
                'admin_cost_amount' => 15000,
                'fund_sponsor' => 'New Sponsor',
                'province' => 'Masbate',
                'district' => '1st District',
            ]))
            ->assertRedirect(route('adl.show', $this->adl).'#allocation-records');

        $this->allocation->refresh();
        $this->assertSame('400000.00', $this->allocation->amount);
        $this->assertSame('400000.00', $this->allocation->grant_amount);
        $this->assertSame('15000.00', $this->allocation->admin_cost_amount);
        $this->assertSame('415000.00', $this->allocation->total_amount);
        $this->assertSame('New Sponsor', $this->allocation->fund_sponsor);
        $this->assertSame('Masbate', $this->allocation->province);
        $this->assertSame('1st District, Masbate', $this->allocation->location);
        $this->assertSame($this->focal->id, $this->allocation->updated_by);
    }

    public function test_grant_cannot_exceed_the_adl_balance_or_drop_below_project_costs(): void
    {
        // ADL 1,000,000 − other allocation 500,000 = 500,000 max.
        $this->actingAs($this->focal)
            ->put(route('adl.allocations.update', [$this->adl, $this->allocation]), $this->payload(['grant_amount' => 500000.01]))
            ->assertSessionHasErrors('edit.grant_amount', null, 'allocationEdit');

        // A failed save reopens the modal for the same allocation.
        $this->followingRedirects()->actingAs($this->focal)
            ->put(route('adl.allocations.update', [$this->adl, $this->allocation]), $this->payload(['grant_amount' => 999999999]))
            ->assertSee('data-reopen="'.$this->allocation->id.'"', false)
            ->assertSee('Grant allocation exceeds what this ADL can give');

        $this->project(183000);

        $this->actingAs($this->focal)
            ->put(route('adl.allocations.update', [$this->adl, $this->allocation]), $this->payload(['grant_amount' => 100000]))
            ->assertSessionHasErrors('edit.grant_amount', null, 'allocationEdit');

        $this->assertSame('300000.00', $this->allocation->fresh()->amount);
    }

    public function test_scope_is_locked_once_projects_use_the_allocation(): void
    {
        $this->project(183000);


        $this->actingAs($this->focal)
            ->put(route('adl.allocations.update', [$this->adl, $this->allocation]), $this->payload(['province' => 'Masbate', 'grant_amount' => 250000]))
            ->assertRedirect();

        $this->allocation->refresh();
        $this->assertSame('Albay', $this->allocation->province);
        $this->assertSame('250000.00', $this->allocation->amount);
    }

    public function test_allocation_must_belong_to_the_adl_and_tc_cannot_edit(): void
    {
        $otherAdl = Adl::create(['adl_number' => 'ADL-E-002', 'grants' => 100, 'admin_cost' => 0, 'total' => 100, 'created_by' => $this->focal->id]);

        $this->actingAs($this->focal)->put(route('adl.allocations.update', [$otherAdl, $this->allocation]), $this->payload())->assertNotFound();

        $tc = User::factory()->create(['role' => UserRole::TC, 'is_active' => true]);
        $this->actingAs($tc)
            ->put(route('adl.allocations.update', [$this->adl, $this->allocation]), $this->payload(['grant_amount' => 1]))
            ->assertForbidden();
    }

    private function payload(array $overrides = []): array
    {
        return ['allocation_id' => $this->allocation->id, 'edit' => array_merge([
            'fund_sponsor' => 'Sponsor',
            'partner' => 'Partner',
            'province' => 'Albay',
            'grant_amount' => 300000,
            'admin_cost_amount' => 0,
        ], $overrides)];
    }

    private function allocate(float $amount, string $province): AdlAllocation
    {
        return AdlAllocation::create([
            'adl_id' => $this->adl->id,
            'fund_sponsor' => 'Sponsor',
            'partner' => 'Partner',
            'province' => $province,
            'location' => $province,
            'amount' => $amount,
            'grant_amount' => $amount,
            'admin_cost_amount' => 0,
            'total_amount' => $amount,
            'created_by' => $this->focal->id,
        ]);
    }

    private function project(float $cost): Project
    {
        return Project::create([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-02-01',
            'project_title' => 'Allocation Project',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'Sponsor',
            'partner' => 'Partner',
            'project_series' => 'Regular TUPAD',
            'tevs_date_verified' => '2026-02-01',
            'province' => 'Albay',
            'district' => '1st District',
            'municipality' => 'Test Municipality',
            'barangay' => 'Test Barangay',
            'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 10,
            'wage_rate' => 455,
            'wages_total' => 182000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_beneficiaries' => 20,
            'insurance_total' => 1000,
            'total_project_cost' => $cost,
            'status' => ProjectStatus::TSSD_EVALUATION,
            'created_by' => $this->focal->id,
        ]);
    }
}
