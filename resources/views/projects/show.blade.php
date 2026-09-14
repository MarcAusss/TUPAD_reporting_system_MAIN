@extends('layouts.app')

@section('title', $project->project_title)

@section('content')

    @php


        if (auth()->user()->isFocal()) {
            if ($project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP) {
                $backUrl = route('projects.index');
                $backLabel = 'Project Registry';
            } else {
                $backUrl = route('payments.index');
                $backLabel = 'Payment Queue';
            }
        } else {
            $backUrl = route('projects.index');
            $backLabel = 'Project Management';
        }


        $canManageProject = auth()->user()->isAdmin() || auth()->user()->isTc();
        $projectImplementationStarted = $project->implementation()->exists() || $project->acpCheckRelease()->exists();
        $projectEditingLocked = $project->status === \App\Enums\ProjectStatus::COMPLETED;


        $canRecordInsuranceClaim = $canManageProject && in_array(
            $project->status,
            [
                \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                \App\Enums\ProjectStatus::FOR_PAYMENT,
                \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                \App\Enums\ProjectStatus::COMPLETED,
            ],
            true,
        );

    @endphp

    <x-page-header eyebrow="Official Project" :title="$project->project_title"
        description="Review the project profile, current workflow status, and the action required to move the project forward.">
        <x-slot:actions>
            <a href="{{ $backUrl }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                ← {{ $backLabel }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div data-project-workspace data-default-tab="{{ $workspace['default_tab'] }}">
        <x-project-workspace-header :project="$project" :workspace="$workspace" />

        @include('projects.partials.quick-workflow-action')

        {{-- Financial Summary --}}

        <div id="financial-summary" data-workspace-panel="financial"
            class="scroll-mt-32 mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4 {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">

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

        <div id="overview" data-workspace-panel="overview"
            class="scroll-mt-32 mt-5 grid gap-5 xl:grid-cols-2 {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

            @if ($project->status !== \App\Enums\ProjectStatus::COMPLETED)
                @php
                    $isThroughAcp = $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP;

                    if ($isThroughAcp) {
                        $completionLiquidationSummary = app(
                            \App\Services\Projects\ProjectAcpLiquidationService::class,
                        )->summary($project);

                        $completionChecklist = [
                            ['label' => 'ACP Payment', 'complete' => (bool) $project->acpPayment, 'tab' => 'workflow'],
                            [
                                'label' => 'ACP Check Release',
                                'complete' => (bool) $project->acpCheckRelease,
                                'tab' => 'workflow',
                            ],
                            [
                                'label' => 'ACP Implementation (Work Period)',
                                'complete' => (bool) $project->implementation,
                                'tab' => 'workflow',
                            ],
                            [
                                'label' => 'ACP Liquidation (Fully Liquidated)',
                                'complete' => (bool) ($completionLiquidationSummary['is_fully_liquidated'] ?? false),
                                'tab' => 'financial',
                            ],
                        ];
                    } else {
                        $completionPaymentSummary = app(\App\Services\Payments\ProjectPaymentService::class)->summary(
                            $project,
                        );

                        $completionChecklist = [
                            [
                                'label' => 'Insurance Enrollment',
                                'complete' => (bool) $project->insuranceEnrollment,
                                'tab' => 'workflow',
                            ],
                            [
                                'label' => 'PPE Delivery',
                                'complete' => $project->ppeDeliveries->isNotEmpty(),
                                'tab' => 'workflow',
                            ],
                            [
                                'label' => 'Notice to Proceed',
                                'complete' => (bool) $project->noticeToProceed,
                                'tab' => 'workflow',
                            ],
                            ['label' => 'Orientation', 'complete' => (bool) $project->orientation, 'tab' => 'workflow'],
                            [
                                'label' => 'Implementation Period (Work Period)',
                                'complete' => (bool) $project->implementation,
                                'tab' => 'workflow',
                            ],
                            [
                                'label' => 'Post-Documentary Requirements',
                                'complete' => $project->postDocumentsComplete(),
                                'tab' => 'workflow',
                            ],
                            [
                                'label' => 'Payment of Wages (Fully Disbursed)',
                                'complete' => (bool) ($completionPaymentSummary['is_fully_paid'] ?? false),
                                'tab' => 'financial',
                            ],
                        ];
                    }

                    $completionRemaining = collect($completionChecklist)
                        ->reject(fn(array $item): bool => $item['complete'])
                        ->count();
                @endphp

                <section
                    class="overflow-hidden rounded-xl border {{ $completionRemaining > 0 ? 'border-amber-200' : 'border-emerald-200' }} bg-white shadow-sm xl:col-span-2">

                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">
                                    Completion Readiness
                                </h2>
                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    Every item below must be complete before this project can automatically move to
                                    Completed status.
                                </p>
                            </div>

                            <span
                                class="rounded-full px-3 py-1 text-xs font-bold {{ $completionRemaining > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $completionRemaining > 0 ? "{$completionRemaining} form(s) remaining" : 'All requirements complete' }}
                            </span>
                        </div>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($completionChecklist as $item)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $item['complete'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                                        {{ $item['complete'] ? '✓' : '•' }}
                                    </span>
                                    <span
                                        class="text-sm {{ $item['complete'] ? 'text-slate-600' : 'font-semibold text-slate-900' }}">
                                        {{ $item['label'] }}
                                    </span>
                                </div>

                                @unless ($item['complete'])
                                    <a href="{{ route('projects.show', ['project' => $project, 'workspace' => $item['tab']]) }}"
                                        class="shrink-0 text-xs font-semibold text-blue-700 hover:underline">
                                        Complete this →
                                    </a>
                                @endunless
                            </li>
                        @endforeach
                    </ul>

                </section>
            @endif

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

                    @if ($project->project_series_remarks)
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

                    @if ($project->tevs_remarks)
                        <div class="grid grid-cols-2 gap-4 px-5 py-3">
                            <dt class="text-xs text-slate-500">
                                TEVS Remarks
                            </dt>

                            <dd class="text-right text-sm font-medium text-slate-800">
                                {{ $project->tevs_remarks }}
                            </dd>
                        </div>
                    @endif

                    @if ($project->remarks)
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

                @if ($canManageProject && !$projectEditingLocked)
                    <details class="border-t border-slate-200">
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
                            <span class="text-xs font-semibold text-blue-800">
                                Edit Project Details
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">
                                Expand / Collapse
                            </span>
                        </summary>

                        <form method="POST" action="{{ route('projects.details.update', $project) }}"
                            class="border-t border-slate-200 p-5">
                            @csrf
                            @method('PUT')

                            @if ($projectImplementationStarted)
                                <div
                                    class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-800">
                                    Implementation has already started, so Duration, Wage Rate, Beneficiaries, and Insurance
                                    Rate/Beneficiaries are locked here. Use Replace Beneficiary (Beneficiaries tab) to
                                    adjust
                                    Insurance Beneficiaries or Total Project Cost instead.
                                </div>
                            @endif

                            <div class="grid gap-4 md:grid-cols-2">

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Date Received</label>
                                    <input type="date" name="date_received" required
                                        value="{{ old('date_received', $project->date_received->toDateString()) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('date_received')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Project Series</label>
                                    <input type="text" name="project_series" required maxlength="100"
                                        value="{{ old('project_series', $project->project_series) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('project_series')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Project Title</label>
                                    <input type="text" name="project_title" required maxlength="255"
                                        value="{{ old('project_title', $project->project_title) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('project_title')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Nature of Work</label>
                                    <textarea name="nature_of_work" required maxlength="3000" rows="3"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('nature_of_work', $project->nature_of_work) }}</textarea>
                                    @error('nature_of_work')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Fund Sponsor</label>
                                    <input type="text" name="fund_sponsor" required maxlength="255"
                                        value="{{ old('fund_sponsor', $project->fund_sponsor) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('fund_sponsor')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Partner</label>
                                    <input type="text" name="partner" required maxlength="255"
                                        value="{{ old('partner', $project->partner) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('partner')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Project Series Remarks <span class="font-normal text-slate-400">(Optional)</span>
                                    </label>
                                    <textarea name="project_series_remarks" maxlength="3000" rows="2"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('project_series_remarks', $project->project_series_remarks) }}</textarea>
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">TEVS Date
                                        Verified</label>
                                    <input type="date" name="tevs_date_verified" required
                                        value="{{ old('tevs_date_verified', $project->tevs_date_verified?->toDateString()) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('tevs_date_verified')
                                        <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        TEVS Remarks <span class="font-normal text-slate-400">(Optional)</span>
                                    </label>
                                    <input type="text" name="tevs_remarks" maxlength="3000"
                                        value="{{ old('tevs_remarks', $project->tevs_remarks) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Remarks <span class="font-normal text-slate-400">(Optional)</span>
                                    </label>
                                    <textarea name="remarks" maxlength="3000" rows="2"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->remarks) }}</textarea>
                                </div>

                                @unless ($projectImplementationStarted)
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">Duration (Number of
                                            Days)</label>
                                        <input type="number" name="number_of_days" required min="10" max="90"
                                            value="{{ old('number_of_days', $project->number_of_days) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                        @error('number_of_days')
                                            <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">Wage Rate</label>
                                        <input type="number" name="wage_rate" required min="0.01" step="0.01"
                                            value="{{ old('wage_rate', $project->wage_rate) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                        @error('wage_rate')
                                            <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">Total
                                            Beneficiaries</label>
                                        <input type="number" name="beneficiaries_total" required min="1"
                                            value="{{ old('beneficiaries_total', $project->beneficiaries_total) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                        @error('beneficiaries_total')
                                            <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">Female
                                            Beneficiaries</label>
                                        <input type="number" name="beneficiaries_female" required min="0"
                                            value="{{ old('beneficiaries_female', $project->beneficiaries_female) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                        @error('beneficiaries_female')
                                            <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">Insurance Rate</label>
                                        <input type="number" name="insurance_rate" required min="0" step="0.01"
                                            value="{{ old('insurance_rate', $project->insurance_rate) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                        @error('insurance_rate')
                                            <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Beneficiaries <span class="font-normal text-slate-400">(Optional)</span>
                                        </label>
                                        <input type="number" name="insurance_beneficiaries" min="0"
                                            value="{{ old('insurance_beneficiaries', $project->insurance_beneficiaries) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Defaults to Total Beneficiaries when left blank.
                                        </p>
                                        @error('insurance_beneficiaries')
                                            <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endunless

                            </div>

                            <div class="mt-5 flex justify-end">
                                <button type="submit"
                                    class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                    Save Project Details
                                </button>
                            </div>

                        </form>
                    </details>
                @endif

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

        <section data-workspace-panel="beneficiaries"
            class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

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

        {{-- Beneficiary Replacement --}}

        @if ($canManageProject && !$projectEditingLocked)
            @php
                $activeBeneficiaries = $project->beneficiaries
                    ->reject(fn($beneficiary) => $beneficiary->isReplaced())
                    ->values();

                $replacedCount = $project->beneficiaries->count() - $activeBeneficiaries->count();

                $currentInsuranceBeneficiaries = $project->insurance_beneficiaries ?? $project->beneficiaries_total;

                $replacementProvinceId =
                    $project->province_id ?: $project->projectLocations()->orderBy('sort_order')->value('province_id');
            @endphp

            <section id="beneficiary-replacement" data-workspace-panel="beneficiaries"
                class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                        Beneficiary Replacement
                    </div>
                    <h2 class="mt-1 text-sm font-semibold text-slate-900">
                        Replace Beneficiary
                    </h2>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                        Use this when a beneficiary quits/withdraws and is replaced by someone else. Original beneficiaries
                        are never deleted &mdash; they stay on record as replaced. The number of replacement beneficiaries
                        added must match the number being replaced.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-600">
                        <span><span class="font-bold text-slate-900">{{ $project->beneficiaries->count() }}</span> encoded
                            on roster</span>
                        <span><span class="font-bold text-emerald-700">{{ $activeBeneficiaries->count() }}</span>
                            active</span>
                        <span><span class="font-bold text-amber-700">{{ $replacedCount }}</span> replaced
                            historically</span>
                    </div>
                </div>

                <details id="replaceBeneficiaryDetails">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
                        <span class="text-xs font-semibold text-blue-800">Replace Beneficiary</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand /
                            Collapse</span>
                    </summary>

                    <form id="replaceBeneficiaryForm" method="POST"
                        action="{{ route('projects.beneficiary-replacements.store', $project) }}"
                        class="border-t border-slate-200 p-5">
                        @csrf

                        @if (
                            $errors->has('reason') ||
                                $errors->has('removed_existing') ||
                                $errors->has('added') ||
                                $errors->has('insurance_beneficiaries') ||
                                $errors->has('beneficiary_addresses'))
                            <div
                                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                                {{ $errors->first('reason') ?: $errors->first('removed_existing') ?: $errors->first('added') ?: $errors->first('insurance_beneficiaries') ?: $errors->first('beneficiary_addresses') }}
                            </div>
                        @endif

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Reason for Replacement <span class="text-rose-600">*</span>
                            </label>
                            <textarea name="reason" required maxlength="2000" rows="2"
                                placeholder="e.g. Beneficiary withdrawal / became unavailable"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                        </div>

                        <div class="mt-5 grid gap-5 lg:grid-cols-2">

                            {{-- Step A: Beneficiaries Being Replaced --}}
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Step A &middot; Being Replaced
                                </h3>

                                @if ($activeBeneficiaries->isNotEmpty())
                                    <div
                                        class="mt-3 max-h-48 space-y-1.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
                                        @foreach ($activeBeneficiaries as $beneficiary)
                                            <label
                                                class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                                                <input type="checkbox" name="removed_existing[]"
                                                    value="{{ $beneficiary->id }}"
                                                    class="replacement-removed-checkbox h-4 w-4 rounded border-slate-300 text-blue-700">
                                                {{ $beneficiary->full_name }}
                                                <span class="text-slate-400">({{ ucfirst($beneficiary->sex) }})</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="mt-2 text-[11px] leading-4 text-slate-400">
                                        No beneficiaries have been encoded on this project's roster yet. Record who is
                                        leaving below.
                                    </p>
                                @endif

                                <div id="removedNewRows" class="mt-3 space-y-2"></div>

                                <button type="button" id="addRemovedNew"
                                    class="mt-3 inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                    + Record a Beneficiary Not Yet on the Roster
                                </button>
                            </div>

                            {{-- Step B: Replacement Beneficiaries --}}
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Step B &middot; Replacement Beneficiaries
                                </h3>

                                <div id="addedRows" class="mt-3 space-y-2"></div>

                                <button type="button" id="addAddedRow"
                                    class="mt-3 inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                    + Add Replacement Beneficiary
                                </button>
                            </div>

                        </div>

                        <div id="replacementCountStatus"
                            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800">
                            Select/record who is being replaced, then add an equal number of replacement beneficiaries.
                        </div>

                        {{-- Optional Updated Project Details --}}
                        <details class="mt-5 rounded-xl border border-slate-200">
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="text-xs font-semibold text-slate-700">
                                    Optional &middot; Updated Project Details
                                </span>
                                <span class="text-[10px] font-medium text-slate-400">
                                    Only use if something actually changes because of this replacement
                                </span>
                            </summary>

                            <div class="space-y-5 border-t border-slate-200 p-4">

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Insurance Beneficiaries <span class="font-normal text-slate-400">(leave unchanged
                                            if not applicable)</span>
                                    </label>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <input type="number" id="replacementInsuranceBeneficiaries"
                                            name="insurance_beneficiaries" min="0"
                                            max="{{ $project->beneficiaries_total }}"
                                            value="{{ old('insurance_beneficiaries', $currentInsuranceBeneficiaries) }}"
                                            class="h-10 w-40 rounded-lg border border-slate-300 px-3 text-sm">
                                        <span class="text-[11px] leading-4 text-slate-500">
                                            Current: {{ number_format($currentInsuranceBeneficiaries) }} &middot;
                                            Total Project Cost will become
                                            <span id="replacementCostPreview" class="font-semibold text-slate-800">
                                                ₱{{ number_format($project->total_project_cost, 2) }}
                                            </span>
                                        </span>
                                    </div>
                                    <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                        Total Project Cost is recomputed automatically from this value &mdash; it is not
                                        typed in directly. Leaving this at its current value records no revision.
                                    </p>
                                </div>

                                <div>
                                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                        <input type="checkbox" id="replacementUpdateAddress"
                                            class="h-4 w-4 rounded border-slate-300 text-blue-700">
                                        Update Beneficiary Address
                                    </label>

                                    <div id="replacementAddressFields"
                                        class="mt-3 hidden rounded-lg border border-slate-200 bg-slate-50 p-3">
                                        @if ($replacementProvinceId)
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label
                                                        class="mb-1.5 block text-[11px] font-semibold text-slate-600">Municipality
                                                        / City</label>
                                                    <select id="replacementMunicipality"
                                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                                                        <option value="">Select municipality / city</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div id="replacementBarangayOptions"
                                                class="mt-3 max-h-40 overflow-y-auto rounded-md border border-slate-200 bg-white p-2">
                                                <div class="px-2 py-4 text-center text-[11px] text-slate-400">Select a
                                                    municipality/city first.</div>
                                            </div>

                                            <div id="replacementBarangayRows" class="mt-3 space-y-2"></div>

                                            <p class="mt-2 text-[11px] leading-4 text-slate-500">
                                                Barangay Total/Female allocations must sum to this project's declared Total
                                                ({{ number_format($project->beneficiaries_total) }}) and Female
                                                ({{ number_format($project->beneficiaries_female) }}) beneficiaries.
                                                For addresses spanning multiple municipalities, use the Beneficiary Mapping
                                                Source section below instead.
                                            </p>
                                        @else
                                            <p class="text-[11px] leading-4 text-amber-700">
                                                This project has no resolvable province, so the address cannot be updated
                                                here. Use the Beneficiary Mapping Source section below once a province is
                                                assigned.
                                            </p>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </details>

                        <div class="mt-5 flex justify-end">
                            <button type="submit"
                                class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                Save Beneficiary Replacement
                            </button>
                        </div>

                    </form>
                </details>

            </section>

            <script>
                (() => {
                    const form = document.getElementById('replaceBeneficiaryForm');
                    if (!form) return;

                    const escapeHtml = value => String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');

                    const fetchJson = async url => {
                        const response = await fetch(url, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        if (!response.ok) throw new Error('Unable to load geographic reference data.');
                        return response.json();
                    };

                    /*
                    |--------------------------------------------------------------------------
                    | Removed / Added Person Rows
                    |--------------------------------------------------------------------------
                    */

                    const personRowHtml = (name, index, requirePwdFlags) => `
                <div class="replacement-person-row grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_90px_auto]">
                    <input type="text" name="${name}[${index}][first_name]" placeholder="First Name" required
                        class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
                    <input type="text" name="${name}[${index}][last_name]" placeholder="Last Name" required
                        class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
                    <select name="${name}[${index}][sex]" required class="h-8 w-full rounded-md border border-slate-300 bg-white px-1 text-xs">
                        <option value="">Sex</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                    <button type="button" data-remove-person-row class="inline-flex h-8 items-center justify-center rounded-md border border-red-200 bg-white px-2 text-[10px] font-semibold text-red-600 hover:bg-red-50">
                        Remove
                    </button>
                    ${requirePwdFlags ? `
                                                <label class="col-span-2 flex items-center gap-1.5 text-[10px] text-slate-600 sm:col-span-4">
                                                    <input type="checkbox" name="${name}[${index}][is_pwd]" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                                    PWD
                                                </label>
                                                <label class="col-span-2 flex items-center gap-1.5 text-[10px] text-slate-600 sm:col-span-4">
                                                    <input type="checkbox" name="${name}[${index}][is_rebel_returnee]" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                                    Rebel Returnee
                                                </label>
                                            ` : ''}
                </div>
            `;

                    const setupRowList = (containerId, buttonId, fieldName, requirePwdFlags) => {
                        const container = document.getElementById(containerId);
                        const button = document.getElementById(buttonId);
                        if (!container || !button) return;

                        let index = 0;

                        button.addEventListener('click', () => {
                            const wrapper = document.createElement('div');
                            wrapper.innerHTML = personRowHtml(fieldName, index++, requirePwdFlags);
                            const row = wrapper.firstElementChild;

                            row.querySelector('[data-remove-person-row]').addEventListener('click', () => {
                                row.remove();
                                updateCountStatus();
                            });

                            row.querySelectorAll('input, select').forEach(el => el.addEventListener('input',
                                updateCountStatus));

                            container.appendChild(row);
                            updateCountStatus();
                        });
                    };

                    setupRowList('removedNewRows', 'addRemovedNew', 'removed_new', false);
                    setupRowList('addedRows', 'addAddedRow', 'added', true);

                    /*
                    |--------------------------------------------------------------------------
                    | Removed/Added Count Validation
                    |--------------------------------------------------------------------------
                    */

                    const status = document.getElementById('replacementCountStatus');

                    function updateCountStatus() {
                        if (!status) return true;

                        const removedExisting = form.querySelectorAll('.replacement-removed-checkbox:checked').length;
                        const removedNew = document.getElementById('removedNewRows').children.length;
                        const added = document.getElementById('addedRows').children.length;
                        const removed = removedExisting + removedNew;

                        if (removed === 0 && added === 0) {
                            status.className =
                                'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                            status.textContent =
                                'Select/record who is being replaced, then add an equal number of replacement beneficiaries.';
                            return false;
                        }

                        if (removed !== added) {
                            status.className =
                                'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                            status.textContent =
                                `Being replaced: ${removed}. Replacement beneficiaries added: ${added}. These must match.`;
                            return false;
                        }

                        status.className =
                            'mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-[11px] font-semibold leading-5 text-emerald-700';
                        status.textContent =
                            `Ready: ${removed} beneficiary(ies) will be replaced by ${added} new beneficiary(ies).`;
                        return true;
                    }

                    form.querySelectorAll('.replacement-removed-checkbox').forEach(el => el.addEventListener('change',
                        updateCountStatus));
                    updateCountStatus();

                    form.addEventListener('submit', event => {
                        if (!updateCountStatus()) {
                            event.preventDefault();
                            status.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                    });

                    /*
                    |--------------------------------------------------------------------------
                    | Optional: Insurance Beneficiaries -> Total Project Cost Preview
                    |--------------------------------------------------------------------------
                    */

                    const insuranceInput = document.getElementById('replacementInsuranceBeneficiaries');
                    const costPreview = document.getElementById('replacementCostPreview');
                    const insuranceRate = Number(@json((float) $project->insurance_rate));
                    const wagesTotal = Number(@json((float) $project->wages_total));
                    const ppeTotal = Number(@json((float) $project->ppe_total));

                    const currency = value => new Intl.NumberFormat('en-PH', {
                        style: 'currency',
                        currency: 'PHP'
                    }).format(value || 0);

                    insuranceInput?.addEventListener('input', () => {
                        const count = Number(insuranceInput.value || 0);
                        const insuranceTotal = insuranceRate * count;
                        costPreview.textContent = currency(wagesTotal + ppeTotal + insuranceTotal);
                    });

                    /*
                    |--------------------------------------------------------------------------
                    | Optional: Update Beneficiary Address (single municipality)
                    |--------------------------------------------------------------------------
                    */

                    const addressToggle = document.getElementById('replacementUpdateAddress');
                    const addressFields = document.getElementById('replacementAddressFields');
                    const municipalitySelect = document.getElementById('replacementMunicipality');
                    const barangayOptions = document.getElementById('replacementBarangayOptions');
                    const barangayRows = document.getElementById('replacementBarangayRows');
                    const provinceId = @json($replacementProvinceId);

                    addressToggle?.addEventListener('change', () => {
                        addressFields.classList.toggle('hidden', !addressToggle.checked);
                    });

                    if (municipalitySelect && provinceId) {
                        fetchJson(@json(route('locations.municipalities', $replacementProvinceId ?: 0)))
                            .then(municipalities => {
                                municipalities.forEach(item => {
                                    const option = document.createElement('option');
                                    option.value = item.id;
                                    option.textContent = item.district ? `${item.name} — ${item.district}` : item
                                        .name;
                                    municipalitySelect.appendChild(option);
                                });
                            })
                            .catch(() => {
                                barangayOptions.innerHTML =
                                    '<div class="px-2 py-4 text-center text-[11px] font-medium text-red-600">Unable to load municipalities.</div>';
                            });

                        municipalitySelect.addEventListener('change', async () => {
                            barangayRows.innerHTML = '';

                            if (!municipalitySelect.value) {
                                barangayOptions.innerHTML =
                                    '<div class="px-2 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>';
                                return;
                            }

                            barangayOptions.innerHTML =
                                '<div class="px-2 py-4 text-center text-[11px] text-slate-400">Loading barangays...</div>';

                            try {
                                const barangays = await fetchJson(
                                    `/locations/municipalities/${municipalitySelect.value}/barangays`);
                                barangayOptions.innerHTML = '';

                                barangays.forEach(barangay => {
                                    const label = document.createElement('label');
                                    label.className =
                                        'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50';
                                    label.innerHTML = `
                                <input type="checkbox" value="${barangay.id}" data-name="${escapeHtml(barangay.name)}" class="replacement-barangay-checkbox h-3.5 w-3.5 rounded border-slate-300 text-blue-700">
                                <span>${escapeHtml(barangay.name)}</span>
                            `;
                                    label.querySelector('input').addEventListener('change',
                                        renderBarangayRows);
                                    barangayOptions.appendChild(label);
                                });
                            } catch (error) {
                                barangayOptions.innerHTML =
                                    '<div class="px-2 py-4 text-center text-[11px] font-medium text-red-600">Unable to load barangays.</div>';
                            }
                        });

                        function renderBarangayRows() {
                            const existing = new Map(
                                Array.from(barangayRows.querySelectorAll('.replacement-barangay-row')).map(row => [
                                    row.dataset.barangayId,
                                    {
                                        total: row.querySelector('.replacement-barangay-total')?.value ?? '',
                                        female: row.querySelector('.replacement-barangay-female')?.value ?? '',
                                    },
                                ])
                            );

                            const checked = Array.from(barangayOptions.querySelectorAll(
                                '.replacement-barangay-checkbox:checked'));
                            barangayRows.innerHTML = '';

                            checked.forEach((checkbox, index) => {
                                const barangayId = String(checkbox.value);
                                const current = existing.get(barangayId) ?? {
                                    total: '',
                                    female: ''
                                };

                                const row = document.createElement('div');
                                row.className =
                                    'replacement-barangay-row grid grid-cols-[minmax(0,1fr)_90px_90px] items-center gap-2 rounded-md border border-blue-100 bg-blue-50/50 p-2';
                                row.dataset.barangayId = barangayId;
                                row.innerHTML = `
                            <div class="truncate text-[11px] font-semibold text-blue-900">${escapeHtml(checkbox.dataset.name)}</div>
                            <input type="number" min="0" step="1" required placeholder="Total"
                                name="beneficiary_addresses[0][barangays][${index}][beneficiaries_total]"
                                value="${escapeHtml(current.total)}"
                                class="replacement-barangay-total h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                            <input type="number" min="0" step="1" required placeholder="Female"
                                name="beneficiary_addresses[0][barangays][${index}][beneficiaries_female]"
                                value="${escapeHtml(current.female)}"
                                class="replacement-barangay-female h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                            <input type="hidden" name="beneficiary_addresses[0][barangays][${index}][barangay_id]" value="${barangayId}">
                        `;
                                barangayRows.appendChild(row);
                            });

                            if (checked.length > 0) {
                                const municipalityInput = document.createElement('input');
                                municipalityInput.type = 'hidden';
                                municipalityInput.name = 'beneficiary_addresses[0][municipality_id]';
                                municipalityInput.value = municipalitySelect.value;
                                barangayRows.appendChild(municipalityInput);
                            }
                        }
                    }
                })();
            </script>
        @endif

        {{-- Insurance Claim (Incident Report) --}}

        @if ($canRecordInsuranceClaim)
            <section id="insurance-claim" data-workspace-panel="beneficiaries"
                class="mt-5 overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-red-700">
                        Insurance Claim
                    </div>
                    <h2 class="mt-1 text-sm font-semibold text-slate-900">
                        Record Insurance Claim
                    </h2>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                        Use this when one or more beneficiaries were injured during project implementation. Record
                        what happened, when, the approximate time, and the full name and address of every injured
                        beneficiary. Once saved, a claim record cannot be edited or removed &mdash; it stays on file
                        as reported.
                    </p>
                </div>

                <details id="insuranceClaimDetails">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
                        <span class="text-xs font-semibold text-red-800">Record Insurance Claim</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand /
                            Collapse</span>
                    </summary>

                    <form id="insuranceClaimForm" method="POST"
                        action="{{ route('projects.insurance-claims.store', $project) }}"
                        class="border-t border-slate-200 p-5">
                        @csrf

                        @if (
                            $errors->has('incident_description') ||
                                $errors->has('incident_date') ||
                                $errors->has('incident_time') ||
                                $errors->has('beneficiaries'))
                            <div
                                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                                {{ $errors->first('incident_description') ?: $errors->first('incident_date') ?: $errors->first('incident_time') ?: $errors->first('beneficiaries') }}
                            </div>
                        @endif

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                What Happened <span class="text-rose-600">*</span>
                            </label>
                            <textarea name="incident_description" required maxlength="2000" rows="3"
                                placeholder="Describe the incident that led to the injury/injuries"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('incident_description') }}</textarea>
                        </div>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date of Incident <span class="text-rose-600">*</span>
                                </label>
                                <input name="incident_date" type="date" required max="{{ now()->format('Y-m-d') }}"
                                    value="{{ old('incident_date') }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Approximate Time <span class="font-normal text-slate-400">(optional)</span>
                                </label>
                                <input name="incident_time" type="time" value="{{ old('incident_time') }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>
                        </div>

                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                Injured Beneficiary(ies)
                            </h3>
                            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                Select from the project roster to auto-fill the name, or leave the dropdown on
                                "Select from Roster (Optional)" to enter someone not yet encoded on the roster.
                            </p>

                            <div id="insuranceClaimRows" class="mt-3 space-y-2"></div>

                            <button type="button" id="addInsuranceClaimRow"
                                class="mt-3 inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                + Add Injured Beneficiary
                            </button>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit"
                                class="h-10 rounded-lg bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800">
                                Save Insurance Claim
                            </button>
                        </div>

                    </form>
                </details>

            </section>

            <script>
                (() => {
                    const form = document.getElementById('insuranceClaimForm');
                    if (!form) return;

                    const roster = @json($project->beneficiaries->map(fn($b) => [
                        'id' => $b->id,
                        'name' => $b->full_name,
                    ])->values());

                    const escapeHtml = value => String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');

                    const rows = document.getElementById('insuranceClaimRows');
                    let rowIndex = 0;

                    const rosterOptionsHtml = roster
                        .map(b => `<option value="${b.id}" data-name="${escapeHtml(b.name)}">${escapeHtml(b.name)}</option>`)
                        .join('');

                    const addRow = () => {
                        const index = rowIndex++;
                        const wrapper = document.createElement('div');
                        wrapper.className =
                            'insurance-claim-row grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]';
                        wrapper.innerHTML = `
                    <select data-role="beneficiary-picker" class="h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                        <option value="">Select from Roster (Optional)</option>
                        ${rosterOptionsHtml}
                    </select>
                    <input type="hidden" name="beneficiaries[${index}][beneficiary_id]" data-role="beneficiary-id">
                    <input type="text" name="beneficiaries[${index}][full_name]" placeholder="Full Name" required
                        class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
                    <input type="text" name="beneficiaries[${index}][address]" placeholder="Address" required
                        class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
                    <button type="button" data-remove-row class="inline-flex h-8 items-center justify-center rounded-md border border-red-200 bg-white px-2 text-[10px] font-semibold text-red-600 hover:bg-red-50">
                        Remove
                    </button>
                `;

                        const picker = wrapper.querySelector('[data-role="beneficiary-picker"]');
                        const beneficiaryIdInput = wrapper.querySelector('[data-role="beneficiary-id"]');
                        const fullNameInput = wrapper.querySelector('input[name$="[full_name]"]');

                        picker.addEventListener('change', () => {
                            const selected = picker.selectedOptions[0];
                            beneficiaryIdInput.value = picker.value;
                            if (picker.value) {
                                fullNameInput.value = selected.dataset.name ?? '';
                            }
                        });

                        wrapper.querySelector('[data-remove-row]').addEventListener('click', () => {
                            wrapper.remove();
                        });

                        rows.appendChild(wrapper);
                    };

                    document.getElementById('addInsuranceClaimRow').addEventListener('click', addRow);

                    // Start with one row so the form isn't empty on expand.
                    addRow();
                })();
            </script>
        @endif

        {{-- Beneficiary Summary --}}

        <section data-workspace-panel="beneficiaries"
            class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Beneficiary Summary</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Only aggregate beneficiary counts are recorded. Individual personal records are not encoded; beneficiary
                    residence geography is stored separately as aggregate address allocations.
                </p>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Beneficiaries</div>
                    <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($project->beneficiaries_total) }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Female Beneficiaries</div>
                    <div class="mt-2 text-2xl font-bold text-slate-900">
                        {{ number_format($project->beneficiaries_female) }}</div>
                </div>
            </div>
        </section>

        {{-- Project Location Coverage --}}

        @if ($project->projectLocations->isNotEmpty())

            <section data-workspace-panel="overview"
                class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Project Location Coverage
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        All selected district, municipality/city, and barangay target areas for this project.
                    </p>
                </div>

                <div class="grid gap-3 p-5 lg:grid-cols-2">

                    @foreach ($project->projectLocations as $location)
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

                                <span
                                    class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 shadow-sm">
                                    {{ $location->barangays->count() }} brgy
                                </span>

                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">

                                @foreach ($location->barangays as $barangay)
                                    <span
                                        class="rounded-md border border-blue-100 bg-blue-50 px-2.5 py-1 text-[11px] font-medium text-blue-800">
                                        {{ $barangay->name }}
                                    </span>
                                @endforeach

                            </div>

                        </div>
                    @endforeach

                </div>

            </section>

        @endif

        {{-- Beneficiary Classification, Intervention Focus & Labor Market --}}

        @php
            $phase7SectorRecords = $project->beneficiarySectors->keyBy(fn($sector) => $sector->sector_key->value);

            $phase7ReferralTotals = [
                'referred' => $project->laborMarketReferrals->sum('interested_referred_total'),
                'provided' => $project->laborMarketReferrals->sum('provided_intervention_total'),
                'amount' => $project->laborMarketReferrals->sum(fn($referral) => (float) $referral->amount_released),
            ];
        @endphp

        <section id="beneficiary-classification" data-workspace-panel="beneficiaries"
            class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Beneficiary Classification &amp; Labor Market
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Beneficiary addresses drive Beneficiary Mapping, while sector classifications and labor-market
                        records remain separate reporting dimensions.
                    </p>
                </div>

                @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                    <a href="{{ route('projects.classifications.show', $project) }}"
                        class="inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">
                        Manage Classification
                    </a>
                @endif
            </div>

            @php
                $beneficiaryAddressGroups = $project->beneficiaryAddresses
                    ->groupBy('municipality_id')
                    ->map(function ($addresses, $municipalityId) {
                        return [
                            'municipality_id' => (int) $municipalityId,
                            'barangays' => $addresses
                                ->map(
                                    fn($address) => [
                                        'barangay_id' => (int) $address->barangay_id,
                                        'beneficiaries_total' => (int) $address->beneficiaries_total,
                                        'beneficiaries_female' => (int) $address->beneficiaries_female,
                                    ],
                                )
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all();

                $beneficiaryAddressFormData = collect(old('beneficiary_addresses', $beneficiaryAddressGroups))
                    ->values()
                    ->all();
                $beneficiaryAddressAllocatedTotal = (int) $project->beneficiaryAddresses->sum('beneficiaries_total');
                $beneficiaryAddressAllocatedFemale = (int) $project->beneficiaryAddresses->sum('beneficiaries_female');
            @endphp

            <div class="border-b border-slate-200 bg-slate-50/70 p-5">
                <div class="rounded-xl border border-blue-200 bg-white shadow-sm">
                    <div
                        class="flex flex-col gap-3 border-b border-blue-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                                Beneficiary Mapping Source
                            </div>
                            <h3 class="mt-1 text-sm font-semibold text-slate-900">
                                Beneficiary Address &amp; Geographic Allocation
                            </h3>
                            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                                Encode where the project beneficiaries reside. These address allocations are used by
                                Beneficiary Mapping and are kept separate from Project Location Mapping.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:min-w-67.5">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Declared Total
                                </div>
                                <div class="mt-1 text-sm font-bold text-slate-900">
                                    {{ number_format($project->beneficiaries_total) }}</div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                                <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Declared Female
                                </div>
                                <div class="mt-1 text-sm font-bold text-slate-900">
                                    {{ number_format($project->beneficiaries_female) }}</div>
                            </div>
                        </div>
                    </div>

                    @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                        @if ($beneficiaryAddressProvince)
                            <form id="beneficiaryAddressForm" method="POST"
                                action="{{ route('projects.beneficiary-addresses.update', $project) }}" class="p-5">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="province_id" value="{{ $beneficiaryAddressProvince->id }}">

                                @if ($errors->has('province_id') || $errors->has('beneficiary_addresses') || $errors->has('beneficiary_addresses.*'))
                                    <div
                                        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                                        {{ $errors->first('province_id') ?: $errors->first('beneficiary_addresses') ?: $errors->first('beneficiary_addresses.*') }}
                                    </div>
                                @endif

                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                                    <div>
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">Province</label>
                                            <div
                                                class="flex h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800">
                                                {{ $beneficiaryAddressProvince->name }}
                                            </div>
                                            <p class="mt-2 text-[11px] leading-5 text-slate-500">
                                                Province is automatically locked to the project/coordinator assignment. Only
                                                municipalities and barangays inside this province can be saved.
                                            </p>
                                        </div>

                                        <div
                                            class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <h4 class="text-xs font-semibold text-slate-800">Municipalities / Cities
                                                    &amp; Barangays</h4>
                                                <p class="mt-1 text-[11px] text-slate-500">Add every beneficiary
                                                    municipality/city, select its barangays, then allocate Total and Female
                                                    counts.</p>
                                            </div>
                                            <button id="addBeneficiaryAddressLocation" type="button"
                                                class="inline-flex h-9 items-center justify-center rounded-lg border border-blue-300 bg-white px-3 text-xs font-semibold text-blue-800 hover:bg-blue-50">
                                                + Add Municipality / City
                                            </button>
                                        </div>

                                        <div id="beneficiaryAddressLocations" class="mt-4 space-y-4"></div>
                                    </div>

                                    <aside>
                                        <div
                                            class="rounded-lg border border-slate-200 bg-slate-50 p-4 lg:sticky lg:top-24">
                                            <div class="text-xs font-semibold text-slate-800">Address Allocation Status
                                            </div>
                                            <div class="mt-3 grid grid-cols-2 gap-2">
                                                <div class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200">
                                                    <div
                                                        class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                                        Allocated</div>
                                                    <div id="beneficiaryAddressTotal"
                                                        class="mt-1 text-sm font-bold text-slate-900">0</div>
                                                </div>
                                                <div class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200">
                                                    <div
                                                        class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                                        Female</div>
                                                    <div id="beneficiaryAddressFemale"
                                                        class="mt-1 text-sm font-bold text-slate-900">0</div>
                                                </div>
                                            </div>

                                            <div id="beneficiaryAddressValidation"
                                                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800">
                                                Complete the beneficiary address allocation.
                                            </div>

                                            <button type="submit"
                                                class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">
                                                Save Beneficiary Addresses
                                            </button>
                                        </div>
                                    </aside>
                                </div>
                            </form>
                        @else
                            <div class="p-5">
                                <div
                                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                                    Beneficiary address encoding is unavailable because this project has no resolvable
                                    assigned province.
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="p-5">
                            <div
                                class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                                Beneficiary address allocation is read-only for this account.
                            </div>
                        </div>
                    @endif

                    <details class="border-t border-slate-200" @if ($project->beneficiaryAddresses->isEmpty()) open @endif>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                            <div>
                                <div class="text-xs font-semibold text-slate-900">View All Beneficiary Address Data</div>
                                <div class="mt-1 text-[11px] text-slate-500">
                                    {{ number_format($project->beneficiaryAddresses->count()) }} barangay record(s) ·
                                    {{ number_format($beneficiaryAddressAllocatedTotal) }} total ·
                                    {{ number_format($beneficiaryAddressAllocatedFemale) }} female
                                </div>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand
                                / Collapse</span>
                        </summary>

                        <div class="overflow-x-auto border-t border-slate-200">
                            <table class="tupad-system-table min-w-190 w-full">
                                <thead class="bg-slate-50">
                                    <tr class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                        <th class="px-4 py-3 text-left">Province</th>
                                        <th class="px-4 py-3 text-left">District</th>
                                        <th class="px-4 py-3 text-left">Municipality / City</th>
                                        <th class="px-4 py-3 text-left">Barangay</th>
                                        <th class="px-4 py-3 text-right">Total</th>
                                        <th class="px-4 py-3 text-right">Female</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($project->beneficiaryAddresses as $address)
                                        <tr>
                                            <td class="px-4 py-3 text-xs text-slate-600">
                                                {{ $address->province?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-xs text-slate-600">
                                                {{ $address->municipality?->district ?? '—' }}</td>
                                            <td class="px-4 py-3 text-xs font-semibold text-slate-800">
                                                {{ $address->municipality?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-xs text-slate-700">
                                                {{ $address->barangay?->name ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right text-xs font-semibold text-slate-900">
                                                {{ number_format($address->beneficiaries_total) }}</td>
                                            <td class="px-4 py-3 text-right text-xs text-slate-600">
                                                {{ number_format($address->beneficiaries_female) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-5 py-8 text-center text-xs text-slate-400">
                                                No beneficiary address allocation has been encoded yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            </div>

            @if ((auth()->user()->isAdmin() || auth()->user()->isTc()) && $beneficiaryAddressProvince)
                <script>
                    (() => {
                        const root = document.getElementById('beneficiaryAddressLocations');
                        const addButton = document.getElementById('addBeneficiaryAddressLocation');
                        const form = document.getElementById('beneficiaryAddressForm');
                        const status = document.getElementById('beneficiaryAddressValidation');
                        const totalOutput = document.getElementById('beneficiaryAddressTotal');
                        const femaleOutput = document.getElementById('beneficiaryAddressFemale');

                        if (!root || !addButton || !form || !status) return;

                        const initialGroups = @json($beneficiaryAddressFormData);
                        const municipalityUrl = @json(route('locations.municipalities', $beneficiaryAddressProvince));
                        const declaredTotal = Number(@json((int) $project->beneficiaries_total));
                        const declaredFemale = Number(@json((int) $project->beneficiaries_female));
                        let municipalityOptions = [];
                        let nextIndex = 0;

                        const escapeHtml = value => String(value ?? '')
                            .replaceAll('&', '&amp;')
                            .replaceAll('<', '&lt;')
                            .replaceAll('>', '&gt;')
                            .replaceAll('"', '&quot;')
                            .replaceAll("'", '&#039;');

                        const fetchJson = async url => {
                            const response = await fetch(url, {
                                headers: {
                                    'Accept': 'application/json'
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Unable to load geographic reference data.');
                            }

                            return response.json();
                        };

                        const selectedMunicipalityIds = () => Array.from(
                                root.querySelectorAll('.beneficiary-municipality-select')
                            )
                            .map(select => select.value)
                            .filter(Boolean);

                        const refreshMunicipalityOptions = () => {
                            const selected = selectedMunicipalityIds();

                            root.querySelectorAll('.beneficiary-municipality-select').forEach(select => {
                                Array.from(select.options).forEach(option => {
                                    if (!option.value) return;
                                    option.disabled = option.value !== select.value && selected.includes(option
                                        .value);
                                });
                            });
                        };

                        const updateStatus = () => {
                            const totalInputs = Array.from(root.querySelectorAll('.beneficiary-address-total'));
                            const femaleInputs = Array.from(root.querySelectorAll('.beneficiary-address-female'));

                            const allocatedTotal = totalInputs.reduce((sum, input) => sum + Number(input.value || 0), 0);
                            const allocatedFemale = femaleInputs.reduce((sum, input) => sum + Number(input.value || 0), 0);
                            const incomplete = totalInputs.length === 0 ||
                                totalInputs.some(input => input.value === '') ||
                                femaleInputs.some(input => input.value === '');
                            const femaleExceeds = totalInputs.some((input, index) =>
                                Number(femaleInputs[index]?.value || 0) > Number(input.value || 0)
                            );

                            totalOutput.textContent = allocatedTotal.toLocaleString();
                            femaleOutput.textContent = allocatedFemale.toLocaleString();
                            totalOutput.classList.toggle('text-red-600', allocatedTotal > declaredTotal);
                            femaleOutput.classList.toggle('text-red-600', allocatedFemale > declaredFemale);

                            if (femaleExceeds) {
                                status.className =
                                    'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                                status.textContent = 'A barangay Female count cannot exceed that barangay Total count.';
                                return false;
                            }

                            if (incomplete) {
                                status.className =
                                    'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                                status.textContent =
                                    'Select at least one barangay and complete all Total and Female allocations.';
                                return false;
                            }

                            const exceedsTotal = allocatedTotal > declaredTotal;
                            const exceedsFemale = allocatedFemale > declaredFemale;

                            if (exceedsTotal || exceedsFemale) {
                                status.className =
                                    'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                                status.textContent = exceedsTotal ?
                                    `Allocated total of ${allocatedTotal.toLocaleString()} exceeds the project's declared Total Beneficiaries of ${declaredTotal.toLocaleString()} by ${(allocatedTotal - declaredTotal).toLocaleString()}.` :
                                    `Allocated female count of ${allocatedFemale.toLocaleString()} exceeds the project's declared Female Beneficiaries of ${declaredFemale.toLocaleString()} by ${(allocatedFemale - declaredFemale).toLocaleString()}.`;
                                return false;
                            }

                            if (allocatedTotal !== declaredTotal || allocatedFemale !== declaredFemale) {
                                status.className =
                                    'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                                status.textContent =
                                    `Allocated ${allocatedTotal.toLocaleString()} of ${declaredTotal.toLocaleString()} total and ${allocatedFemale.toLocaleString()} of ${declaredFemale.toLocaleString()} female beneficiaries.`;
                                return false;
                            }

                            status.className =
                                'mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-[11px] font-semibold leading-5 text-emerald-700';
                            status.textContent = 'Beneficiary address allocation is complete and ready to save.';
                            return true;
                        };

                        const renderSelectedBarangays = (card, groupIndex) => {
                            const selectedBox = card.querySelector('.beneficiary-selected-barangays');
                            const checked = Array.from(card.querySelectorAll('.beneficiary-barangay-checkbox:checked'));
                            const existing = new Map(
                                Array.from(selectedBox.querySelectorAll('.beneficiary-address-row')).map(row => [
                                    row.dataset.barangayId,
                                    {
                                        total: row.querySelector('.beneficiary-address-total')?.value ?? '',
                                        female: row.querySelector('.beneficiary-address-female')?.value ?? '',
                                    },
                                ])
                            );

                            selectedBox.innerHTML = '';

                            if (checked.length === 0) {
                                selectedBox.innerHTML =
                                    '<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>';
                                updateStatus();
                                return;
                            }

                            checked.forEach((checkbox, barangayIndex) => {
                                const barangayId = checkbox.value;
                                const values = existing.get(barangayId) || {
                                    total: checkbox.dataset.total ?? '',
                                    female: checkbox.dataset.female ?? '',
                                };
                                const row = document.createElement('div');
                                row.className =
                                    'beneficiary-address-row grid gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_110px_110px]';
                                row.dataset.barangayId = barangayId;
                                row.innerHTML = `
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-800">${escapeHtml(checkbox.dataset.name)}</div>
                                <div class="mt-1 text-[10px] text-slate-400">Beneficiary home address allocation</div>
                                <input type="hidden" name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][barangay_id]" value="${escapeHtml(barangayId)}">
                            </div>
                            <label>
                                <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Total</span>
                                <input type="number" min="0" step="1" required name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][beneficiaries_total]" value="${escapeHtml(values.total)}" class="beneficiary-address-total h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                            </label>
                            <label>
                                <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Female</span>
                                <input type="number" min="0" step="1" required name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][beneficiaries_female]" value="${escapeHtml(values.female)}" class="beneficiary-address-female h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                            </label>
                        `;

                                row.querySelectorAll('input[type="number"]').forEach(input => {
                                    input.addEventListener('input', updateStatus);
                                });
                                selectedBox.appendChild(row);
                            });

                            updateStatus();
                        };

                        const loadBarangays = async (card, groupIndex, municipalityId, initialBarangays = []) => {
                            const optionsBox = card.querySelector('.beneficiary-barangay-options');
                            const selectedMap = new Map(
                                (initialBarangays || []).map(row => [String(row.barangay_id), row])
                            );

                            if (!municipalityId) {
                                optionsBox.innerHTML =
                                    '<div class="px-3 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>';
                                card.querySelector('.beneficiary-selected-barangays').innerHTML =
                                    '<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>';
                                updateStatus();
                                return;
                            }

                            optionsBox.innerHTML =
                                '<div class="px-3 py-4 text-center text-[11px] text-slate-400">Loading barangays...</div>';

                            try {
                                const barangays = await fetchJson(`/locations/municipalities/${municipalityId}/barangays`);
                                optionsBox.innerHTML = '';

                                barangays.forEach(barangay => {
                                    const existing = selectedMap.get(String(barangay.id));
                                    const label = document.createElement('label');
                                    label.className =
                                        'flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-xs text-slate-700 hover:bg-slate-50';
                                    label.innerHTML = `
                                <input type="checkbox" value="${barangay.id}" data-name="${escapeHtml(barangay.name)}" data-total="${escapeHtml(existing?.beneficiaries_total ?? '')}" data-female="${escapeHtml(existing?.beneficiaries_female ?? '')}" class="beneficiary-barangay-checkbox h-4 w-4 rounded border-slate-300 text-blue-700" ${existing ? 'checked' : ''}>
                                <span>${escapeHtml(barangay.name)}</span>
                            `;
                                    label.querySelector('input').addEventListener('change', () =>
                                        renderSelectedBarangays(card, groupIndex));
                                    optionsBox.appendChild(label);
                                });

                                renderSelectedBarangays(card, groupIndex);
                            } catch (error) {
                                optionsBox.innerHTML =
                                    '<div class="px-3 py-4 text-center text-[11px] font-medium text-red-600">Unable to load barangays. Refresh the page and try again.</div>';
                            }
                        };

                        const addLocationCard = async initial => {
                            const groupIndex = nextIndex++;
                            const card = document.createElement('div');
                            card.className = 'beneficiary-address-card rounded-xl border border-slate-200 bg-white p-4';
                            card.dataset.index = groupIndex;
                            card.innerHTML = `
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Municipality / City</label>
                                <select name="beneficiary_addresses[${groupIndex}][municipality_id]" required class="beneficiary-municipality-select h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs">
                                    <option value="">Select municipality / city</option>
                                    ${municipalityOptions.map(item => `<option value="${item.id}">${escapeHtml(item.name)}${item.district ? ` — ${escapeHtml(item.district)}` : ''}</option>`).join('')}
                                </select>
                            </div>
                            <button type="button" class="remove-beneficiary-address-card inline-flex h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-[11px] font-semibold text-red-700 hover:bg-red-50">Remove</button>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-slate-500">Select Barangays</div>
                                <div class="beneficiary-barangay-options max-h-52 overflow-y-auto p-2">
                                    <div class="px-3 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-2 text-[10px] font-bold uppercase tracking-wide text-slate-500">Selected Barangay Allocation</div>
                                <div class="beneficiary-selected-barangays space-y-2">
                                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>
                                </div>
                            </div>
                        </div>
                    `;

                            root.appendChild(card);

                            const select = card.querySelector('.beneficiary-municipality-select');
                            if (initial?.municipality_id) {
                                select.value = String(initial.municipality_id);
                            }

                            select.addEventListener('change', async () => {
                                refreshMunicipalityOptions();
                                await loadBarangays(card, groupIndex, select.value, []);
                            });

                            card.querySelector('.remove-beneficiary-address-card').addEventListener('click', () => {
                                card.remove();
                                refreshMunicipalityOptions();
                                updateStatus();
                            });

                            refreshMunicipalityOptions();

                            if (select.value) {
                                await loadBarangays(card, groupIndex, select.value, initial?.barangays || []);
                            } else {
                                updateStatus();
                            }
                        };

                        const initialize = async () => {
                            try {
                                municipalityOptions = await fetchJson(municipalityUrl);
                            } catch (error) {
                                status.className =
                                    'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                                status.textContent = 'Unable to load municipalities/cities for the assigned province.';
                                addButton.disabled = true;
                                return;
                            }

                            if (Array.isArray(initialGroups) && initialGroups.length > 0) {
                                for (const group of initialGroups) {
                                    await addLocationCard(group);
                                }
                            } else {
                                await addLocationCard(null);
                            }

                            updateStatus();
                        };

                        addButton.addEventListener('click', () => addLocationCard(null));

                        form.addEventListener('submit', event => {
                            if (!updateStatus()) {
                                event.preventDefault();
                                status.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                            }
                        });

                        initialize();
                    })();
                </script>
            @endif

            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                <div class="bg-white px-5 py-4 sm:col-span-2 xl:col-span-1">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                        Primary Intervention Focus
                    </div>
                    <div class="mt-1 text-sm font-semibold leading-5 text-slate-900">
                        {{ $project->intervention_focus?->label() ?? 'Not yet classified' }}
                    </div>
                </div>

                <div class="bg-white px-5 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                        Referral Records
                    </div>
                    <div class="mt-1 text-lg font-bold text-slate-900">
                        {{ number_format($project->laborMarketReferrals->count()) }}
                    </div>
                </div>

                <div class="bg-white px-5 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                        Referred / Provided
                    </div>
                    <div class="mt-1 text-lg font-bold text-slate-900">
                        {{ number_format($phase7ReferralTotals['referred']) }} /
                        {{ number_format($phase7ReferralTotals['provided']) }}
                    </div>
                </div>

                <div class="bg-white px-5 py-4">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                        Intervention Amount Released
                    </div>
                    <div class="mt-1 text-lg font-bold text-emerald-700">
                        ₱{{ number_format($phase7ReferralTotals['amount'], 2) }}
                    </div>
                </div>
            </div>

            <div class="grid gap-5 p-5 xl:grid-cols-2">
                @foreach ([
            'Priority / Vulnerable Sectors' => \App\Enums\BeneficiarySectorCategory::priorityVulnerable(),
            'Occupational / Livelihood Sectors' => \App\Enums\BeneficiarySectorCategory::occupationalLivelihood(),
        ] as $groupLabel => $categories)
                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-800">
                            {{ $groupLabel }}
                        </div>
                        <table class="tupad-system-table w-full">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                    <th class="px-4 py-2 text-left">Category</th>
                                    <th class="px-4 py-2 text-right">Total</th>
                                    <th class="px-4 py-2 text-right">Female</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($categories as $category)
                                    @php
                                        $phase7Sector = $phase7SectorRecords->get($category->value);
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-xs font-medium text-slate-700">
                                            {{ $category->label() }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-xs font-semibold text-slate-900">
                                            {{ number_format($phase7Sector?->beneficiaries_total ?? 0) }}
                                        </td>
                                        <td class="px-4 py-2 text-right text-xs text-slate-600">
                                            {{ number_format($phase7Sector?->beneficiaries_female ?? 0) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Evaluation & Approval --}}

        <section id="evaluation" data-workspace-panel="workflow"
            class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

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

                @if ($project->status === \App\Enums\ProjectStatus::ONGOING_PROFILING)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="text-sm font-semibold text-amber-900">Ongoing Profiling</div>

                        <p class="mt-1 text-xs leading-5 text-amber-800">
                            Review the project profile and supporting information. Submit to TSSD Evaluation only when
                            profiling is complete.
                        </p>

                        @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                            <form method="POST" action="{{ route('projects.evaluation.start', $project) }}"
                                class="mt-4">
                                @csrf

                                <button type="submit"
                                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                    Submit to TSSD Evaluation
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                {{-- TSSD Evaluation / Compliance --}}

                @if (in_array(
                        $project->status,
                        [\App\Enums\ProjectStatus::TSSD_EVALUATION, \App\Enums\ProjectStatus::FOR_COMPLIANCE],
                        true))

                    @if ($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE)

                        @php
                            $latestEvaluation = $project->evaluations
                                ->where('result', 'for_compliance')
                                ->sortByDesc('evaluated_at')
                                ->first();

                            $complianceAgingDays = $latestEvaluation?->evaluated_at
                                ? (int) $latestEvaluation->evaluated_at
                                    ->copy()
                                    ->startOfDay()
                                    ->diffInDays(now()->startOfDay())
                                : 0;
                        @endphp

                        <div class="mb-5 overflow-hidden rounded-xl border border-amber-200 bg-amber-50">

                            <div class="border-b border-amber-200 px-4 py-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                                    <div>
                                        <div class="text-sm font-semibold text-amber-900">
                                            Project for Compliance
                                        </div>

                                        <p class="mt-1 text-xs leading-5 text-amber-700">
                                            Record the Date of Compliance. Saving automatically moves this project to For
                                            Approval.
                                        </p>
                                    </div>

                                    <div class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-right">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-amber-600">
                                            Aging
                                        </div>

                                        <div class="mt-0.5 text-lg font-bold text-amber-900">
                                            {{ number_format($complianceAgingDays) }}
                                            <span class="text-xs font-semibold">
                                                day(s)
                                            </span>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="p-4">

                                @if ($latestEvaluation)
                                    <div class="grid gap-4 lg:grid-cols-2">

                                        <div class="rounded-lg border border-amber-200 bg-white p-4">
                                            <div class="text-xs font-semibold text-amber-800">
                                                Findings
                                            </div>

                                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                                                {{ $latestEvaluation->findings ?: '—' }}
                                            </p>
                                        </div>

                                        <div class="rounded-lg border border-amber-200 bg-white p-4">
                                            <div class="text-xs font-semibold text-amber-800">
                                                Required Documents
                                            </div>

                                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                                                {{ $latestEvaluation->required_documents ?: '—' }}
                                            </p>
                                        </div>

                                    </div>

                                    <div class="mt-4 text-xs text-amber-700">
                                        Compliance requested:
                                        <span class="font-semibold">
                                            {{ $latestEvaluation->evaluated_at->format('F d, Y') }}
                                        </span>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('projects.compliance.store', $project) }}"
                                    class="mt-5">

                                    @csrf

                                    <div class="grid gap-4 md:grid-cols-2 md:items-end">

                                        <div>
                                            <label for="compliance-date"
                                                class="mb-2 block text-xs font-semibold text-slate-700">
                                                Date of Compliance
                                                <span class="text-rose-600">*</span>
                                            </label>

                                            <input id="compliance-date" name="compliance_date" type="date" required
                                                min="{{ $latestEvaluation?->evaluated_at?->toDateString() }}"
                                                value="{{ old('compliance_date', now()->toDateString()) }}"
                                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                            @error('compliance_date')
                                                <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>

                                    </div>

                                    <div class="mt-4">
                                        <label for="compliance-remarks"
                                            class="mb-2 block text-xs font-semibold text-slate-700">
                                            Compliance Remarks
                                            <span class="text-rose-600">*</span>
                                        </label>

                                        <textarea id="compliance-remarks" name="compliance_remarks" rows="3" required maxlength="5000"
                                            placeholder="State what was submitted to comply, e.g. the specific documents provided for each required item above..."
                                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('compliance_remarks') }}</textarea>

                                        <p class="mt-1 text-[11px] leading-4 text-amber-700">
                                            Describe the complied documents against the Required Documents listed above.
                                            This
                                            is kept on record in the project's evaluation history and Compliance History.
                                        </p>

                                        @error('compliance_remarks')
                                            <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div class="mt-4 flex md:justify-end">
                                        <button type="submit"
                                            class="h-10 rounded-lg bg-amber-700 px-5 text-sm font-semibold text-white hover:bg-amber-800">
                                            Save Compliance
                                        </button>
                                    </div>

                                </form>

                            </div>

                        </div>

                    @endif

                    @if ($project->status === \App\Enums\ProjectStatus::TSSD_EVALUATION)
                        <form method="POST" action="{{ route('projects.evaluation.store', $project) }}"
                            class="space-y-4">

                            @csrf

                            <div class="grid gap-4 md:grid-cols-2">

                                <div>

                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Evaluation Result
                                    </label>

                                    <select id="evaluation-result" name="result" required
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                        <option value="">
                                            Select result
                                        </option>

                                        <option value="for_compliance" @selected(old('result') === 'for_compliance')>
                                            For Compliance
                                        </option>

                                        <option value="for_approval" @selected(old('result') === 'for_approval')>
                                            For Approval
                                        </option>

                                    </select>

                                </div>

                            </div>

                            <div id="for-approval-note"
                                class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                                <div class="text-xs font-semibold text-emerald-900">
                                    Ready for Approval
                                </div>

                                <p class="mt-1 text-xs leading-5 text-emerald-700">
                                    Findings and Required Documents are not required when the evaluation result is For
                                    Approval.
                                </p>
                            </div>

                            <div id="compliance-fields" class="space-y-4">
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

                                    <textarea id="evaluation-findings" name="findings" rows="3"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        placeholder="State the findings that require compliance...">{{ old('findings') }}</textarea>

                                </div>

                                <div>

                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Required Documents
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <textarea id="evaluation-required-documents" name="required_documents" rows="3"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                        placeholder="List the documentary requirements to be complied with...">{{ old('required_documents') }}</textarea>

                                </div>
                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

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

                {{-- For Approval --}}

                @if ($project->status === \App\Enums\ProjectStatus::FOR_APPROVAL)
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">

                        <div class="text-sm font-semibold text-emerald-800">
                            Project ready for approval
                        </div>

                        <p class="mt-1 text-xs leading-5 text-emerald-700">
                            Assign the official Project Code during approval. One project receives one Project Code, and
                            that code cannot be reused by another project.
                            Saving approval automatically updates the project status to Approved.
                        </p>

                    </div>

                    <form method="POST" action="{{ route('projects.approval.store', $project) }}"
                        class="mt-5 space-y-4">

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
                                    Official Project Code
                                    <span class="text-rose-600">*</span>
                                </label>

                                <input name="project_code" type="text" required autocomplete="off"
                                    value="{{ old('project_code') }}" placeholder="Example: TUPAD-ALB-2026-001"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-semibold uppercase tracking-wide">

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

                {{-- Approved --}}

                @if (in_array(
                        $project->status,
                        [
                            \App\Enums\ProjectStatus::APPROVED,
                            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                            \App\Enums\ProjectStatus::FOR_PAYMENT,
                            \App\Enums\ProjectStatus::COMPLETED,
                        ],
                        true) && $project->approval)
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

        @if ($project->evaluations->isNotEmpty())

            <section id="evaluation-history" data-workspace-panel="workflow"
                class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">

                    <h2 class="text-sm font-semibold text-slate-900">
                        Evaluation History
                    </h2>

                </div>

                <div class="overflow-x-auto">

                    <table class="tupad-system-table min-w-full">

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

                                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                    Compliance Remarks
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
                                        {{ $evaluation->evaluator?->name ?? 'System' }}
                                    </td>

                                    <td class="px-5 py-4">

                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $evaluation->result === 'for_approval' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                            {{ $evaluation->result === 'for_approval' ? 'For Approval' : 'For Compliance' }}
                                        </span>

                                        @if ($evaluation->result === 'for_compliance')
                                            <div class="mt-2 text-[10px] leading-4 text-slate-500">
                                                @if ($evaluation->isComplied())
                                                    Compliance:
                                                    <span class="font-semibold text-slate-700">
                                                        {{ $evaluation->compliance_date->format('M d, Y') }}
                                                    </span>
                                                    · Aging:
                                                    <span class="font-semibold text-slate-700">
                                                        {{ number_format($evaluation->agingDays()) }} day(s)
                                                    </span>
                                                @else
                                                    Compliance pending
                                                    · Aging:
                                                    <span class="font-semibold text-amber-700">
                                                        {{ number_format($evaluation->agingDays()) }} day(s)
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                    </td>

                                    <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                        {{ $evaluation->findings ?: '—' }}
                                    </td>

                                    <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                        {{ $evaluation->required_documents ?: '—' }}
                                    </td>

                                    <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                        {{ $evaluation->compliance_remarks ?: '—' }}
                                    </td>

                                </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            </section>

        @endif

        {{-- Implementation Preparation --}}

        @if (in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                ],
                true) && $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION)

            <section id="implementation" data-workspace-panel="workflow"
                class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                        <div>

                            <h2 class="text-sm font-semibold text-slate-900">
                                Project Implementation
                            </h2>

                            <p class="mt-1 text-xs text-slate-500">
                                Direct Administration workflow: Insurance, PPE, Notice to Proceed, Orientation, and Work
                                Period.
                            </p>

                        </div>

                        @if ($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)
                            <span
                                class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                Ready for Implementation
                            </span>
                        @endif

                    </div>

                </div>

                @php
                    $preparationItems = [
                        'Insurance' => (bool) $project->insuranceEnrollment,
                        'PPE Delivery' => $project->ppeDeliveries->isNotEmpty(),
                        'Notice to Proceed' => (bool) $project->noticeToProceed,
                        'Orientation' => (bool) $project->orientation,
                        'Implementation Period' => (bool) $project->implementation,
                    ];

                    $completedPreparation = collect($preparationItems)->filter()->count();

                    $preparationPercent =
                        count($preparationItems) > 0 ? ($completedPreparation / count($preparationItems)) * 100 : 0;
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

                        {{-- Implementation Requirements --}}

                        <div class="xl:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white">

                            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                                <h3 class="text-sm font-semibold text-slate-900">
                                    Implementation Requirements
                                </h3>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    Record Insurance, PPE, and Notice to Proceed separately for this Direct Administration
                                    project. Each saves independently &mdash; once all three are on record, the status
                                    automatically becomes For Implementation.
                                </p>
                            </div>

                            <div class="grid gap-5 p-5 xl:grid-cols-3">

                                {{-- Insurance Enrollment --}}

                                <form method="POST" action="{{ route('projects.implementation.insurance', $project) }}"
                                    class="rounded-xl border border-slate-200 p-5">
                                    @csrf
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                                Requirement 1
                                            </div>

                                            <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                                Insurance Enrollment
                                            </h4>
                                        </div>

                                        @if ($project->insuranceEnrollment)
                                            <span
                                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
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

                                            <input name="date_enrolled" type="date" required
                                                value="{{ old('date_enrolled', $project->insuranceEnrollment?->date_enrolled?->format('Y-m-d')) }}"
                                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                            @error('date_enrolled')
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

                                                <div
                                                    class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                                    <span class="font-semibold text-slate-900">
                                                        {{ number_format($project->insurance_beneficiaries ?? $project->beneficiaries_total) }}
                                                    </span>

                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
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

                                                <div
                                                    class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                                    <span class="font-semibold text-slate-900">
                                                        ₱{{ number_format($project->insurance_total, 2) }}
                                                    </span>

                                                    <span
                                                        class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
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

                                            <select name="payment_mode" required
                                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                                <option value="">
                                                    Select mode
                                                </option>

                                                <option value="voucher" @selected(old('payment_mode', $project->insuranceEnrollment?->payment_mode) === 'voucher')>
                                                    Voucher
                                                </option>

                                                <option value="ca" @selected(old('payment_mode', $project->insuranceEnrollment?->payment_mode) === 'ca')>
                                                    CA
                                                </option>
                                            </select>

                                            @error('payment_mode')
                                                <p class="mt-1 text-xs font-medium text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
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

                                        <div>
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                                Remarks
                                            </label>

                                            <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->insuranceEnrollment?->remarks) }}</textarea>
                                        </div>

                                    </div>

                                    <button type="submit"
                                        class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                        Save Insurance Enrollment
                                    </button>
                                </form>

                                {{-- PPE Delivery --}}

                                <div class="rounded-xl border border-slate-200 p-5">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                                Requirement 2
                                            </div>

                                            <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                                PPE Delivery
                                            </h4>
                                        </div>

                                        @if ($project->ppeDeliveries->isNotEmpty())
                                            <span
                                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                                Saved
                                            </span>
                                        @endif
                                    </div>

                                    @if ($project->ppeDeliveries->isNotEmpty())
                                        <div class="mt-3 space-y-2">
                                            @foreach ($project->ppeDeliveries as $delivery)
                                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-xs font-semibold text-emerald-900">
                                                            {{ $delivery->delivery_receipt_date->format('M d, Y') }}
                                                        </span>
                                                        <span class="text-[10px] text-emerald-700">
                                                            By {{ $delivery->recorder?->name ?? 'System' }}
                                                        </span>
                                                    </div>

                                                    <ul class="mt-1.5 space-y-0.5 text-[11px] leading-4 text-slate-700">
                                                        @forelse($delivery->items as $deliveryItem)
                                                            <li>
                                                                {{ $deliveryItem->ppeItem?->product ?? 'PPE item' }}
                                                                &times; {{ number_format($deliveryItem->quantity) }}
                                                            </li>
                                                        @empty
                                                            <li class="text-slate-400">{{ $delivery->ppe_provided }}</li>
                                                        @endforelse
                                                    </ul>

                                                    @if ($delivery->remarks)
                                                        <p class="mt-1.5 text-[11px] leading-4 text-slate-500">
                                                            {{ $delivery->remarks }}
                                                        </p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('projects.implementation.ppe', $project) }}"
                                        class="ppe-delivery-form mt-4 border-t border-slate-200 pt-4">
                                        @csrf

                                        <div class="text-xs font-semibold text-slate-700">
                                            Add Delivery Receipt
                                        </div>

                                        <div class="mt-3">
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                                Date of Delivery Receipt
                                            </label>

                                            <input name="delivery_receipt_date" type="date" required
                                                value="{{ old('delivery_receipt_date') }}"
                                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                            @error('delivery_receipt_date')
                                                <p class="mt-1 text-xs font-medium text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>

                                        <div class="mt-4">
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                                PPE Provided
                                            </label>

                                            @if ($project->ppeItems->isNotEmpty())
                                                <p class="mb-2 text-[11px] leading-4 text-slate-500">
                                                    Click every PPE item included in this receipt, then enter the quantity
                                                    delivered for each.
                                                </p>

                                                <div class="grid gap-2 sm:grid-cols-2">
                                                    @foreach ($project->ppeItems as $ppeItem)
                                                        @php
                                                            $remaining = $ppeItem->remainingDeliverableQuantity();
                                                        @endphp

                                                        <div
                                                            class="ppe-item-toggle rounded-lg border border-slate-300 p-2.5 {{ $remaining <= 0 ? 'opacity-50' : '' }}">
                                                            <button type="button"
                                                                class="ppe-item-button flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs font-semibold text-slate-700"
                                                                data-item-id="{{ $ppeItem->id }}"
                                                                {{ $remaining <= 0 ? 'disabled' : '' }}>
                                                                <span class="min-w-0 truncate">
                                                                    {{ $ppeItem->product }}
                                                                    <span
                                                                        class="font-normal text-slate-400">({{ $ppeItem->ppe_type->label() }})</span>
                                                                </span>
                                                                <span
                                                                    class="ppe-item-check hidden text-emerald-600">&check;</span>
                                                            </button>

                                                            <div class="mt-0.5 px-2 text-[10px] text-slate-400">
                                                                Remaining: {{ number_format($remaining) }} /
                                                                {{ number_format($ppeItem->plannedQuantity()) }}
                                                            </div>

                                                            <div class="ppe-item-quantity mt-2 hidden px-2">
                                                                <input type="hidden"
                                                                    name="items[{{ $ppeItem->id }}][ppe_item_id]"
                                                                    value="{{ $ppeItem->id }}" disabled>
                                                                <input type="number"
                                                                    name="items[{{ $ppeItem->id }}][quantity]"
                                                                    min="1" max="{{ $remaining }}"
                                                                    placeholder="Quantity" disabled
                                                                    class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
                                                                @error("items.{$ppeItem->id}.quantity")
                                                                    <p class="mt-1 text-[10px] font-medium text-red-600">
                                                                        {{ $message }}
                                                                    </p>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>

                                                @error('items')
                                                    <p class="mt-2 text-xs font-medium text-red-600">
                                                        {{ $message }}
                                                    </p>
                                                @enderror
                                            @else
                                                <div
                                                    class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-[11px] leading-4 text-slate-500">
                                                    No PPE items were declared for this project, so no items need to be
                                                    selected here. Recording the receipt date is sufficient.
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-4">
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                                Remarks
                                            </label>

                                            <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                                        </div>

                                        <button type="submit"
                                            class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                            Add Delivery Receipt
                                        </button>
                                    </form>
                                </div>

                                <script>
                                    (() => {
                                        document
                                            .querySelectorAll('.ppe-delivery-form .ppe-item-toggle')
                                            .forEach(wrapper => {
                                                const button = wrapper.querySelector('.ppe-item-button');
                                                const check = wrapper.querySelector('.ppe-item-check');
                                                const quantityBlock = wrapper.querySelector('.ppe-item-quantity');

                                                if (!button || button.disabled || !quantityBlock) return;

                                                const inputs = quantityBlock.querySelectorAll('input');

                                                button.addEventListener('click', () => {
                                                    const selected = !wrapper.classList.contains('ppe-item-selected');

                                                    wrapper.classList.toggle('ppe-item-selected', selected);
                                                    wrapper.classList.toggle('border-blue-400', selected);
                                                    wrapper.classList.toggle('bg-blue-50', selected);
                                                    button.classList.toggle('text-blue-800', selected);
                                                    check.classList.toggle('hidden', !selected);
                                                    quantityBlock.classList.toggle('hidden', !selected);

                                                    inputs.forEach(input => {
                                                        input.disabled = !selected;
                                                    });

                                                    if (selected) {
                                                        quantityBlock.querySelector('input[type="number"]')?.focus();
                                                    }
                                                });
                                            });
                                    })();
                                </script>

                                {{-- Notice to Proceed --}}

                                <form method="POST" action="{{ route('projects.implementation.ntp', $project) }}"
                                    class="rounded-xl border border-slate-200 p-5">
                                    @csrf
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                                Requirement 3
                                            </div>

                                            <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                                Notice to Proceed
                                            </h4>
                                        </div>

                                        @if ($project->noticeToProceed)
                                            <span
                                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                                Saved
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-4 grid gap-4">
                                        <div>
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                                Date &amp; Time Issued
                                            </label>

                                            <input name="date_issued" type="datetime-local" required
                                                value="{{ old('date_issued', $project->noticeToProceed?->date_issued?->format('Y-m-d\TH:i')) }}"
                                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                            @error('date_issued')
                                                <p class="mt-1 text-xs font-medium text-red-600">
                                                    {{ $message }}
                                                </p>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                                Date &amp; Time Released
                                            </label>

                                            <input name="date_released" type="datetime-local" required
                                                value="{{ old('date_released', $project->noticeToProceed?->date_released?->format('Y-m-d\TH:i')) }}"
                                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                            @error('date_released')
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

                                        <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->noticeToProceed?->remarks) }}</textarea>
                                    </div>

                                    <button type="submit"
                                        class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                        Save Notice to Proceed
                                    </button>
                                </form>
                            </div>

                        </div>

                        @if ($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)
                            <div class="xl:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                                <div class="text-xs font-semibold text-emerald-900">
                                    Pre-Implementation Requirements Complete
                                </div>
                                <p class="mt-1 text-xs leading-5 text-emerald-800">
                                    The project is now For Implementation. Record the Orientation and Work Period.
                                    Once both are complete, the actual date controls the automatic implementation status.
                                </p>
                            </div>

                            {{-- Orientation --}}

                            <form method="POST" action="{{ route('projects.implementation.orientation', $project) }}"
                                class="rounded-xl border border-slate-200 p-5">

                                @csrf

                                <h3 class="text-sm font-semibold text-slate-900">
                                    Orientation
                                </h3>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2">

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Date of Orientation
                                        </label>

                                        <input name="orientation_date" type="date" required
                                            value="{{ old('orientation_date', $project->orientation?->orientation_date?->format('Y-m-d')) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        @error('orientation_date')
                                            <p class="mt-1 text-xs font-medium text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Total Beneficiaries Oriented
                                        </label>

                                        <input name="beneficiaries_oriented" type="number" min="0"
                                            max="{{ $project->beneficiaries_total }}" required
                                            value="{{ old('beneficiaries_oriented', $project->orientation?->beneficiaries_oriented) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Maximum: {{ number_format($project->beneficiaries_total) }} declared
                                            beneficiaries.
                                        </p>

                                        @error('beneficiaries_oriented')
                                            <p class="mt-1 text-xs font-medium text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Venue
                                        </label>

                                        <input name="venue" type="text" maxlength="255" required
                                            placeholder="e.g. Barangay Covered Court"
                                            value="{{ old('venue', $project->orientation?->venue) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        @error('venue')
                                            <p class="mt-1 text-xs font-medium text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Oriented By
                                        </label>

                                        <input name="oriented_by" type="text" maxlength="255" required
                                            placeholder="Name of facilitator"
                                            value="{{ old('oriented_by', $project->orientation?->oriented_by) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        @error('oriented_by')
                                            <p class="mt-1 text-xs font-medium text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror
                                    </div>

                                </div>

                                <div class="mt-4">
                                    <div class="mb-2 text-xs font-semibold text-slate-700">
                                        Program Coverage for Monthly Reporting
                                    </div>
                                    <p class="mb-3 text-[11px] leading-4 text-slate-500">
                                        Mark the beneficiary programs actually covered during this orientation. Legacy
                                        records may remain unspecified.
                                    </p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                            <input type="checkbox" name="alkansssya_conducted" value="1"
                                                @checked(old('alkansssya_conducted', $project->orientation?->alkansssya_conducted))
                                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#063b86]">
                                            <span>
                                                <span class="block text-xs font-semibold text-slate-800">AlkanSSSya</span>
                                                <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Included in
                                                    the recorded TUPAD beneficiary orientation.</span>
                                            </span>
                                        </label>
                                        <label
                                            class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                            <input type="checkbox" name="yakap_conducted" value="1"
                                                @checked(old('yakap_conducted', $project->orientation?->yakap_conducted))
                                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#063b86]">
                                            <span>
                                                <span class="block text-xs font-semibold text-slate-800">YAKAP Program for
                                                    TUPAD Beneficiaries</span>
                                                <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Included in
                                                    the recorded TUPAD beneficiary orientation.</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-4">

                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Remarks
                                    </label>

                                    <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->orientation?->remarks) }}</textarea>

                                </div>

                                <button type="submit"
                                    class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                    Save Orientation
                                </button>

                            </form>

                            {{-- Implementation Period --}}

                            <form method="POST" action="{{ route('projects.implementation.period', $project) }}"
                                class="rounded-xl border border-slate-200 p-5 xl:col-span-2">

                                @csrf

                                <h3 class="text-sm font-semibold text-slate-900">
                                    Implementation Period
                                </h3>

                                <p class="mt-1 text-xs text-slate-500">
                                    Enter the planned implementation Start Date and End Date.
                                    The approved {{ $project->number_of_days }}-day duration is shown as reference only.
                                </p>

                                <div class="mt-4 grid gap-4 md:grid-cols-2">

                                    <div>

                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Start Date
                                        </label>

                                        <input id="implementation-start-date" name="start_date" type="date" required
                                            value="{{ old('start_date', $project->implementation?->start_date?->format('Y-m-d')) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                    </div>

                                    <div>

                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            End Date
                                        </label>

                                        <input id="implementation-end-date" name="end_date" type="date" required
                                            value="{{ old('end_date', $project->implementation?->end_date?->format('Y-m-d')) }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Enter the actual planned End Date. It cannot be earlier than the Start Date.
                                        </p>

                                    </div>

                                </div>

                                <div class="mt-4">

                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Remarks
                                    </label>

                                    <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->implementation?->remarks) }}</textarea>

                                </div>

                                <button type="submit"
                                    class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                    Save Implementation Period
                                </button>

                            </form>
                        @elseif($project->status === \App\Enums\ProjectStatus::APPROVED)
                            <div class="xl:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                                <div class="text-xs font-semibold text-amber-900">
                                    Orientation and Work Period are not open yet
                                </div>
                                <p class="mt-1 text-xs leading-5 text-amber-800">
                                    Complete Insurance, PPE, and Notice to Proceed first. When all three are complete,
                                    the project automatically moves to For Implementation and scheduling becomes available.
                                </p>
                            </div>
                        @endif

                    </div>

                @endif

            </section>
        @elseif(in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                    \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                    \App\Enums\ProjectStatus::COMPLETED,
                ],
                true) && $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP)
            <section id="implementation" data-workspace-panel="workflow"
                class="scroll-mt-32 mt-5 rounded-xl border border-violet-200 bg-violet-50 p-5 {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
                <div class="text-sm font-semibold text-violet-950">
                    Through ACP Workflow
                </div>
                <p class="mt-1 text-xs leading-5 text-violet-800">
                    Through ACP uses its own payment, check-release, implementation, and liquidation workflow. Direct
                    Administration Insurance, PPE, Notice to Proceed, Post-Documentary Requirements, and Payment of Wages
                    forms apply only to Direct Administration projects.
                </p>

                @if (
                    (auth()->user()->isAdmin() || auth()->user()->isFocal()) &&
                        in_array(
                            $project->status,
                            [
                                \App\Enums\ProjectStatus::FOR_PAYMENT,
                                \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                                \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                            ],
                            true))
                    <div class="mt-4">
                        <a href="{{ route('acp-payments.show', $project) }}"
                            class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                            Open Through ACP Payment & Check Release
                        </a>
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if (
                        (auth()->user()->isAdmin() || auth()->user()->isTc()) &&
                            in_array(
                                $project->status,
                                [
                                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                                    \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                                    \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                                    \App\Enums\ProjectStatus::COMPLETED,
                                ],
                                true))
                        <a href="{{ route('acp-implementation.show', $project) }}"
                            class="inline-flex h-10 items-center rounded-lg border border-violet-300 bg-white px-4 text-sm font-semibold text-violet-800 hover:bg-violet-100">
                            Open ACP Implementation
                        </a>
                    @endif

                    @if (
                        (auth()->user()->isAdmin() || auth()->user()->isFocal()) &&
                            in_array(
                                $project->status,
                                [
                                    \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                                    \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                                    \App\Enums\ProjectStatus::COMPLETED,
                                ],
                                true))
                        <a href="{{ route('acp-liquidations.show', $project) }}"
                            class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                            Open ACP Liquidation
                        </a>
                    @endif
                </div>
            </section>

        @endif

        {{-- Authoritative Project Workflow Guide --}}
        <section id="final-workflow" data-workspace-panel="workflow"
            class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
                Authoritative {{ $project->implementation_mode->label() }} Workflow
            </div>

            @php
                $workflowStatuses = app(\App\Services\Projects\ProjectWorkflowDefinition::class)->happyPathFor(
                    $project->implementation_mode,
                );
            @endphp

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($workflowStatuses as $workflowStatus)
                    <span
                        class="inline-flex items-center rounded-full border px-3 py-1.5 text-[11px] font-semibold {{ $project->status === $workflowStatus
                            ? 'border-blue-300 bg-blue-50 text-blue-800'
                            : 'border-slate-200 bg-slate-50 text-slate-600' }}">
                        {{ $loop->iteration }}. {{ $workflowStatus->label() }}
                    </span>
                @endforeach
            </div>

            <p class="mt-3 text-[11px] leading-5 text-slate-500">
                For Compliance remains an optional TSSD evaluation branch before For Approval when deficiencies require
                corrective submission.
            </p>
        </section>

        {{-- Post-Documentary Requirements --}}

        @if (
            $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION &&
                in_array(
                    $project->status,
                    [
                        \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                        \App\Enums\ProjectStatus::FOR_PAYMENT,
                        \App\Enums\ProjectStatus::COMPLETED,
                    ],
                    true))

            <section id="post-documents" data-workspace-panel="workflow"
                class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Submission of Post-Documentary Requirements
                            </h2>

                            <p class="mt-1 text-xs text-slate-500">
                                TC/Admin records the complete post-implementation documentary submission.
                                Financial processing begins only after the required submission is recorded.
                            </p>
                        </div>

                        @if ($project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS)
                            <span
                                class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700">
                                Auto-update → For Payment
                            </span>
                        @endif
                    </div>
                </div>

                @if (
                    $project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS &&
                        (auth()->user()->isAdmin() || auth()->user()->isTc()))
                    <form method="POST" action="{{ route('projects.post-documents.store', $project) }}"
                        enctype="multipart/form-data" class="border-b border-slate-200 p-5">
                        @csrf

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Received
                                </label>

                                <input type="date" name="date_received" required
                                    value="{{ old('date_received', now()->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Forwarded to IMSD
                                </label>

                                <input type="date" name="date_forwarded_to_imsd" required
                                    value="{{ old('date_forwarded_to_imsd') }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Attachments Received
                                </label>

                                <input type="file" name="attachments[]" multiple required
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">

                                <p class="mt-1 text-[11px] text-slate-400">
                                    Select one or more files. Maximum 10 MB per attachment.
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button type="submit"
                                class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                                Save Post-Documentary Requirements
                            </button>
                        </div>
                    </form>
                @endif

                <div class="overflow-x-auto">

                    <table class="tupad-system-table min-w-full">

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
                                        {{ $document->date_forwarded_to_imsd?->format('M d, Y') ?? 'Not yet forwarded' }}
                                    </td>

                                    <td class="px-5 py-4">

                                        @if ($document->attachment_path)
                                            <a href="{{ route('projects.post-documents.download', [
                                                'project' => $project,
                                                'projectPostDocument' => $document,
                                            ]) }}"
                                                class="text-sm font-semibold text-blue-700 hover:underline">
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

                                    <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">
                                        No post-documentary requirements recorded.
                                    </td>

                                </tr>
                            @endforelse

                        </tbody>

                    </table>

                </div>

            </section>

        @endif

        {{-- Payment of Wages --}}

        @if (
            $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION &&
                in_array($project->status, [\App\Enums\ProjectStatus::FOR_PAYMENT, \App\Enums\ProjectStatus::COMPLETED], true))

            <section id="payment" data-workspace-panel="financial"
                class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Payment of Wages
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Wage obligations and disbursements are managed by the Focal/Admin account.
                    </p>
                </div>

                <div class="p-5">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="text-sm font-semibold text-blue-950">
                            Obligation and Disbursement Processing
                        </div>
                        <p class="mt-1 text-xs leading-5 text-blue-700">
                            Official project references, totals, payment tranches, and their corresponding disbursements are
                            consolidated in the Payment of Wages interface.
                        </p>

                        @if (auth()->user()->isAdmin() || auth()->user()->isFocal())
                            <a href="{{ route('payments.show', $project) }}"
                                class="mt-3 inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">
                                Manage Payment of Wages
                            </a>
                        @else
                            <p class="mt-3 text-xs font-semibold text-blue-800">
                                Waiting for Focal/Admin payment action.
                            </p>
                        @endif
                    </div>
                </div>
            </section>

        @endif
        {{-- PPE Requirements --}}

        <section id="ppe-requirements" data-workspace-panel="overview"
            class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    PPE Requirements
                </h2>

            </div>

            <div class="overflow-x-auto">

                <table class="tupad-system-table min-w-full">

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
                                Quantity
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
                                    {{ $project->term === \App\Enums\ProjectTerm::LONG_TERM ? number_format($item->quantity) : '—' }}
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

                                <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">
                                    No PPE requirement was recorded.
                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

        {{-- Beneficiary Replacement History --}}

        @if ($project->beneficiaryReplacements->isNotEmpty())
            <section data-workspace-panel="history"
                class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Beneficiary Replacement History
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Every beneficiary replacement recorded for this project, oldest information preserved even after
                        the replacement is complete.
                    </p>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($project->beneficiaryReplacements as $replacement)
                        <details class="group/replacement">
                            <summary
                                class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden hover:bg-slate-50/60">
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-slate-900">
                                        {{ $replacement->performed_at->format('F d, Y g:i A') }}
                                        &middot;
                                        {{ $replacement->removedMembers()->count() }} replaced,
                                        {{ $replacement->addedMembers()->count() }} added
                                    </div>
                                    <div class="mt-1 max-w-2xl truncate text-[11px] text-slate-500">
                                        {{ $replacement->reason }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-[10px] font-semibold">
                                    <span class="text-slate-400">
                                        By {{ $replacement->performer?->name ?? 'System' }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-500">
                                        Expand / Collapse
                                    </span>
                                </div>
                            </summary>

                            <div class="border-t border-slate-200 bg-slate-50/60 px-5 py-4">

                                <div class="grid gap-4 md:grid-cols-2">

                                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                            Original Beneficiaries
                                        </div>
                                        <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                            @forelse($replacement->removedMembers()->with('beneficiary')->get() as $member)
                                                <li>{{ $member->beneficiary?->full_name ?? 'Unknown beneficiary' }}</li>
                                            @empty
                                                <li class="text-slate-400">None recorded.</li>
                                            @endforelse
                                        </ul>
                                    </div>

                                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                            Replacement Beneficiaries
                                        </div>
                                        <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                            @forelse($replacement->addedMembers()->with('beneficiary')->get() as $member)
                                                <li>{{ $member->beneficiary?->full_name ?? 'Unknown beneficiary' }}</li>
                                            @empty
                                                <li class="text-slate-400">None recorded.</li>
                                            @endforelse
                                        </ul>
                                    </div>

                                </div>

                                @if ($replacement->hasAnyDetailChange())
                                    <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">
                                            Updated Details
                                        </div>
                                        <ul class="mt-2 space-y-1 text-xs text-blue-900">
                                            @if ($replacement->insurance_beneficiaries_changed)
                                                <li>
                                                    Insurance Beneficiaries: Changed
                                                    ({{ number_format($replacement->insurance_beneficiaries_before) }}
                                                    &rarr;
                                                    {{ number_format($replacement->insurance_beneficiaries_after) }})
                                                </li>
                                            @endif
                                            @if ($replacement->total_project_cost_changed)
                                                <li>
                                                    Total Project Cost: Changed
                                                    (₱{{ number_format($replacement->total_project_cost_before, 2) }}
                                                    &rarr;
                                                    ₱{{ number_format($replacement->total_project_cost_after, 2) }})
                                                </li>
                                            @endif
                                            @if ($replacement->beneficiary_address_changed)
                                                <li>Beneficiary Address: Changed</li>
                                            @endif
                                        </ul>
                                    </div>
                                @else
                                    <div class="mt-4 text-[11px] leading-4 text-slate-400">
                                        Updated Details: Insurance Beneficiaries &mdash; No Change &middot;
                                        Total Project Cost &mdash; No Change &middot;
                                        Beneficiary Address &mdash; No Change
                                    </div>
                                @endif

                            </div>
                        </details>
                    @endforeach
                </div>

            </section>
        @endif

        {{-- Insurance Claim History --}}

        @if ($project->insuranceClaims->isNotEmpty())
            <section data-workspace-panel="history"
                class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Insurance Claim History
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Every insurance claim (incident report) recorded for this project.
                    </p>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach ($project->insuranceClaims as $claim)
                        <details class="group/claim">
                            <summary
                                class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden hover:bg-slate-50/60">
                                <div class="min-w-0">
                                    <div class="text-xs font-semibold text-slate-900">
                                        {{ $claim->incident_date->format('F d, Y') }}
                                        @if ($claim->incident_time)
                                            &middot; approx. {{ $claim->incident_time->format('g:i A') }}
                                        @endif
                                        &middot;
                                        {{ $claim->beneficiaries->count() }}
                                        {{ $claim->beneficiaries->count() === 1 ? 'beneficiary' : 'beneficiaries' }} injured
                                    </div>
                                    <div class="mt-1 max-w-2xl truncate text-[11px] text-slate-500">
                                        {{ $claim->incident_description }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 text-[10px] font-semibold">
                                    <span class="text-slate-400">
                                        Reported by {{ $claim->reporter?->name ?? 'System' }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-500">
                                        Expand / Collapse
                                    </span>
                                </div>
                            </summary>

                            <div class="border-t border-slate-200 bg-slate-50/60 px-5 py-4">

                                <div class="rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-red-700">
                                        What Happened
                                    </div>
                                    <p class="mt-2 whitespace-pre-line text-xs leading-5 text-slate-700">
                                        {{ $claim->incident_description }}
                                    </p>
                                </div>

                                <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-red-700">
                                        Injured Beneficiary(ies)
                                    </div>
                                    <ul class="mt-2 space-y-1.5 text-xs text-slate-700">
                                        @foreach ($claim->beneficiaries as $injured)
                                            <li>
                                                <span class="font-semibold text-slate-900">{{ $injured->full_name }}</span>
                                                <span class="text-slate-500">&mdash; {{ $injured->address }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                            </div>
                        </details>
                    @endforeach
                </div>

            </section>
        @endif

        {{-- Project Status History --}}

        <section id="history" data-workspace-panel="history"
            class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Project Status History
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Historical workflow transitions for this project.
                </p>

            </div>

            <div class="overflow-x-auto">

                <table class="tupad-system-table min-w-full">

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

                        @forelse($project
                                ->statusHistory
                                ->sortByDesc('changed_at')
                            as $history)
                            <tr>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                                    {{ $history->changed_at->format('M d, Y g:i A') }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $history->from_status?->label() ?? 'Created' }}
                                </td>

                                <td class="px-5 py-4">

                                    <span
                                        class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
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

                                <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">
                                    No status history has been recorded yet.
                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>



    </div>

@endsection
