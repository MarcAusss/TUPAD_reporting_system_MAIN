<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrientationPrintElapsedMonthsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ended_months_print_zero_and_upcoming_months_stay_blank(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');

        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        Province::query()->create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        $html = $this->actingAs($admin)
            ->get(route('reports.periodic.print', ['form' => 'orientations', 'fiscal_year' => 2026]))
            ->assertOk()
            ->getContent();
        $html = preg_replace('/>\s+</', '><', $html);

        $zeroMonth = '<tr><td colspan="4">&nbsp;</td><td class="orientation-center">0</td><td class="orientation-center">0</td>';
        $blankMonth = '<tr><td colspan="9" class="orientation-center">&nbsp;</td></tr>';

        // January–April have ended with no orientation: they print 0.
        $this->assertSame(4, substr_count($html, $zeroMonth));
        // May (current month) through December stay blank.
        $this->assertSame(8, substr_count($html, $blankMonth));

        // Q1 and Q2 have started and show 0; Q3 and Q4 have not and stay blank.
        $this->assertSame(2, preg_match_all('/QUARTER<\/td><td class="orientation-center">0<\/td>/', $html));
        $this->assertSame(2, preg_match_all('/QUARTER<\/td><td><\/td><td><\/td>/', $html));
    }
}
