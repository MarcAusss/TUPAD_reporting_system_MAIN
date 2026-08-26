@extends('layouts.app')

@section('title', $queueTitle)

@section('content')

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

    <div>
        <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
            Project Workflow
        </div>

        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
            {{ $queueTitle }}
        </h1>

        <p class="mt-1 max-w-3xl text-sm text-slate-500">
            {{ $queueDescription }}
        </p>
    </div>

    <a
        href="{{ route('projects.index') }}"
        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        View All Projects
    </a>

</div>

<section class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">

    <div class="text-xs font-bold uppercase tracking-[0.1em] text-blue-700">
        Responsible Account
    </div>

    <div class="mt-1 text-sm font-semibold text-blue-950">
        {{ $queueOwner }}
    </div>

    <p class="mt-1 text-xs leading-5 text-blue-700">
        This queue is a filtered view of official project records.
        Opening a project takes you to the existing Project Detail workflow.
    </p>

</section>

<form
    method="GET"
    action="{{ route('project-workflow.index', ['queue' => $queue]) }}"
    class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
>

    <div class="flex flex-col gap-3 sm:flex-row">

        <div class="flex-1">

            <label
                for="workflow-search"
                class="sr-only"
            >
                Search workflow projects
            </label>

            <input
                id="workflow-search"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search project, code, province, municipality..."
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >

        </div>

        <button
            type="submit"
            class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800"
        >
            Search
        </button>

        @if(request()->filled('q'))

            <a
                href="{{ route('project-workflow.index', ['queue' => $queue]) }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Clear
            </a>

        @endif

    </div>

</form>

