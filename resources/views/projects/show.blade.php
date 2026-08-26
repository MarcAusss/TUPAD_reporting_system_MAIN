@extends('layouts.app')

@section('title', $project->project_title)

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | Role-aware Back Link
    |--------------------------------------------------------------------------
    */

    if (auth()->user()->isFocal()) {
        $backUrl = route('payments.index');
        $backLabel = 'Payment Queue';
    } else {
        $backUrl = route('projects.index');
        $backLabel = 'Project Management';
    }


    /*
    |--------------------------------------------------------------------------
    | UX: Recommended Next Action
    |--------------------------------------------------------------------------
    */

    $nextAction = match ($project->status) {
        \App\Enums\ProjectStatus::ONGOING_PROFILING =>
            'Submit the project for TSSD Evaluation.',

        \App\Enums\ProjectStatus::TSSD_EVALUATION =>
            'Record the TSSD evaluation result.',

        \App\Enums\ProjectStatus::FOR_COMPLIANCE =>
            'Review the compliance submission and record the next evaluation result.',

        \App\Enums\ProjectStatus::FOR_APPROVAL =>
            'Complete the project approval action.',

        \App\Enums\ProjectStatus::APPROVED =>
            'Complete the implementation preparation requirements.',

        \App\Enums\ProjectStatus::FOR_IMPLEMENTATION =>
            'Start implementation after all preparation requirements are complete.',

        \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION =>
            'Complete implementation and prepare the required post-documents.',

        \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS =>
            'Record the submitted post-documentary requirements.',

        \App\Enums\ProjectStatus::FOR_PAYMENT =>
            $project->obligation
                ? 'Record the Release of Assistance to complete the project.'
                : 'The Focal/Admin must record Payment of Wages / obligation information.',

        \App\Enums\ProjectStatus::COMPLETED =>
            'No pending workflow action. This project is complete.',
    };
@endphp

<x-page-header
    eyebrow="Official Project"
    :title="$project->project_title"
    description="Review the project profile, current workflow status, and the action required to move the project forward."
>
    <x-slot:actions>
        <a
            href="{{ $backUrl }}"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            ← {{ $backLabel }}
        </a>
    </x-slot:actions>
</x-page-header>

@if($project->status === \App\Enums\ProjectStatus::COMPLETED)
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                ✓
            </div>

            <div>
                <div class="text-sm font-semibold text-emerald-900">
                    Project Completed
                </div>

                <p class="mt-1 text-xs leading-5 text-emerald-700">
                    Post-documentary requirements, Payment of Wages, and Release of Assistance have been recorded.
                </p>
            </div>
        </div>
    </div>
@endif

<div class="mb-5 grid gap-4 xl:grid-cols-[1fr_320px]">

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge
                :tone="$project->status === \App\Enums\ProjectStatus::COMPLETED ? 'success' : ($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE ? 'warning' : 'info')"
            >
                {{ $project->status->label() }}
            </x-status-badge>

            <span class="text-xs font-semibold text-slate-500">
                {{ $project->term->label() }}
            </span>

            @if($project->approval?->project_code)
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                    {{ $project->approval->project_code }}
                </span>
            @endif
        </div>

        <div class="mt-4 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
            Recommended Next Action
        </div>

        <div class="mt-1 text-sm font-semibold leading-6 text-slate-800">
            {{ $nextAction }}
        </div>

    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-5">

        <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-600">
            Project Snapshot
        </div>

        <dl class="mt-3 space-y-2 text-xs">
            <div class="flex items-center justify-between gap-4">
                <dt class="text-blue-700">Beneficiaries</dt>
                <dd class="font-semibold text-blue-950">{{ number_format($project->beneficiaries_total) }}</dd>
            </div>

            <div class="flex items-center justify-between gap-4">
                <dt class="text-blue-700">Duration</dt>
                <dd class="font-semibold text-blue-950">{{ $project->number_of_days }} day(s)</dd>
            </div>

            <div class="flex items-center justify-between gap-4">
                <dt class="text-blue-700">Total Cost</dt>
                <dd class="font-semibold text-blue-950">₱{{ number_format($project->total_project_cost, 2) }}</dd>
            </div>
        </dl>

    </div>

</div>

<div class="sticky top-[65px] z-20 mb-5 overflow-x-auto rounded-xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur">

    <nav class="flex min-w-max items-center gap-1" aria-label="Project detail sections">

        <a href="#overview" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950">
            Overview
        </a>

        <a href="#evaluation" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950">
            Evaluation
        </a>

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                ],
                true
            )
        )
            <a href="#implementation" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950">
                Implementation
            </a>
        @endif

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::COMPLETED,
                ],
                true
            )
        )
            <a href="#final-workflow" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950">
                Final Workflow
            </a>
        @endif

        <a href="#history" class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950">
            History
        </a>

    </nav>

</div>

{{-- Financial Summary --}}

