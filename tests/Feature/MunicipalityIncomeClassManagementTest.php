<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\MunicipalityIncomeClasses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalityIncomeClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $daraga;

    private Municipality $legazpi;

    protected function setUp(): void
    {
        parent::setUp();

        $albay = Province::create(['code' => '0505', 'name' => 'Albay', 'is_active' => true]);
        $this->daraga = Municipality::create(['province_id' => $albay->id, 'code' => '050504000', 'name' => 'Daraga', 'is_active' => true]);
        $this->legazpi = Municipality::create(['province_id' => $albay->id, 'code' => '050506000', 'name' => 'City of Legazpi', 'is_city' => true, 'is_active' => true]);
    }

    public function test_initial_list_fills_blanks_without_overwriting_existing_values(): void
    {
        $this->legazpi->update(['income_class' => '2nd Class']);

        MunicipalityIncomeClasses::applyInitial();

        $this->assertSame('1st Class', $this->daraga->fresh()->income_class);
        $this->assertSame('2nd Class', $this->legazpi->fresh()->income_class);
    }

    public function test_focal_can_edit_income_classes(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('locations.income-classes.edit'))
            ->assertOk()
            ->assertSee('Daraga')
            ->assertSee('City of Legazpi');

        $this->actingAs($focal)
            ->put(route('locations.income-classes.update'), [
                'income_classes' => [
                    $this->daraga->id => '3rd Class',
                    $this->legazpi->id => 'Component City',
                ],
            ])
            ->assertRedirect(route('locations.income-classes.edit'))
            ->assertSessionHas('success');

        $daraga = $this->daraga->fresh();
        $this->assertSame('3rd Class', $daraga->income_class);
        $this->assertSame($focal->id, $daraga->income_class_updated_by);
        $this->assertNotNull($daraga->income_class_updated_at);
        $this->assertSame('Component City', $this->legazpi->fresh()->income_class);
    }

    public function test_unknown_income_class_is_rejected(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->put(route('locations.income-classes.update'), [
                'income_classes' => [$this->daraga->id => '9th Class'],
            ])
            ->assertSessionHasErrors('income_classes.'.$this->daraga->id);

        $this->assertNull($this->daraga->fresh()->income_class);
    }

    public function test_non_focal_users_cannot_edit_income_classes(): void
    {
        foreach ([UserRole::ADMIN, UserRole::TC] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user)
                ->put(route('locations.income-classes.update'), [
                    'income_classes' => [$this->daraga->id => '3rd Class'],
                ])
                ->assertForbidden();
        }

        $this->assertNull($this->daraga->fresh()->income_class);
    }
}
