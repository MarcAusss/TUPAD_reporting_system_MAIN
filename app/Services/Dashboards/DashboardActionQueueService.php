<?php

namespace App\Services\Dashboards;

use App\Enums\ImplementationMode;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectDisbursement;
use App\Models\ProjectImplementation;
use App\Models\ProjectObligation;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Projects\ProjectWorkspacePresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardActionQueueService
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
        private readonly ProjectWorkspacePresenter $workspacePresenter,
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
            // Completed projects are only kept when their beneficiary
            // deductions are still outstanding (completion does not wait).
            ->where(fn ($query) => $query
                ->where('status', '!=', ProjectStatus::COMPLETED->value)
                ->orWhere(fn ($pending) => $pending
                    ->whereNotNull('obligations_completed_at')
                    ->whereNull('beneficiary_deductions_recorded_at')))
            ->addSelect([
                'obligated_beneficiaries' => ProjectObligation::query()
                    ->selectRaw('coalesce(sum(beneficiaries_total), 0)')
                    ->whereColumn('project_obligations.project_id', 'projects.id'),
                'obligated_female' => ProjectObligation::query()
                    ->selectRaw('coalesce(sum(beneficiaries_female), 0)')
                    ->whereColumn('project_obligations.project_id', 'projects.id'),
                'current_status_changed_at' => ProjectStatusHistory::query()
                    ->select('changed_at')
                    ->whereColumn('project_status_histories.project_id', 'projects.id')
                    ->whereColumn('project_status_histories.to_status', 'projects.status')
                    ->latest('changed_at')
                    ->limit(1),
                // When the latest fully disbursed, not-yet-released tranche was
                // disbursed: the moment the TC's Release of Assistance is due.
                'release_pending_since' => ProjectDisbursement::query()
                    ->selectRaw('max(project_disbursements.created_at)')
                    ->join('project_obligations', 'project_obligations.id', '=', 'project_disbursements.project_obligation_id')
                    ->whereColumn('project_obligations.project_id', 'projects.id')
                    ->whereNull('project_obligations.release_date')
                    ->whereRaw(ProjectObligation::FULLY_DISBURSED_SQL),
                'work_period_end_date' => ProjectImplementation::query()
                    ->select('end_date')
                    ->whereColumn('project_implementations.project_id', 'projects.id')
                    ->limit(1),
            ])
            ->withExists(['nafa', 'payout'])
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
                'state_token' => sha1($matching
                    ->map(fn (array $item): string => $item['project_id'].':'.$item['status_label'].':'.$item['status_started_at']->getTimestamp())
                    ->sort()
                    ->implode('|')),
                'items' => $matching,
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
            'release' => [
                'label' => 'Release of Assistance',
                'description' => 'Direct Administration projects with fully disbursed tranches awaiting the Release of Assistance.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'release-of-assistance'],
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'statuses' => [ProjectStatus::FOR_PAYMENT],
                'filter' => fn (Project $project): bool =>
                    $project->release_pending_since !== null,
                // The TC's action starts when the Focal fully disburses a tranche.
                'started_at_attribute' => 'release_pending_since',
                'action_label' => 'Release of Assistance',
                'item_route' => 'projects.show',
                'item_route_params' => ['workspace' => 'workflow'],
                'item_anchor' => 'release-of-assistance',
            ],
            'beneficiary_deduction' => [
                'label' => 'Beneficiary Deduction',
                'description' => 'Obligations completed with fewer beneficiaries than declared; the TC indicates the addresses of the beneficiaries not included.',
                'route' => 'project-workflow.index',
                'route_params' => ['queue' => 'beneficiary-deduction'],
                'implementation_mode' => ImplementationMode::DIRECT_ADMINISTRATION,
                'statuses' => [ProjectStatus::FOR_PAYMENT, ProjectStatus::COMPLETED],
                'filter' => fn (Project $project): bool =>
                    $project->obligations_completed_at !== null
                    && $project->beneficiary_deductions_recorded_at === null
                    && (
                        (int) $project->beneficiaries_total > (int) $project->obligated_beneficiaries
                        || (int) $project->beneficiaries_female > (int) $project->obligated_female
                    ),
                'started_at_attribute' => 'obligations_completed_at',
                'action_label' => 'Beneficiary Deduction',
                'item_route' => 'projects.show',
                'item_route_params' => ['workspace' => 'overview'],
                'item_anchor' => 'section-beneficiary-deduction-{id}',
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
                // GSIS enrollment, PPE, NAFA, NTP, orientation, and work period
                // are recorded on the project's implementation steps.
                'item_route' => 'projects.show',
                'item_route_params' => ['workspace' => 'workflow'],
                'item_anchor' => 'implementation',
            ],
            'acp_release' => [
                'label' => 'ACP Release of Assistance',
                'description' => 'Through ACP projects whose work period has ended and need the Release of Assistance.',
                'route' => 'acp-workflow.implementation',
                'implementation_mode' => ImplementationMode::THROUGH_ACP,
                'statuses' => [ProjectStatus::ONGOING_IMPLEMENTATION],
                'filter' => fn (Project $project): bool =>
                    $project->nafa_exists
                    && ! $project->payout_exists
                    && $project->work_period_end_date !== null
                    && Carbon::parse($project->work_period_end_date)->startOfDay()->lte(now('Asia/Manila')->startOfDay()),
                'started_at_attribute' => 'work_period_end_date',
                'action_label' => 'Release of Assistance',
                'item_route' => 'projects.show',
                'item_route_params' => ['workspace' => 'workflow'],
                'item_anchor' => 'acp-release-of-assistance',
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

        if (! in_array($project->status, $definition['statuses'], true)) {
            return false;
        }

        return ! isset($definition['filter']) || $definition['filter']($project);
    }

    /** @param array<string,mixed> $definition */
    private function item(
        Project $project,
        string $queueKey,
        array $definition,
        int $attentionDays,
        int $criticalDays,
    ): array {
        $startedAt = isset($definition['started_at_attribute'])
            && $project->getAttribute($definition['started_at_attribute'])
                ? Carbon::parse($project->getAttribute($definition['started_at_attribute']))
                : $this->statusStartedAt($project);
        $ageDays = $this->ageDays($startedAt);

        return [
            'project_id' => $project->id,
            'project_title' => $project->project_title,
            'location' => collect([$project->municipality, $project->province])->filter()->implode(', '),
            'implementation_mode_label' => $project->implementation_mode?->label() ?? '',
            'beneficiaries_total' => (int) $project->beneficiaries_total,
            'total_project_cost' => (float) $project->total_project_cost,
            'progress' => $this->workspacePresenter->progressSummary($project),
            'status' => $project->status,
            'status_label' => $project->status->label(),
            'queue_key' => $queueKey,
            'queue_label' => $definition['label'],
            'queue_url' => route($definition['route'], $definition['route_params'] ?? []),
            'action_label' => $definition['action_label'] ?? null,
            'item_url' => isset($definition['item_route'])
                ? route($definition['item_route'], ['project' => $project->id] + ($definition['item_route_params'] ?? []))
                    .(isset($definition['item_anchor']) ? '#'.str_replace('{id}', (string) $project->id, $definition['item_anchor']) : '')
                : null,
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
