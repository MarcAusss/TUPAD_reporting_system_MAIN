<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\ReportDimension;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectAcpCheckRelease;
use App\Models\ProjectAcpLiquidation;
use App\Models\ProjectAcpPayment;
use App\Models\ProjectDisbursement;
use App\Models\ProjectObligation;
use App\Models\User;
use App\Reports\ReportFilters;
use App\Services\Dashboards\ExecutiveDashboardService;
use App\Services\Reports\ReportingDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MajorRevisionPhase12DWorkflowDashboardReportingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $focal;
    private User $tc;
    private User $gip;
    private Adl $adl;
    private AdlAllocation $allocation;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);
        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);
        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);
        $this->gip = User::factory()->create([
            'role' => UserRole::GIP,
            'is_active' => true,
        ]);

        $this->adl = Adl::create([
            'adl_number' => 'ADL-MR12D-001',
            'date_received' => '2026-01-05',
            'grants' => '20000.00',
            'admin_cost' => '0.00',
            'total' => '20000.00',
            'created_by' => $this->focal->id,
        ]);

        $this->allocation = AdlAllocation::create([
            'adl_id' => $this->adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Mixed Implementation Partners',
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => '20000.00',
            'grant_amount' => '20000.00',
            'admin_cost_amount' => '0.00',
            'total_amount' => '20000.00',
            'created_by' => $this->focal->id,
        ]);
    }

    public function test_acp_workflow_queues_follow_existing_role_architecture_and_exclude_direct_administration(): void
    {
        $acpPayment = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::FOR_PAYMENT,
            'ACP Payment Queue Project',
        );
        $directPayment = $this->createProject(
            ImplementationMode::DIRECT_ADMINISTRATION,
            ProjectStatus::FOR_PAYMENT,
            'Direct Administration Payment Queue Project',
        );
        $acpImplementation = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::FOR_IMPLEMENTATION,
            'ACP Implementation Queue Project',
        );
        $this->createAcpCheckRelease($acpImplementation);
        $acpLiquidation = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::FOR_LIQUIDATION,
            'ACP Liquidation Queue Project',
        );
        $this->createAcpCheckRelease($acpLiquidation);

        $this->actingAs($this->focal)
            ->get(route('acp-workflow.payment'))
            ->assertOk()
            ->assertSee($acpPayment->project_title)
            ->assertDontSee($directPayment->project_title);

        $this->actingAs($this->focal)
            ->get(route('acp-workflow.liquidation'))
            ->assertOk()
            ->assertSee($acpLiquidation->project_title);

        $this->actingAs($this->focal)
            ->get(route('acp-workflow.implementation'))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->get(route('acp-workflow.implementation'))
            ->assertOk()
            ->assertSee($acpImplementation->project_title);

        $this->actingAs($this->tc)
            ->get(route('acp-workflow.payment'))
            ->assertForbidden();

        foreach ([
            'acp-workflow.payment',
            'acp-workflow.check-release',
            'acp-workflow.implementation',
            'acp-workflow.liquidation',
        ] as $routeName) {
            $this->actingAs($this->admin)
                ->get(route($routeName))
                ->assertOk();

            $this->actingAs($this->gip)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_role_dashboards_expose_only_the_authorized_acp_workflow_shortcuts(): void
    {
        $this->actingAs($this->focal)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Through ACP Financial Queue')
            ->assertSee('ACP Payment')
            ->assertSee('Check Release')
            ->assertSee('Liquidation')
            ->assertDontSee('ACP Implementation');

        $this->actingAs($this->tc)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Through ACP Workflow')
            ->assertSee('ACP Implementation')
            ->assertDontSee('Through ACP Financial Queue');

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('ACP Payment')
            ->assertSee('Check Release')
            ->assertSee('ACP Implementation')
            ->assertSee('Liquidation');
    }

    public function test_reporting_keeps_da_and_acp_financial_sources_distinct_and_does_not_double_count_liquidation(): void
    {
        $direct = $this->createProject(
            ImplementationMode::DIRECT_ADMINISTRATION,
            ProjectStatus::FOR_PAYMENT,
            'DA Financial Project',
            '1000.00',
        );
        $obligation = $this->createDaObligation($direct, '1000.00');
        ProjectDisbursement::create([
            'project_obligation_id' => $obligation->id,
            'amount' => '600.00',
            'date_disbursed' => '2026-08-24',
            'ldap_check_number' => 'MR12D-DA-001',
            'recorded_by' => $this->focal->id,
        ]);

        $acp = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::PARTIALLY_LIQUIDATED,
            'ACP Financial Project',
            '5000.00',
        );
        $this->createAcpPayment($acp, '5000.00');
        $this->createAcpCheckRelease($acp, '5000.00');
        ProjectAcpLiquidation::create([
            'project_id' => $acp->id,
            'liquidation_date' => '2026-08-29',
            'amount' => '2000.00',
            'liquidation_reference' => 'MR12D-LIQ-001',
            'recorded_by' => $this->focal->id,
        ]);

        $row = app(ReportingDataService::class)
            ->fundStatus(new ReportFilters(), ReportDimension::OVERALL)
            ->sole();

        $this->assertSame(100000, $row['direct_admin_obligated_cents']);
        $this->assertSame(60000, $row['direct_admin_disbursed_cents']);
        $this->assertSame(500000, $row['acp_payment_cents']);
        $this->assertSame(500000, $row['acp_check_released_cents']);
        $this->assertSame(200000, $row['acp_liquidated_cents']);
        $this->assertSame(600000, $row['obligated_cents']);
        $this->assertSame(560000, $row['disbursed_cents']);

        // Liquidation is a separate accountability measure. It is not a second
        // disbursement and therefore must not increase total disbursed funds.
        $this->assertNotSame(760000, $row['disbursed_cents']);
    }

    public function test_implementation_mode_filter_is_consistent_in_reporting_executive_and_presentation_surfaces(): void
    {
        $this->createProject(
            ImplementationMode::DIRECT_ADMINISTRATION,
            ProjectStatus::FOR_IMPLEMENTATION,
            'DA Filter Project',
            '1000.00',
        );
        $acp = $this->createProject(
            ImplementationMode::THROUGH_ACP,
            ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            'ACP Filter Project',
            '5000.00',
        );
        $this->createAcpPayment($acp, '5000.00');

        $filters = ReportFilters::fromArray([
            'implementation_mode' => ImplementationMode::THROUGH_ACP->value,
        ]);

        $physical = app(ReportingDataService::class)
            ->physicalFinancial($filters, ReportDimension::OVERALL)
            ->sole();

        $this->assertSame(1, $physical['project_count']);
        $this->assertSame(500000, $physical['acp_payment_cents']);
        $this->assertSame(0, $physical['direct_admin_obligated_cents']);

        $dashboard = app(ExecutiveDashboardService::class)->build($filters);
        $this->assertSame(1, $dashboard['kpis']['total_projects']);
        $this->assertSame(
            'Through ACP',
            $dashboard['active_filters']['Implementation Mode'] ?? null,
        );

        $query = [
            'implementation_mode' => ImplementationMode::THROUGH_ACP->value,
        ];

        $this->actingAs($this->admin)
            ->get(route('executive-dashboard.index', $query))
            ->assertOk()
            ->assertSee('Implementation Mode: Through ACP')
            ->assertSee('ACP Payment Recorded');

        $this->actingAs($this->admin)
            ->get(route('executive-dashboard.presentation', $query))
            ->assertOk()
            ->assertSee('Implementation Mode: Through ACP')
            ->assertSee('ACP Payment Recorded');

        $this->actingAs($this->admin)
            ->get(route('reports.index', [
                'report_type' => ReportType::FUND_STATUS->value,
                'group_by' => ReportDimension::OVERALL->value,
                ...$query,
            ]))
            ->assertOk()
            ->assertSee('Implementation Mode')
            ->assertSee('Through ACP')
            ->assertSee('ACP Payment Recorded');
    }

    public function test_invalid_cross_mode_status_filter_combinations_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('executive-dashboard.index'))
            ->get(route('executive-dashboard.index', [
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION->value,
                'status' => ProjectStatus::FOR_LIQUIDATION->value,
            ]))
            ->assertRedirect(route('executive-dashboard.index'))
            ->assertSessionHasErrors('status');

        $this->actingAs($this->admin)
            ->from(route('reports.index'))
            ->get(route('reports.index', [
                'report_type' => ReportType::PHYSICAL_FINANCIAL->value,
                'group_by' => ReportDimension::OVERALL->value,
                'implementation_mode' => ImplementationMode::THROUGH_ACP->value,
                'status' => ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value,
            ]))
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors('status');
    }

    private function createProject(
        ImplementationMode $mode,
        ProjectStatus $status,
        string $title,
        string $totalProjectCost = '5000.00',
    ): Project {
        $this->sequence++;

        return Project::create([
            'adl_allocation_id' => $this->allocation->id,
            'date_received' => '2026-08-20',
            'project_title' => $title,
            'nature_of_work' => 'Community work',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'Partner '.$this->sequence,
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'implementation_mode' => $mode,
            'number_of_days' => 10,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => '400.00',
            'wages_total' => $mode === ImplementationMode::DIRECT_ADMINISTRATION
                ? $totalProjectCost
                : '4000.00',
            'ppe_total' => $mode === ImplementationMode::THROUGH_ACP
                ? '500.00'
                : '0.00',
            'insurance_rate' => '50.00',
            'insurance_beneficiaries' => 10,
            'insurance_total' => $mode === ImplementationMode::THROUGH_ACP
                ? '500.00'
                : '0.00',
            'total_project_cost' => $totalProjectCost,
            'status' => $status,
            'created_by' => $this->tc->id,
        ]);
    }

    private function createDaObligation(Project $project, string $amount): ProjectObligation
    {
        return ProjectObligation::create([
            'project_id' => $project->id,
            'tranche_number' => 1,
            'adl_number' => $this->adl->adl_number,
            'fund_sponsor' => $project->fund_sponsor,
            'partner' => $project->partner,
            'project_location' => $project->full_location,
            'term' => $project->term->label(),
            'beneficiaries_total' => $project->beneficiaries_total,
            'beneficiaries_female' => $project->beneficiaries_female,
            'amount' => $amount,
            'obligation_date' => '2026-08-22',
            'month' => '2026-08',
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->focal->id,
        ]);
    }

    private function createAcpPayment(
        Project $project,
        string $amount = '5000.00',
    ): ProjectAcpPayment {
        return ProjectAcpPayment::create([
            'project_id' => $project->id,
            'amount' => $amount,
            'payment_date' => '2026-08-22',
            'payee' => 'Authorized ACP Proponent',
            'payment_reference' => 'MR12D-DV-'.$project->id,
            'recorded_by' => $this->focal->id,
        ]);
    }

    private function createAcpCheckRelease(
        Project $project,
        string $amount = '5000.00',
    ): ProjectAcpCheckRelease {
        return ProjectAcpCheckRelease::create([
            'project_id' => $project->id,
            'check_number' => 'MR12D-CHECK-'.str_pad((string) $project->id, 4, '0', STR_PAD_LEFT),
            'check_date' => '2026-08-23',
            'amount' => $amount,
            'released_date' => '2026-08-23',
            'released_to' => 'Authorized ACP Proponent',
            'recorded_by' => $this->focal->id,
        ]);
    }
}
