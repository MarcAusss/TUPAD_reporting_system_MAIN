<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MajorRevisionPhase14AReportsNavigationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_roles_can_open_all_report_workspaces_while_gip_cannot(): void
    {
        $routes = [
            'reports.workspace.physical-financial',
            'reports.workspace.fund-status',
            'reports.workspace.monthly',
            'reports.workspace.quarterly',
            'reports.workspace.geographic-mapping',
        ];

        foreach ([UserRole::ADMIN, UserRole::FOCAL, UserRole::TC] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            foreach ($routes as $route) {
                $this->actingAs($user)
                    ->get(route($route))
                    ->assertOk();
            }
        }

        $gip = User::factory()->create(['role' => UserRole::GIP, 'is_active' => true]);
        foreach ($routes as $route) {
            $this->actingAs($gip)
                ->get(route($route))
                ->assertForbidden();
        }
    }

    public function test_sidebar_reports_dropdown_exposes_five_top_level_report_categories(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('reports.workspace.physical-financial'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Physical &amp; Financial', false)
            ->assertSee('Fund Status')
            ->assertSee('Monthly Reports')
            ->assertSee('Quarterly Reports')
            ->assertSee('Geographic Mapping');
    }

    public function test_physical_financial_workspace_contains_requested_five_views_and_current_data_links(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        $response = $this->actingAs($admin)
            ->get(route('reports.workspace.physical-financial'))
            ->assertOk()
            ->assertSee('Overall Accomplishment')
            ->assertSee('Accomplishment per Quarter')
            ->assertSee('Accomplishment per Month')
            ->assertSee('Short-Term Accomplishment')
            ->assertSee('Long-Term Accomplishment');

        $response->assertSee(route('reports.workspace.physical-financial', [
            'view' => 'quarter',
        ]), false);

    }

    public function test_fund_status_workspace_keeps_nested_fund_utilization_and_all_requested_report_views(): void
    {
        $focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true]);

        $this->actingAs($focal)
            ->get(route('reports.workspace.fund-status'))
            ->assertOk()
            ->assertSee('Fund Utilization Report')
            ->assertSee('TUPAD Allocation')
            ->assertSee('Accomplishment (Obligated)')
            ->assertSee('Balance')
            ->assertSee('Report ADL')
            ->assertSee('Report Province')
            ->assertSee('Report Status')
            ->assertSee('Report Sponsor')
            ->assertSee('Report NGA')
            ->assertSee('Report District')
            ->assertSee('Report LCE');
    }

    public function test_monthly_and_quarterly_workspaces_keep_child_data_inside_their_parent_reports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('reports.workspace.monthly'))
            ->assertOk()
            ->assertSee('Statistical Performance Reporting System (SPRS)')
            ->assertSee('List of Orientations Conducted')
            ->assertSee('AlkanSSSya')
            ->assertSee('YAKAP Program for TUPAD Beneficiaries');

        $this->actingAs($admin)
            ->get(route('reports.workspace.quarterly'))
            ->assertOk()
            ->assertSee('Consolidated Quarterly Progress Report (CQPR)')
            ->assertSee('Number of TUPAD Beneficiaries Referred to Active Labor Market');
    }

    public function test_geographic_mapping_workspace_has_four_mapping_families_and_requested_nested_dimensions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('reports.workspace.geographic-mapping'))
            ->assertOk()
            ->assertSee('Project Mapping')
            ->assertSee('Beneficiary Mapping')
            ->assertSee('Sector Mapping')
            ->assertSee('Intervention-Focus Mapping')
            ->assertSee('Barangay')
            ->assertSee('Priority / Vulnerable Sectors')
            ->assertSee('Occupational / Livelihood Sectors')
            ->assertSee('Persons with Disabilities')
            ->assertSee('Persons Deprived of Liberty')
            ->assertSee('Transport Workers')
            ->assertSee('Administrative, Clerical and Logistical Support');
    }

    public function test_existing_report_generation_and_export_routes_are_preserved_and_workspace_routes_keep_security_middleware(): void
    {
        foreach ([
            'reports.index',
            'reports.print',
            'reports.export.pdf',
            'reports.export.excel',
            'reports.export.csv',
        ] as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName));
        }

        foreach ([
            'reports.workspace.physical-financial',
            'reports.workspace.fund-status',
            'reports.workspace.monthly',
            'reports.workspace.quarterly',
            'reports.workspace.geographic-mapping',
        ] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('province.scope', $middleware);
            $this->assertContains('role:admin,tc,focal', $middleware);
        }
    }
}