@if($queue === 'implementation')

    @php
        $forImplementation =
            $implementationBoard[
                \App\Enums\ProjectStatus::FOR_IMPLEMENTATION->value
            ];

        $ongoingImplementation =
            $implementationBoard[
                \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION->value
            ];

        $forPostDocuments =
            $implementationBoard[
                \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value
            ];

        $boardColumns = [
            [
                'key' => 'for-implementation',
                'title' => 'For Implementation',
                'description' => 'No work period yet, preparation is incomplete, or the approved Start Date has not arrived.',
                'projects' => $forImplementation,
                'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
            ],
            [
                'key' => 'ongoing-implementation',
                'title' => 'Ongoing Implementation',
                'description' => 'The current date is within the approved implementation work period.',
                'projects' => $ongoingImplementation,
                'badge' => 'bg-blue-50 text-blue-700 border-blue-200',
            ],
            [
                'key' => 'for-submission-post-docs',
                'title' => 'For Submission of Post Docs',
                'description' => 'The implementation work period has ended and post-documentary requirements are now due.',
                'projects' => $forPostDocuments,
                'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            ],
        ];
    @endphp

    {{-- =========================================================
        Implementation Stage Summary
    ========================================================== --}}

    <section class="mb-5 grid gap-4 lg:grid-cols-3">

        <div class="rounded-xl border border-amber-300 bg-amber-50/70 px-4 py-3">
            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">
                    <div class="text-[12px] font-bold uppercase tracking-wide text-amber-700">
                        For Implementation
                    </div>

                    <p class="mt-1 text-[11px] leading-4 text-slate-600">
                        Waiting for work period or Start Date.
                    </p>
                </div>

                <div class="text-3xl font-bold leading-none text-slate-950">
                    {{ number_format($forImplementation->count()) }}
                </div>

            </div>

            <div class="mt-3 border-t border-amber-300 pt-2 text-[10px] italic leading-4 text-slate-500">
                No work period, preparation incomplete, or today is before Start Date.
            </div>
        </div>

        <div class="rounded-xl border border-blue-400 bg-blue-50/70 px-4 py-3">
            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">
                    <div class="text-[12px] font-bold uppercase tracking-wide text-blue-700">
                        Ongoing Implementation
                    </div>

                    <p class="mt-1 text-[11px] leading-4 text-slate-600">
                        Currently within the implementation period.
                    </p>
                </div>

                <div class="text-3xl font-bold leading-none text-slate-950">
                    {{ number_format($ongoingImplementation->count()) }}
                </div>

            </div>

            <div class="mt-3 border-t border-blue-300 pt-2 text-[10px] italic leading-4 text-slate-500">
                Start Date ≤ today &lt; End Date.
            </div>
        </div>

        <div class="rounded-xl border border-emerald-400 bg-emerald-50/70 px-4 py-3">
            <div class="flex items-start justify-between gap-4">

                <div class="min-w-0">
                    <div class="text-[12px] font-bold tracking-wide text-emerald-800">
                        For Submission of Post Docs
                    </div>

                    <p class="mt-1 text-[11px] leading-4 text-slate-600">
                        Work period has already ended.
                    </p>
                </div>

                <div class="text-3xl font-bold leading-none text-slate-950">
                    {{ number_format($forPostDocuments->count()) }}
                </div>

            </div>

            <div class="mt-3 border-t border-emerald-300 pt-2 text-[10px] italic leading-4 text-slate-500">
                Today is on or after the End Date.
            </div>
        </div>

    </section>

    {{-- =========================================================
        Minimal Three-Column Implementation Board
    ========================================================== --}}

    <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">

        <div class="grid gap-5 xl:grid-cols-3">

            @foreach($boardColumns as $column)

                @php
                    $columnMeta =
                        match ($column['key']) {
                            'for-implementation' => [
                                'icon_bg' => 'bg-amber-50',
                                'icon_text' => 'text-amber-600',
                                'count_bg' => 'bg-slate-100 text-slate-600',
                                'status_badge' => 'bg-amber-50 text-amber-700',
                                'primary_button' => 'border border-amber-400 bg-white text-amber-700 hover:bg-amber-50',
                                'column_title' => 'For Implementation',
                            ],

                            'ongoing-implementation' => [
                                'icon_bg' => 'bg-blue-50',
                                'icon_text' => 'text-blue-600',
                                'count_bg' => 'bg-slate-100 text-slate-600',
                                'status_badge' => 'bg-blue-50 text-blue-700',
                                'primary_button' => 'border border-[#063b86] bg-[#063b86] text-white hover:bg-[#052f6b]',
                                'column_title' => 'Ongoing Implementation',
                            ],

                            default => [
                                'icon_bg' => 'bg-emerald-50',
                                'icon_text' => 'text-emerald-600',
                                'count_bg' => 'bg-slate-100 text-slate-600',
                                'status_badge' => 'bg-emerald-50 text-emerald-700',
                                'primary_button' => 'border border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800',
                                'column_title' => 'For Submission of Post Docs',
                            ],
                        };
                @endphp

                <div class="relative min-w-0">

                    {{-- Arrow connector between stages --}}
                    @if(! $loop->last)
                        <div class="pointer-events-none absolute -right-[18px] top-1/2 z-20 hidden -translate-y-1/2 xl:block">
                            <svg
                                class="h-9 w-9 text-slate-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                            >
                                <path d="M5 12h13"></path>
                                <path d="m14 7 5 5-5 5"></path>
                            </svg>
                        </div>
                    @endif

                    <div class="h-full rounded-xl border border-slate-200 bg-[#fbfcfe] p-3">

                        {{-- Column title --}}
                        <div class="mb-3 flex items-center justify-between gap-3 px-1 py-1">

                            <div class="flex min-w-0 items-center gap-3">

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $columnMeta['icon_bg'] }} {{ $columnMeta['icon_text'] }}">

                                    @if($column['key'] === 'for-implementation')
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <rect x="5" y="5" width="14" height="15" rx="2"></rect>
                                            <path d="M8 3v4"></path>
                                            <path d="M16 3v4"></path>
                                            <path d="M8 11h8"></path>
                                            <path d="M8 15h5"></path>
                                        </svg>
                                    @elseif($column['key'] === 'ongoing-implementation')
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <circle cx="12" cy="12" r="8"></circle>
                                            <path d="M12 7v5l3 2"></path>
                                        </svg>
                                    @else
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path d="M7 3h8l4 4v14H7z"></path>
                                            <path d="M15 3v5h5"></path>
                                            <path d="m10 14 2 2 4-4"></path>
                                        </svg>
                                    @endif

                                </div>

                                <h2 class="truncate text-[13px] font-bold text-slate-900">
                                    {{ $columnMeta['column_title'] }}
                                </h2>

                            </div>

                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-[10px] font-bold {{ $columnMeta['count_bg'] }}">
                                {{ $column['projects']->count() }}
                            </span>

                        </div>

                        {{-- Minimal project cards --}}
                        <div class="space-y-3">

                            @forelse($column['projects'] as $project)

                                @php
                                    $implementation =
                                        $project->implementation;

                                    $statusLabel =
                                        match ($column['key']) {
                                            'for-implementation' =>
                                                'For Implementation',

                                            'ongoing-implementation' =>
                                                'Ongoing Implementation',

                                            default =>
                                                'For Submission of Post Docs',
                                        };
                                @endphp

                                <article
                                    class="rounded-xl border border-slate-200 bg-white p-3 shadow-[0_1px_2px_rgba(15,23,42,0.03)]"
                                    title="{{ $project->project_title }}"
                                >

                                    <div class="mb-3">
                                        <span class="inline-flex rounded-md px-2 py-1 text-[9px] font-bold uppercase tracking-wide {{ $columnMeta['status_badge'] }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>

                                    <dl class="space-y-2 text-[11px]">

                                        <div class="grid grid-cols-[92px_minmax(0,1fr)] items-start gap-2">
                                            <dt class="text-slate-500">
                                                Project Code:
                                            </dt>

                                            <dd class="truncate font-bold text-[#0b2f67]">
                                                {{ $project->approval?->project_code ?: 'Not Assigned' }}
                                            </dd>
                                        </div>

                                        <div class="grid grid-cols-[92px_minmax(0,1fr)] items-start gap-2">
                                            <dt class="text-slate-500">
                                                Beneficiaries:
                                            </dt>

                                            <dd class="font-semibold text-slate-700">
                                                {{ number_format($project->beneficiaries_total) }}
                                            </dd>
                                        </div>

                                        <div class="grid grid-cols-[92px_minmax(0,1fr)] items-start gap-2">
                                            <dt class="text-slate-500">
                                                Status:
                                            </dt>

                                            <dd class="leading-4 text-slate-700">
                                                {{ $statusLabel }}
                                            </dd>
                                        </div>

                                        @if($implementation)
                                            <div class="grid grid-cols-[92px_minmax(0,1fr)] items-start gap-2 border-t border-slate-100 pt-2">
                                                <dt class="text-slate-400">
                                                    Work Period:
                                                </dt>

                                                <dd class="text-[10px] leading-4 text-slate-500">
                                                    {{ $implementation->start_date?->format('M d, Y') }}
                                                    –
                                                    {{ $implementation->end_date?->format('M d, Y') }}
                                                </dd>
                                            </div>
                                        @endif

                                    </dl>

                                    <div class="mt-3 grid grid-cols-2 gap-2">

                                        <a
                                            href="{{ route('projects.show', $project) }}"
                                            class="inline-flex h-8 items-center justify-center rounded-md border border-slate-300 bg-white px-2 text-[10px] font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            View
                                        </a>

                                        @if(! $implementation)

                                            <button
                                                type="button"
                                                class="js-open-work-period-modal inline-flex h-8 items-center justify-center rounded-md px-2 text-[10px] font-semibold {{ $columnMeta['primary_button'] }}"
                                                data-project-title="{{ $project->project_title }}"
                                                data-project-code="{{ $project->approval?->project_code ?: 'No project code yet' }}"
                                                data-duration="{{ (int) $project->number_of_days }}"
                                                data-action="{{ route('projects.implementation.period', $project) }}"
                                            >
                                                Set Work Period
                                            </button>

                                        @elseif(
                                            $project->implementation_board_stage
                                            === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->value
                                        )

                                            <a
                                                href="{{ route('projects.show', $project) }}#post-documents"
                                                class="inline-flex h-8 items-center justify-center rounded-md px-2 text-[10px] font-semibold {{ $columnMeta['primary_button'] }}"
                                            >
                                                Submit Post Documents
                                            </a>

                                        @else

                                            <a
                                                href="{{ route('projects.show', $project) }}#implementation"
                                                class="inline-flex h-8 items-center justify-center rounded-md px-2 text-[10px] font-semibold {{ $columnMeta['primary_button'] }}"
                                            >
                                                Open
                                            </a>

                                        @endif

                                    </div>

                                </article>

                            @empty

                                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center">
                                    <div class="text-xs font-semibold text-slate-600">
                                        No projects
                                    </div>

                                    <p class="mt-1 text-[10px] leading-4 text-slate-400">
                                        Projects appear here automatically when they meet this stage.
                                    </p>
                                </div>

                            @endforelse

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    </section>

    {{-- Set Work Period Modal --}}
    <div
        id="workPeriodModal"
        class="fixed inset-0 z-[80] hidden"
        aria-hidden="true"
    >
        <div
            class="absolute inset-0 bg-slate-950/50 backdrop-blur-[1px]"
            data-work-period-close
        ></div>

        <div class="relative flex min-h-full items-center justify-center p-4">
            <div
                class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="workPeriodModalTitle"
            >
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                            Implementation Work Period
                        </div>

                        <h2
                            id="workPeriodModalTitle"
                            class="mt-1 text-lg font-bold text-slate-900"
                        >
                            Set Work Period
                        </h2>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Select the Start Date. The End Date is calculated automatically from the approved project duration.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-lg text-slate-500 hover:bg-slate-50 hover:text-slate-900"
                        aria-label="Close work period modal"
                        data-work-period-close
                    >
                        ×
                    </button>
                </div>

                <form
                    id="workPeriodModalForm"
                    method="POST"
                    action=""
                >
                    @csrf

                    <div class="space-y-5 px-6 py-5">

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                        Project
                                    </div>

                                    <div
                                        id="workPeriodProjectTitle"
                                        class="mt-1 text-sm font-semibold text-slate-900"
                                    >
                                        —
                                    </div>

                                    <div
                                        id="workPeriodProjectCode"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        —
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                        Approved Duration
                                    </div>

                                    <div class="mt-1 flex items-center gap-2">
                                        <span
                                            id="workPeriodDuration"
                                            class="text-sm font-semibold text-slate-900"
                                        >
                                            —
                                        </span>

                                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">
                                            Locked
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label
                                for="workPeriodStartDate"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Start Date
                                <span class="text-red-600">*</span>
                            </label>

                            <input
                                id="workPeriodStartDate"
                                name="start_date"
                                type="date"
                                required
                                class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            >

                            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                The project remains For Implementation until this date arrives.
                            </p>
                        </div>

                        <div>
                            <label
                                for="workPeriodEndDate"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                End Date
                            </label>

                            <div class="relative">
                                <input
                                    id="workPeriodEndDate"
                                    type="text"
                                    readonly
                                    value="Select a Start Date"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 pr-28 text-sm font-semibold text-slate-700"
                                >

                                <span class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-blue-50 px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-blue-700">
                                    Automatic
                                </span>
                            </div>

                            <p
                                id="workPeriodCalculationNote"
                                class="mt-1 text-[11px] leading-4 text-slate-500"
                            >
                                End Date will be calculated from the approved duration.
                            </p>
                        </div>

                        <div>
                            <label
                                for="workPeriodRemarks"
                                class="mb-2 block text-sm font-semibold text-slate-700"
                            >
                                Remarks
                                <span class="font-normal text-slate-400">
                                    (Optional)
                                </span>
                            </label>

                            <textarea
                                id="workPeriodRemarks"
                                name="remarks"
                                rows="3"
                                maxlength="3000"
                                placeholder="Optional implementation-period remarks"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                        </div>

                        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                            <div class="text-xs font-semibold text-blue-900">
                                Automatic workflow movement
                            </div>

                            <p class="mt-1 text-xs leading-5 text-blue-700">
                                Before Start Date: For Implementation. On Start Date: Ongoing Implementation.
                                On the calculated End Date: For Submission of Post Docs.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            data-work-period-close
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]"
                        >
                            Set Work Period
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


