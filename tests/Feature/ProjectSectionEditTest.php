<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Http\Controllers\ProjectSectionEditController;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\ProjectEditLog;
use App\Models\ProjectEditRequest;
use App\Models\ProjectEvaluation;
use App\Models\ProjectObligation;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSectionEditTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;
    private User $tc;
    private Province $province;
    private Project $project;
    private ProjectEvaluation $evaluation;
    private ProjectObligation $obligation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->province = Province::create(['code' => '050500000', 'name' => 'Albay', 'is_active' => true]);
        $this->focal = User::factory()->create(['role' => UserRole::FOCAL, 'is_active' => true, 'name' => 'Focal Maria']);
        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
            'assigned_province_id' => $this->province->id,
            'name' => 'Coordinator Juan',
        ]);

        $this->project = $this->createProject();
    }

    public function test_overview_shows_every_recorded_workflow_step_as_its_own_section(): void
    {
        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertOk()
            ->assertSee('Project &amp; Workflow Records', false)
            ->assertSee('Project Profile (Create Project)')
            ->assertSee('TSSD Evaluation #1')
            ->assertSee('Compliance #1')
            ->assertSee('Compliance documents submitted')
            ->assertSee('Project Approval')
            ->assertSee('Implementation Work Period')
            ->assertSee('Obligation – Tranche 1')
            ->assertSee('Disbursement – Tranche 1 (LDAP-001)')
            ->assertSee('Request Edit')
            ->assertDontSee('Save Section Changes');
    }

    public function test_focal_edits_a_section_directly_and_the_overview_notes_the_change(): void
    {
        $this->actingAs($this->focal)
            ->put($this->sectionUrl('compliance', $this->evaluation->id), [
                'compliance_date' => '2026-07-08',
                'compliance_remarks' => 'Corrected compliance remarks',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Corrected compliance remarks', $this->evaluation->fresh()->compliance_remarks);

        $log = ProjectEditLog::firstOrFail();
        $this->assertSame($this->focal->id, $log->edited_by);
        $this->assertNull($log->approved_by);
        $this->assertSame('Compliance Remarks', $log->changes[0]['field']);

        $this->actingAs($this->focal)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertOk()
            ->assertSee('Edited by Focal Maria')
            ->assertSee('Compliance documents submitted')
            ->assertSee('Corrected compliance remarks');
    }

    public function test_tc_needs_focal_approval_and_each_approval_covers_one_save(): void
    {
        $url = $this->sectionUrl('project', $this->project->id);

        $this->actingAs($this->tc)
            ->put($url, $this->projectProfilePayload(['project_title' => 'Unapproved Title']))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->post(route('projects.sections.edit-requests.store', [$this->project, 'project', $this->project->id]), [
                'reason' => 'Typo in the project title',
            ])
            ->assertSessionHas('success');

        $editRequest = ProjectEditRequest::firstOrFail();
        $this->assertSame(ProjectEditRequest::PENDING, $editRequest->status);

        // The Focal sees the request with Approve / Decline buttons in the bell.
        $this->actingAs($this->focal)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonFragment([
                'kind' => 'edit_request',
                'message' => 'Coordinator Juan requests to edit Project Profile.',
                'reason' => 'Typo in the project title',
            ])
            ->assertJsonFragment(['label' => 'Approve', 'url' => route('edit-requests.approve', $editRequest)]);

        $this->actingAs($this->tc)
            ->postJson(route('edit-requests.approve', $editRequest))
            ->assertForbidden();

        $this->actingAs($this->focal)
            ->postJson(route('edit-requests.approve', $editRequest))
            ->assertOk()
            ->assertJsonPath('status', ProjectEditRequest::APPROVED);

        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertJsonFragment([
                'kind' => 'edit_decision',
                'message' => 'Your edit request for Project Profile was approved by Focal Maria. You can edit it once.',
            ]);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertSee('Edit approved by Focal Maria · one save')
            ->assertSee('Save Section Changes');

        $this->actingAs($this->tc)
            ->put($url, $this->projectProfilePayload(['project_title' => 'Corrected Project Title']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected Project Title', $this->project->fresh()->project_title);
        $this->assertSame(ProjectEditRequest::USED, $editRequest->fresh()->status);

        $log = ProjectEditLog::firstOrFail();
        $this->assertSame($this->tc->id, $log->edited_by);
        $this->assertSame($this->focal->id, $log->approved_by);

        $this->actingAs($this->tc)
            ->get(route('projects.show', ['project' => $this->project, 'workspace' => 'overview']))
            ->assertSee('Edited by Coordinator Juan')
            ->assertSee('(approved by Focal Maria)');

        // The approval is used up; another correction needs a new request.
        $this->actingAs($this->tc)
            ->put($url, $this->projectProfilePayload(['project_title' => 'Second Unapproved Title']))
            ->assertForbidden();

        $this->actingAs($this->tc)
            ->post(route('projects.sections.edit-requests.store', [$this->project, 'project', $this->project->id]))
            ->assertSessionHas('success');

        $this->assertSame(2, ProjectEditRequest::count());
    }

    public function test_focal_can_decline_and_the_tc_is_told(): void
    {
        $this->actingAs($this->tc)
            ->post(route('projects.sections.edit-requests.store', [$this->project, 'implementation', $this->project->implementation->id]));

        $editRequest = ProjectEditRequest::firstOrFail();

        $this->actingAs($this->focal)
            ->post(route('edit-requests.decline', $editRequest))
            ->assertRedirect();

        $this->assertSame(ProjectEditRequest::DECLINED, $editRequest->fresh()->status);

        $this->actingAs($this->tc)
            ->getJson(route('notifications.feed'))
            ->assertJsonFragment(['message' => 'Your edit request for Work Period was declined by Focal Maria.']);

        $this->actingAs($this->tc)
            ->put($this->sectionUrl('implementation', $this->project->implementation->id), [
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-20',
            ])
            ->assertForbidden();
    }

    public function test_tranche_edits_cannot_exceed_project_data_or_go_below_the_disbursed_amount(): void
    {
        $bag = ProjectSectionEditController::errorBag('obligation', $this->obligation->id);
        $payload = [
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wages_amount' => '800.00',
            'insurance_amount' => '50.00',
            'ppe_amount' => '150.00',
            'obligation_date' => '2026-09-01',
            'payee' => 'TUPAD Beneficiaries',
        ];

        $this->actingAs($this->focal)
            ->put($this->sectionUrl('obligation', $this->obligation->id), ['wages_amount' => '900.00'] + $payload)
            ->assertSessionHasErrors('wages_amount', null, $bag);

        $this->actingAs($this->focal)
            ->put($this->sectionUrl('obligation', $this->obligation->id), ['wages_amount' => '100.00', 'insurance_amount' => '0', 'ppe_amount' => '0'] + $payload)
            ->assertSessionHasErrors('wages_amount', null, $bag);

        $this->actingAs($this->focal)
            ->put($this->sectionUrl('obligation', $this->obligation->id), ['payee' => 'Corrected Payee', 'wages_amount' => '700.00'] + $payload)
            ->assertSessionHasNoErrors();

        $obligation = $this->obligation->fresh();
        $this->assertSame('Corrected Payee', $obligation->payee);
        $this->assertEquals(900.0, (float) $obligation->amount);
    }

    public function test_a_released_tranche_is_corrected_through_the_overview_not_the_release_form(): void
    {
        $this->obligation->disbursements()->first()->update(['amount' => '1000.00']);
        $this->obligation->update([
            'release_mode' => '(Actual) Cash Payout',
            'release_date' => now()->addDays(3)->toDateString(),
            'release_venue' => 'Old Venue',
            'released_by' => $this->tc->id,
            'released_at' => now(),
        ]);

        $this->actingAs($this->tc)
            ->post(route('projects.release-of-assistance.store', [$this->project, $this->obligation]), [
                'payout_mode' => '(Actual) Cash Payout',
                'payout_date' => now()->addDays(3)->toDateString(),
                'venue' => 'New Venue',
            ])
            ->assertForbidden();

        $this->actingAs($this->focal)
            ->put($this->sectionUrl('release', $this->obligation->id), [
                'release_mode' => 'Others',
                'release_mode_other' => 'Cooperative payout',
                'release_date' => now()->addDays(4)->toDateString(),
                'release_venue' => 'New Venue',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Others: Cooperative payout', $this->obligation->fresh()->release_mode);
        $this->assertSame('New Venue', $this->obligation->fresh()->release_venue);
    }

    private function sectionUrl(string $section, int $record): string
    {
        return route('projects.sections.update', [$this->project, $section, $record]);
    }

    private function projectProfilePayload(array $overrides = []): array
    {
        return $overrides + [
            'project_title' => $this->project->project_title,
            'date_received' => '2026-07-01',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => '2026-07-02',
        ];
    }

    private function createProject(): Project
    {
        $adl = Adl::create([
            'adl_number' => 'ADL-EDIT-001',
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

        $project = Project::create([
            'adl_allocation_id' => $allocation->id,
            'date_received' => '2026-07-01',
            'project_title' => 'Section Edit Project',
            'nature_of_work' => 'Community clean-up',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_series' => 'Regular TUPAD 2026',
            'tevs_date_verified' => '2026-07-02',
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

        $this->evaluation = $project->evaluations()->create([
            'result' => 'for_compliance',
            'findings' => 'Missing documents',
            'required_documents' => 'Barangay certification',
            'evaluated_by' => $this->tc->id,
            'evaluated_at' => '2026-07-05 09:00:00',
            'compliance_date' => '2026-07-08',
            'compliance_remarks' => 'Compliance documents submitted',
            'complied_by' => $this->tc->id,
            'complied_at' => '2026-07-08 10:00:00',
        ]);

        $project->approval()->create([
            'approval_date' => '2026-07-10',
            'project_code' => 'EDIT-CODE-001',
            'approved_by' => $this->tc->id,
            'approved_at' => '2026-07-10 10:00:00',
        ]);

        $project->implementation()->create([
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-20',
            'recorded_by' => $this->tc->id,
        ]);

        $this->obligation = $project->obligations()->create([
            'tranche_number' => 1,
            'adl_number' => 'ADL-EDIT-001',
            'fund_sponsor' => 'DOLE Regional Office V',
            'partner' => 'LGU Albay',
            'project_location' => 'Rawis, Legazpi City, Albay',
            'term' => 'Short Term',
            'beneficiaries_total' => 10,
            'beneficiaries_female' => 6,
            'wages_amount' => '800.00',
            'insurance_amount' => '50.00',
            'ppe_amount' => '150.00',
            'amount' => '1000.00',
            'obligation_date' => '2026-09-01',
            'month' => 'September 2026',
            'payee' => 'TUPAD Beneficiaries',
            'recorded_by' => $this->focal->id,
        ]);

        $this->obligation->disbursements()->create([
            'amount' => '200.00',
            'date_disbursed' => '2026-09-02',
            'ldap_check_number' => 'LDAP-001',
            'recorded_by' => $this->focal->id,
        ]);

        return $project;
    }
}
