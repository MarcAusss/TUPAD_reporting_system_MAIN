@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    @if ($dashboardMode === 'gip')

        <x-page-header eyebrow="GIP Workspace" title="Dashboard"
            description="Track project drafts you encoded, review returned items, and continue work that still needs TC confirmation.">
            <x-slot:actions>
                <a href="{{ route('project-drafts.create') }}"
                    class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    + New Project Draft
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            @foreach ([['All Drafts', $totalDrafts, 'Project drafts encoded by your account.'], ['Pending TC Review', $pendingDrafts, 'Submitted drafts waiting for TC review.'], ['Returned', $returnedDrafts, 'Drafts requiring correction or clarification.'], ['Confirmed', $confirmedDrafts, 'Drafts successfully confirmed by TC.']] as [$label, $value, $description])
                <article class="tupad-card p-5">

                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        {{ $label }}
                    </div>

                    <div class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">
                        {{ number_format($value) }}
                    </div>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        {{ $description }}
                    </p>

                </article>
            @endforeach

        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">

                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Recent Project Drafts
                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Continue from the most recently updated draft.
                        </p>
                    </div>

                    <a href="{{ route('project-drafts.index') }}"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        View All Drafts
                    </a>

                </div>

                <div class="divide-y divide-slate-100">

                    @forelse($recentDrafts as $draft)
                        <a href="{{ route('project-drafts.show', $draft) }}"
                            class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50">
                            <div class="min-w-0">

                                <div class="truncate text-sm font-semibold text-slate-900">
                                    {{ $draft->project_title }}
                                </div>

                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $draft->status->label() }}
                                    ·
                                    {{ $draft->updated_at->format('M d, Y g:i A') }}
                                </div>

                            </div>

                            <span class="shrink-0 text-xs font-semibold text-blue-700">
                                Open →
                            </span>
                        </a>

                    @empty

                        <x-empty-state title="No project drafts yet"
                            message="Create your first project draft to begin the GIP encoding workflow.">
                            <x-slot:action>
                                <a href="{{ route('project-drafts.create') }}"
                                    class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                    Create Project Draft
                                </a>
                            </x-slot:action>
                        </x-empty-state>
                    @endforelse

                </div>

            </section>

            <aside class="rounded-xl border border-blue-200 bg-blue-50 p-5">

                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-600">
                    Recommended Workflow
                </div>

                <ol class="mt-4 space-y-4">

                    @foreach ([['1', 'Create or update a draft', 'Encode the project information assigned to GIP.'], ['2', 'Submit for TC review', 'The TUPAD Coordinator validates the draft.'], ['3', 'Correct returned drafts', 'Update only the items requested for correction.'], ['4', 'Wait for confirmation', 'Confirmed drafts become available to the official workflow.']] as [$step, $title, $description])
                        <li class="flex gap-3">

                            <div
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-800">
                                {{ $step }}
                            </div>

                            <div>
                                <div class="text-xs font-semibold text-blue-950">
                                    {{ $title }}
                                </div>

                                <p class="mt-1 text-[11px] leading-5 text-blue-700">
                                    {{ $description }}
                                </p>
                            </div>

                        </li>
                    @endforeach

                </ol>

            </aside>

        </div>
    @else
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
                'tc' => 'Review project workflow queues and continue projects that require TC action.',
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
                        class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
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
                        class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        + Add Project
                    </a>

                    <a href="{{ route('project-workflow.index', ['queue' => 'tssd-evaluation']) }}"
                        class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Open Workflow
                    </a>
                @elseif($roleMode === 'admin')
                    <a href="{{ route('projects.index') }}"
                        class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        View Projects
                    </a>

                @endif

            </x-slot:actions>
        </x-page-header>

        {{-- =====================================================
        Role-specific work queue
    ====================================================== --}}
        @if (in_array($roleMode, ['tc', 'admin'], true))

            <section class="mb-5">

                <div class="mb-3 flex items-end justify-between gap-4">

                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Project Workflow
                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Open a queue to continue projects that require action.
                        </p>
                    </div>

                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">

                    @foreach ([['tssd-evaluation', 'TSSD Evaluation', $workflowCounts['tssd']], ['for-approval', 'For Approval', $workflowCounts['approval']], ['implementation', 'Implementation', $workflowCounts['implementation']], ['post-documents', 'Post-Documents', $workflowCounts['post_documents']], ['release-of-assistance', 'Release of Assistance', $workflowCounts['release']]] as [$queue, $label, $count])
                        <a href="{{ route('project-workflow.index', ['queue' => $queue]) }}"
                            class="group rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:bg-blue-50">

                            <div
                                class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 group-hover:text-blue-600">
                                Pending Queue
                            </div>

                            <div class="mt-2 flex items-end justify-between gap-3">

                                <div class="text-sm font-semibold text-slate-800 group-hover:text-blue-950">
                                    {{ $label }}
                                </div>

                                <div class="text-2xl font-extrabold text-slate-900 group-hover:text-blue-900">
                                    {{ number_format($count) }}
                                </div>

                            </div>

                        </a>
                    @endforeach

                </div>

            </section>
        @elseif($roleMode === 'focal')
            <section class="mb-5">

                <div class="mb-3">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Focal Work Queue
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Shortcuts to the fund and payment areas that require Focal attention.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

                    <a href="{{ route('adl.index') }}"
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-200 hover:bg-blue-50">
                        <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                            Fund Management
                        </div>

                        <div class="mt-2 text-sm font-semibold text-slate-900">
                            {{ number_format($totalAdls) }} ADL Record(s)
                        </div>

                        <div class="mt-1 text-xs text-slate-500">
                            Review allocations and remaining balances.
                        </div>
                    </a>

                    @if (Route::has('fund-monitoring.per-adl-current'))
                        <a href="{{ route('fund-monitoring.per-adl-current') }}"
                            class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-200 hover:bg-blue-50">
                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                Monitoring
                            </div>

                            <div class="mt-2 text-sm font-semibold text-slate-900">
                                PER ADL (Current)
                            </div>

                            <div class="mt-1 text-xs text-slate-500">
                                Open the current workbook-aligned fund register.
                            </div>
                        </a>
                    @endif

                    <a href="{{ route('payments.index') }}"
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-200 hover:bg-blue-50">
                        <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                            Payment
                        </div>

                        <div class="mt-2 text-sm font-semibold text-slate-900">
                            {{ number_format($workflowCounts['payment']) }} Waiting
                        </div>

                        <div class="mt-1 text-xs text-slate-500">
                            Projects waiting for Payment of Wages / obligation.
                        </div>
                    </a>

                    <a href="{{ route('reports.index') }}"
                        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-blue-200 hover:bg-blue-50">
                        <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                            Reporting
                        </div>

                        <div class="mt-2 text-sm font-semibold text-slate-900">
                            Reports
                        </div>

                        <div class="mt-1 text-xs text-slate-500">
                            Filter, print, or export official project records.
                        </div>
                    </a>

                </div>

            </section>

        @endif

        {{-- =====================================================
        Program snapshot
    ====================================================== --}}
        <section>

            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-900">
                    Program Snapshot
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Current official project and beneficiary totals.
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">

                <article class="tupad-card p-5">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Active Projects
                    </div>

                    <div class="mt-2 text-2xl font-extrabold text-slate-900">
                        {{ number_format($activeProjects) }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                        {{ number_format($totalProjects) }} total official projects
                    </div>
                </article>

                <article class="tupad-card p-5">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Beneficiaries
                    </div>

                    <div class="mt-2 text-2xl font-extrabold text-slate-900">
                        {{ number_format($totalBeneficiaries) }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                        {{ number_format($femaleBeneficiaries) }} female beneficiaries
                    </div>
                </article>

                <article class="tupad-card p-5">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Completed Projects
                    </div>

                    <div class="mt-2 text-2xl font-extrabold text-slate-900">
                        {{ number_format($completedProjects) }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                        Completed official workflow
                    </div>
                </article>

                <article class="tupad-card p-5">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                        Total Program Budget
                    </div>

                    <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">
                        ₱{{ number_format($totalBudget, 2) }}
                    </div>

                    <div class="mt-1 text-xs text-slate-500">
                        Current adjusted ADL fund basis
                    </div>
                </article>

            </div>

        </section>

        {{-- =====================================================
        Fund trend and utilization
    ====================================================== --}}
        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">

            <section class="tupad-card overflow-hidden">

                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">

                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">
                            Project Cost Trend
                        </h2>

                        <p class="mt-1 text-xs text-slate-500">
                            Cumulative official project cost by month for FY {{ $currentYear }}.
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-bold text-slate-500">
                        FY {{ $currentYear }}
                    </div>

                </div>

                <div class="px-4 pb-4 pt-3 sm:px-5">

                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-60 w-full"
                        preserveAspectRatio="none" aria-label="Cumulative project cost trend">
                        <defs>
                            <linearGradient id="trendFill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#1765d8" stop-opacity="0.18"></stop>

                                <stop offset="100%" stop-color="#1765d8" stop-opacity="0.01"></stop>
                            </linearGradient>
                        </defs>

                        @foreach ([16, 58, 100, 142, 184] as $gridY)
                            <line x1="26" y1="{{ $gridY }}" x2="700" y2="{{ $gridY }}"
                                stroke="#e6edf6" stroke-width="1"></line>
                        @endforeach

                        <polygon points="{{ $areaPoints }}" fill="url(#trendFill)"></polygon>

                        <polyline points="{{ $polyline }}" fill="none" stroke="#1765d8" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round"></polyline>

                        @foreach ($points as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#1765d8"
                                stroke="#ffffff" stroke-width="2"></circle>
                        @endforeach
                    </svg>

                    <div class="grid grid-cols-12 px-2 text-center text-[9px] font-medium text-slate-400">
                        @foreach ($months as $month)
                            <span>{{ $month }}</span>
                        @endforeach
                    </div>

                </div>

            </section>

            <aside class="tupad-card p-5">

                <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                    Available Budget
                </div>

                <div class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">
                    ₱{{ number_format($remainingBudget, 2) }}
                </div>

                <div class="mt-1 text-xs text-slate-500">
                    Remaining balance
                </div>

                <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-slate-100">

                    <div class="h-full rounded-full bg-slate-800" style="width: {{ $remainingPercent }}%"></div>

                </div>

                <div class="mt-2 flex items-center justify-between text-[10px] font-semibold text-slate-500">
                    <span>
                        {{ number_format($remainingPercent, 1) }}% remaining
                    </span>

                    <span>
                        {{ number_format($utilizationPercent, 1) }}% utilized
                    </span>
                </div>

                <dl class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-xs">

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Allocated</dt>
                        <dd class="font-semibold text-slate-900">
                            ₱{{ number_format($totalAllocated, 2) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-500">Total Budget</dt>
                        <dd class="font-semibold text-slate-900">
                            ₱{{ number_format($totalBudget, 2) }}
                        </dd>
                    </div>

                </dl>

                @if ($user->isFocal() || $user->isAdmin())
                    <a href="{{ route('adl.index') }}"
                        class="mt-5 flex h-10 items-center justify-center rounded-lg bg-slate-900 text-xs font-semibold text-white hover:bg-slate-800">
                        View Budget Details
                    </a>
                @else
                    <div
                        class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-center text-[11px] text-slate-500">
                        Fund maintenance is handled by the Focal account.
                    </div>
                @endif

            </aside>

        </div>

        {{-- =====================================================
        Recent projects
    ====================================================== --}}
        <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">

                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Recent Official Projects
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Most recently updated projects across the official workflow.
                    </p>
                </div>

                @if ($user->isTc() || $user->isAdmin())
                    <a href="{{ route('projects.index') }}"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        View All Projects
                    </a>
                @endif

            </div>

            <div class="overflow-x-auto">

                <table class="min-w-full">

                    <thead class="bg-slate-50">

                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Project
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Location
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Beneficiaries
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Cost
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Action
                            </th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @forelse($recentProjects as $project)
                            <tr class="hover:bg-slate-50">

                                <td class="px-5 py-4">

                                    <div class="max-w-[320px] truncate text-sm font-semibold text-slate-900">
                                        {{ $project->project_title }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ $project->approval?->project_code ?? 'Project code pending' }}
                                    </div>

                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ collect([$project->municipality, $project->province])->filter()->implode(', ') }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-700">
                                    {{ number_format($project->beneficiaries_total) }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-700">
                                    ₱{{ number_format($project->total_project_cost, 2) }}
                                </td>

                                <td class="px-5 py-4">
                                    <x-status-badge :tone="$project->status === \App\Enums\ProjectStatus::COMPLETED
                                        ? 'success'
                                        : ($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE
                                            ? 'warning'
                                            : 'info')">
                                        {{ $project->status->label() }}
                                    </x-status-badge>
                                </td>

                                <td class="px-5 py-4 text-right">

                                    @if ($user->isTc() || $user->isAdmin())
                                        <a href="{{ route('projects.show', $project) }}"
                                            class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Open Project
                                        </a>
                                    @else
                                        <span class="text-xs font-medium text-slate-400">
                                            View through monitoring
                                        </span>
                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="p-0">
                                    <x-empty-state title="No official projects yet"
                                        message="Project activity will appear here when official projects are created." />
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    @endif

@endsection
