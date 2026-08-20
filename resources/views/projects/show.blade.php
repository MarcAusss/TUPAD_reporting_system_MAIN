@extends('layouts.app')

@section('title', $project->project_title)

@section('content')

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

        <div>

            <a href="{{ route('projects.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Project Management
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                {{ $project->project_title }}
            </h1>

            <div class="mt-2 flex flex-wrap items-center gap-2">

                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                    {{ $project->status->label() }}
                </span>

                <span class="text-xs text-slate-500">
                    {{ $project->term->label() }}
                </span>

            </div>

        </div>

    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

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

    <div class="mt-5 grid gap-5 xl:grid-cols-2">

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">
                    Project Information
                </h2>
            </div>

            <dl class="divide-y divide-slate-100">

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">ADL Number</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->allocation->adl->adl_number }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Fund Sponsor</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->allocation->fund_sponsor }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Partner</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->allocation->partner }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Date Received</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->date_received->format('F d, Y') }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Nature of Work</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->nature_of_work }}
                    </dd>
                </div>

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
                    <dt class="text-xs text-slate-500">Location</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->barangay }},
                        {{ $project->municipality }},
                        {{ $project->province }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">District</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->district }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Income Class</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->income_class ?: 'Not yet assigned' }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Mode</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->implementation_mode->label() }}
                    </dd>
                </div>

                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">Duration</dt>
                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->number_of_days }} days
                        —
                        {{ $project->term->label() }}
                    </dd>
                </div>

            </dl>

        </section>

    </div>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Beneficiaries & Wage
            </h2>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-4">

            <div>
                <div class="text-xs text-slate-500">Total Beneficiaries</div>
                <div class="mt-1 text-lg font-bold text-slate-900">
                    {{ number_format($project->beneficiaries_total) }}
                </div>
            </div>

            <div>
                <div class="text-xs text-slate-500">Female</div>
                <div class="mt-1 text-lg font-bold text-slate-900">
                    {{ number_format($project->beneficiaries_female) }}
                </div>
            </div>

            <div>
                <div class="text-xs text-slate-500">Wage Rate</div>
                <div class="mt-1 text-lg font-bold text-slate-900">
                    ₱{{ number_format($project->wage_rate, 2) }}
                </div>
            </div>

            <div>
                <div class="text-xs text-slate-500">Insurance Rate</div>
                <div class="mt-1 text-lg font-bold text-slate-900">
                    ₱{{ number_format($project->insurance_rate, 2) }}
                </div>
            </div>

        </div>

    </section>

    {{-- <------------------------------------------- Project Workflow ------------------------------/> --}}

    <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Evaluation & Approval
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Current project status: {{ $project->status->label() }}
            </p>

        </div>

        <div class="p-5">

            {{-- <------------------------------------------- Ongoing Profiling ------------------------------/> --}}

            @if ($project->status === \App\Enums\ProjectStatus::ONGOING_PROFILING)
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">

                    <div class="text-sm font-semibold text-blue-800">
                        Profiling Complete?
                    </div>

                    <p class="mt-1 text-xs leading-5 text-blue-700">
                        Submit the project to move it into TSSD Evaluation.
                    </p>

                    <form method="POST" action="{{ route('projects.evaluation.start', $project) }}" class="mt-4">
                        @csrf

                        <button type="submit"
                            class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Submit for TSSD Evaluation
                        </button>
                    </form>

                </div>
            @endif

            {{-- <------------------------------------------- TSSD Evaluation ------------------------------/> --}}

            @if (in_array(
                    $project->status,
                    [\App\Enums\ProjectStatus::TSSD_EVALUATION, \App\Enums\ProjectStatus::FOR_COMPLIANCE],
                    true))

                @if ($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE)

                    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4">

                        <div class="text-sm font-semibold text-amber-800">
                            Project requires compliance
                        </div>

                        @php
                            $latestEvaluation = $project->evaluations->sortByDesc('evaluated_at')->first();
                        @endphp

                        @if ($latestEvaluation)

                            @if ($latestEvaluation->findings)
                                <div class="mt-3">
                                    <div class="text-xs font-semibold text-amber-800">
                                        Findings
                                    </div>

                                    <p class="mt-1 whitespace-pre-line text-sm text-amber-700">
                                        {{ $latestEvaluation->findings }}
                                    </p>
                                </div>
                            @endif

                            @if ($latestEvaluation->required_documents)
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

                        <form method="POST" action="{{ route('projects.evaluation.resubmit', $project) }}" class="mt-4">
                            @csrf

                            <button type="submit"
                                class="h-10 rounded-lg bg-amber-700 px-4 text-sm font-semibold text-white hover:bg-amber-800">
                                Resubmit for Evaluation
                            </button>
                        </form>

                    </div>

                @endif

                @if ($project->status === \App\Enums\ProjectStatus::TSSD_EVALUATION)
                    <form method="POST" action="{{ route('projects.evaluation.store', $project) }}" class="space-y-4">

                        @csrf

                        <div class="grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Evaluation Result
                                </label>

                                <select name="result" required
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">
                                        Select result
                                    </option>

                                    <option value="for_compliance">
                                        For Compliance
                                    </option>

                                    <option value="for_approval">
                                        For Approval
                                    </option>
                                </select>

                            </div>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Findings
                            </label>

                            <textarea name="findings" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Evaluation findings..."></textarea>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Required Documents
                            </label>

                            <textarea name="required_documents" rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Required documentary compliance..."></textarea>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Remarks
                            </label>

                            <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>

                        </div>

                        <div class="flex justify-end">

                            <button type="submit"
                                class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Evaluation
                            </button>

                        </div>

                    </form>
                @endif

            @endif

            {{-- <------------------------------------------- For Approval ------------------------------/> --}}

            @if ($project->status === \App\Enums\ProjectStatus::FOR_APPROVAL)
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">

                    <div class="text-sm font-semibold text-emerald-800">
                        Project ready for approval
                    </div>

                    <p class="mt-1 text-xs leading-5 text-emerald-700">
                        Enter the official approval date and Project Code.
                    </p>

                </div>

                <form method="POST" action="{{ route('projects.approval.store', $project) }}" class="mt-5 space-y-4">

                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Date of Approval
                            </label>

                            <input name="approval_date" type="date"
                                value="{{ old('approval_date', now()->format('Y-m-d')) }}" required
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Project Code Number
                            </label>

                            <input name="project_code" type="text" required value="{{ old('project_code') }}"
                                placeholder="Example: TUPAD-2026-001"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm uppercase">

                        </div>

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Approval Remarks
                        </label>

                        <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

                    </div>

                    <div class="flex justify-end">

                        <button type="submit"
                            class="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800">
                            Approve Project
                        </button>

                    </div>

                </form>
            @endif

            {{-- <------------------------------------------- Approved ------------------------------/> --}}

            @if ($project->status === \App\Enums\ProjectStatus::APPROVED && $project->approval)
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

    @if ($project->evaluations->isNotEmpty())

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

                        @foreach ($project->evaluations->sortByDesc('evaluated_at') as $evaluation)
                            <tr>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $evaluation->evaluated_at->format('M d, Y g:i A') }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-700">
                                    {{ $evaluation->evaluator->name }}
                                </td>

                                <td class="px-5 py-4">

                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                {{ $evaluation->result === 'for_approval' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                        {{ $evaluation->result === 'for_approval' ? 'For Approval' : 'For Compliance' }}
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

    {{-- <------------------------------------------- Implementation Preparation ------------------------------/> --}}

    @if (in_array(
            $project->status,
            [
                \App\Enums\ProjectStatus::APPROVED,
                \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            ],
            true))

        <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

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

                    @if ($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)
                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                            Ready for Implementation
                        </span>
                    @endif

                </div>

            </div>

            {{-- <------------------------------------------- Progress ------------------------------/> --}}

            @php
                $preparationItems = [
                    'Insurance' => (bool) $project->insuranceEnrollment,
                    'PPE Delivery' => (bool) $project->ppeDelivery,
                    'Notice to Proceed' => (bool) $project->noticeToProceed,
                    'Orientation' => (bool) $project->orientation,
                    'Implementation Period' => (bool) $project->implementation,
                ];

                $completedPreparation = collect($preparationItems)->filter()->count();

                $preparationPercent = ($completedPreparation / count($preparationItems)) * 100;
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

                    <div class="h-full rounded-full bg-slate-800" style="width: {{ $preparationPercent }}%;"></div>

                </div>

                <div class="mt-4 flex flex-wrap gap-2">

                    @foreach ($preparationItems as $label => $complete)
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ $complete ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $complete ? '✓' : '•' }}
                            {{ $label }}
                        </span>
                    @endforeach

                </div>

            </div>

            @if (in_array(
                    $project->status,
                    [\App\Enums\ProjectStatus::APPROVED, \App\Enums\ProjectStatus::FOR_IMPLEMENTATION],
                    true))
                <div class="grid gap-5 p-5 xl:grid-cols-2">

                    {{-- <------------------------------------------- Insurance ------------------------------/> --}}

                    <form method="POST" action="{{ route('projects.implementation.insurance', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            Insurance Enrollment
                        </h3>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Enrolled
                                </label>

                                <input name="date_enrolled" type="date" required
                                    value="{{ old('date_enrolled', $project->insuranceEnrollment?->date_enrolled?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Beneficiaries
                                </label>

                                <input name="beneficiary_count" type="number" min="1"
                                    max="{{ $project->beneficiaries_total }}" required
                                    value="{{ old('beneficiary_count', $project->insuranceEnrollment?->beneficiary_count ?? $project->beneficiaries_total) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Amount
                                </label>

                                <input name="amount" type="number" min="0" step="0.01" required
                                    value="{{ old('amount', $project->insuranceEnrollment?->amount ?? $project->insurance_total) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Mode of Payment
                                </label>

                                <select name="payment_mode" required
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">
                                        Select
                                    </option>

                                    <option value="voucher" @selected(old('payment_mode', $project->insuranceEnrollment?->payment_mode) === 'voucher')>
                                        Voucher
                                    </option>

                                    <option value="ca" @selected(old('payment_mode', $project->insuranceEnrollment?->payment_mode) === 'ca')>
                                        CA
                                    </option>
                                </select>

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    OR Number
                                </label>

                                <input name="or_number"
                                    value="{{ old('or_number', $project->insuranceEnrollment?->or_number) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Policy Number
                                </label>

                                <input name="policy_number"
                                    value="{{ old('policy_number', $project->insuranceEnrollment?->policy_number) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                        </div>

                        <button
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Insurance
                        </button>

                    </form>

                    {{-- <------------------------------------------- PPE Delivery ------------------------------/> --}}

                    <form method="POST" action="{{ route('projects.implementation.ppe', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            PPE Delivery
                        </h3>

                        <div class="mt-4">

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Date of Delivery Receipt
                            </label>

                            <input name="delivery_receipt_date" type="date" required
                                value="{{ old('delivery_receipt_date', $project->ppeDelivery?->delivery_receipt_date?->format('Y-m-d')) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div class="mt-4">

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                PPE Provided
                            </label>

                            <textarea name="ppe_provided" rows="4" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('ppe_provided', $project->ppeDelivery?->ppe_provided) }}</textarea>

                        </div>

                        <div class="mt-4">

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Inventory Reference
                            </label>

                            <input name="inventory_reference"
                                value="{{ old('inventory_reference', $project->ppeDelivery?->inventory_reference) }}"
                                placeholder="Optional until Phase 8 integration"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <button
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save PPE Delivery
                        </button>

                    </form>

                    {{-- <------------------------------------------- Notice to Proceed ------------------------------/> --}}

                    <form method="POST" action="{{ route('projects.implementation.ntp', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            Notice to Proceed
                        </h3>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Issued
                                </label>

                                <input name="date_issued" type="date" required
                                    value="{{ old('date_issued', $project->noticeToProceed?->date_issued?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Released
                                </label>

                                <input name="date_released" type="date" required
                                    value="{{ old('date_released', $project->noticeToProceed?->date_released?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                        </div>

                        <button
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Notice to Proceed
                        </button>

                    </form>

                    {{-- <------------------------------------------- Orientation ------------------------------/> --}}

                    <form method="POST" action="{{ route('projects.implementation.orientation', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            Orientation
                        </h3>

                        <div class="mt-4">

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Date of Orientation
                            </label>

                            <input name="orientation_date" type="date" required
                                value="{{ old('orientation_date', $project->orientation?->orientation_date?->format('Y-m-d')) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <button
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Orientation
                        </button>

                    </form>

                    {{-- <------------------------------------------- Implementation Period ------------------------------/> --}}

                    <form method="POST" action="{{ route('projects.implementation.period', $project) }}"
                        class="rounded-xl border border-slate-200 p-5 xl:col-span-2">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            Implementation Period
                        </h3>

                        <p class="mt-1 text-xs text-slate-500">
                            The date range must contain exactly
                            {{ $project->number_of_days }}
                            implementation days.
                        </p>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Start Date
                                </label>

                                <input name="start_date" type="date" required
                                    value="{{ old('start_date', $project->implementation?->start_date?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    End Date
                                </label>

                                <input name="end_date" type="date" required
                                    value="{{ old('end_date', $project->implementation?->end_date?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                        </div>

                        <button
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Implementation Period
                        </button>

                    </form>

                </div>
            @endif

        </section>

    @endif

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

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
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">
                                No PPE requirement was recorded.
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endsection
