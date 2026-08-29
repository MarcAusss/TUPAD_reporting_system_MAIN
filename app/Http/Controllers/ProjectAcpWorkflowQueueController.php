<?php

namespace App\Http\Controllers;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Projects\ProjectAcpLiquidationService;
use App\Services\Projects\ProjectStatusEngine;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ProjectAcpWorkflowQueueController extends Controller
{
    public function payment(Request $request): View
    {
        return $this->render($request, 'payment');
    }

    public function checkRelease(Request $request): View
    {
        return $this->render($request, 'check-release');
    }

    public function implementation(Request $request): View
    {
        return $this->render($request, 'implementation');
    }

    public function liquidation(Request $request): View
    {
        return $this->render($request, 'liquidation');
    }

    private function render(Request $request, string $queue): View
    {
        $config = $this->config($queue);

        $query = app(ProvinceAccessService::class)
            ->scopeProjects(Project::query(), $request->user())
            ->where('implementation_mode', ImplementationMode::THROUGH_ACP->value)
            ->whereIn('status', $config['statuses'])
            ->with([
                'allocation.adl',
                'approval',
                'acpPayment',
                'acpCheckRelease',
                'implementation',
                'acpLiquidations',
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = trim((string) $request->string('q'));

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('project_title', 'like', "%{$search}%")
                        ->orWhere('province', 'like', "%{$search}%")
                        ->orWhere('municipality', 'like', "%{$search}%")
                        ->orWhere('barangay', 'like', "%{$search}%")
                        ->orWhereHas(
                            'approval',
                            fn ($approval) => $approval->where(
                                'project_code',
                                'like',
                                "%{$search}%"
                            )
                        );
                });
            })
            ->latest('updated_at')
            ->latest('id');

        /** @var LengthAwarePaginator $projects */
        $projects = $query
            ->paginate(15)
            ->withQueryString();

        if ($queue === 'implementation') {
            $statusEngine = app(ProjectStatusEngine::class);

            foreach ($projects->getCollection() as $project) {
                $statusEngine->synchronize($project);
                $project->refresh()->loadMissing([
                    'approval',
                    'acpCheckRelease',
                    'implementation',
                ]);
            }

            $projects->setCollection(
                $projects->getCollection()
                    ->filter(fn (Project $project): bool => in_array(
                        $project->status,
                        [
                            ProjectStatus::FOR_IMPLEMENTATION,
                            ProjectStatus::ONGOING_IMPLEMENTATION,
                        ],
                        true,
                    ))
                    ->values()
            );
        }

        $liquidationService = app(ProjectAcpLiquidationService::class);

        foreach ($projects->getCollection() as $project) {
            $project->setAttribute(
                'acp_liquidation_summary',
                $liquidationService->summary($project),
            );
        }

        return view('acp-workflow.index', [
            'projects' => $projects,
            'queue' => $queue,
            'queueTitle' => $config['title'],
            'queueDescription' => $config['description'],
            'queueOwner' => $config['owner'],
            'emptyMessage' => $config['empty'],
            'actionLabel' => $config['action_label'],
            'actionRoute' => $config['action_route'],
        ]);
    }

    /** @return array{title:string,description:string,owner:string,statuses:array<int,string>,empty:string,action_label:string,action_route:string} */
    private function config(string $queue): array
    {
        return match ($queue) {
            'payment' => [
                'title' => 'Through ACP — For Payment',
                'description' => 'Approved Through ACP projects waiting for the official ACP payment record.',
                'owner' => 'Focal / Administrator',
                'statuses' => [ProjectStatus::FOR_PAYMENT->value],
                'empty' => 'No Through ACP projects are currently waiting for payment processing.',
                'action_label' => 'Process ACP Payment',
                'action_route' => 'acp-payments.show',
            ],
            'check-release' => [
                'title' => 'Through ACP — Check Release',
                'description' => 'Paid Through ACP projects waiting for release of the official check to the proponent.',
                'owner' => 'Focal / Administrator',
                'statuses' => [ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT->value],
                'empty' => 'No Through ACP projects are currently waiting for check release.',
                'action_label' => 'Record Check Release',
                'action_route' => 'acp-payments.show',
            ],
            'implementation' => [
                'title' => 'Through ACP — Implementation',
                'description' => 'Through ACP projects with a released check that are waiting to start or are currently implementing.',
                'owner' => 'TUPAD Coordinator / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_IMPLEMENTATION->value,
                    ProjectStatus::ONGOING_IMPLEMENTATION->value,
                ],
                'empty' => 'No Through ACP projects currently require implementation action.',
                'action_label' => 'Open ACP Implementation',
                'action_route' => 'acp-implementation.show',
            ],
            'liquidation' => [
                'title' => 'Through ACP — Liquidation',
                'description' => 'Through ACP projects waiting for full or additional liquidation records.',
                'owner' => 'Focal / Administrator',
                'statuses' => [
                    ProjectStatus::FOR_LIQUIDATION->value,
                    ProjectStatus::PARTIALLY_LIQUIDATED->value,
                ],
                'empty' => 'No Through ACP projects are currently waiting for liquidation.',
                'action_label' => 'Open Liquidation',
                'action_route' => 'acp-liquidations.show',
            ],
            default => abort(404),
        };
    }
}
