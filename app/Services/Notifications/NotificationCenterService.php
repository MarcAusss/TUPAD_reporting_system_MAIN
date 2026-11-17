<?php

namespace App\Services\Notifications;

use App\Models\Project;
use App\Models\ProjectEditRequest;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Dashboards\DashboardActionQueueService;
use App\Services\Projects\ProjectSectionRegistry;
use App\Services\Projects\ProjectWorkspacePresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    private const PROJECT_ITEM_LIMIT = 30;

    /** Declined edit requests stay visible to the TC for this many days. */
    private const DECLINED_VISIBLE_DAYS = 3;

    public function __construct(
        private readonly DashboardActionQueueService $actionQueues,
        private readonly ProvinceAccessService $provinceAccess,
        private readonly ProjectSectionRegistry $sections,
        private readonly ProjectWorkspacePresenter $workspacePresenter,
    ) {
    }

    /**
     * Live notifications derived from authoritative official-project workflow state.
     * No separate read/unread state is persisted.
     *
     * @return array{total_count:int,critical_count:int,attention_count:int,items:Collection<int,array<string,mixed>>}
     */
    public function build(User $user): array
    {
        $items = collect();
        $queueData = $this->actionQueues->build($user);

        foreach ($queueData['queues'] as $queue) {
            if ((int) $queue['count'] < 1) {
                continue;
            }

            $severity = (int) $queue['critical_count'] > 0
                ? 'critical'
                : ((int) $queue['aged_count'] > 0 ? 'attention' : 'normal');

            $items->push([
                'key' => 'queue:'.$queue['key'],
                'title' => $queue['label'],
                'message' => $queue['count'].' pending action(s). Oldest: '.$queue['oldest_days'].' day(s).',
                'count' => (int) $queue['count'],
                'severity' => $severity,
                'url' => $queue['url'],
                'category' => 'Workflow Queue',
                'state_token' => (string) ($queue['state_token'] ?? ''),
            ]);
        }

        $editRequests = $this->editRequestsFor($user);

        if ($editRequests->isNotEmpty()) {
            $items->push([
                'key' => 'edit-requests',
                'title' => $user->isTc() ? 'Edit Request Decisions' : 'Edit Requests',
                'message' => $user->isTc()
                    ? $editRequests->count().' edit request decision(s) from the Focal.'
                    : $editRequests->count().' TUPAD Coordinator edit request(s) awaiting your approval.',
                'count' => $editRequests->count(),
                'severity' => 'attention',
                'url' => route('notifications.index'),
                'category' => 'Edit Request',
                'state_token' => sha1($editRequests->map(fn (ProjectEditRequest $editRequest): string => $editRequest->id.':'.$editRequest->status)->implode('|')),
            ]);
        }

        $severityOrder = ['critical' => 0, 'attention' => 1, 'normal' => 2];
        $items = $items
            ->sortBy(fn (array $item): string => sprintf('%d-%s', $severityOrder[$item['severity']] ?? 9, $item['title']))
            ->values();

        return [
            'total_count' => (int) $items->sum('count'),
            'critical_count' => (int) $items->where('severity', 'critical')->sum('count'),
            'attention_count' => (int) $items->whereIn('severity', ['critical', 'attention'])->sum('count'),
            'items' => $items,
            'project_items' => $this->editRequestItems($user, $editRequests)
                ->concat($this->projectItems($queueData['queues']))
                ->sortByDesc(fn (array $item): string => $item['occurred_at'])
                ->take(self::PROJECT_ITEM_LIMIT)
                ->values(),
        ];
    }

    /**
     * Focal/Admin: pending TC requests in scope. TC: their own approved
     * (not yet used) requests and recently declined ones.
     *
     * @return Collection<int, ProjectEditRequest>
     */
    private function editRequestsFor(User $user): Collection
    {
        $query = ProjectEditRequest::query()
            ->with(['project', 'requester', 'decider'])
            ->whereHas('project', fn ($projects) => $this->provinceAccess->scopeProjects($projects, $user));

        if ($user->isAdmin() || $user->isFocal()) {
            return $query->where('status', ProjectEditRequest::PENDING)->latest()->get();
        }

        if ($user->isTc()) {
            return $query
                ->where('requested_by', $user->id)
                ->where(fn ($decided) => $decided
                    ->where('status', ProjectEditRequest::APPROVED)
                    ->orWhere(fn ($declined) => $declined
                        ->where('status', ProjectEditRequest::DECLINED)
                        ->where('decided_at', '>=', now()->subDays(self::DECLINED_VISIBLE_DAYS))))
                ->latest('decided_at')
                ->get();
        }

        return collect();
    }

    /**
     * @param  Collection<int, ProjectEditRequest>  $editRequests
     * @return Collection<int, array<string, mixed>>
     */
    private function editRequestItems(User $user, Collection $editRequests): Collection
    {
        return $editRequests->map(function (ProjectEditRequest $editRequest) use ($user): array {
            $project = $editRequest->project;
            $sectionLabel = $this->sections->definitions()[$editRequest->section]['label'] ?? $editRequest->section;
            $progress = $this->workspacePresenter->progressSummary($project);
            $occurredAt = Carbon::parse($editRequest->decided_at ?? $editRequest->created_at);
            $sectionUrl = route('projects.show', ['project' => $project->id, 'workspace' => 'overview'])
                .'#section-'.str_replace('_', '-', $editRequest->section).'-'.$editRequest->record_id;

            $isDecision = $user->isTc();
            $approved = $editRequest->status === ProjectEditRequest::APPROVED;

            return [
                'key' => 'edit-request:'.$editRequest->id.':'.$editRequest->status,
                'kind' => $isDecision ? 'edit_decision' : 'edit_request',
                'project_id' => (int) $project->id,
                'project_title' => (string) $project->project_title,
                'location' => collect([$project->municipality, $project->province])->filter()->implode(', '),
                'implementation_mode' => (string) ($project->implementation_mode?->label() ?? ''),
                'beneficiaries_total' => (int) $project->beneficiaries_total,
                'total_project_cost' => (float) $project->total_project_cost,
                'status_label' => $project->status->label(),
                'queue_label' => 'Edit Request',
                'action_label' => null,
                'message' => $isDecision
                    ? ($approved
                        ? sprintf('Your edit request for %s was approved by %s. You can edit it once.', $sectionLabel, $editRequest->decider?->name ?? 'the Focal')
                        : sprintf('Your edit request for %s was declined by %s.', $sectionLabel, $editRequest->decider?->name ?? 'the Focal'))
                    : sprintf('%s requests to edit %s.', $editRequest->requester?->name ?? 'A TUPAD Coordinator', $sectionLabel),
                'reason' => $editRequest->reason,
                'actions' => $isDecision ? [] : [
                    ['label' => 'Approve', 'url' => route('edit-requests.approve', $editRequest), 'style' => 'primary'],
                    ['label' => 'Decline', 'url' => route('edit-requests.decline', $editRequest), 'style' => 'secondary', 'confirm' => 'The TUPAD Coordinator will be notified and can send a new request.'],
                ],
                'progress_percent' => (int) $progress['percent'],
                'stage_label' => (string) $progress['stage_label'],
                'stage_number' => (int) $progress['stage_number'],
                'stage_count' => (int) $progress['stage_count'],
                'severity' => $isDecision && ! $approved ? 'critical' : 'attention',
                'occurred_at' => $occurredAt->toIso8601String(),
                'occurred_human' => $occurredAt->diffForHumans(),
                'url' => $sectionUrl,
            ];
        })->values();
    }

    /**
     * One notification per project, newest status change first, for the header
     * dropdown. Each links to the project's progress section.
     *
     * @param  array<string,array<string,mixed>>  $queues
     * @return Collection<int,array<string,mixed>>
     */
    private function projectItems(array $queues): Collection
    {
        return collect($queues)
            ->flatMap(fn (array $queue): Collection => collect($queue['items'] ?? []))
            ->unique(fn (array $item): string => $item['project_id'].':'.$item['queue_key'])
            ->sortByDesc(fn (array $item): int => $item['status_started_at']->getTimestamp())
            ->take(self::PROJECT_ITEM_LIMIT)
            ->map(fn (array $item): array => [
                'key' => 'project:'.$item['project_id'].':'.$item['status']->value.':'.$item['queue_key'],
                'kind' => 'workflow',
                'project_id' => (int) $item['project_id'],
                'project_title' => (string) $item['project_title'],
                'location' => (string) $item['location'],
                'implementation_mode' => (string) $item['implementation_mode_label'],
                'beneficiaries_total' => (int) $item['beneficiaries_total'],
                'total_project_cost' => (float) $item['total_project_cost'],
                'status_label' => (string) $item['status_label'],
                'queue_label' => (string) $item['queue_label'],
                'action_label' => $item['action_label'] ?? null,
                'progress_percent' => (int) $item['progress']['percent'],
                'stage_label' => (string) $item['progress']['stage_label'],
                'stage_number' => (int) $item['progress']['stage_number'],
                'stage_count' => (int) $item['progress']['stage_count'],
                'severity' => $item['critical'] ? 'critical' : ($item['needs_attention'] ? 'attention' : 'normal'),
                'occurred_at' => $item['status_started_at']->toIso8601String(),
                'occurred_human' => $item['status_started_at']->diffForHumans(),
                'url' => $item['item_url'] ?? route('projects.show', [
                    'project' => $item['project_id'],
                    'workspace' => 'overview',
                ]).'#project-progress',
            ])
            ->values();
    }
}
