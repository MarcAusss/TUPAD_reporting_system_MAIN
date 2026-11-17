<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DistrictRepresentative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictRepresentativeAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_sees_sidebar_link_and_can_save_from_the_admin_page(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('reports.district-representatives.edit'))
            ->assertOk()
            ->assertSee('Edit Representatives')
            ->assertSee('District Representatives');

        $entry = DistrictRepresentative::query()->orderBy('id')->first();

        if ($entry === null) {
            $this->markTestSkipped('No district representative rows are seeded by migrations.');
        }

        $this->actingAs($focal)
            ->put(route('reports.district-representatives.update'), [
                'return_to' => 'admin',
                'representatives' => [['id' => $entry->id, 'representative' => 'Hon. Test Name']],
            ])
            ->assertRedirect(route('reports.district-representatives.edit'));

        $this->assertSame('Hon. Test Name', $entry->fresh()->representative);
    }

    public function test_non_focal_cannot_open_the_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        $this->actingAs($user)
            ->get(route('reports.district-representatives.edit'))
            ->assertForbidden();
    }
}
