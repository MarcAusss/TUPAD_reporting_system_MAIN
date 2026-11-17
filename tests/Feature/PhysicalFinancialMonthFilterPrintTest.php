<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalFinancialMonthFilterPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);
        Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
    }

    public function test_month_view_shows_all_months_by_default_and_one_month_when_filtered(): void
    {
        $all = $this->actingAs($this->focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'month', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('All months')
            ->assertSee('What do you want to print?');

        $this->assertCount(12, $all->viewData('matrix')['periods']);
        $this->assertNull($all->viewData('matrix')['selected_month']);

        $march = $this->actingAs($this->focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'month', 'fiscal_year' => 2026, 'month' => 3]))
            ->assertOk();

        $this->assertSame([['key' => 'month-3', 'label' => 'March']], $march->viewData('matrix')['periods']);
        $this->assertSame(3, $march->viewData('matrix')['selected_month']);
        $this->assertEquals(3, $march->viewData('exportQuery')['month']);
    }

    public function test_month_filter_is_ignored_outside_the_month_view(): void
    {
        $response = $this->actingAs($this->focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'quarter', 'fiscal_year' => 2026, 'month' => 3]))
            ->assertOk()
            ->assertDontSee('All months');

        $this->assertCount(4, $response->viewData('matrix')['periods']);
        $this->assertArrayNotHasKey('month', $response->viewData('exportQuery'));
    }

    public function test_print_all_months_is_one_table_and_single_month_prints_only_that_month(): void
    {
        $query = ['report_type' => 'physical_financial', 'group_by' => 'month', 'fiscal_year' => 2026];

        $all = $this->actingAs($this->focal)->get(route('reports.print', $query))->assertOk();
        $html = $all->getContent();
        $this->assertStringContainsString('class="pf-matrix pf-all-months"', $html);
        $this->assertSame(1, substr_count($html, '<table class="pf-matrix'));
        $this->assertStringContainsString('January', $html);
        $this->assertStringContainsString('December', $html);

        $march = $this->actingAs($this->focal)->get(route('reports.print', [...$query, 'month' => 3]))->assertOk();
        $html = $march->getContent();
        $this->assertStringNotContainsString('class="pf-matrix pf-all-months"', $html);
        $this->assertStringContainsString('March Physical &amp; Financial Accomplishment', $html);
        $this->assertStringNotContainsString('April Physical &amp; Financial Accomplishment', $html);
    }

    public function test_quarter_and_semester_views_offer_the_print_chooser(): void
    {
        $this->actingAs($this->focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'quarter', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('What do you want to print?')
            ->assertSee('All quarters')
            ->assertSee('4th Quarter');

        $this->actingAs($this->focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'semester', 'fiscal_year' => 2026]))
            ->assertOk()
            ->assertSee('What do you want to print?')
            ->assertSee('All semesters')
            ->assertSee('2nd Semester');

        $this->actingAs($this->focal)
            ->get(route('reports.workspace.physical-financial', ['view' => 'overall']))
            ->assertOk()
            ->assertDontSee('What do you want to print?');
    }

    public function test_print_all_quarters_is_one_table_and_single_quarter_prints_only_that_quarter(): void
    {
        $query = ['report_type' => 'physical_financial', 'group_by' => 'quarter', 'fiscal_year' => 2026];

        $html = $this->actingAs($this->focal)->get(route('reports.print', $query))->assertOk()->getContent();
        $this->assertStringContainsString('class="pf-matrix pf-all-quarters"', $html);
        $this->assertSame(1, substr_count($html, '<table class="pf-matrix'));
        $this->assertStringContainsString('Reformulated Target', $html);
        $this->assertStringContainsString('4th Quarter', $html);

        $html = $this->actingAs($this->focal)->get(route('reports.print', [...$query, 'quarter' => 2]))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="pf-matrix pf-all-quarters"', $html);
        $this->assertStringContainsString('2nd Quarter Physical &amp; Financial Accomplishment', $html);
        $this->assertStringNotContainsString('3rd Quarter Physical &amp; Financial Accomplishment', $html);
    }

    public function test_print_all_semesters_is_one_table_and_single_semester_prints_only_that_semester(): void
    {
        $query = ['report_type' => 'physical_financial', 'group_by' => 'semester', 'fiscal_year' => 2026];

        $html = $this->actingAs($this->focal)->get(route('reports.print', $query))->assertOk()->getContent();
        $this->assertStringContainsString('class="pf-matrix pf-all-semesters"', $html);
        $this->assertSame(1, substr_count($html, '<table class="pf-matrix'));
        $this->assertStringContainsString('2nd Semester', $html);

        $html = $this->actingAs($this->focal)->get(route('reports.print', [...$query, 'semester' => 1]))->assertOk()->getContent();
        $this->assertStringNotContainsString('class="pf-matrix pf-all-semesters"', $html);
        $this->assertStringContainsString('1st Semester Physical &amp; Financial Accomplishment', $html);
        $this->assertStringNotContainsString('2nd Semester Physical &amp; Financial Accomplishment', $html);
    }
}
