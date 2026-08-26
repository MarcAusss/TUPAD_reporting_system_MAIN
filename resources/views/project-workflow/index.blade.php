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

    <section class="mb-5 grid gap-4 md:grid-cols-3">

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="text-xs font-bold uppercase tracking-[0.1em] text-amber-700">
                For Implementation
            </div>

            <div class="mt-2 text-3xl font-bold tracking-tight text-amber-950">
                {{ number_format($forImplementation->count()) }}
            </div>

            <p class="mt-1 text-xs leading-5 text-amber-700">
                Waiting for work period or Start Date.
            </p>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="text-xs font-bold uppercase tracking-[0.1em] text-blue-700">
                Ongoing Implementation
            </div>

            <div class="mt-2 text-3xl font-bold tracking-tight text-blue-950">
                {{ number_format($ongoingImplementation->count()) }}
            </div>

            <p class="mt-1 text-xs leading-5 text-blue-700">
                Currently within the implementation period.
            </p>
        </div>

        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs font-bold uppercase tracking-[0.1em] text-emerald-700">
                For Submission of Post Docs
            </div>

            <div class="mt-2 text-3xl font-bold tracking-tight text-emerald-950">
                {{ number_format($forPostDocuments->count()) }}
            </div>

            <p class="mt-1 text-xs leading-5 text-emerald-700">
                Work period has already ended.
            </p>
        </div>

    </section>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <div class="text-xs font-bold uppercase tracking-[0.1em] text-slate-400">
            Automatic Stage Rules
        </div>

        <div class="mt-3 grid gap-3 text-xs leading-5 text-slate-600 lg:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <b class="text-slate-900">
                    For Implementation
                </b>

                <div class="mt-1">
                    No work period, preparation incomplete, or today is before Start Date.
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <b class="text-slate-900">
                    Ongoing Implementation
                </b>

                <div class="mt-1">
                    Start Date ≤ today &lt; End Date.
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                <b class="text-slate-900">
                    For Submission of Post Docs
                </b>

                <div class="mt-1">
                    Today is on or after the End Date.
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-3">

        @foreach($boardColumns as $column)

            <section class="min-w-0 rounded-xl border border-slate-200 bg-slate-50/70">

                <div class="border-b border-slate-200 bg-white px-4 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-bold text-slate-900">
                            {{ $column['title'] }}
                        </h2>

                        <span class="inline-flex min-w-7 items-center justify-center rounded-full border px-2 py-1 text-xs font-bold {{ $column['badge'] }}">
                            {{ $column['projects']->count() }}
                        </span>
                    </div>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        {{ $column['description'] }}
                    </p>
                </div>

                <div class="space-y-3 p-3">

                    @forelse($column['projects'] as $project)

                        @php
                            $implementation =
                                $project->implementation;

                            $today =
                                \Illuminate\Support\Carbon::now(
                                    'Asia/Manila'
                                )->startOfDay();

                            $startDate =
                                $implementation
                                    ? \Illuminate\Support\Carbon::parse(
                                        $implementation->start_date,
                                        'Asia/Manila'
                                    )->startOfDay()
                                    : null;

                            $endDate =
                                $implementation
                                    ? \Illuminate\Support\Carbon::parse(
                                        $implementation->end_date,
                                        'Asia/Manila'
                                    )->startOfDay()
                                    : null;

                            $daysUntilStart =
                                $startDate
                                    ? $today->diffInDays(
                                        $startDate,
                                        false
                                    )
                                    : null;

                            $elapsedDays =
                                $startDate
                                    ? $startDate->diffInDays(
                                        $today
                                    ) + 1
                                    : null;
                        @endphp

                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

                            <div class="flex items-start justify-between gap-3">

                                <div class="min-w-0">

                                    <div class="truncate text-sm font-semibold text-slate-900">
                                        {{ $project->project_title }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ $project->project_code ?: 'No project code yet' }}
                                    </div>

                                </div>

                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-600">
                                    {{ number_format($project->beneficiaries_total) }}
                                    BEN
                                </span>

                            </div>

                            <div class="mt-4 space-y-3">

                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                        ADL
                                    </div>

                                    <div class="mt-1 text-xs font-semibold text-slate-700">
                                        {{ $project->allocation?->adl?->adl_number ?: '—' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                        Location
                                    </div>

                                    <div class="mt-1 text-xs leading-5 text-slate-600">
                                        {{ collect([
                                            $project->barangay,
                                            $project->municipality,
                                            $project->province,
                                        ])->filter()->implode(', ') ?: '—' }}
                                    </div>
                                </div>

                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">

                                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                        Implementation Work Period
                                    </div>

                                    @if(! $implementation)

                                        <div class="mt-2 text-sm font-semibold text-amber-700">
                                            Not Yet Set
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Assign the Start Date before this project can automatically enter Ongoing Implementation.
                                        </p>

                                    @else

                                        <div class="mt-2 text-xs font-semibold text-slate-800">
                                            {{ $startDate->format('M d, Y') }}
                                            —
                                            {{ $endDate->format('M d, Y') }}
                                        </div>

                                        <div class="mt-2">

                                            @if($today->lt($startDate))

                                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                                    Starts in
                                                    {{ max(0, $daysUntilStart) }}
                                                    {{ abs($daysUntilStart) === 1 ? 'day' : 'days' }}
                                                </span>

                                            @elseif($today->gte($endDate))

                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                                    Work Period Ended
                                                </span>

                                            @else

                                                <span class="inline-flex rounded-full bg-blue-50 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-blue-700">
                                                    Day {{ $elapsedDays }}
                                                    of {{ $project->number_of_days }}
                                                </span>

                                            @endif

                                        </div>

                                    @endif

                                </div>

                                @if(! $project->implementation_preparation_complete)

                                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                                        <div class="text-[11px] font-semibold text-amber-800">
                                            Implementation preparation is incomplete.
                                        </div>
                                    </div>

                                @endif

                            </div>

                            <div class="mt-4 flex flex-col gap-2">

                                @if(! $implementation)

                                    <button
                                        type="button"
                                        class="js-open-work-period-modal inline-flex h-9 items-center justify-center rounded-lg bg-[#063b86] px-3 text-xs font-semibold text-white hover:bg-[#052f6b]"
                                        data-project-title="{{ $project->project_title }}"
                                        data-project-code="{{ $project->project_code ?: 'No project code yet' }}"
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
                                        class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-800"
                                    >
                                        Submit Post Documents
                                    </a>

                                @else

                                    <a
                                        href="{{ route('projects.show', $project) }}#implementation"
                                        class="inline-flex h-9 items-center justify-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800"
                                    >
                                        Open Implementation
                                    </a>

                                @endif

                                <a
                                    href="{{ route('projects.show', $project) }}"
                                    class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    View Project
                                </a>

                            </div>

                        </article>

                    @empty

                        <div class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                            <div class="text-sm font-semibold text-slate-600">
                                No projects
                            </div>

                            <p class="mt-1 text-xs leading-5 text-slate-400">
                                Projects move here automatically when they meet this stage's date rules.
                            </p>
                        </div>

                    @endforelse

                </div>

            </section>

        @endforeach

    </div>

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
                                    {{ $project->project_code ?: 'No project code yet' }}
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
                                colspan="6"
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