<div id="overview" class="scroll-mt-32 grid gap-4 md:grid-cols-2 xl:grid-cols-4">

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Wages
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->wages_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            PPE
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->ppe_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Insurance
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->insurance_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Total Project Cost
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->total_project_cost, 2) }}
        </div>

    </div>

</div>

{{-- Project Information --}}

<div class="mt-5 grid gap-5 xl:grid-cols-2">

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Project Information
            </h2>

        </div>

        <dl class="divide-y divide-slate-100">

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    ADL Number
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->allocation->adl->adl_number }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Fund Sponsor
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->fund_sponsor }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Partner
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->partner }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Date Received
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->date_received->format('F d, Y') }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Nature of Work
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->nature_of_work }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Project Series
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->project_series ?: '—' }}
                </dd>

            </div>

            @if($project->project_series_remarks)
                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">
                        Project Series Remarks
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->project_series_remarks }}
                    </dd>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    TEVS Date Verified
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->tevs_date_verified?->format('F d, Y') ?? '—' }}
                </dd>

            </div>

            @if($project->tevs_remarks)
                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">
                        TEVS Remarks
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->tevs_remarks }}
                    </dd>
                </div>
            @endif

            @if($project->remarks)

                <div class="grid grid-cols-2 gap-4 px-5 py-3">

                    <dt class="text-xs text-slate-500">
                        Remarks
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->remarks }}
                    </dd>

                </div>

            @endif

        </dl>

    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Location & Implementation
            </h2>

        </div>

        <dl class="divide-y divide-slate-100">

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Location
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->full_location }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    District
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->district ?: 'Not Assigned' }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Income Class
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->income_class ?: 'Not yet assigned' }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Mode
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->implementation_mode->label() }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Duration
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->number_of_days }} days
                    —
                    {{ $project->term->label() }}
                </dd>

            </div>

        </dl>

    </section>

</div>

{{-- Beneficiaries & Wage --}}

<section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Beneficiaries & Wage
        </h2>

    </div>

    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">

        <div>

            <div class="text-xs text-slate-500">
                Declared Beneficiaries
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->beneficiaries_total) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Female Beneficiaries
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->beneficiaries_female) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Wage Rate
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                ₱{{ number_format($project->wage_rate, 2) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Insurance Rate
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                ₱{{ number_format($project->insurance_rate, 2) }}
            </div>

        </div>

    </div>

</section>

{{-- Beneficiary Summary --}}

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Beneficiary Summary</h2>
        <p class="mt-1 text-xs text-slate-500">
            Only aggregate beneficiary counts are recorded. Individual beneficiary personal records are not encoded.
        </p>
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Beneficiaries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($project->beneficiaries_total) }}</div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Female Beneficiaries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($project->beneficiaries_female) }}</div>
        </div>
    </div>
</section>

{{-- Project Location Coverage --}}

@if($project->projectLocations->isNotEmpty())

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Project Location Coverage
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                All selected district, municipality/city, and barangay target areas for this project.
            </p>
        </div>

        <div class="grid gap-3 p-5 lg:grid-cols-2">

            @foreach($project->projectLocations as $location)

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">

                    <div class="flex items-center justify-between gap-3">

                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                                {{ $location->district }}
                            </div>

                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $location->municipality->name }}
                            </div>
                        </div>

                        <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 shadow-sm">
                            {{ $location->barangays->count() }} brgy
                        </span>

                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">

                        @foreach($location->barangays as $barangay)
                            <span class="rounded-md border border-blue-100 bg-blue-50 px-2.5 py-1 text-[11px] font-medium text-blue-800">
                                {{ $barangay->name }}
                            </span>
                        @endforeach

                    </div>

                </div>

            @endforeach

        </div>

    </section>

@endif

{{-- Evaluation & Approval --}}

