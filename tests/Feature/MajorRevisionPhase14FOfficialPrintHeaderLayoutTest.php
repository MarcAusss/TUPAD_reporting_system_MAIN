<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase14FOfficialPrintHeaderLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_print_uses_inventory_inspired_header_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->get(route('reports.print', [
            'report_type' => 'physical_financial',
            'group_by' => 'overall',
        ]));

        $response->assertOk()
            ->assertSee('official-print-header', false)
            ->assertSee('TUPAD Reporting System')
            ->assertSee('DOLE Regional Office V')
            ->assertSee('Department of Labor and Employment')
            ->assertSee('Physical and Financial Accomplishment')
            ->assertSee('Generated')
            ->assertSee('Official Report')
            ->assertSee('report-table-wrap', false);
    }

    public function test_inventory_system_body_labels_are_not_copied_into_tupad_reporting_print_body(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->get(route('reports.print', [
                'report_type' => 'fund_status',
                'group_by' => 'province',
            ]))
            ->assertOk()
            ->assertDontSee('PPE Stock Report')
            ->assertDontSee('Call-Off Status')
            ->assertDontSee('Supply Inventory');
    }

    public function test_generated_pdf_contains_same_header_identity_and_page_metadata(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->get(route('reports.export.pdf', [
            'report_type' => 'fund_status',
            'group_by' => 'province',
        ]));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('TUPAD', $content);
        $this->assertStringContainsString('TUPAD Reporting System', $content);
        $this->assertStringContainsString('DEPARTMENT OF LABOR AND EMPLOYMENT', $content);
        $this->assertStringContainsString('Generated', $content);
        $this->assertStringContainsString('Page', $content);
    }
    public function test_monthly_and_quarterly_official_forms_use_dedicated_print_routes(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($admin)
            ->get(route('reports.periodic.print', [
                'form' => 'sprs',
                'fiscal_year' => 2026,
                'month' => 8,
            ]))
            ->assertOk()
            ->assertSee('Statistical Performance Reporting System (SPRS)')
            ->assertSee('MONTHLY REPORT · SPRS')
            ->assertSee('August 2026');

        $this->actingAs($admin)
            ->get(route('reports.periodic.print', [
                'form' => 'cqpr',
                'fiscal_year' => 2026,
                'quarter' => 3,
            ]))
            ->assertOk()
            ->assertSee('Consolidated Quarterly Progress Report')
            ->assertSee('QUARTERLY REPORT · CQPR')
            ->assertSee('Q3 2026');
    }

}
