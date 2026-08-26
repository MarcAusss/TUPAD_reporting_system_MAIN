<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Projects\ImplementationStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProjectWorkflowQueueController extends Controller
{
    public function index(
        Request $request,
        string $queue,
        ImplementationStageService $implementationStageService
    ): View {
        $config = $this->queueConfig($queue);

        if ($queue === 'implementation') {
            return $this->implementationBoard(
                $request,
                $config,
                $implementationStageService
            );
        }

        $projects = Project::query()
            ->with([
                'allocation.adl',
                'approval',
                'evaluations',
                'obligation',
                'payout',
            ])
            ->when(
                $queue === 'release-of-assistance',
                function ($query) {
                    $query
                        ->where(
                            'status',
                            ProjectStatus::FOR_PAYMENT->value
                        )
                        ->whereHas('obligation')
                        ->whereDoesntHave('payout');
                },
                function ($query) use ($config) {
                    $query->whereIn(
                        'status',
                        $config['statuses']
                    );
                }
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

    private function implementationBoard(
        Request $request,
        array $config,
        ImplementationStageService $implementationStageService
    ): View {
        $projects =
            Project::query()
                ->with([
                    'allocation.adl',
                    'approval',
                    'insuranceEnrollment',
                    'ppeDelivery',
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
            $stage =
                $implementationStageService
                    ->synchronize(
                        $project,
                        (int) $request->user()->id
                    );

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
                    'Projects automatically move between implementation stages according to their approved work period.',

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
                    'Approved projects requiring implementation preparation, active implementation monitoring, or post-document transition.',
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
                    'For Payment projects whose Payment of Wages / obligation has already been recorded and are ready for final release.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_PAYMENT->value,
                ],
                'empty' =>
                    'No projects are currently ready for Release of Assistance.',
            ],

            default => abort(404),
        };
    }
}