@else

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Project
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            ADL / Partner
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Location
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Beneficiaries
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Status
                        </th>

                        @if($queue === 'for-compliance')
                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Aging
                            </th>
                        @endif

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($projects as $project)

                        <tr class="hover:bg-slate-50/60">

                            <td class="px-5 py-4">

                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $project->project_title }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $project->approval?->project_code ?: 'No project code yet' }}
                                </div>

                            </td>

                            <td class="px-5 py-4">

                                <div class="text-sm text-slate-700">
                                    {{ $project->allocation->adl->adl_number }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $project->partner ?: '—' }}
                                </div>

                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ collect([
                                    $project->barangay,
                                    $project->municipality,
                                    $project->province,
                                ])->filter()->implode(', ') }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-700">
                                {{ number_format($project->beneficiaries_total) }}
                            </td>

                            <td class="px-5 py-4">

                                <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    {{ $project->status->label() }}
                                </span>

                            </td>

                            @if($queue === 'for-compliance')
                                @php
                                    $complianceEvaluation =
                                        $project
                                            ->evaluations
                                            ->where(
                                                'result',
                                                'for_compliance'
                                            )
                                            ->sortByDesc(
                                                'evaluated_at'
                                            )
                                            ->first();

                                    $queueAgingDays =
                                        $complianceEvaluation?->evaluated_at
                                            ? (int) $complianceEvaluation
                                                ->evaluated_at
                                                ->copy()
                                                ->startOfDay()
                                                ->diffInDays(
                                                    now()->startOfDay()
                                                )
                                            : 0;
                                @endphp

                                <td class="px-5 py-4 text-right">
                                    <div class="text-sm font-bold text-amber-700">
                                        {{ number_format($queueAgingDays) }}
                                        day(s)
                                    </div>

                                    <div class="mt-1 text-[10px] text-slate-400">
                                        Since TSSD finding
                                    </div>
                                </td>
                            @endif

                            <td class="px-5 py-4 text-right">

                                <a
                                    href="{{ route('projects.show', $project) }}"
                                    class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800"
                                >
                                    Open Project
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="{{ $queue === 'for-compliance' ? 7 : 6 }}"
                                class="px-5 py-12 text-center"
                            >

                                <div class="text-sm font-semibold text-slate-700">
                                    {{ $emptyMessage }}
                                </div>

                                <p class="mt-1 text-xs text-slate-400">
                                    Projects will appear here automatically when they reach this workflow stage.
                                </p>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <div class="mt-5">
        {{ $projects->links() }}
    </div>

