<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalChiefExecutiveAdminPageTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;
    private Province $albay;
    private Municipality $legazpi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $this->albay = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $this->legazpi = Municipality::query()->create([
            'province_id' => $this->albay->id,
            'code' => '050506000',
            'name' => 'Legazpi City',
            'district' => '2nd District',
            'is_city' => true,
            'is_active' => true,
        ]);
    }

    public function test_focal_sees_sidebar_link_and_can_save_mayors_and_governors(): void
    {
        $this->actingAs($this->focal)
            ->get(route('locations.local-chief-executives.edit'))
            ->assertOk()
            ->assertSee('Edit Mayors &amp; Governors', false)
            ->assertSee('Albay')
            ->assertSee('Legazpi City')
            ->assertSee('Governor')
            ->assertSee('Mayor');

        $this->actingAs($this->focal)
            ->put(route('locations.local-chief-executives.update'), [
                'governors' => [$this->albay->id => 'Governor Juan Dela Cruz'],
                'mayors' => [$this->legazpi->id => '  Maria Santos  '],
            ])
            ->assertRedirect(route('locations.local-chief-executives.edit'))
            ->assertSessionHas('success', '2 Mayor / Governor names updated.');

        $albay = $this->albay->fresh();
        $legazpi = $this->legazpi->fresh();

        // The position title is stripped; only the person's name is stored.
        $this->assertSame('Juan Dela Cruz', $albay->governor);
        $this->assertSame($this->focal->id, $albay->governor_updated_by);
        $this->assertNotNull($albay->governor_updated_at);
        $this->assertSame('Maria Santos', $legazpi->mayor);
        $this->assertSame($this->focal->id, $legazpi->mayor_updated_by);
    }

    public function test_blank_name_clears_and_unchanged_names_are_not_touched(): void
    {
        $this->legazpi->forceFill(['mayor' => 'Maria Santos'])->save();

        $this->actingAs($this->focal)
            ->put(route('locations.local-chief-executives.update'), [
                'governors' => [$this->albay->id => ''],
                'mayors' => [$this->legazpi->id => 'Maria Santos'],
            ])
            ->assertSessionHas('success', 'No changes to save.');

        $this->assertNull($this->legazpi->fresh()->mayor_updated_by);

        $this->actingAs($this->focal)
            ->put(route('locations.local-chief-executives.update'), [
                'mayors' => [$this->legazpi->id => ''],
            ]);

        $this->assertNull($this->legazpi->fresh()->mayor);
    }

    public function test_non_focal_cannot_open_or_save_the_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('locations.local-chief-executives.edit'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('locations.local-chief-executives.update'), [
                'mayors' => [$this->legazpi->id => 'Someone'],
            ])
            ->assertForbidden();

        $this->assertNull($this->legazpi->fresh()->mayor);
    }

    public function test_adl_allocation_form_offers_sponsor_partner_and_lce_dropdowns(): void
    {
        $this->albay->forceFill(['governor' => 'Juan Dela Cruz'])->save();
        $this->legazpi->forceFill(['mayor' => 'Maria Santos'])->save();

        $adl = Adl::query()->create([
            'adl_number' => 'ADL-LCE-001',
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $this->focal->id,
        ]);

        AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Legazpi City',
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => '100000.00',
            'grant_amount' => '100000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '100000.00',
            'created_by' => $this->focal->id,
        ]);

        $this->actingAs($this->focal)
            ->get(route('adl.show', $adl))
            ->assertOk()
            ->assertSee('id="fund_sponsor_choice"', false)
            ->assertSee('id="partner_choice"', false)
            ->assertSee('<option value="DOLE Regional Office V"', false)
            ->assertSee('<option value="LGU Legazpi City"', false)
            ->assertSee('Other — type a new fund sponsor')
            ->assertSee('id="lceType"', false)
            ->assertSee('Mayor and Governor')
            ->assertSee('data-province="Albay"', false)
            ->assertSee('"mayor":"Maria Santos"', false)
            ->assertSee('"Albay":"Juan Dela Cruz"', false)
            // Type-to-search: the LCE box offers every encoded Mayor / Governor.
            ->assertSee('list="lceSuggestions"', false)
            ->assertSee('<datalist id="lceSuggestions">', false);
    }

    public function test_allocation_saves_the_lce_text_chosen_in_the_form(): void
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-LCE-002',
            'grants' => '1000000.00',
            'admin_cost' => '0.00',
            'total' => '1000000.00',
            'created_by' => $this->focal->id,
        ]);

        $this->actingAs($this->focal)
            ->post(route('adl.allocations.store', $adl), [
                'fund_sponsor' => 'DOLE Regional Office V',
                'partner' => 'LGU Legazpi City',
                'lce_type' => 'mayor_governor',
                'local_chief_executive_partylist' => 'Mayor Maria Santos / Governor Juan Dela Cruz',
                'province' => 'Albay',
                'municipality' => 'Legazpi City',
                'grant_amount' => '50000.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('adl_allocations', [
            'adl_id' => $adl->id,
            'municipality' => 'Legazpi City',
            'local_chief_executive_partylist' => 'Mayor Maria Santos / Governor Juan Dela Cruz',
        ]);
    }
}