<section id="evaluation" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Evaluation & Approval
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Current project status: {{ $project->status->label() }}
        </p>

    </div>

    <div class="p-5">

        {{-- Ongoing Profiling --}}

        @if($project->status === \App\Enums\ProjectStatus::ONGOING_PROFILING)
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="text-sm font-semibold text-blue-900">Project Profiling Ready</div>

                <p class="mt-1 text-xs leading-5 text-blue-700">
                    This project uses aggregate beneficiary counts only. No individual beneficiary registry is required before TSSD Evaluation.
                </p>

                @if(auth()->user()->isAdmin() || auth()->user()->isTc())
                    <form method="POST" action="{{ route('projects.evaluation.start', $project) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Submit for TSSD Evaluation
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- TSSD Evaluation / Compliance --}}

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::TSSD_EVALUATION,
                    \App\Enums\ProjectStatus::FOR_COMPLIANCE,
                ],
                true
            )
        )

            @if($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE)

                <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4">

                    <div class="text-sm font-semibold text-amber-800">
                        Project requires compliance
                    </div>

                    @php
                        $latestEvaluation = $project
                            ->evaluations
                            ->sortByDesc('evaluated_at')
                            ->first();
                    @endphp

                    @if($latestEvaluation)

                        @if($latestEvaluation->findings)

                            <div class="mt-3">

                                <div class="text-xs font-semibold text-amber-800">
                                    Findings
                                </div>

                                <p class="mt-1 whitespace-pre-line text-sm text-amber-700">
                                    {{ $latestEvaluation->findings }}
                                </p>

                            </div>

                        @endif

                        @if($latestEvaluation->required_documents)

                            <div class="mt-3">

                                <div class="text-xs font-semibold text-amber-800">
                                    Required Documents
                                </div>

                                <p class="mt-1 whitespace-pre-line text-sm text-amber-700">
                                    {{ $latestEvaluation->required_documents }}
                                </p>

                            </div>

                        @endif

                    @endif

                    <form
                        method="POST"
                        action="{{ route('projects.evaluation.resubmit', $project) }}"
                        class="mt-4"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="h-10 rounded-lg bg-amber-700 px-4 text-sm font-semibold text-white hover:bg-amber-800"
                        >
                            Resubmit for Evaluation
                        </button>

                    </form>

                </div>

            @endif

            @if($project->status === \App\Enums\ProjectStatus::TSSD_EVALUATION)

                <form
                    method="POST"
                    action="{{ route('projects.evaluation.store', $project) }}"
                    class="space-y-4"
                >

                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Evaluation Result
                            </label>

                            <select
                                id="evaluation-result"
                                name="result"
                                required
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                            >

                                <option value="">
                                    Select result
                                </option>

                                <option
                                    value="for_compliance"
                                    @selected(old('result') === 'for_compliance')
                                >
                                    For Compliance
                                </option>

                                <option
                                    value="for_approval"
                                    @selected(old('result') === 'for_approval')
                                >
                                    For Approval
                                </option>

                            </select>

                        </div>

                    </div>

                    <div
                        id="for-approval-note"
                        class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3"
                    >
                        <div class="text-xs font-semibold text-emerald-900">
                            Ready for Approval
                        </div>

                        <p class="mt-1 text-xs leading-5 text-emerald-700">
                            Findings and Required Documents are not required when the evaluation result is For Approval.
                        </p>
                    </div>

                    <div
                        id="compliance-fields"
                        class="space-y-4"
                    >
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">

                            <div class="text-xs font-semibold text-amber-900">
                                Compliance Details Required
                            </div>

                            <p class="mt-1 text-xs leading-5 text-amber-700">
                                Both Findings and Required Documents are required when the result is For Compliance.
                            </p>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Findings
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                id="evaluation-findings"
                                name="findings"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="State the findings that require compliance..."
                            >{{ old('findings') }}</textarea>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Required Documents
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                id="evaluation-required-documents"
                                name="required_documents"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="List the documentary requirements to be complied with..."
                            >{{ old('required_documents') }}</textarea>

                        </div>
                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old('remarks') }}</textarea>

                    </div>

                    <div class="flex justify-end">

                        <button
                            type="submit"
                            class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            Save Evaluation
                        </button>

                    </div>

                </form>

            @endif

        @endif

        {{-- For Approval --}}

        @if($project->status === \App\Enums\ProjectStatus::FOR_APPROVAL)

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">

                <div class="text-sm font-semibold text-emerald-800">
                    Project ready for approval
                </div>

                <p class="mt-1 text-xs leading-5 text-emerald-700">
                    Assign the official Project Code during approval. One project receives one Project Code, and that code cannot be reused by another project.
                </p>

            </div>

            <form
                method="POST"
                action="{{ route('projects.approval.store', $project) }}"
                class="mt-5 space-y-4"
            >

                @csrf

                <div class="grid gap-4 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date of Approval
                        </label>

                        <input
                            name="approval_date"
                            type="date"
                            value="{{ old('approval_date', now()->format('Y-m-d')) }}"
                            required
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Official Project Code
                            <span class="text-rose-600">*</span>
                        </label>

                        <input
                            name="project_code"
                            type="text"
                            required
                            autocomplete="off"
                            value="{{ old('project_code') }}"
                            placeholder="Example: TUPAD-ALB-2026-001"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-semibold uppercase tracking-wide"
                        >

                        <p class="mt-1.5 text-[10px] leading-4 text-slate-500">
                            This becomes the single official Project Code for this project after approval.
                        </p>

                        @error('project_code')
                            <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Approval Remarks
                    </label>

                    <textarea
                        name="remarks"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >{{ old('remarks') }}</textarea>

                </div>

                <div class="flex justify-end">

                    <button
                        type="submit"
                        class="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800"
                    >
                        Approve Project
                    </button>

                </div>

            </form>

        @endif

        {{-- Approved --}}

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::COMPLETED,
                ],
                true
            )
            && $project->approval
        )

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Approved Project
                        </div>

                        <div class="mt-1 text-xl font-bold text-emerald-900">
                            {{ $project->approval->project_code }}
                        </div>

                    </div>

                    <div class="text-left sm:text-right">

                        <div class="text-xs text-emerald-600">
                            Date of Approval
                        </div>

                        <div class="mt-1 text-sm font-semibold text-emerald-900">
                            {{ $project->approval->approval_date->format('F d, Y') }}
                        </div>

                    </div>

                </div>

            </div>

        @endif

    </div>

