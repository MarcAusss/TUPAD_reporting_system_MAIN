<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NgaTargetReportPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_links_to_print_with_the_active_filters(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('reports.workspace.nga-targets'))
            ->assertOk()
            ->assertSee(route('reports.workspace.nga-targets.print'), false);

        $this->actingAs($focal)
            ->get(route('reports.workspace.nga-targets', ['q' => 'Albay']))
            ->assertOk()
            ->assertSee(route('reports.workspace.nga-targets.print', ['q' => 'Albay']), false);
    }

    public function test_print_renders_the_official_layout(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $html = $this->actingAs($focal)
            ->get(route('reports.workspace.nga-targets.print', ['q' => 'Albay']))
            ->assertOk()
            ->assertSee('NGA Target Accomplishment')
            ->assertSee('Print Report')
            ->assertSee('Search: Albay')
            ->getContent();

        $this->assertStringContainsString('class="nga-matrix"', $html);
        $this->assertStringContainsString('Municipality', $html);
        $this->assertStringContainsString('No projects match the selected filters.', $html);
    }
}
