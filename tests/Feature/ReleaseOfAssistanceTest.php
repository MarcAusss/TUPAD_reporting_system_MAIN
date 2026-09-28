<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\ProjectReleaseOfAssistanceController;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use App\Services\Projects\ProjectStatusEngine;
use App\Services\Projects\ProjectWorkspacePresenter;
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

    public function test_release_is_blocked_until_the_tranche_is_fully_disbursed(): void
    {
        $project = $this->createProject();
        $tranche = $this->saveTranche($project, '800.00', '50.00', '150.00');

        $this->release($project, $tranche, now())->assertForbidden();

        $this->disburse($project, $tranche, '400.00');
        $this->release($project, $tranche, now())->assertForbidden();

        $this->disburse($project, $tranche, '600.00');
        $this->release($project, $tranche, now()->addDays(3))->assertSessionHasNoErrors();

        $tranche->refresh();
        $this->assertSame('(Actual) Cash Payout', $tranche->release_mode);
        $this->assertSame('Barangay Rawis Covered Court', $tranche->release_venue);
        $this->assertSame($this->tc->id, $tranche->released_by);
        $this->assertNotNull($tranche->released_at);
    }

    public function test_tc_is_notified_only_after_a_tranche_is_fully_disbursed(): void
    {
        $project = $this->createProject('Newly Obligated Project');
        $tranche = $this->saveTranche($project, '800.00');

        $this->assertNotInTcFeed('Newly Obligated Project');

        $this->disburse($project, $tranche, '300.00');
        $this->assertNotInTcFeed('Newly Obligated Project');

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertOk()
            ->assertSee('Waiting for the Focal to obligate and disburse')
            ->assertSee('Waiting for the Focal to fully disburse this tranche')
            ->assertDontSee('Save Release of Assistance');

        $this->disburse($project, $tranche, '500.00')
            ->assertSessionHas('success', fn (string $message): bool =>
                str_contains($message, 'the TUPAD Coordinator has been notified to record its Release of Assistance'));

        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonFragment([
                'project_title' => 'Newly Obligated Project',
                'action_label' => 'Release of Assistance',
                'url' => route('projects.show', ['project' => $project, 'workspace' => 'workflow']).'#release-of-assistance',
            ])
            ->assertJsonFragment(['title' => 'Release of Assistance']);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertOk()
            ->assertSee('Record the Release of Assistance')
            ->assertSee('Ready for Release of Assistance')
            ->assertSee('Save Release of Assistance');

        $this->release($project, $tranche, now()->addDays(2))->assertSessionHasNoErrors();

        $this->assertNotInTcFeed('Newly Obligated Project');

        // After the release, the TC waits for the Focal again.
        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertSee('Waiting for the Focal to obligate and disburse')
            ->assertSee('Released · payout date pending')
            ->assertSee('Barangay Rawis Covered Court');
    }

    public function test_focal_cannot_record_release_of_assistance(): void
    {
        $project = $this->createProject();
        $tranche = $this->completeAndDisburseSingleTranche($project);

        $this->actingAs($this->focal)
            ->post(route('projects.release-of-assistance.store', [$project, $tranche]), $this->releasePayload(now()))
            ->assertForbidden();

        $this->assertNull($tranche->fresh()->release_date);
    }

    public function test_release_offers_the_official_modes_of_payment(): void
    {
        $project = $this->createProject();
        $this->completeAndDisburseSingleTranche($project);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertOk()
            ->assertSeeInOrder([
                '(Actual) Cash Payout',
                'Release of Reference Number',
                'Through MRSP (Money Remittance Service Providers)',
                'Awarding of Check',
                'Others, specify',
            ]);
    }

    public function test_others_mode_of_payment_requires_and_stores_the_specified_mode(): void
    {
        $project = $this->createProject();
        $tranche = $this->completeAndDisburseSingleTranche($project);
        $bag = ProjectReleaseOfAssistanceController::errorBag($tranche);

        $this->release($project, $tranche, now()->addDay(), ['payout_mode' => 'Others'])
            ->assertSessionHasErrors('payout_mode_other', null, $bag);

        $this->release($project, $tranche, now()->addDay(), [
            'payout_mode' => 'Others',
            'payout_mode_other' => 'Payout through cooperative',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Others: Payout through cooperative', $tranche->fresh()->release_mode);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertSee('Others: Payout through cooperative')
            ->assertSee('Correct this release in the Overview');
    }

    public function test_release_requires_mode_date_and_venue(): void
    {
        $project = $this->createProject();
        $tranche = $this->completeAndDisburseSingleTranche($project);

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', [$project, $tranche]), [
                'payout_mode' => 'Carrier Pigeon',
            ])
            ->assertSessionHasErrors(
                ['payout_mode', 'payout_date', 'venue'],
                null,
                ProjectReleaseOfAssistanceController::errorBag($tranche),
            );
    }

    public function test_every_tranche_needs_a_release_and_reached_payout_date_before_completion(): void
    {
        $project = $this->createProject();
        $this->saveTranche($project, '400.00', '25.00', '75.00', beneficiaries: 5, female: 3);
        $this->saveTranche($project, '400.00', '25.00', '75.00', beneficiaries: 5, female: 3, intent: 'complete');

        [$first, $second] = $project->obligations()->orderBy('tranche_number')->get()->all();

        $this->disburse($project, $first, '500.00');
        $this->release($project, $first, now()->subDay());

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);

        $this->disburse($project, $second, '500.00');
        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);

        $lastPayout = CarbonImmutable::now('Asia/Manila')->addDays(4)->startOfDay();
        $this->release($project, $second, $lastPayout);

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);

        $engine = app(ProjectStatusEngine::class);

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $engine->synchronize($project->fresh(), today: $lastPayout->subDay()));
        $this->assertSame(ProjectStatus::COMPLETED, $engine->synchronize($project->fresh(), today: $lastPayout));
    }

    public function test_project_completes_when_the_last_release_date_has_already_arrived(): void
    {
        $project = $this->createProject();
        $tranche = $this->completeAndDisburseSingleTranche($project);

        $this->release($project, $tranche, now()->subDay())
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'now Completed'));

        $this->assertSame(ProjectStatus::COMPLETED, $project->fresh()->status);
    }

    public function test_project_does_not_complete_before_the_focal_completes_the_tranches(): void
    {
        $project = $this->createProject();
        $tranche = $this->saveTranche($project, '800.00', '50.00', '150.00');

        $this->disburse($project, $tranche, '1000.00');
        $this->release($project, $tranche, now()->subDay())->assertSessionHasNoErrors();

        $this->assertSame(ProjectStatus::FOR_PAYMENT, $project->fresh()->status);
    }

    public function test_tranches_completed_below_project_cost_complete_once_released(): void
    {
        $project = $this->createProject();
        $tranche = $this->saveTranche($project, '640.00', '40.00', '120.00', beneficiaries: 8, female: 5, intent: 'complete');

        $this->disburse($project, $tranche, '800.00');
        $this->release($project, $tranche, now()->subDay());

        $this->assertSame(ProjectStatus::COMPLETED, $project->fresh()->status);
    }

    public function test_release_of_assistance_queue_lists_only_fully_disbursed_unreleased_tranches(): void
    {
        $waiting = $this->createProject('Waiting Release Project');
        $this->completeAndDisburseSingleTranche($waiting);

        $notDisbursed = $this->createProject('Not Yet Disbursed Project');
        $this->saveTranche($notDisbursed, '800.00');

        $this->actingAs($this->tc)
            ->get(route('project-workflow.index', ['queue' => 'release-of-assistance']))
            ->assertOk()
            ->assertSee('Waiting Release Project')
            ->assertDontSee('Not Yet Disbursed Project');

        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonFragment(['project_title' => 'Waiting Release Project'])
            ->assertJsonMissing(['project_title' => $notDisbursed->project_title]);
    }

    public function test_project_progress_shows_release_of_assistance_stage_and_details(): void
    {
        $project = $this->createProject();
        $tranche = $this->completeAndDisburseSingleTranche($project);

        $workspace = app(ProjectWorkspacePresenter::class)->present($project->fresh(), $this->tc);

        $this->assertSame('Release of Assistance', $workspace['stages'][6]['label']);
        $this->assertSame(6, $workspace['current_stage_index']);

        $this->release($project, $tranche, now()->addDays(5), ['remarks' => 'Bring valid ID']);

        $this->actingAs($this->focal)
            ->get(route('projects.show', ['project' => $project, 'workspace' => 'workflow']))
            ->assertOk()
            ->assertSee('Release of Assistance Progress')
            ->assertSee('1 of 1 tranche(s) released')
            ->assertSee('(Actual) Cash Payout')
            ->assertSee(now()->addDays(5)->format('F d, Y'))
            ->assertSee('Barangay Rawis Covered Court')
            ->assertSee('Bring valid ID')
            ->assertSee($this->tc->name);

        $this->actingAs($this->focal)
            ->get(route('payments.show', $project))
            ->assertOk()
            ->assertSee('Release of Assistance:')
            ->assertSee('Barangay Rawis Covered Court');
    }

    private function assertNotInTcFeed(string $title): void
    {
        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonMissing(['project_title' => $title]);
    }

    private function releasePayload($payoutDate): array
    {
        return [
            'payout_mode' => '(Actual) Cash Payout',
            'payout_date' => $payoutDate->format('Y-m-d'),
            'venue' => 'Barangay Rawis Covered Court',
            'remarks' => 'Payout schedule',
        ];
    }

    private function release(Project $project, ProjectObligation $tranche, $payoutDate, array $overrides = [])
    {
        return $this->actingAs($this->tc)
            ->post(
                route('projects.release-of-assistance.store', [$project, $tranche]),
                $overrides + $this->releasePayload($payoutDate)
            );
    }

    private function saveTranche(
        Project $project,
        string $wages,
        string $insurance = '0.00',
        string $ppe = '0.00',
        int $beneficiaries = 10,
        int $female = 6,
        string $intent = 'save',
    ): ProjectObligation {
        $this->actingAs($this->focal)
            ->post(route('projects.payment.store', $project), [
                'intent' => $intent,
                'tranches' => [[
                    'beneficiaries_total' => $beneficiaries,
                    'beneficiaries_female' => $female,
                    'wages_amount' => $wages,
                    'insurance_amount' => $insurance,
                    'ppe_amount' => $ppe,
                    'obligation_date' => now()->toDateString(),
                    'payee' => 'TUPAD Beneficiaries',
                ]],
            ])
            ->assertSessionHasNoErrors();

        return $project->obligations()->orderByDesc('tranche_number')->firstOrFail();
    }

    private function disburse(Project $project, ProjectObligation $tranche, string $amount)
    {
        return $this->actingAs($this->focal)
            ->post(route('projects.payment.disbursements.store', [$project, $tranche]), [
                'amount' => $amount,
                'date_disbursed' => now()->toDateString(),
                'ldap_check_number' => 'LDAP-'.uniqid(),
            ]);
    }

    private function completeAndDisburseSingleTranche(Project $project): ProjectObligation
    {
        $tranche = $this->saveTranche($project, '800.00', '50.00', '150.00', intent: 'complete');
        $this->disburse($project, $tranche, '1000.00')->assertSessionHasNoErrors();

        return $tranche->fresh();
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
