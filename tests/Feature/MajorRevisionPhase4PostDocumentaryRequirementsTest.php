<?php

namespace Tests\Feature;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Adl;
use App\Models\AdlAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MajorRevisionPhase4PostDocumentaryRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private User $tc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tc = User::factory()->create([
            'role' => UserRole::TC,
            'is_active' => true,
        ]);
    }

    public function test_post_document_page_uses_revised_three_required_inputs(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        );

        $this
            ->actingAs($this->tc)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(
                'Submission of Post-Documentary Requirements'
            )
            ->assertSee('Date Received')
            ->assertSee('Attachments Received')
            ->assertSee('Date Forwarded to IMSD')
            ->assertSee(
                'Save Post-Documentary Requirements'
            )
            ->assertSee('Auto-update → For Payment');
    }

    public function test_complete_post_document_submission_saves_multiple_attachments_and_auto_moves_to_for_payment(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        );

        $response = $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.post-documents.store',
                    $project
                ),
                [
                    'date_received' =>
                        '2026-08-26',

                    'attachments' => [
                        UploadedFile::fake()->create(
                            'accomplishment-report.pdf',
                            120,
                            'application/pdf'
                        ),
                        UploadedFile::fake()->create(
                            'payroll-summary.xlsx',
                            120,
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        ),
                    ],

                    'date_forwarded_to_imsd' =>
                        '2026-08-26',

                    'remarks' =>
                        'Complete post-document set.',
                ]
            );

        $response->assertRedirect();

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status
        );

        $this->assertDatabaseCount(
            'project_post_documents',
            2
        );

        $this->assertDatabaseHas(
            'project_post_documents',
            [
                'project_id' => $project->id,
                'document_type' =>
                    'accomplishment-report.pdf',
                'recorded_by' => $this->tc->id,
            ]
        );

        $this->assertDatabaseHas(
            'project_post_documents',
            [
                'project_id' => $project->id,
                'document_type' =>
                    'payroll-summary.xlsx',
                'recorded_by' => $this->tc->id,
            ]
        );
    }

    public function test_date_forwarded_to_imsd_is_required_and_cannot_precede_date_received(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.post-documents.store',
                    $project
                ),
                [
                    'date_received' =>
                        '2026-08-26',

                    'attachments' => [
                        UploadedFile::fake()->create(
                            'report.pdf',
                            120,
                            'application/pdf'
                        ),
                    ],

                    'date_forwarded_to_imsd' =>
                        '2026-08-25',
                ]
            )
            ->assertSessionHasErrors(
                'date_forwarded_to_imsd'
            );

        $this->assertSame(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            $project->fresh()->status
        );

        $this->assertDatabaseCount(
            'project_post_documents',
            0
        );
    }

    public function test_at_least_one_attachment_received_is_required(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.post-documents.store',
                    $project
                ),
                [
                    'date_received' =>
                        '2026-08-26',

                    'date_forwarded_to_imsd' =>
                        '2026-08-26',
                ]
            )
            ->assertSessionHasErrors();

        $this->assertDatabaseCount(
            'project_post_documents',
            0
        );

        $this->assertSame(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            $project->fresh()->status
        );
    }

    public function test_legacy_single_attachment_payload_remains_supported(): void
    {
        $project = $this->createProject(
            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
        );

        $this
            ->actingAs($this->tc)
            ->post(
                route(
                    'projects.post-documents.store',
                    $project
                ),
                [
                    'date_received' =>
                        '2026-08-26',

                    'document_type' =>
                        'Accomplishment Report',

                    'attachment' =>
                        UploadedFile::fake()->create(
                            'legacy-report.pdf',
                            120,
                            'application/pdf'
                        ),

                    'date_forwarded_to_imsd' =>
                        '2026-08-26',
                ]
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'project_post_documents',
            [
                'project_id' =>
                    $project->id,
                'document_type' =>
                    'Accomplishment Report',
            ]
        );

        $this->assertSame(
            ProjectStatus::FOR_PAYMENT,
            $project->fresh()->status
        );
    }

    private function createProject(
        ProjectStatus $status
    ): Project {
        $focal = User::factory()->create([
            'role' => UserRole::FOCAL,
            'is_active' => true,
        ]);

        $adl = Adl::create([
            'adl_number' => 'ADL-R4-' . uniqid(),
            'grants' => 1000000,
            'admin_cost' => 0,
            'total' => 1000000,
            'created_by' => $focal->id,
        ]);

        $allocation = AdlAllocation::create([
            'adl_id' => $adl->id,
            'location' => 'Albay',
            'amount' => 1000000,
            'created_by' => $focal->id,
        ]);

        return Project::create([
            'adl_allocation_id' =>
                $allocation->id,
            'date_received' =>
                '2026-08-01',
            'project_title' =>
                'R4 Post-Document Project',
            'nature_of_work' =>
                'Community clean-up',
            'fund_sponsor' =>
                'DOLE Regional Office V',
            'partner' =>
                'LGU Albay',
            'project_series' =>
                'Regular TUPAD 2026',
            'tevs_date_verified' =>
                '2026-08-01',
            'province' =>
                'Albay',
            'district' =>
                '2nd District',
            'municipality' =>
                'Legazpi City',
            'barangay' =>
                'Rawis',
            'implementation_mode' =>
                ImplementationMode::DIRECT_ADMINISTRATION,
            'number_of_days' =>
                20,
            'term' =>
                'short_term',
            'beneficiaries_total' =>
                50,
            'beneficiaries_female' =>
                25,
            'insurance_beneficiaries' =>
                50,
            'wage_rate' =>
                455,
            'wages_total' =>
                455000,
            'ppe_total' =>
                0,
            'insurance_rate' =>
                50,
            'insurance_total' =>
                2500,
            'total_project_cost' =>
                457500,
            'status' =>
                $status,
            'created_by' =>
                $this->tc->id,
        ]);
    }
}
