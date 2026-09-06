<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReformulatedTargetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_focal_can_open_and_save_reformulated_targets(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $albay = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->actingAs($focal)
            ->get(route('reports.reformulated-targets.edit', [
                'fiscal_year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Manage Reformulated Target', false)
            ->assertSee('Albay');

        $this->actingAs($focal)
            ->put(route('reports.reformulated-targets.update'), [
                'fiscal_year' => 2026,
                'targets' => [
                    [
                        'province_id' => $albay->id,
                        'physical_target' => 6518,
                        'financial_target' => '33897700.00',
                    ],
                ],
            ])
            ->assertRedirect(route('reports.reformulated-targets.edit', [
                'fiscal_year' => 2026,
            ]));

        $this->assertDatabaseHas('reformulated_targets', [
            'province_id' => $albay->id,
            'fiscal_year' => 2026,
            'physical_target' => 6518,
            'financial_target_cents' => 3389770000,
            'updated_by' => $focal->id,
        ]);
    }

    public function test_non_focal_users_cannot_edit_reformulated_targets(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);

        $province = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.reformulated-targets.edit'))
            ->assertForbidden();

        $this->actingAs($tc)
            ->put(route('reports.reformulated-targets.update'), [
                'fiscal_year' => 2026,
                'targets' => [
                    [
                        'province_id' => $province->id,
                        'physical_target' => 1,
                        'financial_target' => '1.00',
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_saved_target_feeds_the_physical_financial_report_for_selected_year(): void
    {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $albay = Province::query()->create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $this->actingAs($focal)
            ->put(route('reports.reformulated-targets.update'), [
                'fiscal_year' => 2026,
                'targets' => [
                    [
                        'province_id' => $albay->id,
                        'physical_target' => 6518,
                        'financial_target' => '33897700.00',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial', [
                'view' => 'overall',
                'fiscal_year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('6,518')
            ->assertSee('₱33,897,700.00')
            ->assertSee('Edit Reformulated Target');
    }
}
