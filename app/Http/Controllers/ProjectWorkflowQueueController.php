<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectEvaluation;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Projects\ImplementationStageService;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectWorkflowQueueController extends Controller
{
    public function index(
        Request $request,
        string $queue,
        ImplementationStageService $implementationStageService,
        ProjectStatusEngine $statusEngine,
        ProvinceAccessService $provinceAccess,
    ): View {
        $config = $this->queueConfig($queue);

        if ($queue === 'implementation') {
            return $this->implementationBoard(
                $request,
                $config,
                $implementationStageService,
                $statusEngine,
                $provinceAccess,
            );
        }

        $projects = $provinceAccess->scopeProjects(Project::query(), $request->user())
            ->with([
                'allocation.adl',
                'approval',
                'evaluations',
            ])
            ->whereIn('status', $config['statuses'])
            ->when(
                isset($config['scope']),
                fn (Builder $query): Builder => $config['scope']($query),
            )
            ->when(
                $request->filled('q'),
                function ($query) use ($request) {
                    $search = trim(
                        (string) $request->string('q')
                    );

                    $query->where(
                        function ($subQuery) use ($search) {
                            $subQuery
                                ->where(
                                    'project_title',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'approval',
                                    fn ($approvalQuery) =>
                                        $approvalQuery->where(
                                            'project_code',
                                            'like',
                                            "%{$search}%"
                                        )
                                )
                                ->orWhere(
                                    'province',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'municipality',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'barangay',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )
            ->latest('updated_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view(
            'project-workflow.index',
            [
                'projects' => $projects,
                'queue' => $queue,
                'queueTitle' => $config['title'],
                'queueDescription' => $config['description'],
                'queueOwner' => $config['owner'],
                'emptyMessage' => $config['empty'],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Compliance History
    |--------------------------------------------------------------------------
    |
    | Unlike the "For Compliance" queue (a live view filtered to the project's
    | CURRENT status), this lists every project that ever received a TSSD
    | "for_compliance" finding, whether it is still pending or has long since
    | complied and moved on to Approval/Implementation/Completed — so nothing
    | drops out of the record once it is resolved.
    |
    */

    public function complianceHistory(
        Request $request,
        ProvinceAccessService $provinceAccess,
    ): View {
        $status = $request->string('status')->toString();

        $evaluations = ProjectEvaluation::query()
            ->where('result', 'for_compliance')
            ->whereHas(
                'project',
                fn (Builder $query): Builder => $provinceAccess->scopeProjects($query, $request->user()),
            )
            ->with([
                'project.allocation.adl',
                'project.approval',
                'evaluator',
                'complier',
                'attachments',
            ])
            ->when(
                $request->filled('q'),
                function (Builder $query) use ($request): void {
                    $search = trim((string) $request->string('q'));

                    $query->whereHas(
                        'project',
                        function (Builder $projectQuery) use ($search): void {
                            $projectQuery
                                ->where('project_title', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'approval',
                                    fn ($approvalQuery) => $approvalQuery->where('project_code', 'like', "%{$search}%"),
                                )
                                ->orWhere('province', 'like', "%{$search}%")
                                ->orWhere('municipality', 'like', "%{$search}%")
                                ->orWhere('barangay', 'like', "%{$search}%");
                        },
                    );
                },
            )
            ->when(
                $status === 'pending',
                fn (Builder $query) => $query->whereNull('complied_at'),
            )
            ->when(
                $status === 'complied',
                fn (Builder $query) => $query->whereNotNull('complied_at'),
            )
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $countsQuery = ProjectEvaluation::query()
            ->where('result', 'for_compliance')
            ->whereHas(
                'project',
                fn (Builder $query): Builder => $provinceAccess->scopeProjects($query, $request->user()),
            );

        $pendingCount = (clone $countsQuery)->whereNull('complied_at')->count();
        $compliedCount = (clone $countsQuery)->whereNotNull('complied_at')->count();

        return view(
            'project-workflow.compliance-history',
            [
                'evaluations' => $evaluations,
                'status' => $status,
                'pendingCount' => $pendingCount,
                'compliedCount' => $compliedCount,
            ]
        );
    }

    private function implementationBoard(
        Request $request,
        array $config,
        ImplementationStageService $implementationStageService,
        ProjectStatusEngine $statusEngine,
        ProvinceAccessService $provinceAccess,
    ): View {
        $projects =
            $provinceAccess->scopeProjects(Project::query(), $request->user())
                ->with([
                    'allocation.adl',
                    'approval',
                    'insuranceEnrollment',
                    'ppeDeliveries',
                    'noticeToProceed',
                    'orientation',
                    'implementation',
                ])
                ->whereIn(
                    'status',
                    [
                        ProjectStatus::APPROVED->value,
                        ProjectStatus::FOR_IMPLEMENTATION->value,
                        ProjectStatus::ONGOING_IMPLEMENTATION->value,
                        ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value,
                    ]
                )
                ->where(
                    'implementation_mode',
                    ImplementationMode::DIRECT_ADMINISTRATION->value
                )
                ->when(
                    $request->filled('q'),
                    function ($query) use ($request) {
                        $search =
                            trim(
                                (string) $request->string('q')
                            );

                        $query->where(
                            function ($subQuery) use ($search) {
                                $subQuery
                                    ->where(
                                        'project_title',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhereHas(
                                        'approval',
                                        fn ($approvalQuery) =>
                                            $approvalQuery->where(
                                                'project_code',
                                                'like',
                                                "%{$search}%"
                                            )
                                    )
                                    ->orWhere(
                                        'province',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'municipality',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'barangay',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                    }
                )
                ->latest('updated_at')
                ->latest('id')
                ->get();

        $board = [
            ProjectStatus::FOR_IMPLEMENTATION->value =>
                collect(),

            ProjectStatus::ONGOING_IMPLEMENTATION->value =>
                collect(),

            ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value =>
                collect(),
        ];

        foreach ($projects as $project) {
            $statusEngine->synchronize($project);

            if (! in_array(
                $project->status,
                [
                    ProjectStatus::APPROVED,
                    ProjectStatus::FOR_IMPLEMENTATION,
                    ProjectStatus::ONGOING_IMPLEMENTATION,
                    ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                ],
                true,
            )) {
                continue;
            }

            $stage =
                $implementationStageService
                    ->stageFor($project);

            $project->setAttribute(
                'implementation_board_stage',
                $stage->value
            );

            $project->setAttribute(
                'implementation_preparation_complete',
                $implementationStageService
                    ->preparationComplete(
                        $project
                    )
            );

            $board[$stage->value]
                ->push(
                    $project
                );
        }

        return view(
            'project-workflow.index',
            [
                'projects' =>
                    $projects,

                'implementationBoard' =>
                    $board,

                'queue' =>
                    'implementation',

                'queueTitle' =>
                    $config['title'],

                'queueDescription' =>
                    'Direct Administration projects automatically move between implementation stages according to their approved work period.',

                'queueOwner' =>
                    $config['owner'],

                'emptyMessage' =>
                    $config['empty'],
            ]
        );
    }

    private function queueConfig(
        string $queue
    ): array {
        return match ($queue) {
            'tssd-evaluation' => [
                'title' => 'TSSD Evaluation',
                'description' =>
                    'Projects waiting for a TSSD evaluation result.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::TSSD_EVALUATION->value,
                ],
                'empty' =>
                    'No projects are currently waiting for TSSD evaluation.',
            ],

            'for-compliance' => [
                'title' => 'Projects for Compliance',
                'description' =>
                    'Projects with TSSD findings that require compliance before approval.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_COMPLIANCE->value,
                ],
                'empty' =>
                    'No projects are currently waiting for compliance.',
            ],

            'for-approval' => [
                'title' => 'For Approval',
                'description' =>
                    'Projects already evaluated and ready for approval action.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_APPROVAL->value,
                ],
                'empty' =>
                    'No projects are currently waiting for approval.',
            ],

            'implementation' => [
                'title' => 'Implementation',
                'description' =>
                    'Direct Administration projects requiring implementation preparation, active implementation monitoring, or post-document transition.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::APPROVED->value,
                    ProjectStatus::FOR_IMPLEMENTATION->value,
                    ProjectStatus::ONGOING_IMPLEMENTATION->value,
                    ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value,
                ],
                'empty' =>
                    'No projects currently require implementation action.',
            ],

            'post-documents' => [
                'title' => 'Post-Documentary Requirements',
                'description' =>
                    'Projects waiting for submission or recording of post-documentary requirements.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value,
                ],
                'empty' =>
                    'No projects are currently waiting for post-documentary requirements.',
            ],

            'release-of-assistance' => [
                'title' => 'Release of Assistance',
                'description' =>
                    'Direct Administration projects with fully disbursed tranches waiting for the mode of payment, payout date, and venue.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_PAYMENT->value,
                ],
                'scope' => fn (Builder $query): Builder => $query
                    ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
                    ->whereHas('obligations', fn (Builder $obligations): Builder => $obligations->awaitingRelease()),
                'empty' =>
                    'No projects are currently waiting for a Release of Assistance.',
            ],

            'beneficiary-deduction' => [
                'title' => 'Beneficiary Deduction',
                'description' =>
                    'Obligations completed with fewer beneficiaries than the project declared. Indicate the addresses of the beneficiaries not included to produce the Actual Beneficiary Mapping.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_PAYMENT->value,
                    ProjectStatus::COMPLETED->value,
                ],
                'scope' => fn (Builder $query): Builder => $query
                    ->where('implementation_mode', ImplementationMode::DIRECT_ADMINISTRATION->value)
                    ->whereNotNull('obligations_completed_at')
                    ->whereNull('beneficiary_deductions_recorded_at')
                    ->where(fn (Builder $shortfall): Builder => $shortfall
                        ->whereRaw('projects.beneficiaries_total > (select coalesce(sum(project_obligations.beneficiaries_total), 0) from project_obligations where project_obligations.project_id = projects.id)')
                        ->orWhereRaw('projects.beneficiaries_female > (select coalesce(sum(project_obligations.beneficiaries_female), 0) from project_obligations where project_obligations.project_id = projects.id)')),
                'empty' =>
                    'No projects are currently waiting for beneficiary deductions.',
            ],

            default => abort(404),
        };
    }
}
