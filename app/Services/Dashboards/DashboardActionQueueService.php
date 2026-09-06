<?php

namespace App\Services\Dashboards;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardActionQueueService
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
    ) {
    }

    /**
     * Build role-aware operational queue metrics from the same scoped project
     * records used by the official workflow. Aging is informational only and
     * never changes a project status.
     *
     * @return array{
     *     attention_days:int,
     *     critical_days:int,
     *     total_pending:int,
     *     aged_pending:int,
     *     critical_pending:int,
     *     oldest_days:int,
     *     queues:array<string,array<string,mixed>>,
     *     oldest_items:Collection<int,array<string,mixed>>
     * }
     */
    public function build(User $user): array
    {
        $attentionDays = max(1, (int) config('tupad_dashboard.attention_after_days', 7));
        $criticalDays = max($attentionDays, (int) config('tupad_dashboard.critical_after_days', 14));
        $definitions = $this->definitionsFor($user);

        $projects = $this->provinceAccess
            ->scopeProjects(Project::query(), $user)
            ->where('status', '!=', ProjectStatus::COMPLETED->value)
            ->addSelect([
                'current_status_changed_at' => ProjectStatusHistory::query()
                    ->select('changed_at')
                    ->whereColumn('project_status_histories.project_id', 'projects.id')
                    ->whereColumn('project_status_histories.to_status', 'projects.status')
                    ->latest('changed_at')
                    ->limit(1),
            ])
            ->get();

        $queues = [];
        $allItems = collect();

        foreach ($definitions as $key => $definition) {
            $matching = $projects
                ->filter(fn (Project $project): bool => $this->matches($project, $definition))
                ->map(fn (Project $project): array => $this->item(
                    project: $project,
                    queueKey: $key,
                    definition: $definition,
                    attentionDays: $attentionDays,
                    criticalDays: $criticalDays,
                ))
                ->sortByDesc('age_days')
                ->values();

            $queues[$key] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'url' => route($definition['route'], $definition['route_params'] ?? []),
                'count' => $matching->count(),
                'aged_count' => $matching->where('needs_attention', true)->count(),
                'critical_count' => $matching->where('critical', true)->count(),
                'oldest_days' => (int) ($matching->max('age_days') ?? 0),
            ];

            $allItems = $allItems->concat($matching);
        }

        $oldestItems = $allItems
            ->sortByDesc('age_days')
            ->take(6)
            ->values();

        return [
            'attention_days' => $attentionDays,
            'critical_days' => $criticalDays,
            'total_pending' => (int) collect($queues)->sum('count'),
            'aged_pending' => (int) collect($queues)->sum('aged_count'),
            'critical_pending' => (int) collect($queues)->sum('critical_count'),
            'oldest_days' => (int) (collect($queues)->max('oldest_days') ?? 0),
            'queues' => $queues,
            'oldest_items' => $oldestItems,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function definitionsFor(User $user): array
    {
        $tcOperational = [
            'tssd' => [
                'label' => 'TSSD Evaluation',
                'description' => 'Projects awaiting TSSD evaluation.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'tssd-evaluation'],
                'statuses' => [ProjectStatus::TSSD_EVALUATION],
            ],
            'compliance' => [
                'label' => 'For Compliance',
                'description' => 'Projects waiting for corrective compliance action.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'for-compliance'],
                'statuses' => [ProjectStatus::FOR_COMPLIANCE],
            ],
            'approval' => [
                'label' => 'For Approval',
                'description' => 'Projects ready for approval action.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'for-approval'],
                'statuses' => [ProjectStatus::FOR_APPROVAL],
            ],
            'implementation' => [
                'label' => 'DA Implementation',
                'description' => 'Direct Administration projects in preparation or implementation.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'implementation'],
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'statuses' => [
                    ProjectStatus::APPROVED,
                    ProjectStatus::FOR_IMPLEMENTATION,
                    ProjectStatus::ONGOING_IMPLEMENTATION,
                ],
            ],
            'post_documents' => [
                'label' => 'Post-Documents',
                'description' => 'Direct Administration projects awaiting post-document submission.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'post-documents'],
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'statuses' => [ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS],
            ],
            'acp_implementation' => [
                'label' => 'ACP Implementation',
                'description' => 'Through ACP projects in implementation.',
                'route' => 'acp-workflow.implementation',
                'implementation_mode' => ImplementationMode::THROUGH_ACP,
                'statuses' => [
                    ProjectStatus::FOR_IMPLEMENTATION,
                    ProjectStatus::ONGOING_IMPLEMENTATION,
                ],
            ],
        ];

        $financial = [
            'payment' => [
                'label' => 'DA Payment',
                'description' => 'Projects requiring obligation or disbursement action.',
                'route' => 'payments.index',
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'statuses' => [ProjectStatus::FOR_PAYMENT],
            ],
            'acp_payment' => [
                'label' => 'ACP Payment',
                'description' => 'Through ACP projects waiting for payment recording.',
                'route' => 'acp-workflow.payment',
                'implementation_mode' => ImplementationMode::THROUGH_ACP,
                'statuses' => [ProjectStatus::FOR_PAYMENT],
            ],
            'acp_check_release' => [
                'label' => 'Check Release',
                'description' => 'Through ACP projects waiting for check release.',
                'route' => 'acp-workflow.check-release',
                'implementation_mode' => ImplementationMode::THROUGH_ACP,
                'statuses' => [ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT],
            ],
            'acp_liquidation' => [
                'label' => 'Liquidation',
                'description' => 'Through ACP projects waiting for partial or final liquidation.',
                'route' => 'acp-workflow.liquidation',
                'implementation_mode' => ImplementationMode::THROUGH_ACP,
                'statuses' => [
                    ProjectStatus::FOR_LIQUIDATION,
                    ProjectStatus::PARTIALLY_LIQUIDATED,
                ],
            ],
        ];

        if ($user->isTc()) {
            return $tcOperational;
        }

        if ($user->isFocal()) {
            return $financial;
        }

        if ($user->isAdmin()) {
            return $tcOperational + $financial;
        }

        return [];
    }

    /** @param array<string,mixed> $definition */
    private function matches(Project $project, array $definition): bool
    {
        if (isset($definition['implementation_mode'])
            && $project->implementation_mode !== $definition['implementation_mode']) {
            return false;
        }

        return in_array($project->status, $definition['statuses'], true);
    }

    /** @param array<string,mixed> $definition */
    private function item(
        Project $project,
        string $queueKey,
        array $definition,
        int $attentionDays,
        int $criticalDays,
    ): array {
        $startedAt = $this->statusStartedAt($project);
        $ageDays = $this->ageDays($startedAt);

        return [
            'project_id' => $project->id,
            'project_title' => $project->project_title,
            'location' => collect([$project->municipality, $project->province])->filter()->implode(', '),
            'status' => $project->status,
            'status_label' => $project->status->label(),
            'queue_key' => $queueKey,
            'queue_label' => $definition['label'],
            'queue_url' => route($definition['route'], $definition['route_params'] ?? []),
            'status_started_at' => $startedAt,
            'age_days' => $ageDays,
            'needs_attention' => $ageDays >= $attentionDays,
            'critical' => $ageDays >= $criticalDays,
        ];
    }

    private function statusStartedAt(Project $project): Carbon
    {
        $value = $project->getAttribute('current_status_changed_at')
            ?? $project->updated_at
            ?? $project->created_at
            ?? now();

        return Carbon::parse($value);
    }

    private function ageDays(Carbon $startedAt): int
    {
        $start = $startedAt->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($start->greaterThan($today)) {
            return 0;
        }

        return (int) $start->diffInDays($today);
    }
}
