<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectWorkflowQueueController extends Controller
{
    public function index(
        Request $request,
        string $queue
    ): View {
        $config = $this->queueConfig($queue);

        $projects = Project::query()
            ->with([
                'allocation.adl',
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
                                ->orWhere(
                                    'project_code',
                                    'like',
                                    "%{$search}%"
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

    private function queueConfig(
        string $queue
    ): array {
        return match ($queue) {
            'tssd-evaluation' => [
                'title' => 'TSSD Evaluation',
                'description' =>
                    'Projects requiring TSSD evaluation or compliance review.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::TSSD_EVALUATION->value,
                    ProjectStatus::FOR_COMPLIANCE->value,
                ],
                'empty' =>
                    'No projects currently require TSSD evaluation or compliance review.',
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
                    'Approved projects requiring implementation preparation or implementation monitoring.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::APPROVED->value,
                    ProjectStatus::FOR_IMPLEMENTATION->value,
                    ProjectStatus::ONGOING_IMPLEMENTATION->value,
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