</section>

{{-- Evaluation History --}}

@if($project->evaluations->isNotEmpty())

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Evaluation History
            </h2>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Evaluator
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Result
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Findings
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Required Documents
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @foreach($project->evaluations->sortByDesc('evaluated_at') as $evaluation)

                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->evaluated_at->format('M d, Y g:i A') }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $evaluation->evaluator?->name ?? 'System' }}
                            </td>

                            <td class="px-5 py-4">

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $evaluation->result === 'for_approval'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-700' }}"
                                >
                                    {{ $evaluation->result === 'for_approval'
                                        ? 'For Approval'
                                        : 'For Compliance' }}
                                </span>

                            </td>

                            <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->findings ?: '—' }}
                            </td>

                            <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->required_documents ?: '—' }}
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </section>

@endif

{{-- Implementation Preparation --}}

@if(
    in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::APPROVED,
            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        ],
        true
    )
)

    <section id="implementation" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <h2 class="text-sm font-semibold text-slate-900">
                        Implementation Preparation
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Complete the required preparation before project implementation.
                    </p>

                </div>

                @if($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)

                    <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        Ready for Implementation
                    </span>

                @endif

            </div>

        </div>

        @php
            $preparationItems = [
                'Insurance' => (bool) $project->insuranceEnrollment,
                'PPE Delivery' => (bool) $project->ppeDelivery,
                'Notice to Proceed' => (bool) $project->noticeToProceed,
                'Orientation' => (bool) $project->orientation,
                'Implementation Period' => (bool) $project->implementation,
            ];

            $completedPreparation = collect($preparationItems)
                ->filter()
                ->count();

            $preparationPercent =
                count($preparationItems) > 0
                    ? ($completedPreparation / count($preparationItems)) * 100
                    : 0;
        @endphp

        <div class="border-b border-slate-200 p-5">

            <div class="flex items-center justify-between">

                <span class="text-xs font-semibold text-slate-600">
                    Preparation Completion
                </span>

                <span class="text-xs font-semibold text-slate-800">
                    {{ $completedPreparation }}/{{ count($preparationItems) }}
                </span>

            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">

                <div
                    class="h-full rounded-full bg-slate-800"
                    style="width: {{ $preparationPercent }}%;"
                ></div>

            </div>

            <div class="mt-4 flex flex-wrap gap-2">

                @foreach($preparationItems as $label => $complete)

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $complete
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-slate-100 text-slate-500' }}"
                    >
                        {{ $complete ? '✓' : '•' }}
                        {{ $label }}
                    </span>

                @endforeach

            </div>

        </div>

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                ],
                true
            )
        )

            <div class="grid gap-5 p-5 xl:grid-cols-2">

                {{-- Combined Implementation Requirements --}}

                <form
                    method="POST"
                    action="{{ route('projects.implementation.requirements', $project) }}"
                    class="xl:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                    @csrf

                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">
                                    Implementation Requirements
                                </h3>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    Complete Insurance Enrollment, PPE Delivery, and Notice to Proceed,
                                    then save all three requirements using one submission.
                                </p>
                            </div>

                            <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                Single Submission
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-5 p-5 xl:grid-cols-3">

                        {{-- Insurance Enrollment --}}

                        <section class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 1
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        Insurance Enrollment
                                    </h4>
                                </div>

                                @if($project->insuranceEnrollment)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                                <div class="text-xs font-semibold text-blue-900">
                                    Approved project values are locked
                                </div>

                                <p class="mt-1 text-xs leading-5 text-blue-700">
                                    Insurance Beneficiaries and Insurance Amount use the approved
                                    project values and cannot be edited here.
                                </p>
                            </div>

                            <div class="mt-4 grid gap-4">

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Enrolled
                                    </label>

                                    <input
                                        name="insurance[date_enrolled]"
                                        type="date"
                                        required
                                        value="{{ old(
                                            'insurance.date_enrolled',
                                            $project->insuranceEnrollment?->date_enrolled?->format('Y-m-d')
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >

                                    @error('insurance.date_enrolled')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Beneficiaries
                                        </label>

                                        <div class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                            <span class="font-semibold text-slate-900">
                                                {{ number_format(
                                                    $project->insurance_beneficiaries
                                                    ?? $project->beneficiaries_total
                                                ) }}
                                            </span>

                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Locked
                                            </span>
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Uses the approved insurance beneficiary count.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Amount
                                        </label>

                                        <div class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                            <span class="font-semibold text-slate-900">
                                                ₱{{ number_format($project->insurance_total, 2) }}
                                            </span>

                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Locked
                                            </span>
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Uses the approved project insurance amount.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Mode of Payment
                                    </label>

                                    <select
                                        name="insurance[payment_mode]"
                                        required
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                    >
                                        <option value="">
                                            Select mode
                                        </option>

                                        <option
                                            value="voucher"
                                            @selected(
                                                old(
                                                    'insurance.payment_mode',
                                                    $project->insuranceEnrollment?->payment_mode
                                                ) === 'voucher'
                                            )
                                        >
                                            Voucher
                                        </option>

                                        <option
                                            value="ca"
                                            @selected(
                                                old(
                                                    'insurance.payment_mode',
                                                    $project->insuranceEnrollment?->payment_mode
                                                ) === 'ca'
                                            )
                                        >
                                            CA
                                        </option>
                                    </select>

                                    @error('insurance.payment_mode')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        OR Number
                                    </label>

                                    <input
                                        name="insurance[or_number]"
                                        value="{{ old(
                                            'insurance.or_number',
                                            $project->insuranceEnrollment?->or_number
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Policy Number
                                    </label>

                                    <input
                                        name="insurance[policy_number]"
                                        value="{{ old(
                                            'insurance.policy_number',
                                            $project->insuranceEnrollment?->policy_number
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Remarks
                                    </label>

                                    <textarea
                                        name="insurance[remarks]"
                                        rows="2"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    >{{ old(
                                        'insurance.remarks',
                                        $project->insuranceEnrollment?->remarks
                                    ) }}</textarea>
                                </div>

                            </div>
                        </section>

                        {{-- PPE Delivery --}}

                        <section class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 2
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        PPE Delivery
                                    </h4>
                                </div>

                                @if($project->ppeDelivery)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date of Delivery Receipt
                                </label>

                                <input
                                    name="ppe[delivery_receipt_date]"
                                    type="date"
                                    required
                                    value="{{ old(
                                        'ppe.delivery_receipt_date',
                                        $project->ppeDelivery?->delivery_receipt_date?->format('Y-m-d')
                                    ) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                >

                                @error('ppe.delivery_receipt_date')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    PPE Provided
                                </label>

                                <textarea
                                    name="ppe[ppe_provided]"
                                    rows="5"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >{{ old(
                                    'ppe.ppe_provided',
                                    $project->ppeDelivery?->ppe_provided
                                ) }}</textarea>

                                @error('ppe.ppe_provided')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea
                                    name="ppe[remarks]"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >{{ old(
                                    'ppe.remarks',
                                    $project->ppeDelivery?->remarks
                                ) }}</textarea>
                            </div>
                        </section>

                        {{-- Notice to Proceed --}}

                        <section class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 3
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        Notice to Proceed
                                    </h4>
                                </div>

                                @if($project->noticeToProceed)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4 grid gap-4">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Issued
                                    </label>

                                    <input
                                        name="ntp[date_issued]"
                                        type="date"
                                        required
                                        value="{{ old(
                                            'ntp.date_issued',
                                            $project->noticeToProceed?->date_issued?->format('Y-m-d')
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >

                                    @error('ntp.date_issued')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Released
                                    </label>

                                    <input
                                        name="ntp[date_released]"
                                        type="date"
                                        required
                                        value="{{ old(
                                            'ntp.date_released',
                                            $project->noticeToProceed?->date_released?->format('Y-m-d')
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >

                                    @error('ntp.date_released')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea
                                    name="ntp[remarks]"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >{{ old(
                                    'ntp.remarks',
                                    $project->noticeToProceed?->remarks
                                ) }}</textarea>
                            </div>
                        </section>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-slate-500">
                            Saving will update all three implementation requirements together.
                        </p>

                        <button
                            type="submit"
                            class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]"
                        >
                            Save Implementation Requirements
                        </button>
                    </div>
                </form>

                {{-- Orientation --}}

                <form
                    method="POST"
                    action="{{ route('projects.implementation.orientation', $project) }}"
                    class="rounded-xl border border-slate-200 p-5"
                >

                    @csrf

                    <h3 class="text-sm font-semibold text-slate-900">
                        Orientation
                    </h3>

                    <div class="mt-4">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date of Orientation
                        </label>

                        <input
                            name="orientation_date"
                            type="date"
                            required
                            value="{{ old(
                                'orientation_date',
                                $project->orientation?->orientation_date?->format('Y-m-d')
                            ) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div class="mt-4">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old(
                            'remarks',
                            $project->orientation?->remarks
                        ) }}</textarea>

                    </div>

                    <button
                        type="submit"
                        class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Save Orientation
                    </button>

                </form>

                {{-- Implementation Period --}}

                <form
                    method="POST"
                    action="{{ route('projects.implementation.period', $project) }}"
                    class="rounded-xl border border-slate-200 p-5 xl:col-span-2"
                >

                    @csrf

                    <h3 class="text-sm font-semibold text-slate-900">
                        Implementation Period
                    </h3>

                    <p class="mt-1 text-xs text-slate-500">
                        Select the implementation Start Date.
                        The system automatically calculates the End Date using the project's
                        {{ $project->number_of_days }}-day duration.
                    </p>

                    <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                        <div class="text-xs font-semibold text-blue-900">
                            Automatic End Date
                        </div>

                        <p class="mt-1 text-xs leading-5 text-blue-700">
                            End Date = Start Date + {{ $project->number_of_days }} day(s).
                            The calculated End Date cannot be manually changed.
                        </p>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Start Date
                            </label>

                            <input
                                id="implementation-start-date"
                                name="start_date"
                                type="date"
                                required
                                data-duration-days="{{ $project->number_of_days }}"
                                value="{{ old(
                                    'start_date',
                                    $project->implementation?->start_date?->format('Y-m-d')
                                ) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                            >

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                End Date
                            </label>

                            <input
                                id="implementation-end-date"
                                type="date"
                                readonly
                                tabindex="-1"
                                value="{{ $project->implementation?->end_date?->format('Y-m-d') }}"
                                class="h-10 w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700"
                            >

                            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                Automatically calculated from the Start Date and approved project duration.
                            </p>

                        </div>

                    </div>

                    <div class="mt-4">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old(
                            'remarks',
                            $project->implementation?->remarks
                        ) }}</textarea>

                    </div>

                    <button
                        type="submit"
                        class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Save Implementation Period
                    </button>

                </form>

            </div>

        @endif

    </section>

