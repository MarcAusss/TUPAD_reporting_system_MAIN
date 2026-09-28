<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\ProjectStatusEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseOfAssistanceTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;
    private User $tc;
    private Province $province;

    protected function setUp(): void
    {
        parent::setUp();

        $this->province = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);

        $this->focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->province->id,
        ]);
    }

    public function test_tc_records_release_of_assistance_after_tranches_are_completed(): void
    {
        $project = $this->createProject();
        $this->completeTranches($project);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertOk()
            ->assertSee('Record the Release of Assistance')
            ->assertSee('Mode of Payment')
            ->assertSee('Date of Payout')
            ->assertSee('Save Release of Assistance');

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Obligation tranches completed')
            ->assertDontSee('Save Tranches');

        $this->actingAs($this->focal)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Waiting for the Release of Assistance');

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', $project), $this->releasePayload(now()->addDays(5)))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('project_payouts', [
            'project_id' => $project->id,
            'payout_mode' => 'Cash',
            'venue' => 'Barangay Rawis Covered Court',
            'recorded_by' => $this->tc->id,
        ]);
    }

    public function test_release_is_blocked_before_tranches_are_completed(): void
    {
        $project = $this->createProject();

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', $project), $this->releasePayload(now()))
            ->assertForbidden();

        $this->assertDatabaseCount('project_payouts', 0);
    }

    public function test_focal_cannot_record_release_of_assistance(): void
    {
        $project = $this->createProject();
        $this->completeTranches($project);

        $this->actingAs($this->focal)
            ->post(route('projects.release-of-assistance.store', $project), $this->releasePayload(now()))
            ->assertForbidden();

        $this->assertDatabaseCount('project_payouts', 0);
    }

    public function test_release_requires_mode_date_and_venue(): void
    {
        $project = $this->createProject();
        $this->completeTranches($project);

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', $project), [
                'payout_mode' => 'Carrier Pigeon',
            ])
            ->assertSessionHasErrors(['payout_mode', 'payout_date', 'venue']);
    }

    public function test_future_payout_date_keeps_project_for_payment_until_date_is_reached(): void
    {
        $project = $this->createProject();
        $this->completeTranches($project);
        $this->fullyDisburse($project);

        $payoutDate = CarbonImmutable::now('Asia/Manila')->addDays(3)->startOfDay();

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', $project), $this->releasePayload($payoutDate));

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);

        $engine = app(ProjectStatusEngine::class);

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $engine->synchronize($project->fresh(), today: $payoutDate->subDay())
        );

        $this->assertSame(
            ProjectStatus::COMPLETED,
            $engine->synchronize($project->fresh(), today: $payoutDate)
        );
    }

    public function test_project_completes_immediately_when_payout_date_has_arrived_and_fully_disbursed(): void
    {
        $project = $this->createProject();
        $this->completeTranches($project);
        $this->fullyDisburse($project);

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', $project), $this->releasePayload(now()->subDay()));

        $this->assertSame(ProjectStatus::COMPLETED, $project->fresh()->status);
    }

    public function test_project_stays_incomplete_while_not_fully_disbursed(): void
    {
        $project = $this->createProject();
        $this->completeTranches($project);

        $obligation = $project->obligations()->firstOrFail();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.disbursements.store', [$project, $obligation]), [
                'amount' => '500.00',
                'date_disbursed' => now()->toDateString(),
                'ldap_check_number' => 'LDAP-PARTIAL',
            ]);

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', $project), $this->releasePayload(now()->subDay()));

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);
    }

    public function test_release_of_assistance_queue_lists_projects_waiting_for_the_tc(): void
    {
        $waiting = $this->createProject('Waiting Release Project');
        $this->completeTranches($waiting);

        $notYetCompleted = $this->createProject('Tranches Still Open Project');

        $this->actingAs($this->tc)
            ->get(route('project-workflow.index', ['queue' => 'release-of-assistance']))
            ->assertOk()
            ->assertSee('Waiting Release Project')
            ->assertDontSee('Tranches Still Open Project');

        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonFragment(['project_title' => 'Waiting Release Project'])
            ->assertJsonMissing(['project_title' => $notYetCompleted->project_title]);
    }

    private function releasePayload($payoutDate): array
    {
        return [
            'payout_mode' => 'Cash',
            'payout_date' => $payoutDate->format('Y-m-d'),
            'venue' => 'Barangay Rawis Covered Court',
            'remarks' => 'Payout schedule',
        ];
    }

    private function completeTranches(Project $project): void
    {
        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => 'complete',
                'tranches' => [[
                    'beneficiaries_total' => 10,
                    'beneficiaries_female' => 6,
                    'wages_amount' => '800.00',
                    'insurance_amount' => '50.00',
                    'ppe_amount' => '150.00',
                    'obligation_date' => now()->toDateString(),
                    'payee' => 'TUPAD Beneficiaries',
                ]],
            ])
            ->assertSessionHasNoErrors();
    }

    private function fullyDisburse(Project $project): void
    {
        $obligation = $project->obligations()->firstOrFail();

        $this->actingAs($this->focal)
            ->post(route('projects.payment.disbursements.store', [$project, $obligation]), [
                'amount' => '1000.00',
                'date_disbursed' => now()->toDateString(),
                'ldap_check_number' => 'LDAP-FULL-'.$project->id,
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Total Project Cost = ₱800 wages + ₱50 insurance + ₱150 PPE = ₱1,000.
     */
    private function createProject(string $title = 'Release of Assistance Project'): Project
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-ROA-'.uniqid(),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'location' => 'Albay',
            'province' => 'Albay',
            'amount' => 1000000,
            'created_by' => $this->focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => now()->toDateString(),
            'project_title' => $title,
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'province' => 'Albay',
            'district' => '2nd District',
            'municipality' => 'Legazpi City',
            'barangay' => 'Rawis',
            'province_id' => $this->province->id,
            'implementation_mode' => 'direct_administration',
            'number_of_days' => 20,
            'term' => 'short_term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wage_rate' => 455,
            'wages_total' => '800.00',
            'ppe_total' => '150.00',
            'insurance_rate' => 50,
            'insurance_total' => '50.00',
            'total_project_cost' => '1000.00',
            'status' => ProjectStatus::FOR_PAYMENT,
            'created_by' => $this->tc->id,
        ]);
    }
}
