<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\Dashboards\DashboardActionQueueService;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    private const PROJECT_ITEM_LIMIT = 30;

    public function __construct(
        private readonly DashboardActionQueueService $actionQueues,
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

        $severityOrder = ['critical' => 0, 'attention' => 1, 'normal' => 2];
        $items = $items
            ->sortBy(fn (array $item): string => sprintf('%d-%s', $severityOrder[$item['severity']] ?? 9, $item['title']))
            ->values();

        return [
            'total_count' => (int) $items->sum('count'),
            'critical_count' => (int) $items->where('severity', 'critical')->sum('count'),
            'attention_count' => (int) $items->whereIn('severity', ['critical', 'attention'])->sum('count'),
            'items' => $items,
            'project_items' => $this->projectItems($queueData['queues']),
        ];
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
            ->unique('project_id')
            ->sortByDesc(fn (array $item): int => $item['status_started_at']->getTimestamp())
            ->take(self::PROJECT_ITEM_LIMIT)
            ->map(fn (array $item): array => [
                'key' => 'project:'.$item['project_id'].':'.$item['status']->value.':'.$item['queue_key'],
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