@endif

{{-- Final Project Workflow Guide --}}
<section id="final-workflow" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
        Final Project Workflow
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold text-slate-500">1. TC / Admin</div>
            <div class="mt-1 text-sm font-bold text-slate-900">
                Post-Documentary Requirements
            </div>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Record required post-implementation documents.
            </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold text-slate-500">2. Focal / Admin</div>
            <div class="mt-1 text-sm font-bold text-slate-900">
                Payment of Wages / Obligation
            </div>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Record payment or obligation information for an eligible project.
            </p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold text-slate-500">3. TC / Admin</div>
            <div class="mt-1 text-sm font-bold text-slate-900">
                Release of Assistance
            </div>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Record the final release and complete the project.
            </p>
        </div>
    </div>
</section>

{{-- Post-Documentary Requirements --}}

@if(
    in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true
    )
)

    <section id="post-documents" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Post-Documentary Requirements
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                TC/Admin records the submitted post-implementation documentary requirements.
                Once complete, the project moves to Payment of Wages / obligation processing.
            </p>

        </div>

        @if(
            $project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
            && (
                auth()->user()->isAdmin()
                || auth()->user()->isTc()
            )
        )

            <form
                method="POST"
                action="{{ route('projects.post-documents.store', $project) }}"
                enctype="multipart/form-data"
                class="border-b border-slate-200 p-5"
            >

                @csrf

                <div class="grid gap-4 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Received
                        </label>

                        <input
                            type="date"
                            name="date_received"
                            required
                            value="{{ old('date_received', now()->format('Y-m-d')) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Document Type
                        </label>

                        <input
                            name="document_type"
                            required
                            value="{{ old('document_type') }}"
                            placeholder="Example: Accomplishment Report"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Attachment
                        </label>

                        <input
                            type="file"
                            name="attachment"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                        >

                        <p class="mt-1 text-[11px] text-slate-400">
                            Maximum 10 MB.
                        </p>

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Forwarded to IMSD
                        </label>

                        <input
                            type="date"
                            name="date_forwarded_to_imsd"
                            value="{{ old('date_forwarded_to_imsd') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div class="md:col-span-2">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="3"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old('remarks') }}</textarea>

                    </div>

                </div>

                <div class="mt-4 flex justify-end">

                    <button
                        type="submit"
                        class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Save Post-Document
                    </button>

                </div>

            </form>

        @endif

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date Received
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Document
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Forwarded to IMSD
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Attachment
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($project->postDocuments as $document)

                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->date_received->format('M d, Y') }}
                            </td>

                            <td class="px-5 py-4 text-sm font-medium text-slate-800">
                                {{ $document->document_type }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->date_forwarded_to_imsd?->format('M d, Y')
                                    ?? 'Not yet forwarded' }}
                            </td>

                            <td class="px-5 py-4">

                                @if($document->attachment_path)

                                    <a
                                        href="{{ route(
                                            'projects.post-documents.download',
                                            [
                                                'project' => $project,
                                                'projectPostDocument' => $document,
                                            ]
                                        ) }}"
                                        class="text-sm font-semibold text-blue-700 hover:underline"
                                    >
                                        Download File
                                    </a>

                                @else

                                    <span class="text-sm text-slate-400">
                                        None
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-sm text-slate-400"
                            >
                                No post-documentary requirements recorded.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endif