@endif



@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal =
            document.getElementById(
                'workPeriodModal'
            );

        const form =
            document.getElementById(
                'workPeriodModalForm'
            );

        const projectTitle =
            document.getElementById(
                'workPeriodProjectTitle'
            );

        const projectCode =
            document.getElementById(
                'workPeriodProjectCode'
            );

        const durationLabel =
            document.getElementById(
                'workPeriodDuration'
            );

        const startDateInput =
            document.getElementById(
                'workPeriodStartDate'
            );

        const endDateInput =
            document.getElementById(
                'workPeriodEndDate'
            );

        const calculationNote =
            document.getElementById(
                'workPeriodCalculationNote'
            );

        const remarksInput =
            document.getElementById(
                'workPeriodRemarks'
            );

        let activeDuration = 0;

        function formatDate(
            year,
            monthIndex,
            day
        ) {
            return new Intl.DateTimeFormat(
                'en-PH',
                {
                    year: 'numeric',
                    month: 'long',
                    day: '2-digit',
                    timeZone: 'UTC',
                }
            ).format(
                new Date(
                    Date.UTC(
                        year,
                        monthIndex,
                        day
                    )
                )
            );
        }

        function calculateEndDate() {
            if (
                !startDateInput?.value
                || activeDuration < 1
            ) {
                if (endDateInput) {
                    endDateInput.value =
                        'Select a Start Date';
                }

                if (calculationNote) {
                    calculationNote.textContent =
                        'End Date will be calculated from the approved duration.';
                }

                return;
            }

            const parts =
                startDateInput
                    .value
                    .split('-')
                    .map(Number);

            if (parts.length !== 3) {
                return;
            }

            const [
                year,
                month,
                day,
            ] = parts;

            const endDate =
                new Date(
                    Date.UTC(
                        year,
                        month - 1,
                        day
                    )
                );

            /*
             * Keep this preview aligned with the current server rule:
             * End Date = Start Date + approved number_of_days.
             */
            endDate.setUTCDate(
                endDate.getUTCDate()
                + activeDuration
            );

            if (endDateInput) {
                endDateInput.value =
                    formatDate(
                        endDate.getUTCFullYear(),
                        endDate.getUTCMonth(),
                        endDate.getUTCDate()
                    );
            }

            if (calculationNote) {
                calculationNote.textContent =
                    'Calculated automatically from the '
                    + activeDuration
                    + '-day approved project duration.';
            }
        }

        function openModal(button) {
            if (
                !modal
                || !form
                || !button
            ) {
                return;
            }

            const duration =
                Number.parseInt(
                    button.dataset.duration || '0',
                    10
                );

            activeDuration =
                Number.isFinite(duration)
                    ? Math.max(1, duration)
                    : 1;

            form.action =
                button.dataset.action || '';

            if (projectTitle) {
                projectTitle.textContent =
                    button.dataset.projectTitle
                    || 'Project';
            }

            if (projectCode) {
                projectCode.textContent =
                    button.dataset.projectCode
                    || 'No project code yet';
            }

            if (durationLabel) {
                durationLabel.textContent =
                    activeDuration
                    + (
                        activeDuration === 1
                            ? ' day'
                            : ' days'
                    );
            }

            if (startDateInput) {
                startDateInput.value = '';
            }

            if (remarksInput) {
                remarksInput.value = '';
            }

            calculateEndDate();

            modal.classList.remove(
                'hidden'
            );

            modal.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.classList.add(
                'overflow-hidden'
            );

            window.setTimeout(
                function () {
                    startDateInput?.focus();
                },
                0
            );
        }

        function closeModal() {
            if (!modal) {
                return;
            }

            modal.classList.add(
                'hidden'
            );

            modal.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.classList.remove(
                'overflow-hidden'
            );
        }

        document
            .querySelectorAll(
                '.js-open-work-period-modal'
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        'click',
                        function () {
                            openModal(button);
                        }
                    );
                }
            );

        document
            .querySelectorAll(
                '[data-work-period-close]'
            )
            .forEach(
                function (button) {
                    button.addEventListener(
                        'click',
                        closeModal
                    );
                }
            );

        startDateInput?.addEventListener(
            'change',
            calculateEndDate
        );

        startDateInput?.addEventListener(
            'input',
            calculateEndDate
        );

        document.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key === 'Escape'
                    && !modal?.classList.contains(
                        'hidden'
                    )
                ) {
                    closeModal();
                }
            }
        );
    });
</script>
@endpush

@endsection
