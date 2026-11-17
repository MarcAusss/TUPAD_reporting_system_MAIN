<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\ReformulatedTarget;
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
                        'short_term_physical_target' => 6000,
                        'short_term_financial_target' => '30000000.00',
                        'long_term_physical_target' => 518,
                        'long_term_financial_target' => '3897700.00',
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
            'short_term_physical_target' => 6000,
            'short_term_financial_target_cents' => 3000000000,
            'long_term_physical_target' => 518,
            'long_term_financial_target_cents' => 389770000,
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
                        'short_term_physical_target' => 1,
                        'short_term_financial_target' => '1.00',
                        'long_term_physical_target' => 0,
                        'long_term_financial_target' => '0.00',
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
                        'short_term_physical_target' => 6000,
                        'short_term_financial_target' => '30000000.00',
                        'long_term_physical_target' => 518,
                        'long_term_financial_target' => '3897700.00',
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

    public function test_overall_report_offers_term_table_using_the_saved_split(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $albay = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        ReformulatedTarget::query()->create([
            'province_id' => $albay->id,
            'fiscal_year' => 2026,
            'physical_target' => 6518,
            'financial_target_cents' => 3389770000,
            'short_term_physical_target' => 6000,
            'short_term_financial_target_cents' => 3000000000,
            'long_term_physical_target' => 518,
            'long_term_financial_target_cents' => 389770000,
        ]);

        $response = $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'overall', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('By Term (Short / Long)')
            ->assertSee('₱30,000,000.00')
            ->assertSee('₱3,897,700.00')
            ->assertDontSee('Target not yet split by term');

        $albayRow = collect($response->viewData('matrix')['rows'])->firstWhere('province', 'Albay');
        $this->assertSame(['physical' => 6000, 'financial_cents' => 3000000000], $albayRow['terms']['short_term']['target']);
        $this->assertSame(['physical' => 518, 'financial_cents' => 389770000], $albayRow['terms']['long_term']['balance']);
    }

    public function test_semester_report_offers_term_table_split_by_semester(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $albay = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        ReformulatedTarget::query()->create([
            'province_id' => $albay->id,
            'fiscal_year' => 2026,
            'physical_target' => 6518,
            'financial_target_cents' => 3389770000,
            'short_term_physical_target' => 6000,
            'short_term_financial_target_cents' => 3000000000,
            'long_term_physical_target' => 518,
            'long_term_financial_target_cents' => 389770000,
        ]);

        $response = $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'semester', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('By Term (Short / Long)')
            ->assertSee('₱30,000,000.00');

        $albayRow = collect($response->viewData('matrix')['rows'])->firstWhere('province', 'Albay');
        $this->assertSame(['semester-1', 'semester-2'], array_keys($albayRow['terms']['short_term']['periods']));
        $this->assertSame(['physical' => 0, 'financial_cents' => 0], $albayRow['terms']['long_term']['periods']['semester-2']);

        $total = $response->viewData('matrix')['total'];
        $this->assertSame(['physical' => 518, 'financial_cents' => 389770000], $total['terms']['long_term']['target']);

        // Quarter view gets the same term table with four quarters.
        $quarter = $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'quarter', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('By Term (Short / Long)')
            ->assertSee('4th Quarter');

        $quarterRow = collect($quarter->viewData('matrix')['rows'])->firstWhere('province', 'Albay');
        $this->assertSame(['quarter-1', 'quarter-2', 'quarter-3', 'quarter-4'], array_keys($quarterRow['terms']['short_term']['periods']));
        $this->assertSame(['physical' => 6000, 'financial_cents' => 3000000000], $quarterRow['terms']['short_term']['balance']);

        // Month view keeps the combined table only.
        $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'month', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertDontSee('By Term (Short / Long)');
    }

    public function test_combined_only_target_is_flagged_as_not_split_in_term_table(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        $albay = Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        ReformulatedTarget::query()->create([
            'province_id' => $albay->id,
            'fiscal_year' => 2026,
            'physical_target' => 6518,
            'financial_target_cents' => 3389770000,
        ]);

        $response = $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'overall', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('Target not yet split by term');

        $albayRow = collect($response->viewData('matrix')['rows'])->firstWhere('province', 'Albay');
        $this->assertNull($albayRow['terms']['short_term']['target']);
        $this->assertSame(6518, $albayRow['target']['physical']);

        $this->actingAs($focal)
            ->get(route('reports.reformulated-targets.edit', ['fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('Saved total, not yet split');
    }
}