{{-- Payment of Wages / Obligation --}}

@if(
    in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true
    )
)

    <section id="payment" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Payment of Wages / Obligation
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Focal/Admin records the wage payment or obligation information after post-documentary requirements are completed.
            </p>

        </div>

        @if($project->obligation)

            <dl class="divide-y divide-slate-100">

                @foreach([
                    'ADL Number' =>
                        $project->obligation->adl_number,

                    'Fund Sponsor' =>
                        $project->obligation->fund_sponsor,

                    'Partner' =>
                        $project->obligation->partner,

                    'Location' =>
                        $project->obligation->project_location,

                    'Term' =>
                        $project->obligation->term,

                    'Beneficiaries' =>
                        number_format(
                            $project->obligation->beneficiaries_total
                        ),

                    'Female Beneficiaries' =>
                        number_format(
                            $project->obligation->beneficiaries_female
                        ),

                    'Amount' =>
                        '₱'.number_format(
                            $project->obligation->amount,
                            2
                        ),

                    'Date' =>
                        $project
                            ->obligation
                            ->obligation_date
                            ->format('F d, Y'),

                    'Month' =>
                        $project->obligation->month,

                    'Payee' =>
                        $project->obligation->payee,
                ] as $label => $value)

                    <div class="grid grid-cols-2 gap-4 px-5 py-3">

                        <dt class="text-xs text-slate-500">
                            {{ $label }}
                        </dt>

                        <dd class="text-right text-sm font-medium text-slate-800">
                            {{ $value }}
                        </dd>

                    </div>

                @endforeach

                @if($project->obligation->remarks)

                    <div class="grid grid-cols-2 gap-4 px-5 py-3">

                        <dt class="text-xs text-slate-500">
                            Remarks
                        </dt>

                        <dd class="text-right text-sm font-medium text-slate-800">
                            {{ $project->obligation->remarks }}
                        </dd>

                    </div>

                @endif

            </dl>

        @elseif(
            $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT
            && (
                auth()->user()->isAdmin()
                || auth()->user()->isFocal()
            )
        )

            <form
                method="POST"
                action="{{ route('projects.payment.store', $project) }}"
                class="p-5"
            >

                @csrf

                <div class="mb-5 rounded-lg border border-slate-200 bg-slate-50 p-4">

                    <div class="text-xs font-semibold text-slate-500">
                        Project information will be automatically copied into the obligation record.
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-3">

                        <div>

                            <div class="text-[11px] text-slate-400">
                                ADL
                            </div>

                            <div class="mt-1 text-sm font-semibold text-slate-800">
                                {{ $project->allocation->adl->adl_number }}
                            </div>

                        </div>

                        <div>

                            <div class="text-[11px] text-slate-400">
                                Partner
                            </div>

                            <div class="mt-1 text-sm font-semibold text-slate-800">
                                {{ $project->partner }}
                            </div>

                        </div>

                        <div>

                            <div class="text-[11px] text-slate-400">
                                Total Project Cost
                            </div>

                            <div class="mt-1 text-sm font-semibold text-slate-800">
                                ₱{{ number_format($project->total_project_cost, 2) }}
                            </div>

                        </div>

                    </div>

                </div>

                <div class="grid gap-4 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Amount
                        </label>

                        <input
                            name="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                            value="{{ old(
                                'amount',
                                $project->total_project_cost
                            ) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Obligation Date
                        </label>

                        <input
                            name="obligation_date"
                            type="date"
                            required
                            value="{{ old(
                                'obligation_date',
                                now()->format('Y-m-d')
                            ) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Month
                        </label>

                        <input
                            name="month"
                            required
                            value="{{ old(
                                'month',
                                now()->format('F Y')
                            ) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Payee
                        </label>

                        <input
                            name="payee"
                            required
                            value="{{ old('payee') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                </div>

                <div class="mt-4">

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >{{ old('remarks') }}</textarea>

                </div>

                <div class="mt-4 flex justify-end">

                    <button
                        type="submit"
                        class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Record Payment of Wages
                    </button>

                </div>

            </form>

        @else

            <div class="p-5 text-sm text-slate-500">
                Waiting for the Focal/Admin account to record Payment of Wages / obligation information.
            </div>

        @endif

    </section>

