<?php

namespace Tests\Feature;

use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalFinancialTableFirstReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_uses_four_table_views_and_removes_term_views(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.workspace.physical-financial'))
            ->assertOk()
            ->assertSee('Overall Accomplishment')
            ->assertSee('Accomplishment per Semester')
            ->assertSee('Accomplishment per Quarter')
            ->assertSee('Accomplishment per Month')
            ->assertSee('Reformulated Target')
            ->assertSee('Albay')
            ->assertSee('Camarines Norte')
            ->assertSee('TOTAL')
            ->assertDontSee('Short-Term Accomplishment')
            ->assertDontSee('Long-Term Accomplishment');
    }

    public function test_semester_screen_and_print_use_the_supplied_period_layout(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.workspace.physical-financial', [
                'view' => 'semester',
                'fiscal_year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('CY2026')
            ->assertSee('1st Semester')
            ->assertSee('2nd Semester')
            ->assertDontSee('Short-Term')
            ->assertDontSee('Long-Term');

        $this->actingAs($admin)
            ->get(route('reports.print', [
                'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
                'group_by' => ReportDimension::SEMESTER->value,
                'fiscal_year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Letter portrait')
            ->assertSee('1st Semester')
            ->assertSee('2nd Semester')
            ->assertDontSee('Short-Term')
            ->assertDontSee('Long-Term');
    }
}
