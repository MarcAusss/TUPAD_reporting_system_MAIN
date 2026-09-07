<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class P2DashboardActionAgingTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_tc_dashboard_exposes_scoped_action_queue_aging_from_status_history(): void
    {
        Carbon::setTestNow('2026-09-06 12:00:00');

        $albay = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);
        $masbate = Province::create([
            'code' => '054100000',
            'name' => 'Masbate',
            'is_active' => true,
        ]);

        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $albay->id,
        ]);

        $oldTssd = $this->createProject($tc, $albay, ProjectStatus::TSSD_EVALUATION, ImplementationMode::DIRECT_ADMINISTRATION, 'Old Albay TSSD');
        $recentCompliance = $this->createProject($tc, $albay, ProjectStatus::FOR_COMPLIANCE, ImplementationMode::DIRECT_ADMINISTRATION, 'Recent Albay Compliance');
        $criticalAcp = $this->createProject($tc, $albay, ProjectStatus::FOR_IMPLEMENTATION, ImplementationMode::THROUGH_ACP, 'Critical Albay ACP');
        $foreign = $this->createProject($tc, $masbate, ProjectStatus::TSSD_EVALUATION, ImplementationMode::DIRECT_ADMINISTRATION, 'Foreign Masbate TSSD');

        $this->ageCurrentStatus($oldTssd, 10);
        $this->ageCurrentStatus($recentCompliance, 2);
        $this->ageCurrentStatus($criticalAcp, 15);
        $this->ageCurrentStatus($foreign, 20);

        $response = $this->actingAs($tc)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Action Queue Aging')
            ->assertSee('Oldest Pending Actions')
            ->assertSee('data-dashboard-queue="tssd"', false)
            ->assertSee('data-dashboard-queue="compliance"', false)
            ->assertSee('data-dashboard-queue="acp_implementation"', false)
            ->assertSee('Critical Albay ACP')
            ->assertDontSee('Foreign Masbate TSSD');

        $data = $response->viewData('actionQueueData');

        $this->assertSame(1, $data['queues']['tssd']['count']);
        $this->assertSame(1, $data['queues']['tssd']['aged_count']);
        $this->assertSame(10, $data['queues']['tssd']['oldest_days']);
        $this->assertSame(1, $data['queues']['compliance']['count']);
        $this->assertSame(0, $data['queues']['compliance']['aged_count']);
        $this->assertSame(1, $data['queues']['acp_implementation']['critical_count']);
        $this->assertSame(15, $data['oldest_days']);
    }

    public function test_focal_dashboard_contains_financial_queues_only(): void
    {
        Carbon::setTestNow('2026-09-06 12:00:00');

        $albay = Province::create([
            'code' => '050500000',
            'name' => 'Albay',
            'is_active' => true,
        ]);

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);
        $tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $albay->id,
        ]);

        $payment = $this->createProject($tc, $albay, ProjectStatus::FOR_PAYMENT, ImplementationMode::DIRECT_ADMINISTRATION, 'DA Payment Action');
        $check = $this->createProject($tc, $albay, ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT, ImplementationMode::THROUGH_ACP, 'ACP Check Action');
        $tssd = $this->createProject($tc, $albay, ProjectStatus::TSSD_EVALUATION, ImplementationMode::DIRECT_ADMINISTRATION, 'TC Only Evaluation');

        $this->ageCurrentStatus($payment, 8);
        $this->ageCurrentStatus($check, 3);
        $this->ageCurrentStatus($tssd, 20);

        $response = $this->actingAs($focal)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Action Queue Aging')
            ->assertSee('DA Payment Action')
            ->assertSee('ACP Check Action');

        $data = $response->viewData('actionQueueData');
        $queues = $data['queues'];

        $this->assertArrayHasKey('payment', $queues);
        $this->assertArrayHasKey('acp_check_release', $queues);
        $this->assertArrayNotHasKey('tssd', $queues);
        $this->assertSame(1, $queues['payment']['aged_count']);
        $this->assertFalse(
            $data['oldest_items']->contains(
                fn (array $item): bool => $item['project_title'] === 'TC Only Evaluation'
            )
        );
    }
    private function createProject(
        User $tc,
        Province $province,
        ProjectStatus $status,
        ImplementationMode $mode,
        string $title,
    ): Project {
        $this->sequence++;

        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-P2-'.str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'province' => $province->name,
            'location' => $province->name,
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => $title,
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU '.$province->name,
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => now()->toDateString(),
            'province_id' => $province->id,
            'province' => $province->name,
            'district' => '2nd District',
            'municipality' => 'Test Municipality',
            'barangay' => 'Test Barangay',
            'implementation_mode' => $mode,
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 20,
            'beneficiaries_female' => 10,
            'wage_rate' => 455,
            'wages_total' => 182000,
            'ppe_total' => 0,
            'insurance_rate' => 50,
            'insurance_beneficiaries' => 20,
            'insurance_total' => 1000,
            'total_project_cost' => 183000,
            'status' => $status,
            'created_by' => $tc->id,
        ]);
    }

    private function ageCurrentStatus(Project $project, int $days): void
    {
        $project->statusHistory()
            ->where('to_status', $project->status->value)
            ->latest('changed_at')
            ->firstOrFail()
            ->update([
                'changed_at' => now()->subDays($days),
            ]);
    }
}