@endif

{{-- Release of Assistance --}}

@if(
    in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true
    )
)

    <section id="release" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Release of Assistance
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                TC/Admin records the actual release after Payment of Wages / obligation information exists.
                Saving this final step completes the project.
            </p>

        </div>

        @if($project->payout)

            <dl class="divide-y divide-slate-100">

                <div class="grid grid-cols-2 gap-4 px-5 py-3">

                    <dt class="text-xs text-slate-500">
                        Date of Release
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->payout->payout_date->format('F d, Y') }}
                    </dd>

                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">

                    <dt class="text-xs text-slate-500">
                        Mode of Release
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->payout->payout_mode }}
                    </dd>

                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">

                    <dt class="text-xs text-slate-500">
                        Venue
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->payout->venue }}
                    </dd>

                </div>

                @if($project->payout->remarks)

                    <div class="grid grid-cols-2 gap-4 px-5 py-3">

                        <dt class="text-xs text-slate-500">
                            Remarks
                        </dt>

                        <dd class="text-right text-sm font-medium text-slate-800">
                            {{ $project->payout->remarks }}
                        </dd>

                    </div>

                @endif

            </dl>

        @elseif(
            $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT
            && $project->obligation
            && (
                auth()->user()->isAdmin()
                || auth()->user()->isTc()
            )
        )

            <form
                method="POST"
                action="{{ route('projects.payout.store', $project) }}"
                class="p-5"
            >

                @csrf

                <div class="grid gap-4 md:grid-cols-3">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date of Release
                        </label>

                        <input
                            name="payout_date"
                            type="date"
                            required
                            value="{{ old(
                                'payout_date',
                                now()->format('Y-m-d')
                            ) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Mode of Release
                        </label>

                        <input
                            name="payout_mode"
                            required
                            value="{{ old('payout_mode') }}"
                            placeholder="Example: Cash Card / Direct Release"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Venue
                        </label>

                        <input
                            name="venue"
                            required
                            value="{{ old('venue') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                </div>

                <div class="mt-4">

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >{{ old('remarks') }}</textarea>

                </div>

                <div class="mt-4 flex justify-end">

                    <button
                        type="submit"
                        class="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800"
                    >
                        Record Release & Complete Project
                    </button>

                </div>

            </form>

        @elseif(
            $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT
            && ! $project->obligation
        )

            <div class="p-5 text-sm text-slate-500">
                Payment of Wages / obligation information must be recorded before Release of Assistance.
            </div>

        @endif

    </section>

