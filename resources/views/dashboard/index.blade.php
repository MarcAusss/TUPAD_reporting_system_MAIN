@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

        @php
            $user = auth()->user();

            $roleTitle = match ($roleMode) {
                'focal' => 'Focal Fund Monitoring Dashboard',
                'tc' => 'TUPAD Coordinator Dashboard',
                'admin' => 'Administrator Dashboard',
                default => 'Dashboard',
            };

            $roleDescription = match ($roleMode) {
                'focal' => 'Monitor ADL funds, allocations, payments, and regional utilization.',
                'tc' => 'Monitor funds, beneficiaries, reports, and workflow queues of '.($assignedProvinceName ?? 'your assigned province').'.',
                'admin' => 'Review program-wide funds, projects, workflow queues, and administrative activity.',
                default => 'Overview of TUPAD program funds and official project activity.',
            };

            $remainingPercent = $totalBudget > 0 ? max(0, 100 - $utilizationPercent) : 0;

            $maxTrend = max(1, (float) $cumulativeTrend->max());

            $chartWidth = 720;
            $chartHeight = 210;
            $plotTop = 16;
            $plotBottom = 184;
            $plotLeft = 26;
            $plotRight = 700;
            $stepX = ($plotRight - $plotLeft) / 11;

            $points = $cumulativeTrend
                ->values()
                ->map(function ($value, $index) use ($maxTrend, $plotTop, $plotBottom, $plotLeft, $stepX) {
                    $x = $plotLeft + $index * $stepX;

                    $ratio = $maxTrend > 0 ? $value / $maxTrend : 0;

                    $y = $plotBottom - ($plotBottom - $plotTop) * $ratio;

                    return [
                        'x' => round($x, 2),
                        'y' => round($y, 2),
                    ];
                });

            $polyline = $points->map(fn($point) => $point['x'] . ',' . $point['y'])->implode(' ');

            $areaPoints = $plotLeft . ',' . $plotBottom . ' ' . $polyline . ' ' . $plotRight . ',' . $plotBottom;

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        @endphp

        <x-page-header :eyebrow="$roleTitle" title="Dashboard" :description="$roleDescription">
            <x-slot:actions>

                @if ($roleMode === 'focal')

                    <a href="{{ route('adl.index') }}"
                        class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                        Manage ADL
                    </a>

                    @if (Route::has('fund-monitoring.per-adl-current'))
                        <a href="{{ route('fund-monitoring.per-adl-current') }}"
                            class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Open Monitoring
                        </a>
                    @endif
                @elseif($roleMode === 'tc')
                    <a href="{{ route('projects.create') }}"
                        class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                        + Add Project
                    </a>

                    <a href="{{ route('project-workflow.index', ['queue' => 'tssd-evaluation']) }}"
                        class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Open Workflow
                    </a>
                @elseif($roleMode === 'admin')
                    <a href="{{ route('projects.index') }}"
                        class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                        View Projects
                    </a>

                @endif

            </x-slot:actions>
        </x-page-header>

        {{-- =====================================================
        Action queue aging summary
    ====================================================== --}}
        @if ($roleMode === 'focal')
            {{-- Fund position, status, beneficiaries, implementation, fund flow and activity,
                 then the provincial resource mapping and the report pies. --}}
            @include('dashboard.partials.focal-filters')
            @include('dashboard.partials.focal-overview')
            <div class="mt-5">
                @include('dashboard.partials.focal-beneficiary-distribution')
            </div>
            @include('dashboard.partials.report-pie-charts')
        @elseif ($roleMode === 'tc')
            {{-- Same layout as the Focal dashboard, limited to the assigned province. --}}
            @include('dashboard.partials.focal-filters')
            @include('dashboard.partials.focal-overview')

            {{-- Beneficiaries (left) and projects (right), drawn the same way. --}}
            <div class="mt-5 grid gap-5 xl:grid-cols-2" data-tc-distributions>
                <div class="min-w-0">
                    @include('dashboard.partials.focal-beneficiary-distribution', ['bdCompact' => true])
                </div>
                <div class="min-w-0">
                    @include('dashboard.partials.focal-beneficiary-distribution', [
                        'distribution' => $projectDistribution,
                        'bdKind' => 'projects',
                        'bdDataId' => 'tcProjectDistributionData',
                        'bdCompact' => true,
                    ])
                </div>
            </div>

            @include('dashboard.partials.report-pie-charts')
            <div class="mt-5"></div>
        @else
            <section class="mb-5" data-dashboard-aging-summary>
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Action Queue Aging</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Prioritize records that have remained in their current workflow status the longest.
                        </p>
                    </div>
                    <p class="text-[11px] leading-4 text-slate-400 sm:max-w-md sm:text-right">
                        Attention at {{ $actionQueueData['attention_days'] }}+ days; critical at
                        {{ $actionQueueData['critical_days'] }}+ days. These are internal dashboard indicators, not statutory deadlines.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['Pending Actions', $actionQueueData['total_pending'], 'Projects currently waiting in your role-specific queues.'],
                        ['Needs Attention', $actionQueueData['aged_pending'], 'Items aged '.$actionQueueData['attention_days'].' days or more.'],
                        ['Critical Aging', $actionQueueData['critical_pending'], 'Items aged '.$actionQueueData['critical_days'].' days or more.'],
                        ['Oldest Pending', $actionQueueData['oldest_days'].' days', 'Age of the oldest pending action visible to your account.'],
                    ] as [$label, $value, $description])
                        <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $label }}</div>
                            <div class="mt-2 text-2xl font-extrabold text-slate-900">
                                {{ is_numeric($value) ? number_format($value) : $value }}
                            </div>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- =====================================================
        Role-specific work queue
    ====================================================== --}}
        @if ($roleMode === 'admin')
            <section class="mb-5">
                <div class="mb-3">
                    <h2 class="text-sm font-semibold text-slate-900">Project Workflow</h2>
                    <p class="mt-1 text-xs text-slate-500">Open a queue to continue projects that require action.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach (['tssd', 'compliance', 'approval', 'implementation', 'post_documents'] as $queueKey)
                        @php($queue = $actionQueueData['queues'][$queueKey] ?? null)
                        @if ($queue)
                            <a href="{{ $queue['url'] }}" data-dashboard-queue="{{ $queueKey }}"
                                class="group rounded-xl border bg-white p-4 shadow-sm transition {{ $queue['critical_count'] > 0 ? 'border-rose-200 hover:bg-rose-50' : ($queue['aged_count'] > 0 ? 'border-amber-200 hover:bg-amber-50' : 'border-slate-200 hover:border-blue-200 hover:bg-blue-50') }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Pending Queue</div>
                                    @if ($queue['aged_count'] > 0)
                                        <span class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800">
                                            {{ number_format($queue['aged_count']) }} aged
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-2 flex items-end justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-800">{{ $queue['label'] }}</div>
                                    <div class="text-2xl font-extrabold text-slate-900">{{ number_format($queue['count']) }}</div>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3 text-[11px] text-slate-500">
                                    <span>{{ $queue['description'] }}</span>
                                    <span class="shrink-0 font-semibold">Oldest {{ $queue['oldest_days'] }}d</span>
                                </div>
                            </a>
                        @endif
                    @endforeach
                </div>
            </section>

            @php($acpImplementation = $actionQueueData['queues']['acp_implementation'] ?? null)
            @if ($acpImplementation || $roleMode === 'admin')
                <section class="mb-5">
                    <div class="mb-3">
                        <h2 class="text-sm font-semibold text-slate-900">Through ACP Workflow</h2>
                        <p class="mt-1 text-xs text-slate-500">Separate ACP implementation and financial queues.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach (['acp_implementation', 'acp_payment', 'acp_check_release', 'acp_liquidation'] as $queueKey)
                            @php($queue = $actionQueueData['queues'][$queueKey] ?? null)
                            @if ($queue)
                                <a href="{{ $queue['url'] }}" data-dashboard-queue="{{ $queueKey }}"
                                    class="rounded-xl border bg-white p-4 shadow-sm {{ $queue['critical_count'] > 0 ? 'border-rose-200 hover:bg-rose-50' : ($queue['aged_count'] > 0 ? 'border-amber-200 hover:bg-amber-50' : 'border-slate-200 hover:border-blue-200 hover:bg-blue-50') }}">
                                    <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">Through ACP</div>
                                    <div class="mt-2 flex items-end justify-between gap-3">
                                        <div class="text-sm font-semibold text-slate-900">{{ $queue['label'] }}</div>
                                        <div class="text-2xl font-extrabold text-slate-900">{{ number_format($queue['count']) }}</div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                        <span>{{ number_format($queue['aged_count']) }} aged</span>
                                        <span class="font-semibold">Oldest {{ $queue['oldest_days'] }}d</span>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        @elseif ($roleMode === 'tc')
            <div class="mt-5">@include('dashboard.partials.tc-aging-strip')</div>
            @include('dashboard.partials.tc-work-queue')
        @endif

        @if ($roleMode === 'admin')
            @include('dashboard.partials.program-snapshot')
            @include('dashboard.partials.fund-trend-chart')
            @include('dashboard.partials.adl-utilization-chart')
        @endif

        {{-- The Focal and TC dashboards leave Oldest Pending Actions to the work queue and show Recent Official Projects on Notifications. --}}
        @if (! in_array($roleMode, ['focal', 'tc'], true))
            @if ($actionQueueData['oldest_items']->isNotEmpty())
                <section class="tupad-table-shell mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-dashboard-oldest-actions>
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Oldest Pending Actions</h2>
                            <p class="mt-1 text-xs text-slate-500">Oldest visible items across the queues assigned to your role.</p>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-400">Age is measured from the latest recorded status transition.</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="tupad-system-table min-w-full">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Queue</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Location</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Age</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($actionQueueData['oldest_items'] as $item)
                                    <tr>
                                        <td class="px-5 py-3 text-sm font-semibold text-slate-900">{{ $item['project_title'] }}</td>
                                        <td class="px-5 py-3 text-xs text-slate-600">{{ $item['queue_label'] }}</td>
                                        <td class="px-5 py-3 text-xs text-slate-600">{{ $item['status_label'] }}</td>
                                        <td class="px-5 py-3 text-xs text-slate-500">{{ $item['location'] ?: 'Location not available' }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $item['critical'] ? 'bg-rose-50 text-rose-700' : ($item['needs_attention'] ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                                {{ $item['age_days'] }} days
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <a href="{{ $item['queue_url'] }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Open Queue →</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @include('dashboard.partials.recent-official-projects')
        @endif

@endsection
