<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Services\Dashboards\DashboardActionQueueService;
use Illuminate\Support\Collection;

class NotificationCenterService
{
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
        ];
    }
}