@endif

{{-- PPE Requirements --}}

<section id="ppe-requirements" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            PPE Requirements
        </h2>

    </div>

    <div class="overflow-x-auto">

        <table class="min-w-full">

            <thead class="bg-slate-50">

                <tr>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Type
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Product
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Beneficiaries
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Unit Amount
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Total
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse($project->ppeItems as $item)

                    <tr>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $item->ppe_type->label() }}
                        </td>

                        <td class="px-5 py-4 text-sm font-medium text-slate-800">
                            {{ $item->product }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-600">
                            {{ number_format($item->beneficiary_count) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-600">
                            ₱{{ number_format($item->unit_amount, 2) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            ₱{{ number_format($item->total_amount, 2) }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="px-5 py-10 text-center text-sm text-slate-400"
                        >
                            No PPE requirement was recorded.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>

{{-- Project Status History --}}

<section id="history" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Project Status History
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Historical workflow transitions for this project.
        </p>

    </div>

    <div class="overflow-x-auto">

        <table class="min-w-full">

            <thead class="bg-slate-50">

                <tr>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Date & Time
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        From
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        To
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Changed By
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Remarks
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse(
                    $project
                        ->statusHistory
                        ->sortByDesc('changed_at')
                    as $history
                )

                    <tr>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                            {{ $history->changed_at->format('M d, Y g:i A') }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $history->from_status?->label() ?? 'Created' }}
                        </td>

                        <td class="px-5 py-4">

                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                {{ $history->to_status->label() }}
                            </span>

                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $history->changer?->name ?? 'System' }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-500">
                            {{ $history->remarks ?: '—' }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="px-5 py-10 text-center text-sm text-slate-400"
                        >
                            No status history has been recorded yet.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>



<script>
document.addEventListener('DOMContentLoaded', function () {
    const startInput =
        document.getElementById('implementation-start-date');

    const endInput =
        document.getElementById('implementation-end-date');

    if (!startInput || !endInput) {
        return;
    }

    const durationDays =
        Number.parseInt(
            startInput.dataset.durationDays || '0',
            10
        );

    const formatLocalDate = (date) => {
        const year = date.getFullYear();

        const month = String(
            date.getMonth() + 1
        ).padStart(2, '0');

        const day = String(
            date.getDate()
        ).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const refreshEndDate = () => {
        if (
            !startInput.value
            || !Number.isFinite(durationDays)
            || durationDays < 1
        ) {
            endInput.value = '';
            return;
        }

        const [year, month, day] =
            startInput.value
                .split('-')
                .map(Number);

        const calculatedDate =
            new Date(
                year,
                month - 1,
                day
            );

        calculatedDate.setDate(
            calculatedDate.getDate()
            + durationDays
        );

        endInput.value =
            formatLocalDate(
                calculatedDate
            );
    };

    startInput.addEventListener(
        'change',
        refreshEndDate
    );

    startInput.addEventListener(
        'input',
        refreshEndDate
    );

    refreshEndDate();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resultSelect = document.getElementById('evaluation-result');

    if (!resultSelect) {
        return;
    }

    const complianceFields = document.getElementById('compliance-fields');
    const approvalNote = document.getElementById('for-approval-note');
    const findings = document.getElementById('evaluation-findings');
    const requiredDocuments = document.getElementById('evaluation-required-documents');

    const syncEvaluationFields = () => {
        const isCompliance =
            resultSelect.value === 'for_compliance';

        const isApproval =
            resultSelect.value === 'for_approval';

        complianceFields?.classList.toggle(
            'hidden',
            !isCompliance
        );

        approvalNote?.classList.toggle(
            'hidden',
            !isApproval
        );

        if (findings) {
            findings.required = isCompliance;
            findings.disabled = !isCompliance;
        }

        if (requiredDocuments) {
            requiredDocuments.required = isCompliance;
            requiredDocuments.disabled = !isCompliance;
        }
    };

    resultSelect.addEventListener(
        'change',
        syncEvaluationFields
    );

    syncEvaluationFields();
});
</script>

@endsection