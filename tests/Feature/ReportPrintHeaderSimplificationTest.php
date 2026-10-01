<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPrintHeaderSimplificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_landscape_report_print_uses_dole_letterhead_with_only_report_type_and_date_identity_strip(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.print', [
            'report_type' => 'fund_status',
            'group_by' => 'province',
        ]));

        $response->assertOk()
            ->assertSee('dole-official-letterhead', false)
            ->assertSee('report-print-title', false)
            ->assertSee('Rating Guide:')
            ->assertSee('Summary per Province')
            ->assertSee('As of ')
            ->assertDontSee('official-print-header__meta', false)
            ->assertDontSee('official-print-header__title', false);
    }

    public function test_letter_portrait_report_uses_same_header_structure_and_portrait_sizing_rules(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.print', [
            'report_type' => 'physical_financial',
            'group_by' => 'overall',
        ]));

        $response->assertOk()
            ->assertSee('dole-official-letterhead', false)
            ->assertSee('report-print-title', false)
            ->assertSee('Rating Guide:')
            ->assertSee('Physical and Financial Accomplishment')
            ->assertSee('As of ')
            ->assertSee('@page { size: Letter portrait;', false)
            ->assertSee('.print-portrait .dole-official-letterhead__inner', false);
    }

    public function test_periodic_print_uses_the_same_shared_letterhead_and_simplified_identity_strip(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.periodic.print', [
                'form' => 'sprs',
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('dole-official-letterhead', false)
            ->assertSee('report-print-title', false)
            ->assertSee('Rating Guide:')
            ->assertSee('Statistical Performance Reporting System (SPRS)')
            ->assertSee('As of ')
            ->assertDontSee('official-print-header__meta', false);
    }
}
