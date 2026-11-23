<?php

namespace App\Services\Notifications;

use App\Models\GpaiEnrollment;
use App\Models\GpaiEnrollmentItem;
use App\Models\Project;
use App\Models\ProjectEditRequest;
use App\Models\User;
use App\Services\Auth\ProvinceAccessService;
use App\Services\Dashboards\DashboardActionQueueService;
use App\Services\Gsis\GpaiEnrollmentService;
use App\Services\Projects\ProjectSectionRegistry;
use App\Services\Projects\ProjectWorkspacePresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    private const PROJECT_ITEM_LIMIT = 30;

    /** Declined edit requests stay visible to the TC for this many days. */
    private const DECLINED_VISIBLE_DAYS = 3;

    /** GSIS GPAI enrollment news stays visible to the TC for this many days. */
    private const GPAI_VISIBLE_DAYS = 14;

    public function __construct(
        private readonly DashboardActionQueueService $actionQueues,
        private readonly ProvinceAccessService $provinceAccess,
        private readonly ProjectSectionRegistry $sections,
        private readonly ProjectWorkspacePresenter $workspacePresenter,
        private readonly GpaiEnrollmentService $gpai,
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

        $gpaiItems = $this->gpaiProjectItems($user);
        $items = $items->concat($this->gpaiSummaryItems($user, $gpaiItems));

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
                ->concat($gpaiItems)
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

    /**
     * TC: projects in their province recently enrolled to GSIS GPAI by the
     * GSIS Enrollment Officer, and those whose OR and Policy were received.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function gpaiProjectItems(User $user): Collection
    {
        if ($user->isGsis()) {
            return $this->gsisOfficerItems();
        }

        if (! $user->isTc()) {
            return collect();
        }

        $since = now()->subDays(self::GPAI_VISIBLE_DAYS);

        return GpaiEnrollmentItem::query()
            ->with(['enrollment', 'project'])
            ->whereHas('project', fn ($projects) => $this->provinceAccess->scopeProjects($projects, $user))
            ->whereHas('enrollment', fn ($enrollments) => $enrollments
                ->where(fn ($recent) => $recent
                    ->where('created_at', '>=', $since)
                    ->orWhere('or_policy_received_on', '>=', $since->toDateString())))
            ->latest('id')
            ->limit(self::PROJECT_ITEM_LIMIT)
            ->get()
            ->map(function (GpaiEnrollmentItem $item): array {
                $enrollment = $item->enrollment;
                $project = $item->project;
                $complete = $enrollment->isComplete();
                $occurredAt = Carbon::parse($complete ? ($enrollment->distributed_on ?? $enrollment->or_policy_received_on) : $enrollment->created_at);

                return [
                    'key' => 'gpai:'.$item->id.':'.($complete ? 'complete' : 'enrolled'),
                    'kind' => 'gpai_enrollment',
                    'project_id' => (int) $project->id,
                    'project_title' => (string) $project->project_title,
                    'location' => collect([$project->municipality, $project->province])->filter()->implode(', '),
                    'implementation_mode' => (string) ($project->implementation_mode?->label() ?? ''),
                    'beneficiaries_total' => (int) $project->beneficiaries_total,
                    'total_project_cost' => (float) $project->total_project_cost,
                    'status_label' => $project->status->label(),
                    'queue_label' => 'GSIS GPAI Enrollment',
                    'action_label' => null,
                    'message' => $complete
                        ? sprintf('OR and Policy received for %s (OR No. %s, Policy ID %s).', $enrollment->gpai_number, $enrollment->or_number ?: '—', $enrollment->policy_id ?: '—')
                        : sprintf('Enrolled to GSIS GPAI under %s on %s: %s beneficiaries, premium ₱%s.', $enrollment->gpai_number, $enrollment->transmittal_date->format('M j, Y'), number_format($item->beneficiaries), number_format((float) $item->premium, 2)),
                    'reason' => null,
                    'actions' => [],
                    'progress_percent' => 0,
                    'stage_label' => $enrollment->stageLabel(),
                    'stage_number' => 0,
                    'stage_count' => 0,
                    'severity' => 'normal',
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_human' => $occurredAt->diffForHumans(),
                    'url' => route('projects.show', ['project' => $project->id, 'workspace' => 'overview']),
                ];
            })
            ->values();
    }

    /**
     * GSIS Enrollment Officer list items, matching the bell count: every
     * transmittal still waiting for the OR and Policy (opens it at its next
     * step) and every project awaiting enrollment (opens Enroll Projects).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function gsisOfficerItems(): Collection
    {
        $stageKeys = array_keys(GpaiEnrollment::STAGES);
        $stageCount = count($stageKeys);

        $transmittals = GpaiEnrollment::query()
            ->with(['items.project'])
            ->whereNull('or_policy_received_on')
            ->latest('updated_at')
            ->limit(self::PROJECT_ITEM_LIMIT)
            ->get()
            ->map(function (GpaiEnrollment $enrollment) use ($stageKeys, $stageCount): array {
                $stageNumber = (int) array_search($enrollment->stage(), $stageKeys, true) + 1;
                $next = $enrollment->nextStep();
                $occurredAt = Carbon::parse($enrollment->updated_at ?? $enrollment->created_at);

                return [
                    'key' => 'gsis-transmittal:'.$enrollment->id.':'.$enrollment->stage().':'.($next ?? 'done'),
                    'kind' => 'gpai_enrollment',
                    'project_id' => null,
                    'project_title' => $enrollment->natureOfPayment(),
                    'location' => GpaiEnrollment::modeLabel($enrollment->payment_mode).' · '.$enrollment->items->count().' project(s)',
                    'implementation_mode' => '',
                    'beneficiaries_total' => $enrollment->beneficiaries(),
                    'total_project_cost' => $enrollment->totalCents() / 100,
                    'status_label' => $enrollment->stageLabel(),
                    'queue_label' => 'GSIS GPAI Enrollment',
                    'action_label' => null,
                    'message' => $next
                        ? sprintf('%s next step: %s.', $enrollment->gpai_number, GpaiEnrollment::STEPS[$next][0])
                        : sprintf('%s is waiting for the OR and Policy.', $enrollment->gpai_number),
                    'reason' => null,
                    'actions' => [],
                    'progress_percent' => (int) round(($stageNumber - 1) / max(1, $stageCount - 1) * 100),
                    'stage_label' => $enrollment->stageLabel(),
                    'stage_number' => $stageNumber,
                    'stage_count' => $stageCount,
                    'severity' => 'normal',
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_human' => $occurredAt->diffForHumans(),
                    'url' => route('gsis.enrollments.edit', $enrollment).($next ? '#step-'.$next : ''),
                ];
            });

        // Requested projects come first (eligibleProjects() order) and date from the request.
        $awaiting = $this->gpai->eligibleProjects()
            ->take(self::PROJECT_ITEM_LIMIT)
            ->map(function (Project $project) use ($stageCount): array {
                $request = $project->latestGpaiEnrollmentRequest;
                $occurredAt = Carbon::parse($request?->created_at ?? $project->updated_at ?? $project->created_at);

                return [
                    'key' => 'gsis-awaiting:'.$project->id.($request ? ':request-'.$request->id : ''),
                    'kind' => 'gpai_enrollment',
                    'project_id' => (int) $project->id,
                    'project_title' => (string) $project->project_title,
                    'location' => collect([$project->municipality, $project->province])->filter()->implode(', '),
                    'implementation_mode' => (string) ($project->implementation_mode?->label() ?? ''),
                    'beneficiaries_total' => (int) $project->insurance_beneficiaries,
                    'total_project_cost' => (float) $project->insurance_total,
                    'status_label' => 'Awaiting Enrollment',
                    'queue_label' => 'GSIS GPAI Enrollment',
                    'action_label' => null,
                    'message' => $request
                        ? sprintf('Enrollment requested by %s: %s insured beneficiaries, premium ₱%s.', $request->requester?->name ?? 'a TUPAD Coordinator', number_format((int) $project->insurance_beneficiaries), number_format((float) $project->insurance_total, 2))
                        : sprintf('Ready for GPAI enrollment: %s insured beneficiaries, premium ₱%s.', number_format((int) $project->insurance_beneficiaries), number_format((float) $project->insurance_total, 2)),
                    'reason' => null,
                    'actions' => [],
                    'progress_percent' => 0,
                    'stage_label' => $request ? 'Enrollment Requested' : 'Awaiting Enrollment',
                    'stage_number' => 0,
                    'stage_count' => $stageCount,
                    'severity' => $request ? 'critical' : 'attention',
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_human' => $occurredAt->diffForHumans(),
                    'url' => route('gsis.enrollments.create'),
                ];
            });

        return $transmittals->concat($awaiting)->values();
    }

    /**
     * Summary rows: the TC sees how many of their projects were enrolled; the
     * GSIS Enrollment Officer sees projects awaiting enrollment and
     * transmittals still in process.
     *
     * @param  Collection<int, array<string, mixed>>  $gpaiItems
     * @return Collection<int, array<string, mixed>>
     */
    private function gpaiSummaryItems(User $user, Collection $gpaiItems): Collection
    {
        if ($user->isTc()) {
            return $gpaiItems->isEmpty() ? collect() : collect([[
                'key' => 'gpai-enrollments',
                'title' => 'GSIS GPAI Enrollment',
                'message' => $gpaiItems->count().' of your project(s) were enrolled to GSIS GPAI or received their OR and Policy.',
                'count' => $gpaiItems->count(),
                'severity' => 'normal',
                'url' => route('notifications.index').'#gpai-enrollments',
                'category' => 'GSIS Enrollment',
                'state_token' => sha1($gpaiItems->pluck('key')->implode('|')),
            ]]);
        }

        if (! $user->isGsis()) {
            return collect();
        }

        $items = collect();
        $eligible = $this->gpai->eligibleProjectsQuery()->pluck('id');
        // Latest "Request GPAI Enrollment" per still-eligible project.
        $requestIds = \App\Models\GpaiEnrollmentRequest::query()
            ->whereIn('project_id', $eligible)
            ->selectRaw('MAX(id) as id')
            ->groupBy('project_id')
            ->pluck('id');

        if ($eligible->isNotEmpty()) {
            $items->push([
                'key' => 'gpai-awaiting',
                'title' => 'Projects Awaiting GPAI Enrollment',
                'message' => $eligible->count().' project(s) with an insurance budget are ready to enroll'
                    .($requestIds->isNotEmpty() ? ', '.$requestIds->count().' requested by TUPAD Coordinators.' : '.'),
                'count' => $eligible->count(),
                'severity' => $requestIds->isNotEmpty() ? 'critical' : 'attention',
                'url' => route('gsis.enrollments.create'),
                'category' => 'GSIS Enrollment',
                // A new request makes the bell unread again.
                'state_token' => sha1($eligible->implode(',').'|'.$requestIds->sort()->implode(',')),
            ]);
        }

        $inProcess = GpaiEnrollment::query()->whereNull('or_policy_received_on')->pluck('id');

        if ($inProcess->isNotEmpty()) {
            $items->push([
                'key' => 'gpai-in-process',
                'title' => 'Transmittals In Process',
                'message' => $inProcess->count().' GPAI transmittal(s) are waiting for the OR and Policy.',
                'count' => $inProcess->count(),
                'severity' => 'normal',
                'url' => route('gsis.status'),
                'category' => 'GSIS Enrollment',
                'state_token' => sha1($inProcess->implode(',')),
            ]);
        }

        return $items;
    }
}
