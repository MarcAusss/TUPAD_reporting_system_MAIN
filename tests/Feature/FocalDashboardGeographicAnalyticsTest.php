<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTerm;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FocalDashboardGeographicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    /** @var array<string,array{province:Province,municipality:Municipality,barangay:Barangay}> */
    private array $references = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $index = 1;
        foreach (config('tupad_mapping.provinces') as $provinceCode => $definition) {
            $province = Province::query()->create([
                'code' => $provinceCode,
                'name' => $definition['name'],
                'is_active' => true,
            ]);
            $municipality = Municipality::query()->create([
                'province_id' => $province->id,
                'code' => substr($provinceCode, 0, 4).sprintf('%02d', $index).'000',
                'name' => $definition['name'].' Test Municipality',
                'district' => 'Test District',
                'is_city' => false,
                'is_active' => true,
            ]);
            $barangay = Barangay::query()->create([
                'municipality_id' => $municipality->id,
                'code' => substr($municipality->code, 0, 6).'001',
                'name' => $definition['name'].' Test Barangay',
                'is_active' => true,
            ]);

            $this->references[$provinceCode] = compact('province', 'municipality', 'barangay');
            $index++;
        }
    }

    public function test_focal_dashboard_uses_interactive_geographic_chart_instead_of_snapshot_metric_boxes(): void
    {
        $this->actingAs($this->focal)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Geographic Report Analytics')
            ->assertSee('Bicol Geographic Performance')
            ->assertSee('data-dashboard-geographic-analytics', false)
            ->assertSee('Projects')
            ->assertSee('Beneficiaries')
            ->assertSee('Sectors')
            ->assertSee('Intervention Focus')
            ->assertDontSee('Program Snapshot');
    }

    public function test_focal_chart_drills_from_bicol_provinces_to_municipalities_and_barangays(): void
    {
        $albay = $this->references['050500000'];
        $project = $this->project($albay['province']);
        $this->exactLocation($project, $albay['municipality'], $albay['barangay'], 20, 12);

        $region = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', ['family' => 'projects']))
            ->assertOk()
            ->assertJsonPath('level', 'region')
            ->assertJsonCount(6, 'rows');

        $albayRow = collect($region->json('rows'))->firstWhere('name', 'Albay');
        $this->assertNotNull($albayRow);
        $this->assertSame(1, $albayRow['total']);
        $this->assertSame(0, $albayRow['through_acp']);
        $this->assertSame(['Total', 'Through ACP'], collect($region->json('series'))->pluck('label')->all());

        $province = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', [
                'family' => 'beneficiaries',
                'province_id' => $albay['province']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('level', 'province');

        $municipalityRow = collect($province->json('rows'))->firstWhere('id', $albay['municipality']->id);
        $this->assertSame(20, $municipalityRow['total']);
        $this->assertSame(12, $municipalityRow['female']);

        $municipality = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', [
                'family' => 'beneficiaries',
                'province_id' => $albay['province']->id,
                'municipality_id' => $albay['municipality']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('level', 'municipality');

        $barangayRow = collect($municipality->json('rows'))->firstWhere('id', $albay['barangay']->id);
        $this->assertSame(20, $barangayRow['total']);
        $this->assertSame(12, $barangayRow['female']);
    }

    public function test_project_mapping_shows_total_and_through_acp_at_every_geographic_level(): void
    {
        $albay = $this->references['050500000'];

        $directProject = $this->project($albay['province'], ImplementationMode::DIRECT_ADMINISTRATION);
        $this->exactLocation($directProject, $albay['municipality'], $albay['barangay'], 20, 12);

        $acpProject = $this->project($albay['province'], ImplementationMode::THROUGH_ACP);
        $this->exactLocation($acpProject, $albay['municipality'], $albay['barangay'], 15, 9);

        $region = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', ['family' => 'projects']))
            ->assertOk();

        $this->assertSame(['Total', 'Through ACP'], collect($region->json('series'))->pluck('label')->all());
        $albayRegionRow = collect($region->json('rows'))->firstWhere('name', 'Albay');
        $this->assertSame(2, $albayRegionRow['total']);
        $this->assertSame(1, $albayRegionRow['through_acp']);
        $this->assertSame(2, $region->json('summary.projects'));
        $this->assertSame(1, $region->json('summary.through_acp'));

        $province = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', [
                'family' => 'projects',
                'province_id' => $albay['province']->id,
            ]))
            ->assertOk();

        $municipalityRow = collect($province->json('rows'))->firstWhere('id', $albay['municipality']->id);
        $this->assertSame(2, $municipalityRow['total']);
        $this->assertSame(1, $municipalityRow['through_acp']);

        $municipality = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', [
                'family' => 'projects',
                'province_id' => $albay['province']->id,
                'municipality_id' => $albay['municipality']->id,
            ]))
            ->assertOk();

        $barangayRow = collect($municipality->json('rows'))->firstWhere('id', $albay['barangay']->id);
        $this->assertSame(2, $barangayRow['total']);
        $this->assertSame(1, $barangayRow['through_acp']);
        $this->assertLessThanOrEqual($barangayRow['total'], $barangayRow['through_acp']);
    }

    public function test_sector_chart_discloses_that_project_level_sector_counts_are_not_geographically_fabricated(): void
    {
        $response = $this->actingAs($this->focal)
            ->getJson(route('dashboard.geographic-analytics', [
                'family' => 'sectors',
                'sector_group' => 'priority_vulnerable',
            ]))
            ->assertOk()
            ->assertJsonPath('family', 'sectors');

        $this->assertStringContainsString(
            'does not claim those beneficiaries are all members of that sector',
            $response->json('data_note'),
        );
        $this->assertSame(['Total', 'Female'], collect($response->json('series'))->pluck('label')->all());
    }

    public function test_dashboard_geographic_endpoint_is_focal_only(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson(route('dashboard.geographic-analytics', ['family' => 'projects']))
            ->assertForbidden();
    }

    public function test_dashboard_javascript_uses_chartjs_and_bar_click_drilldown(): void
    {
        $javascript = File::get(resource_path('js/dashboard-geographic-analytics.js'));

        $this->assertStringContainsString("import Chart from 'chart.js/auto'", $javascript);
        $this->assertStringContainsString("indexAxis: 'y'", $javascript);
        $this->assertStringContainsString("if (state.level === 'region')", $javascript);
        $this->assertStringContainsString("municipality_id: row.id", $javascript);
    }

    private function project(
        Province $province,
        ImplementationMode $mode = ImplementationMode::DIRECT_ADMINISTRATION,
    ): Project
    {
        $adl = Adl::query()->create([
            'adl_number' => 'ADL-DASH-GEO-'.$province->id.'-'.uniqid(),
            'grants' => '500000.00',
            'admin_cost' => '0.00',
            'total' => '500000.00',
            'created_by' => $this->focal->id,
        ]);
        $allocation = AdlAllocation::query()->create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'location' => $province->name,
            'province' => $province->name,
            'amount' => '200000.00',
            'grant_amount' => '200000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '200000.00',
            'created_by' => $this->focal->id,
        ]);

        return Project::query()->create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-08-01',
            'project_title' => 'Dashboard Geographic Project',
            'nature_of_work' => 'Dashboard geographic analytics regression test.',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => 'Test District',
            'municipality' => 'Test Municipality',
            'barangay' => 'Test Barangay',
            'implementation_mode' => $mode,
            'number_of_days' => 10,
            'term' => ProjectTerm::SHORT_TERM,
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 12,
            'wage_rate' => '500.00',
            'wages_total' => '100000.00',
            'ppe_total' => '5000.00',
            'insurance_rate' => '100.00',
            'insurance_beneficiaries' => 20,
            'insurance_total' => '2000.00',
            'total_project_cost' => '107000.00',
            'status' => ProjectStatus::ONGOING_PROFILING,
            'created_by' => $this->focal->id,
            'updated_by' => $this->focal->id,
        ]);
    }

    private function exactLocation(
        Project $project,
        Municipality $municipality,
        Barangay $barangay,
        int $total,
        int $female,
    ): void {
        $location = ProjectLocation::query()->create([
            'project_id' => $project->id,
            'province_id' => $municipality->province_id,
            'municipality_id' => $municipality->id,
            'district' => $municipality->district,
            'sort_order' => 1,
        ]);

        $location->barangays()->attach($barangay->id, [
            'beneficiaries_total' => $total,
            'beneficiaries_female' => $female,
        ]);
    }
}
