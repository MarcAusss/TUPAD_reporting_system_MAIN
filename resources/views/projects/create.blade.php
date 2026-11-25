@extends('layouts.app')

@section('title', 'Add Project')

@section('content')

    <div class="mx-auto max-w-330">

        <x-page-header eyebrow="Project Management" title="Add Official Project" :breadcrumbs="[['label' => 'Projects', 'url' => route('projects.index')], ['label' => 'Create Project']]"
            description="Encode the official project profile in six short steps, then review before saving. Location fields load in sequence, and Term, Wages, Insurance, PPE, and Total Project Cost are calculated as you encode.">
            <x-slot:actions>
                <a href="{{ route('projects.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
            </x-slot:actions>
        </x-page-header>

        @if ($allocations->isEmpty())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                <div class="text-xs font-bold uppercase tracking-widest text-red-700">
                    No Allocation Available
                </div>

                <p class="mt-1 text-xs leading-5 text-red-800">
                    {{ $exhaustedAllocationCount > 0
                        ? "Every ADL allocation you can access ({$exhaustedAllocationCount}) has no remaining balance."
                        : 'No ADL allocation is available to you yet.' }}
                    A new official project cannot be encoded until an allocation with remaining budget exists.
                </p>
            </div>
        @elseif($exhaustedAllocationCount > 0)
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                <div class="text-xs font-bold uppercase tracking-widest text-amber-700">
                    Some Allocations Hidden
                </div>

                <p class="mt-1 text-xs leading-5 text-amber-800">
                    {{ $exhaustedAllocationCount }}
                    ADL allocation{{ $exhaustedAllocationCount === 1 ? '' : 's' }}
                    with no remaining balance {{ $exhaustedAllocationCount === 1 ? 'is' : 'are' }} not shown in the
                    Allocation dropdown below, since {{ $exhaustedAllocationCount === 1 ? 'it' : 'they' }} can no
                    longer fund a new project.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('projects.store') }}" class="space-y-5" id="projectForm"
            data-wizard data-warn-unsaved data-wizard-errors='@json($errors->keys())'>

            @csrf

            <input type="hidden" name="exact_barangay_allocation" value="1">

            {{-- Step navigation (rendered by resources/js/form-wizard.js) --}}
            <div class="sticky top-21 z-20 rounded-xl border border-slate-200 bg-white/95 p-2 shadow-sm backdrop-blur">
                <div data-wizard-stepper></div>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-2 pt-2 mt-2 text-xs text-slate-500">
                    <p data-wizard-progress class="font-semibold text-slate-700">
                        Complete each section, then save.
                    </p>
                    <p>Fields marked <span class="font-bold text-rose-600">*</span> are required. Totals update as you type.</p>
                </div>
            </div>

            <div class="min-w-0 space-y-5">

                <div data-wizard-step data-wizard-title="Allocation & Profile" class="space-y-5">

                    {{-- ADL / Allocation --}}
                    <section id="allocation" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                ADL Allocation
                            </h2>
                        </div>

                        <div class="p-6">

                            <label for="adlAllocationSelect" class="mb-2 block text-sm font-semibold text-slate-700">
                                Allocation
                            </label>

                            <select id="adlAllocationSelect" name="adl_allocation_id" required data-wizard-summary="ADL allocation"
                                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                                <option value="">
                                    Select allocation
                                </option>

                                @foreach ($allocations as $allocation)
                                    @php($allocationFinance = $allocationFinancials->get($allocation->id))
                                    <option value="{{ $allocation->id }}"
                                        data-adl-number="{{ $allocation->adl->adl_number }}"
                                        data-location="{{ $allocation->location }}"
                                        data-allocation="{{ number_format(($allocationFinance['allocation_cents'] ?? 0) / 100, 2, '.', '') }}"
                                        data-utilized="{{ number_format(($allocationFinance['utilized_cents'] ?? 0) / 100, 2, '.', '') }}"
                                        data-remaining="{{ number_format(max(0, $allocationFinance['remaining_cents'] ?? 0) / 100, 2, '.', '') }}"
                                        @selected(old('adl_allocation_id') == $allocation->id)>
                                        {{ $allocation->adl->adl_number }}
                                        —
                                        {{ $allocation->location }}
                                        —
                                        ₱{{ number_format($allocation->amount, 2) }}
                                    </option>
                                @endforeach

                            </select>

                            <p class="mt-2 text-xs text-slate-500">
                                Select the ADL allocation that will fund this official project.
                                Fund Sponsor and Partner are encoded below by the TUPAD Coordinator.
                            </p>

                            <div id="allocationBudgetPanel"
                                class="mt-4 hidden rounded-xl border border-blue-200 bg-blue-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-bold uppercase tracking-widest text-blue-700">Selected
                                            Allocation Capacity</div>
                                        <div id="allocationBudgetLabel" class="mt-1 text-sm font-semibold text-blue-950">
                                        </div>
                                    </div>
                                    <div id="allocationBudgetState"
                                        class="rounded-full bg-white px-3 py-1 text-[11px] font-bold uppercase text-blue-700">
                                        Available</div>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-lg bg-white px-3 py-3">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Allocation</div>
                                        <div id="allocationOriginalAmount" class="mt-1 text-sm font-bold text-slate-900">
                                            ₱0.00</div>
                                    </div>
                                    <div class="rounded-lg bg-white px-3 py-3">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Already Utilized
                                        </div>
                                        <div id="allocationUtilizedAmount" class="mt-1 text-sm font-bold text-slate-900">
                                            ₱0.00</div>
                                    </div>
                                    <div class="rounded-lg bg-white px-3 py-3">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Remaining Available
                                        </div>
                                        <div id="allocationRemainingAmount" class="mt-1 text-sm font-bold text-emerald-700">
                                            ₱0.00</div>
                                    </div>
                                </div>
                                <div id="allocationProjectCostMessage" class="mt-3 text-xs font-medium text-blue-800">Enter
                                    project cost inputs to compare against the remaining allocation.</div>
                            </div>

                        </div>

                    </section>

                    {{-- General --}}
                    <section id="general" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                General Information
                            </h2>
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-2">

                            <div>

                                <label for="date_received" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Date Received
                                </label>

                                <input id="date_received" name="date_received" type="date"
                                    value="{{ old('date_received', now()->format('Y-m-d')) }}" required
                                    data-wizard-summary="Date received"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label for="project_title" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Project Title
                                </label>

                                <input id="project_title" name="project_title" value="{{ old('project_title') }}" required
                                    data-wizard-summary="Project title"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div class="md:col-span-2">

                                <label for="nature_of_work" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Nature of Work
                                </label>

                                <textarea id="nature_of_work" name="nature_of_work" rows="3" required
                                    data-wizard-summary="Nature of work"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('nature_of_work') }}</textarea>

                            </div>

                        </div>

                    </section>

                </div>

                <div data-wizard-step data-wizard-title="Funding & TEVS" class="space-y-5">

                    {{-- Funding Information --}}

                    <section id="funding" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Funding Information
                            </h2>

                            <p class="mt-1 text-xs text-slate-500">
                                Select Sponsor and Partner values maintained by Focal from the ADL breakdown.
                                If the needed value is not listed, choose Other and enter a project-specific value.
                            </p>
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-2">

                            <div>
                                <label for="fund_sponsor" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Fund Sponsor
                                </label>

                                <select id="fund_sponsor" name="fund_sponsor" required data-wizard-summary="Fund sponsor" data-wizard-summary-other="#fund_sponsor_other"
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">Select fund sponsor</option>

                                    @foreach ($fundSponsorOptions as $option)
                                        <option value="{{ $option }}" @selected(old('fund_sponsor') === $option)>
                                            {{ $option }}
                                        </option>
                                    @endforeach

                                    <option value="__other__" @selected(old('fund_sponsor') === '__other__')>
                                        Other — specify below
                                    </option>
                                </select>

                                @if ($fundSponsorOptions->isEmpty())
                                    <p class="mt-1 text-[11px] leading-4 text-amber-600">
                                        No Focal-maintained sponsor reference exists yet. Select Other if needed.
                                    </p>
                                @endif
                            </div>

                            <div id="fundSponsorOtherWrap"
                                class="{{ old('fund_sponsor') === '__other__' ? '' : 'hidden' }}">
                                <label for="fund_sponsor_other" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Other Fund Sponsor
                                </label>

                                <input id="fund_sponsor_other" name="fund_sponsor_other"
                                    value="{{ old('fund_sponsor_other') }}" maxlength="255"
                                    placeholder="Enter sponsor not listed above"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Project-specific only. Focal must add it to an ADL breakdown before it becomes reusable.
                                </p>
                            </div>

                            <div class="md:col-span-2">
                                <div id="partnerProgramRow" class="grid gap-5 sm:grid-cols-2">

                                    <div>
                                        <label for="partner" class="mb-2 block text-sm font-semibold text-slate-700">
                                            Partner
                                        </label>

                                        <select id="partner" name="partner" required data-wizard-summary="Partner" data-wizard-summary-other="#partner_other"
                                            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                            <option value="">Select partner</option>

                                            @foreach ($partnerOptions as $option)
                                                <option value="{{ $option }}" @selected(old('partner') === $option)>
                                                    {{ $option }}
                                                </option>
                                            @endforeach

                                            <option value="__other__" @selected(old('partner') === '__other__')>
                                                Other — specify below
                                            </option>
                                        </select>

                                        @if ($partnerOptions->isEmpty())
                                            <p class="mt-1 text-[11px] leading-4 text-amber-600">
                                                No Focal-maintained partner reference exists yet. Select Other if needed.
                                            </p>
                                        @endif
                                    </div>

                                    <div>
                                        <label for="program" class="mb-2 block text-sm font-semibold text-slate-700">
                                            Program <span class="font-normal text-slate-400">(optional)</span>
                                        </label>

                                        <input id="program" name="program" value="{{ old('program') }}"
                                            maxlength="255" placeholder="e.g. TUPAD"
                                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Encoded by the TUPAD Coordinator for this project, if applicable.
                                        </p>
                                    </div>

                                    <div id="partnerOtherWrap"
                                        class="{{ old('partner') === '__other__' ? '' : 'hidden' }}">
                                        <label for="partner_other"
                                            class="mb-2 block text-sm font-semibold text-slate-700">
                                            Other Partner
                                        </label>

                                        <input id="partner_other" name="partner_other"
                                            value="{{ old('partner_other') }}" maxlength="255"
                                            placeholder="Enter partner not listed above"
                                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Project-specific only. It does not automatically become an official reusable
                                            reference.
                                        </p>
                                    </div>

                                </div>
                            </div>

                        </div>

                    </section>

                    {{-- Project Series / TEVS Verification --}}
                    <section id="verification" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Project Series & TEVS Verification
                            </h2>

                            <p class="mt-1 text-xs text-slate-500">
                                Encode the project series and TEVS verification details required for the official project
                                record.
                            </p>
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-2">

                            <div>
                                <label for="project_series" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Project Series
                                </label>

                                <input id="project_series" name="project_series" value="{{ old('project_series') }}" data-wizard-summary="Project series"
                                    required maxlength="100" placeholder="e.g. Regular TUPAD / Series 2026-01"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                            <div>
                                <label for="project_series_remarks"
                                    class="mb-2 block text-sm font-semibold text-slate-700">
                                    Remarks for Project Series
                                </label>

                                <input id="project_series_remarks" name="project_series_remarks"
                                    value="{{ old('project_series_remarks') }}" maxlength="3000"
                                    placeholder="Optional remarks"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                            <div>
                                <label for="tevs_date_verified" class="mb-2 block text-sm font-semibold text-slate-700">
                                    TEVS Date Verified
                                </label>

                                <input id="tevs_date_verified" name="tevs_date_verified" type="date" data-wizard-summary="TEVS date verified"
                                    value="{{ old('tevs_date_verified') }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                            <div>
                                <label for="tevs_remarks" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Remarks for TEVS Date Verified
                                </label>

                                <input id="tevs_remarks" name="tevs_remarks" value="{{ old('tevs_remarks') }}"
                                    maxlength="3000" placeholder="Optional TEVS remarks"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>

                        </div>

                    </section>

                </div>

                <div data-wizard-step data-wizard-title="Location" class="space-y-5">

                    {{-- Project Location --}}

                    <section id="location" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                                <div>
                                    <h2 class="text-sm font-semibold text-slate-900">
                                        Project Location
                                    </h2>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Select one Bicol province, then add all target locations covered by this project.
                                    </p>
                                </div>

                                <span
                                    class="rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold uppercase tracking-[0.08em] text-blue-700">
                                    Region V only
                                </span>

                            </div>

                        </div>

                        <div class="grid gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_320px]">

                            <div>

                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">

                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-900 text-xs font-bold text-white">1</span>
                                        <div class="text-sm font-semibold text-slate-900">Select Province</div>
                                    </div>

                                    <div class="mt-4 max-w-xl">

                                        <label for="province_id" class="mb-2 block text-sm font-semibold text-slate-700">
                                            Province
                                        </label>

                                        <select id="province_id" name="province_id" required data-wizard-summary="Province"
                                            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                            <option value="">Select Bicol province</option>

                                            @foreach ($provinces as $province)
                                                <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>
                                                    {{ $province->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                    </div>

                                    <div
                                        class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-800">
                                        Districts, municipalities/cities, and barangays are filtered to the selected
                                        province.
                                    </div>

                                </div>

                                <div class="mt-5">

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                                        <div>

                                            <div class="flex items-center gap-3">
                                                <span
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-900 text-xs font-bold text-white">2</span>
                                                <h3 class="text-sm font-semibold text-slate-900">Target Locations</h3>
                                            </div>

                                            <p class="mt-1 pl-10 text-xs text-slate-500">
                                                Add municipalities/cities from different districts inside the selected
                                                province.
                                            </p>

                                            <p class="mt-1 pl-10 text-[11px] font-medium leading-5 text-blue-700">
                                                For every selected barangay, encode its exact Total beneficiary
                                                allocation. The barangay totals automatically become the project's Total
                                                Beneficiaries below.
                                            </p>

                                        </div>

                                        <button id="addProjectLocation" type="button" disabled
                                            class="inline-flex h-10 items-center justify-center rounded-lg border border-blue-300 bg-white px-4 text-xs font-semibold text-blue-800 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            + Add Another Location
                                        </button>

                                    </div>

                                    <div id="projectLocations" class="mt-4 space-y-4"></div>

                                </div>

                                {{-- Filled automatically from the locations above; both stay editable. --}}
                                <div class="mt-4 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 lg:grid-cols-2" data-location-derived>
                                    <div>
                                        <label for="income_class" class="mb-2 block text-sm font-semibold text-slate-700">
                                            Income Class
                                        </label>
                                        <input id="income_class" name="income_class" maxlength="100" list="incomeClassOptions"
                                            value="{{ old('income_class') }}" autocomplete="off"
                                            data-auto-value="" placeholder="Filled from the selected municipality / city"
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <datalist id="incomeClassOptions">
                                            @foreach (['1st Class', '2nd Class', '3rd Class', '4th Class', '5th Class', '6th Class'] as $incomeClass)
                                                <option value="{{ $incomeClass }}"></option>
                                            @endforeach
                                        </datalist>
                                        <p id="incomeClassHint" class="mt-1 text-[11px] leading-4 text-slate-500">
                                            From the municipality / city of the beneficiary locations. You can edit it.
                                        </p>
                                        @error('income_class')
                                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="local_chief_executive" class="mb-2 block text-sm font-semibold text-slate-700">
                                            Local Chief Executive
                                        </label>
                                        <input id="local_chief_executive" name="local_chief_executive" maxlength="255"
                                            value="{{ old('local_chief_executive') }}" autocomplete="off"
                                            data-auto-value="" placeholder="Filled from Mayors & Governors"
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <p id="lceHint" class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Mayor of each municipality / city, and the Governor. You can edit it.
                                        </p>
                                        @error('local_chief_executive')
                                            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <aside>

                                <div class="sticky top-22.5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                                    <h3 class="text-sm font-semibold text-slate-900">
                                        Location Summary
                                    </h3>

                                    <div id="locationSummaryEmpty"
                                        class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center">
                                        <div class="text-xs font-semibold text-slate-600">
                                            No target location selected
                                        </div>

                                        <p class="mt-1 text-[11px] leading-5 text-slate-400">
                                            Select a province and complete the first target location.
                                        </p>
                                    </div>

                                    <div id="locationSummary" class="mt-4 hidden">

                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                                            <div class="text-xs font-semibold text-emerald-900">Total Areas Covered</div>
                                            <div id="locationSummaryCount" data-wizard-summary="Areas covered" class="mt-1 text-xs text-emerald-700">
                                            </div>
                                        </div>

                                        <div class="mt-4 border-t border-slate-100 pt-4">
                                            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">
                                                Province</div>
                                            <div id="locationSummaryProvince"
                                                class="mt-1 text-sm font-semibold text-slate-800"></div>
                                        </div>

                                        <div id="locationSummaryItems" class="mt-4 space-y-3"></div>

                                        <div id="locationAllocationStatus" tabindex="-1"
                                            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800">
                                            Encode the Total beneficiary allocation for every selected barangay. The sum
                                            automatically becomes the project's Total Beneficiaries.
                                        </div>

                                        <div
                                            class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-3 text-[11px] leading-5 text-blue-700">
                                            Multiple target locations may belong to different districts as long as all are
                                            inside the selected province.
                                        </div>

                                    </div>

                                </div>

                            </aside>

                        </div>

                    </section>

                </div>

                <div data-wizard-step data-wizard-title="Beneficiaries & Wage" class="space-y-5">

                    {{-- Implementation --}}
                    <section id="implementation"
                        class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Implementation
                            </h2>
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-3">

                            <div>

                                <label for="implementation_mode" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Mode
                                </label>

                                <select id="implementation_mode" name="implementation_mode" required
                                    data-wizard-summary="Implementation mode"
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                    <option value="">
                                        Select mode
                                    </option>

                                    @foreach ($implementationModes as $mode)
                                        <option value="{{ $mode->value }}" @selected((string) old('implementation_mode') === (string) $mode->value)>
                                            {{ $mode->label() }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            <div>

                                <label for="numberOfDays" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Number of Days
                                </label>

                                <input id="numberOfDays" name="number_of_days" type="number" min="10"
                                    max="90" value="{{ old('number_of_days') }}" required
                                    data-wizard-summary="Number of days"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label for="termPreview" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Term
                                </label>

                                <input id="termPreview" type="text" readonly placeholder="Automatically calculated"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600">

                            </div>

                        </div>

                    </section>

                    {{-- Beneficiaries / Wage --}}
                    <section id="beneficiaries"
                        class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Beneficiaries & Wage
                            </h2>

                            <p class="mt-1 text-xs text-slate-500">
                                Encode the declared beneficiaries and the applicable regional wage rate. Wages are
                                automatically computed as Wage Rate × Beneficiaries × Number of Days.
                            </p>
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-4">

                            <div>

                                <label for="beneficiariesTotal" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Total Beneficiaries
                                </label>

                                <input id="beneficiariesTotal" name="beneficiaries_total" type="number" min="1"
                                    value="{{ old('beneficiaries_total', 0) }}" readonly required
                                    data-wizard-summary="Total beneficiaries"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Automatically totaled from the barangay allocations encoded in Project Location.
                                </p>

                            </div>

                            <div>

                                <label for="beneficiariesFemale" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Female Beneficiaries
                                </label>

                                <input id="beneficiariesFemale" name="beneficiaries_female" type="number"
                                    min="0" value="{{ old('beneficiaries_female', 0) }}" readonly required
                                    data-wizard-summary="Female beneficiaries"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">
                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Automatically totaled from the barangay Female allocations encoded in Project Location.
                                </p>
                                <p id="femaleBeneficiaryLimit" class="hidden">Maximum: enter
                                    Total Beneficiaries first.</p>

                            </div>

                            <div>

                                <label for="wageRate" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Regional Wage Rate
                                </label>

                                <input id="wageRate" name="wage_rate" type="number" step="0.01" min="0.01"
                                    data-money-input value="{{ old('wage_rate', 455) }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Current default: ₱455.00. Adjust only when the applicable regional wage rate changes.
                                </p>

                            </div>

                            <div>

                                <label for="wagesPreview" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Computed Wages
                                </label>

                                <input id="wagesPreview" readonly value="₱0.00" data-wizard-summary="Computed wages"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

                            </div>

                        </div>

                    </section>

                </div>

                <div data-wizard-step data-wizard-title="PPE & Cost" class="space-y-5">

                    {{-- PPE --}}
                    <section id="ppe" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div
                            class="flex flex-col gap-4 border-b border-slate-200 px-6 py-4 lg:flex-row lg:items-start lg:justify-between">

                            <div class="min-w-0 max-w-3xl">
                                <h2 class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900">
                                    PPE Requirements
                                    <span id="ppeLongTermBadge"
                                        class="hidden rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-700">
                                        Long-Term · standard PPE set filled automatically
                                    </span>
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    Encode Non-Hazardous or Hazardous PPE, product, covered beneficiaries, and amount per
                                    beneficiary. PPE totals are included automatically in the project amount. Long-Term
                                    projects (31–90 days) also ask for the Quantity issued per beneficiary; Short-Term
                                    projects (10–30 days) do not.
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                <select id="ppeWorkClassification"
                                    class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                                    <option value="non_hazardous">Non-Hazardous Work</option>
                                    <option value="hazardous">Hazardous Work</option>
                                </select>

                                <button type="button" id="autoFillPpeSet"
                                    class="h-10 whitespace-nowrap rounded-lg border border-blue-300 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                                    Auto-fill Standard Set
                                </button>

                                <button type="button" id="addPpeItem"
                                    class="h-10 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Add PPE Item
                                </button>
                            </div>

                        </div>

                        <div class="p-6">

                            <div id="ppeItems" class="space-y-3"></div>

                            {{-- Split view: shown automatically when both Non-Hazardous and Hazardous PPE are encoded --}}
                            <div id="ppeGroupedItems" class="hidden space-y-5">
                                @foreach ([
                                    ['non_hazardous', 'Non-Hazardous PPE', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800'],
                                    ['hazardous', 'Hazardous PPE', 'border-amber-200', 'bg-amber-50', 'text-amber-800'],
                                ] as [$groupType, $groupLabel, $groupBorder, $groupBg, $groupText])
                                    <div data-ppe-group="{{ $groupType }}" class="overflow-hidden rounded-xl border {{ $groupBorder }}">
                                        <div class="flex items-center justify-between gap-3 {{ $groupBg }} px-4 py-2.5">
                                            <h3 class="text-xs font-bold uppercase tracking-widest {{ $groupText }}">
                                                {{ $groupLabel }}
                                            </h3>
                                            <span data-ppe-group-count class="text-[11px] font-semibold {{ $groupText }}">0 items</span>
                                        </div>

                                        <div data-ppe-group-rows class="space-y-3 p-3"></div>

                                        <div class="flex items-center justify-end gap-3 border-t {{ $groupBorder }} {{ $groupBg }} px-4 py-2.5">
                                            <span class="text-xs font-semibold {{ $groupText }}">{{ $groupLabel }} Subtotal</span>
                                            <span data-ppe-group-subtotal class="min-w-28 text-right text-sm font-bold text-slate-900">₱0.00</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-5 flex justify-end">

                                <div class="text-right">

                                    <div id="ppeTotalLabel" class="text-xs text-slate-500">
                                        PPE Total
                                    </div>

                                    <div id="ppeTotalPreview" data-wizard-summary="PPE total" class="mt-1 text-lg font-bold text-slate-900">
                                        ₱0.00
                                    </div>

                                </div>

                            </div>

                        </div>

                    </section>

                    {{-- Insurance / Total --}}
                    <section id="costing" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Insurance & Total Project Cost
                            </h2>
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-4">

                            <div>

                                <label for="insuranceRate" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Insurance Rate / Beneficiary
                                </label>

                                <input id="insuranceRate" name="insurance_rate" type="number" min="0"
                                    step="0.01" data-money-input value="{{ old('insurance_rate', 50) }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label for="insuranceBeneficiaries"
                                    class="mb-2 block text-sm font-semibold text-slate-700">
                                    Insurance Beneficiaries
                                </label>

                                <input id="insuranceBeneficiaries" name="insurance_beneficiaries" type="number"
                                    min="0" value="{{ old('insurance_beneficiaries') }}"
                                    placeholder="Beneficiaries requiring insurance" required
                                    data-wizard-summary="Insurance beneficiaries"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p id="insuranceBeneficiaryLimit" class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Maximum: enter Total Beneficiaries first.
                                </p>

                                @error('insurance_beneficiaries')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div>

                                <label for="insurancePreview" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Insurance Total
                                </label>

                                <input id="insurancePreview" readonly value="₱0.00" data-wizard-summary="Insurance total"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Insurance Rate × Insurance Beneficiaries
                                </p>

                            </div>

                            <div>

                                <label for="projectTotalPreview" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Total Project Cost
                                </label>

                                <input id="projectTotalPreview" readonly value="₱0.00" data-wizard-summary="Total project cost"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-bold">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Wages + PPE + Insurance
                                </p>

                            </div>

                        </div>

                    </section>

                </div>

                <div data-wizard-step data-wizard-title="Review & Save" class="space-y-5">

                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                Review the Project
                            </h2>
                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                Check the details below. Use <strong>Edit</strong> to go back to a step; nothing you
                                entered is lost.
                            </p>
                        </div>

                        <div data-wizard-review class="grid gap-3 p-6 lg:grid-cols-2"></div>
                    </section>

                    {{-- Remarks --}}
                    <section id="remarks"
                        class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                        <label for="remarks_input" class="mb-2 block text-sm font-semibold text-slate-700">
                            Remarks <span class="font-normal text-slate-400">(optional)</span>
                        </label>

                        <textarea id="remarks_input" name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

                    </section>

                    <section class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
                        <h2 class="text-sm font-semibold text-blue-950">
                            What happens when you save
                        </h2>

                        <p class="mt-1 text-xs leading-5 text-blue-900">
                            The project moves directly to <strong>TSSD Evaluation</strong> status. No separate
                            submission step is needed.
                        </p>
                    </section>

                </div>

                <div
                    class="sticky bottom-3 z-20 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur sm:p-4">

                    <div class="flex flex-wrap items-center justify-between gap-2">

                        <a href="{{ route('projects.index') }}"
                            class="inline-flex h-11 items-center rounded-lg px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            Cancel
                        </a>

                        <div class="flex flex-1 justify-end gap-2">

                            <button type="button" data-wizard-prev hidden
                                class="inline-flex h-11 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                                Back
                            </button>

                            <button type="button" data-wizard-next hidden
                                class="inline-flex h-11 items-center gap-1.5 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6d]">
                                <span data-wizard-next-label>Next</span>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </button>

                            <button type="submit" data-wizard-submit
                                class="inline-flex h-11 items-center rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800">
                                Save Official Project
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </form>

    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fundSponsorSelect =
                document.getElementById('fund_sponsor');

            const fundSponsorOtherWrap =
                document.getElementById('fundSponsorOtherWrap');

            const fundSponsorOtherInput =
                document.getElementById('fund_sponsor_other');

            const partnerSelect =
                document.getElementById('partner');

            const partnerOtherWrap =
                document.getElementById('partnerOtherWrap');

            const partnerOtherInput =
                document.getElementById('partner_other');

            const partnerProgramRow =
                document.getElementById('partnerProgramRow');

            function toggleOtherFundingInput(
                select,
                wrapper,
                input
            ) {
                const useOther =
                    select?.value === '__other__';

                wrapper?.classList.toggle(
                    'hidden',
                    !useOther
                );

                if (input) {
                    input.required = useOther;

                    if (!useOther) {
                        input.value = '';
                    }
                }
            }

            function togglePartnerProgramRowWidth() {
                const useOther =
                    partnerSelect?.value === '__other__';

                partnerProgramRow?.classList.toggle(
                    'sm:grid-cols-3',
                    useOther
                );

                partnerProgramRow?.classList.toggle(
                    'sm:grid-cols-2',
                    !useOther
                );
            }

            fundSponsorSelect?.addEventListener(
                'change',
                () =>
                toggleOtherFundingInput(
                    fundSponsorSelect,
                    fundSponsorOtherWrap,
                    fundSponsorOtherInput
                )
            );

            partnerSelect?.addEventListener(
                'change',
                () => {
                    toggleOtherFundingInput(
                        partnerSelect,
                        partnerOtherWrap,
                        partnerOtherInput
                    );

                    togglePartnerProgramRowWidth();
                }
            );

            toggleOtherFundingInput(
                fundSponsorSelect,
                fundSponsorOtherWrap,
                fundSponsorOtherInput
            );

            toggleOtherFundingInput(
                partnerSelect,
                partnerOtherWrap,
                partnerOtherInput
            );

            togglePartnerProgramRowWidth();

            const provinceSelect = document.getElementById('province_id');
            const projectLocations = document.getElementById('projectLocations');
            const addProjectLocation = document.getElementById('addProjectLocation');

            const locationSummary = document.getElementById('locationSummary');
            const locationSummaryEmpty = document.getElementById('locationSummaryEmpty');
            const locationSummaryCount = document.getElementById('locationSummaryCount');
            const locationSummaryProvince = document.getElementById('locationSummaryProvince');
            const locationSummaryItems = document.getElementById('locationSummaryItems');
            const locationAllocationStatus = document.getElementById('locationAllocationStatus');

            let locationIndex = 0;
            let provinceDistricts = [];

            const escapeHtml = (value) => {
                const div = document.createElement('div');
                div.textContent = value ?? '';
                return div.innerHTML;
            };

            async function fetchLocationJson(url) {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json'
                    },
                });

                if (!response.ok) {
                    throw new Error(`Location request failed (${response.status})`);
                }

                return response.json();
            }

            async function resetForProvince() {
                projectLocations.innerHTML = '';
                locationIndex = 0;
                provinceDistricts = [];

                const provinceId = provinceSelect.value;
                addProjectLocation.disabled = !provinceId;

                if (!provinceId) {
                    updateLocationSummary();
                    return;
                }

                provinceDistricts = await fetchLocationJson(
                    `/locations/provinces/${provinceId}/districts`
                );

                addLocationCard();
                updateLocationSummary();
            }

            function addLocationCard() {
                if (!provinceSelect.value) {
                    return;
                }

                const index = locationIndex++;
                const card = document.createElement('div');

                card.className =
                    'location-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm';

                card.dataset.index = index;

                const districtOptions = provinceDistricts
                    .map(
                        district =>
                        `<option value="${escapeHtml(district)}">${escapeHtml(district)}</option>`
                    )
                    .join('');

                card.innerHTML = `
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="text-slate-300">⋮⋮</span>
                            <div class="location-title text-sm font-semibold text-slate-900">
                                Location ${index + 1}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="remove-location text-xs font-semibold text-red-600 hover:text-red-700"
                        >
                            Remove
                        </button>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                District
                            </label>

                            <select
                                name="project_locations[${index}][district]"
                                class="district-select h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                required
                            >
                                <option value="">Select district</option>
                                ${districtOptions}
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Municipality / City
                            </label>

                            <select
                                name="project_locations[${index}][municipality_id]"
                                class="municipality-select h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100"
                                required
                                disabled
                            >
                                <option value="">Select municipality / city</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Barangays
                        </label>

                        <div class="rounded-lg border border-slate-300 bg-white">
                            <div class="border-b border-slate-200 p-2">
                                <input
                                    type="search"
                                    class="barangay-search h-9 w-full rounded-md border border-slate-200 bg-slate-50 px-3 text-xs outline-none focus:border-blue-300 focus:bg-white"
                                    placeholder="Search and select barangays..."
                                    disabled
                                >
                            </div>

                            <div class="barangay-options max-h-48 overflow-y-auto p-2">
                                <div class="px-2 py-5 text-center text-xs text-slate-400">
                                    Select a municipality / city first.
                                </div>
                            </div>
                        </div>

                        <div class="selected-barangays mt-3 space-y-2"></div>

                        <div class="mt-2 text-[11px] leading-4 text-slate-400">
                            Allocation column: Total beneficiaries.
                        </div>
                    </div>
                `;

                projectLocations.appendChild(card);
                wireLocationCard(card);
                renumberLocationCards();
                updateLocationSummary();
            }

            function wireLocationCard(card) {
                const districtSelect = card.querySelector('.district-select');
                const municipalitySelect = card.querySelector('.municipality-select');
                const barangaySearch = card.querySelector('.barangay-search');
                const barangayOptions = card.querySelector('.barangay-options');
                const selectedBarangays = card.querySelector('.selected-barangays');
                const removeButton = card.querySelector('.remove-location');

                districtSelect.addEventListener('change', async function() {
                    municipalitySelect.disabled = true;
                    municipalitySelect.innerHTML =
                        '<option value="">Loading...</option>';

                    barangaySearch.disabled = true;
                    barangaySearch.value = '';
                    barangayOptions.innerHTML =
                        '<div class="px-2 py-5 text-center text-xs text-slate-400">Select a municipality / city first.</div>';
                    selectedBarangays.innerHTML = '';

                    if (!this.value) {
                        municipalitySelect.innerHTML =
                            '<option value="">Select municipality / city</option>';
                        updateLocationSummary();
                        return;
                    }

                    const provinceId = provinceSelect.value;
                    const district = encodeURIComponent(this.value);

                    const municipalities = await fetchLocationJson(
                        `/locations/provinces/${provinceId}/municipalities?district=${district}`
                    );

                    municipalitySelect.innerHTML =
                        '<option value="">Select municipality / city</option>';

                    municipalities.forEach(municipality => {
                        const option = document.createElement('option');
                        option.value = municipality.id;
                        option.textContent = municipality.name;
                        municipalitySelect.appendChild(option);
                    });

                    municipalitySelect.disabled = false;
                    updateLocationSummary();
                });

                municipalitySelect.addEventListener('change', async function() {
                    barangaySearch.value = '';
                    selectedBarangays.innerHTML = '';

                    if (!this.value) {
                        barangaySearch.disabled = true;
                        barangayOptions.innerHTML =
                            '<div class="px-2 py-5 text-center text-xs text-slate-400">Select a municipality / city first.</div>';
                        updateLocationSummary();
                        return;
                    }

                    barangayOptions.innerHTML =
                        '<div class="px-2 py-5 text-center text-xs text-slate-400">Loading barangays...</div>';

                    const barangays = await fetchLocationJson(
                        `/locations/municipalities/${this.value}/barangays`
                    );

                    barangayOptions.innerHTML = '';

                    barangays.forEach(barangay => {
                        const label = document.createElement('label');

                        label.className =
                            'barangay-option flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-xs text-slate-700 hover:bg-slate-50';

                        label.dataset.search = barangay.name.toLowerCase();

                        label.innerHTML = `
                            <input
                                type="checkbox"
                                name="project_locations[${card.dataset.index}][barangay_ids][]"
                                value="${barangay.id}"
                                data-name="${escapeHtml(barangay.name)}"
                                class="h-4 w-4 rounded border-slate-300 text-blue-700"
                            >
                            <span>${escapeHtml(barangay.name)}</span>
                        `;

                        barangayOptions.appendChild(label);
                    });

                    barangaySearch.disabled = false;

                    barangayOptions
                        .querySelectorAll('input[type="checkbox"]')
                        .forEach(checkbox =>
                            checkbox.addEventListener('change', () => {
                                renderBarangayChips(card);
                                updateLocationSummary();
                            })
                        );

                    updateLocationSummary();
                });

                barangaySearch.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();

                    barangayOptions
                        .querySelectorAll('.barangay-option')
                        .forEach(option => {
                            option.classList.toggle(
                                'hidden',
                                !option.dataset.search.includes(query)
                            );
                        });
                });

                removeButton.addEventListener('click', function() {
                    card.remove();
                    renumberLocationCards();

                    if (
                        projectLocations.children.length === 0 &&
                        provinceSelect.value
                    ) {
                        addLocationCard();
                    }

                    updateLocationSummary();
                });
            }

            function renderBarangayChips(card) {
                const selected = card.querySelector('.selected-barangays');

                const existingValues = new Map();

                selected
                    .querySelectorAll('.barangay-allocation-row')
                    .forEach(row => {
                        existingValues.set(
                            row.dataset.barangayId, {
                                total: row.querySelector('.barangay-beneficiaries-total')?.value ??
                                    '',
                                female: row.querySelector('.barangay-beneficiaries-female')?.value ??
                                    '',
                            }
                        );
                    });

                selected.innerHTML = '';

                const checkedBarangays = Array.from(
                    card.querySelectorAll(
                        '.barangay-options input[type="checkbox"]:checked'
                    )
                );

                if (checkedBarangays.length === 0) {
                    const empty = document.createElement('div');
                    empty.className =
                        'rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-[11px] text-slate-400';
                    empty.textContent =
                        'Select at least one barangay to encode beneficiary allocation.';
                    selected.appendChild(empty);
                    updateAllocationValidation();
                    return;
                }

                checkedBarangays.forEach(checkbox => {
                    const barangayId = String(checkbox.value);
                    const current = existingValues.get(barangayId) ?? {
                        total: '',
                        female: '',
                    };

                    const row = document.createElement('div');
                    row.className =
                        'barangay-allocation-row grid grid-cols-2 gap-2 rounded-lg border border-blue-100 bg-blue-50/50 p-3 sm:grid-cols-[minmax(0,1fr)_95px_95px] sm:items-center';
                    row.dataset.barangayId = barangayId;
                    row.dataset.barangayName = checkbox.dataset.name;

                    row.innerHTML = `
                        <div class="col-span-2 min-w-0 sm:col-span-1">
                            <div class="truncate text-[11px] font-semibold text-blue-900">
                                ${escapeHtml(checkbox.dataset.name)}
                            </div>
                            <div class="mt-0.5 text-[11px] text-blue-600">
                                Exact beneficiary allocation
                            </div>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                Total
                            </span>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                required
                                name="project_locations[${card.dataset.index}][barangay_allocations][${barangayId}][beneficiaries_total]"
                                value="${escapeHtml(current.total)}"
                                data-barangay-id="${barangayId}"
                                class="barangay-beneficiaries-total h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs"
                            >
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                Female
                            </span>
                            <input
                                type="number"
                                min="0"
                                step="1"
                                required
                                name="project_locations[${card.dataset.index}][barangay_allocations][${barangayId}][beneficiaries_female]"
                                value="${escapeHtml(current.female)}"
                                data-barangay-id="${barangayId}"
                                class="barangay-beneficiaries-female h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs"
                            >
                        </label>
                    `;

                    row
                        .querySelectorAll('input')
                        .forEach(input =>
                            input.addEventListener('input', () => {
                                updateLocationSummary();
                            })
                        );

                    selected.appendChild(row);
                });

                updateAllocationValidation();
            }

            function renumberLocationCards() {
                projectLocations
                    .querySelectorAll('.location-card')
                    .forEach((card, visualIndex) => {
                        const title = card.querySelector('.location-title');

                        if (title) {
                            title.textContent = `Location ${visualIndex + 1}`;
                        }
                    });
            }

            function updateAllocationValidation() {
                const allocationRows = Array.from(
                    projectLocations.querySelectorAll('.barangay-allocation-row')
                );

                let allocatedTotal = 0;
                let allocatedFemale = 0;
                let incomplete = false;
                let femaleExceeds = false;

                allocationRows.forEach(row => {
                    const totalInput = row.querySelector(
                        '.barangay-beneficiaries-total'
                    );
                    const femaleInput = row.querySelector(
                        '.barangay-beneficiaries-female'
                    );

                    const rowFemaleExceeds = totalInput?.value !== '' &&
                        femaleInput?.value !== '' &&
                        Number(femaleInput.value) > Number(totalInput.value);

                    femaleInput?.setCustomValidity(
                        rowFemaleExceeds ? 'Female cannot exceed this barangay\'s Total.' : ''
                    );
                    femaleInput?.classList.toggle('border-red-400', rowFemaleExceeds);

                    if (rowFemaleExceeds) {
                        femaleExceeds = true;
                    }

                    if (totalInput?.value === '' || femaleInput?.value === '') {
                        incomplete = true;
                    }

                    allocatedTotal += Number(totalInput?.value || 0);
                    allocatedFemale += Number(femaleInput?.value || 0);
                });

                /*
                |--------------------------------------------------------------------------
                | Total Beneficiaries is derived, not encoded directly. Keep it in sync
                | with the barangay allocations entered in Project Location.
                |--------------------------------------------------------------------------
                */

                const beneficiariesTotalInput =
                    document.getElementById('beneficiariesTotal');

                const beneficiariesFemaleInput =
                    document.getElementById('beneficiariesFemale');

                let totalsChanged = false;

                if (
                    beneficiariesTotalInput &&
                    beneficiariesTotalInput.value !== String(allocatedTotal)
                ) {
                    beneficiariesTotalInput.value = String(allocatedTotal);
                    totalsChanged = true;
                }

                if (
                    beneficiariesFemaleInput &&
                    beneficiariesFemaleInput.value !== String(allocatedFemale)
                ) {
                    beneficiariesFemaleInput.value = String(allocatedFemale);
                    totalsChanged = true;
                }

                if (totalsChanged) {
                    calculate();
                }

                if (!locationAllocationStatus) {
                    return allocationRows.length > 0 && !incomplete && !femaleExceeds && allocatedTotal > 0;
                }

                if (allocationRows.length === 0) {
                    locationAllocationStatus.className =
                        'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                    locationAllocationStatus.textContent =
                        'Select barangays and encode their Total and Female beneficiary allocation.';
                    return false;
                }

                if (femaleExceeds) {
                    locationAllocationStatus.className =
                        'mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                    locationAllocationStatus.textContent =
                        'A barangay Female count cannot exceed that barangay\'s Total count.';
                    return false;
                }

                if (incomplete) {
                    locationAllocationStatus.className =
                        'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                    locationAllocationStatus.textContent =
                        `Allocated so far: ${allocatedTotal.toLocaleString()} total, ${allocatedFemale.toLocaleString()} female. Complete every selected barangay's Total and Female allocation.`;
                    return false;
                }

                if (allocatedTotal <= 0) {
                    locationAllocationStatus.className =
                        'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                    locationAllocationStatus.textContent =
                        'Encode at least one barangay Total beneficiary allocation.';
                    return false;
                }

                locationAllocationStatus.className =
                    'mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-[11px] font-semibold leading-5 text-emerald-700';
                locationAllocationStatus.textContent =
                    `Total Beneficiaries automatically set to ${allocatedTotal.toLocaleString()} (${allocatedFemale.toLocaleString()} female) from the barangay allocations.`;

                return true;
            }

            /*
            |--------------------------------------------------------------------------
            | Income Class + Local Chief Executive auto-fill
            |--------------------------------------------------------------------------
            | Filled from the municipalities / cities of the beneficiary locations
            | and the province's Governor. A value the TC typed is kept: the field
            | only follows the locations while it still holds the auto-filled value.
            */
            const LOCATION_REFERENCE = @json($locationReference);
            const incomeClassInput = document.getElementById('income_class');
            const lceInput = document.getElementById('local_chief_executive');

            function setAutoValue(input, value) {
                if (!input) return;

                const untouched = input.value.trim() === '' || input.value === input.dataset.autoValue;
                input.dataset.autoValue = value;

                if (untouched) {
                    input.value = value;
                }
            }

            function updateLocationDerivedFields() {
                const municipalityIds = Array.from(projectLocations.querySelectorAll('.municipality-select'))
                    .map((select) => select.value)
                    .filter(Boolean);
                const municipalities = [...new Set(municipalityIds)]
                    .map((id) => LOCATION_REFERENCE.municipalities[id])
                    .filter(Boolean);

                const incomeClasses = [...new Set(municipalities.map((row) => row.income_class).filter(Boolean))];
                setAutoValue(incomeClassInput, incomeClasses.join(' / '));

                const officials = municipalities
                    .filter((row) => row.mayor)
                    .map((row) => municipalities.length > 1 ? `Mayor ${row.mayor} (${row.name})` : `Mayor ${row.mayor}`);
                const governor = LOCATION_REFERENCE.governors[provinceSelect.value];

                if (governor) {
                    officials.push(`Governor ${governor}`);
                }

                setAutoValue(lceInput, officials.join(' / '));
            }

            function updateLocationSummary() {
                updateLocationDerivedFields();
                const provinceName =
                    provinceSelect.options[
                        provinceSelect.selectedIndex
                    ]?.textContent?.trim();

                const completeItems = Array.from(
                        projectLocations.querySelectorAll('.location-card')
                    )
                    .map(card => {
                        const district =
                            card.querySelector('.district-select')?.value;

                        const municipality =
                            card.querySelector('.municipality-select');

                        const municipalityName =
                            municipality?.options[
                                municipality.selectedIndex
                            ]?.textContent?.trim();

                        const barangays = Array.from(
                            card.querySelectorAll(
                                '.barangay-options input[type="checkbox"]:checked'
                            )
                        ).map(input => {
                            const allocationRow = card.querySelector(
                                `.barangay-allocation-row[data-barangay-id="${input.value}"]`
                            );

                            return {
                                name: input.dataset.name,
                                total: allocationRow?.querySelector(
                                        '.barangay-beneficiaries-total'
                                    )?.value ??
                                    '',
                                female: allocationRow?.querySelector(
                                        '.barangay-beneficiaries-female'
                                    )?.value ??
                                    '',
                            };
                        });

                        if (
                            !district ||
                            !municipality?.value ||
                            barangays.length === 0
                        ) {
                            return null;
                        }

                        return {
                            district,
                            municipalityName,
                            barangays,
                        };
                    })
                    .filter(Boolean);

                const barangayCount = completeItems.reduce(
                    (total, item) => total + item.barangays.length,
                    0
                );

                const districtCount = new Set(
                    completeItems.map(item => item.district)
                ).size;

                if (
                    !provinceSelect.value ||
                    completeItems.length === 0
                ) {
                    locationSummary.classList.add('hidden');
                    locationSummaryEmpty.classList.remove('hidden');
                    updateAllocationValidation();
                    return;
                }

                locationSummaryEmpty.classList.add('hidden');
                locationSummary.classList.remove('hidden');

                locationSummaryProvince.textContent = provinceName;

                locationSummaryCount.textContent =
                    `${districtCount} district(s) · ${completeItems.length} municipality/city location(s) · ${barangayCount} barangay(s)`;

                locationSummaryItems.innerHTML = completeItems
                    .map(
                        item => `
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div class="text-[11px] font-bold text-blue-800">
                                    ${escapeHtml(item.district)}
                                </div>

                                <div class="mt-1 text-xs font-semibold text-slate-800">
                                    ${escapeHtml(item.municipalityName)}
                                </div>

                                <div class="mt-2 space-y-1.5">
                                    ${item.barangays.map(
                                        barangay => `
                                                            <div class="flex items-center justify-between gap-3 rounded-md bg-white px-2 py-1.5 text-[11px] shadow-sm">
                                                                <span class="min-w-0 truncate font-medium text-slate-600">
                                                                    ${escapeHtml(barangay.name)}
                                                                </span>
                                                                <span class="shrink-0 font-semibold text-blue-800">
                                                                    ${barangay.total === '' ? '—' : Number(barangay.total).toLocaleString()} total ·
                                                                    ${barangay.female === '' ? '—' : Number(barangay.female).toLocaleString()} female
                                                                </span>
                                                            </div>
                                                        `
                                    ).join('')}
                                </div>
                            </div>
                        `
                    )
                    .join('');

                updateAllocationValidation();
            }

            provinceSelect.addEventListener('change', resetForProvince);
            addProjectLocation.addEventListener('click', addLocationCard);

            if (provinceSelect.value) {
                resetForProvince();
            }

            const days = document.getElementById('numberOfDays');
            const beneficiaries = document.getElementById('beneficiariesTotal');
            const wageRate = document.getElementById('wageRate');
            const insuranceRate = document.getElementById('insuranceRate');
            const insuranceBeneficiaries = document.getElementById('insuranceBeneficiaries');
            const beneficiariesFemale = document.getElementById('beneficiariesFemale');
            const femaleBeneficiaryLimit = document.getElementById('femaleBeneficiaryLimit');
            const insuranceBeneficiaryLimit = document.getElementById('insuranceBeneficiaryLimit');
            const allocationSelect = document.getElementById('adlAllocationSelect');
            const allocationBudgetPanel = document.getElementById('allocationBudgetPanel');
            const allocationBudgetLabel = document.getElementById('allocationBudgetLabel');
            const allocationOriginalAmount = document.getElementById('allocationOriginalAmount');
            const allocationUtilizedAmount = document.getElementById('allocationUtilizedAmount');
            const allocationRemainingAmount = document.getElementById('allocationRemainingAmount');
            const allocationBudgetState = document.getElementById('allocationBudgetState');
            const allocationProjectCostMessage = document.getElementById('allocationProjectCostMessage');

            const termPreview = document.getElementById('termPreview');
            const wagesPreview = document.getElementById('wagesPreview');
            const insurancePreview = document.getElementById('insurancePreview');
            const ppeTotalPreview = document.getElementById('ppeTotalPreview');
            const projectTotalPreview = document.getElementById('projectTotalPreview');

            const ppeItems = document.getElementById('ppeItems');
            const addPpeItem = document.getElementById('addPpeItem');
            const ppeGroupedItems = document.getElementById('ppeGroupedItems');
            const ppeTotalLabel = document.getElementById('ppeTotalLabel');
            const ppeGroups = {
                non_hazardous: ppeGroupedItems.querySelector('[data-ppe-group="non_hazardous"]'),
                hazardous: ppeGroupedItems.querySelector('[data-ppe-group="hazardous"]'),
            };

            let ppeIndex = 0;

            function allPpeRows() {
                return Array.from(document.querySelectorAll('[data-ppe-row]'))
                    .sort((a, b) => Number(a.dataset.ppeOrder) - Number(b.dataset.ppeOrder));
            }

            function ppeRowType(row) {
                return row.querySelector('[data-ppe-type]').value === 'hazardous' ?
                    'hazardous' :
                    'non_hazardous';
            }

            /*
            |--------------------------------------------------------------------------
            | PPE Hazard Grouping
            |--------------------------------------------------------------------------
            |
            | When the encoded PPE mixes Non-Hazardous and Hazardous items, rows are
            | moved into two separate tables, each with its own subtotal, and the
            | grand total is relabeled as the Overall PPE Total. With a single type
            | the plain list is kept. Rows are only moved when their container
            | changes (keeps focus while typing) and keep their original order.
            |--------------------------------------------------------------------------
            */

            function placePpeRow(row, container) {
                if (row.parentElement === container) return;

                const order = Number(row.dataset.ppeOrder);
                const before = Array.from(container.querySelectorAll(':scope > [data-ppe-row]'))
                    .find((existing) => Number(existing.dataset.ppeOrder) > order);

                container.insertBefore(row, before || null);
            }

            function arrangePpeGroups() {
                const rows = allPpeRows();
                const types = new Set(rows.map(ppeRowType));
                const split = types.has('hazardous') && types.has('non_hazardous');

                rows.forEach(function(row) {
                    const target = split ?
                        ppeGroups[ppeRowType(row)].querySelector('[data-ppe-group-rows]') :
                        ppeItems;

                    placePpeRow(row, target);
                });

                ppeItems.classList.toggle('hidden', split);
                ppeGroupedItems.classList.toggle('hidden', !split);
                ppeTotalLabel.textContent = split ? 'Overall PPE Total' : 'PPE Total';

                return split;
            }

            function clearPpeRows() {
                document.querySelectorAll('[data-ppe-row]').forEach((row) => row.remove());
                ppeIndex = 0;
            }

            function currency(value) {
                return new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                }).format(value || 0);
            }

            function selectedAllocationData() {
                const option = allocationSelect?.options[allocationSelect.selectedIndex];
                if (!option?.value) return null;

                return {
                    adl: option.dataset.adlNumber || '',
                    location: option.dataset.location || '',
                    allocation: Number(option.dataset.allocation || 0),
                    utilized: Number(option.dataset.utilized || 0),
                    remaining: Number(option.dataset.remaining || 0),
                };
            }

            function updateAllocationBudgetPanel(projectTotal = 0) {
                const data = selectedAllocationData();

                if (!data) {
                    allocationBudgetPanel?.classList.add('hidden');
                    allocationSelect?.setCustomValidity('');
                    return;
                }

                allocationBudgetPanel?.classList.remove('hidden');
                allocationBudgetLabel.textContent = [data.adl, data.location].filter(Boolean).join(' · ');
                allocationOriginalAmount.textContent = currency(data.allocation);
                allocationUtilizedAmount.textContent = currency(data.utilized);
                allocationRemainingAmount.textContent = currency(data.remaining);

                const afterProject = data.remaining - projectTotal;
                const exceeded = projectTotal > data.remaining + 0.00001;

                if (exceeded) {
                    const overBy = Math.abs(afterProject);
                    allocationBudgetState.textContent = 'Exceeded';
                    allocationBudgetState.className =
                        'rounded-full bg-red-100 px-3 py-1 text-[11px] font-bold uppercase text-red-700';
                    allocationProjectCostMessage.className = 'mt-3 text-xs font-semibold text-red-700';
                    allocationProjectCostMessage.textContent =
                        `Total Project Cost ${currency(projectTotal)} exceeds the remaining allocation ${currency(data.remaining)} by ${currency(overBy)}.`;
                    allocationSelect.setCustomValidity(
                        `Total Project Cost cannot exceed the remaining allocation of ${currency(data.remaining)}.`
                    );
                } else {
                    allocationBudgetState.textContent = 'Available';
                    allocationBudgetState.className =
                        'rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold uppercase text-emerald-700';
                    allocationProjectCostMessage.className = 'mt-3 text-xs font-medium text-emerald-700';
                    allocationProjectCostMessage.textContent =
                        `Remaining after this project: ${currency(Math.max(0, afterProject))}.`;
                    allocationSelect.setCustomValidity('');
                }
            }

            function calculate() {
                const dayValue = Number(days.value || 0);
                const beneficiaryValue = Number(beneficiaries.value || 0);
                const wageValue = Number(window.TupadMoney.unformat(wageRate.value) || 0);
                const insuranceValue = Number(window.TupadMoney.unformat(insuranceRate.value) || 0);
                const insuranceBeneficiaryValue = Number(
                    insuranceBeneficiaries.value || 0
                );

                const beneficiaryMaximum = Math.max(0, beneficiaryValue);

                beneficiariesFemale.max = String(beneficiaryMaximum);
                insuranceBeneficiaries.max = String(beneficiaryMaximum);

                femaleBeneficiaryLimit.textContent =
                    `Maximum: ${beneficiaryMaximum.toLocaleString()} beneficiaries.`;
                insuranceBeneficiaryLimit.textContent =
                    `Maximum: ${beneficiaryMaximum.toLocaleString()} beneficiaries.`;

                if (Number(beneficiariesFemale.value || 0) > beneficiaryMaximum) {
                    beneficiariesFemale.setCustomValidity(
                        `Female beneficiaries cannot exceed Total Beneficiaries (${beneficiaryMaximum}).`
                    );
                } else {
                    beneficiariesFemale.setCustomValidity('');
                }

                if (insuranceBeneficiaryValue > beneficiaryValue) {
                    insuranceBeneficiaries.setCustomValidity(
                        'Insurance beneficiaries cannot exceed total project beneficiaries.'
                    );
                } else {
                    insuranceBeneficiaries.setCustomValidity('');
                }

                const isLongTerm = dayValue >= 31 && dayValue <= 90;

                if (dayValue >= 10 && dayValue <= 30) {
                    termPreview.value = 'Short-Term';
                } else if (isLongTerm) {
                    termPreview.value = 'Long-Term';
                } else {
                    termPreview.value = '';
                }

                const wages = dayValue * beneficiaryValue * wageValue;

                const insurance =
                    insuranceBeneficiaryValue *
                    insuranceValue;

                let ppeTotal = 0;
                const ppeSubtotals = {
                    non_hazardous: 0,
                    hazardous: 0
                };
                const ppeCounts = {
                    non_hazardous: 0,
                    hazardous: 0
                };

                arrangePpeGroups();

                allPpeRows()
                    .forEach(function(row) {
                        const count = Number(
                            row.querySelector('[data-ppe-count]').value || 0
                        );

                        const countInput = row.querySelector('[data-ppe-count]');
                        const amount = Number(
                            window.TupadMoney.unformat(row.querySelector('[data-ppe-unit]').value) || 0
                        );

                        countInput.max = String(beneficiaryMaximum);
                        if (count > beneficiaryMaximum) {
                            countInput.setCustomValidity(
                                `PPE beneficiaries cannot exceed Total Beneficiaries (${beneficiaryMaximum}).`
                            );
                        } else {
                            countInput.setCustomValidity('');
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | PPE Quantity (Long-Term only)
                        |--------------------------------------------------------------------------
                        |
                        | Short-Term projects issue a single PPE set per beneficiary, so the
                        | Quantity input only appears — and only counts toward the total — for
                        | Long-Term projects.
                        |--------------------------------------------------------------------------
                        */

                        const quantityInput = row.querySelector('[data-ppe-quantity]');
                        let quantity = 1;

                        row.querySelector('[data-ppe-quantity-field]').classList.toggle('hidden', !isLongTerm);
                        quantityInput.disabled = !isLongTerm;
                        quantityInput.required = isLongTerm;

                        if (isLongTerm) {
                            quantity = Math.max(0, Number(quantityInput.value || 0));

                            if (quantityInput.value === '' || quantity < 1) {
                                quantityInput.setCustomValidity(
                                    'Enter the PPE quantity per beneficiary for this Long-Term project.'
                                );
                            } else {
                                quantityInput.setCustomValidity('');
                            }
                        } else {
                            quantityInput.setCustomValidity('');
                        }

                        const total = count * amount * (isLongTerm ? quantity : 1);

                        row.querySelector('[data-ppe-total]').value =
                            currency(total);

                        ppeTotal += total;
                        ppeSubtotals[ppeRowType(row)] += total;
                        ppeCounts[ppeRowType(row)] += 1;
                    });

                Object.entries(ppeGroups).forEach(function([type, group]) {
                    group.querySelector('[data-ppe-group-subtotal]').textContent =
                        currency(ppeSubtotals[type]);
                    group.querySelector('[data-ppe-group-count]').textContent =
                        `${ppeCounts[type]} ${ppeCounts[type] === 1 ? 'item' : 'items'}`;
                });

                wagesPreview.value = currency(wages);
                insurancePreview.value = currency(insurance);

                ppeTotalPreview.textContent = currency(ppeTotal);

                const projectTotal = wages + insurance + ppeTotal;
                projectTotalPreview.value = currency(projectTotal);
                updateAllocationBudgetPanel(projectTotal);
            }

            function addRow() {
                const index = ppeIndex++;

                const row = document.createElement('div');

                row.dataset.ppeRow = 'true';
                row.dataset.ppeOrder = String(index);

                row.className =
                    'flex min-w-0 flex-wrap items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4';

                const labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500';
                const inputClass = 'h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm';

                row.innerHTML = `
            <label class="min-w-36 flex-1">
                <span class="${labelClass}">Type</span>
                <select data-ppe-type name="ppe_items[${index}][ppe_type]" class="${inputClass}">
                    <option value="non_hazardous">Non-Hazardous</option>
                    <option value="hazardous">Hazardous</option>
                </select>
            </label>

            <div class="min-w-44 flex-[1.5]">
                <span class="${labelClass}">PPE Product</span>
                <div class="flex flex-col gap-2">
                    <select data-ppe-product-select aria-label="PPE Product" class="${inputClass}">
                        <option value="">Select product</option>
                        <option value="TUPAD Shirt">TUPAD Shirt</option>
                        <option value="Gloves">Gloves</option>
                        <option value="Rubber Boots">Rubber Boots</option>
                        <option value="Mask">Mask</option>
                        <option value="Bucket Hat">Bucket Hat</option>
                        <option value="__other__">Others</option>
                    </select>

                    <input
                        data-ppe-product-other
                        type="text"
                        maxlength="100"
                        placeholder="Specify other PPE product"
                        class="hidden ${inputClass}"
                    >

                    <input
                        data-ppe-product-value
                        name="ppe_items[${index}][product]"
                        type="hidden"
                        value=""
                    >
                </div>
            </div>

            <label class="min-w-28 flex-1">
                <span class="${labelClass}">Beneficiaries</span>
                <input
                    data-ppe-count
                    name="ppe_items[${index}][beneficiary_count]"
                    type="number"
                    min="1"
                    placeholder="0"
                    class="${inputClass}"
                >
            </label>

            <label class="min-w-28 flex-1">
                <span class="${labelClass}">Unit Amount</span>
                <input
                    data-ppe-unit
                    data-money-input
                    name="ppe_items[${index}][unit_amount]"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="0.00"
                    class="${inputClass}"
                >
            </label>

            <label data-ppe-quantity-field class="hidden min-w-28 flex-1">
                <span class="${labelClass}">Qty / Beneficiary</span>
                <input
                    data-ppe-quantity
                    name="ppe_items[${index}][quantity]"
                    type="number"
                    min="1"
                    step="1"
                    placeholder="0"
                    class="${inputClass}"
                >
            </label>

            <label class="min-w-32 flex-1">
                <span class="${labelClass}">Total</span>
                <input
                    data-ppe-total
                    readonly
                    tabindex="-1"
                    value="₱0.00"
                    class="h-10 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-900"
                >
            </label>

            <button
                type="button"
                data-remove-ppe
                class="mt-5 inline-flex h-10 shrink-0 items-center justify-center whitespace-nowrap rounded-lg border border-red-200 bg-white px-4 text-xs font-semibold text-red-600 hover:bg-red-50"
            >
                Remove
            </button>
        `;

                ppeItems.appendChild(row);
                window.TupadMoney.initialize(row);

                const productSelect = row.querySelector(
                    '[data-ppe-product-select]'
                );
                const productOther = row.querySelector(
                    '[data-ppe-product-other]'
                );
                const productValue = row.querySelector(
                    '[data-ppe-product-value]'
                );

                function syncPpeProduct() {
                    if (productSelect.value === '__other__') {
                        productOther.classList.remove('hidden');
                        productOther.required = true;
                        productValue.value = productOther.value.trim();
                        return;
                    }

                    productOther.classList.add('hidden');
                    productOther.required = false;
                    productOther.value = '';
                    productValue.value = productSelect.value;
                }

                productSelect.addEventListener('change', function() {
                    syncPpeProduct();
                    calculate();
                });

                productOther.addEventListener('input', function() {
                    productValue.value = productOther.value.trim();
                    calculate();
                });

                syncPpeProduct();

                row
                    .querySelectorAll('input, select')
                    .forEach(function(element) {
                        element.addEventListener(
                            'input',
                            calculate
                        );
                    });

                row
                    .querySelector('[data-remove-ppe]')
                    .addEventListener('click', function() {
                        row.remove();
                        calculate();
                    });

                calculate();
            }

            [
                days,
                beneficiaries,
                beneficiariesFemale,
                wageRate,
                insuranceRate,
                insuranceBeneficiaries,
            ].forEach(function(element) {
                element.addEventListener(
                    'input',
                    calculate
                );
            });

            addPpeItem.addEventListener(
                'click',
                addRow
            );

            /*
            |--------------------------------------------------------------------------
            | Standard PPE Set Auto-fill
            |--------------------------------------------------------------------------
            |
            | Prefills PPE Requirements from the official TUPAD PPE price list.
            | Only the Shirt's quantity doubles for Long-Term (31-90 day)
            | projects; every other item stays at quantity 1. Every field this
            | sets stays fully editable afterward — it's a starting point, not
            | a lock, and validation/storage never changes.
            |--------------------------------------------------------------------------
            */

            const PPE_STANDARD_SETS = {
                non_hazardous: [{
                        ppe_type: 'non_hazardous',
                        product: 'TUPAD Shirt',
                        unit_amount: 280.00,
                        qty: 2
                    },
                    {
                        ppe_type: 'non_hazardous',
                        product: 'Bucket Hat',
                        unit_amount: 70.00,
                        qty: 1
                    },
                ],
                hazardous: [{
                        ppe_type: 'non_hazardous',
                        product: 'TUPAD Shirt',
                        unit_amount: 280.00,
                        qty: 2
                    },
                    {
                        ppe_type: 'non_hazardous',
                        product: 'Bucket Hat',
                        unit_amount: 70.00,
                        qty: 1
                    },
                    {
                        ppe_type: 'hazardous',
                        product: 'Mask',
                        unit_amount: 80.00,
                        qty: 1
                    },
                    {
                        ppe_type: 'hazardous',
                        product: 'Gloves',
                        unit_amount: 70.00,
                        qty: 1
                    },
                    {
                        ppe_type: 'hazardous',
                        product: 'Rubber Boots',
                        unit_amount: 350.00,
                        qty: 1
                    },
                ],
            };

            const autoFillPpeSet = document.getElementById('autoFillPpeSet');
            const ppeWorkClassification = document.getElementById('ppeWorkClassification');

            const ppeSection = document.getElementById('ppe');
            const ppeLongTermBadge = document.getElementById('ppeLongTermBadge');

            // True while the PPE rows are an untouched automatic fill, so they
            // may be refreshed when the days/beneficiaries change. Any edit by
            // the user (trusted input/change event) keeps their rows.
            let ppeAutoManaged = false;

            ppeSection?.addEventListener('input', (event) => { if (event.isTrusted) ppeAutoManaged = false; });
            ppeSection?.addEventListener('change', (event) => {
                if (event.isTrusted && event.target !== ppeWorkClassification) ppeAutoManaged = false;
            });
            addPpeItem.addEventListener('click', () => { ppeAutoManaged = false; });
            ppeSection?.addEventListener('click', (event) => {
                if (event.target.closest('[data-remove-ppe]')) ppeAutoManaged = false;
            });

            function isLongTermDays() {
                const dayValue = Number(days.value || 0);
                return dayValue >= 31 && dayValue <= 90;
            }

            function fillStandardPpeSet() {
                const isLongTerm = isLongTermDays();
                const beneficiaryValue = Number(beneficiaries.value || 0);
                const set = PPE_STANDARD_SETS[ppeWorkClassification.value] || [];

                clearPpeRows();

                set.forEach(function(item) {
                    addRow();

                    const rows = allPpeRows();
                    const row = rows[rows.length - 1];

                    row.querySelector('[data-ppe-type]').value = item.ppe_type;

                    const productSelect = row.querySelector('[data-ppe-product-select]');
                    productSelect.value = item.product;
                    productSelect.dispatchEvent(new Event('change'));

                    const countInput = row.querySelector('[data-ppe-count]');
                    countInput.value = beneficiaryValue;

                    const unitInput = row.querySelector('[data-ppe-unit]');
                    unitInput.value = item.unit_amount.toFixed(2);
                    unitInput.dispatchEvent(new Event('input'));

                    if (isLongTerm) {
                        row.querySelector('[data-ppe-quantity]').value = item.qty;
                    }
                });

                calculate();
            }

            // Long-Term projects: highlight PPE and show the standard set
            // automatically (no click needed) while there are no PPE rows yet
            // or the rows are still the untouched automatic fill.
            function syncLongTermPpe() {
                const isLongTerm = isLongTermDays();

                ppeSection?.classList.toggle('ring-2', isLongTerm);
                ppeSection?.classList.toggle('ring-blue-400', isLongTerm);
                ppeSection?.classList.toggle('border-blue-300', isLongTerm);
                ppeLongTermBadge?.classList.toggle('hidden', !isLongTerm);

                if (!isLongTerm || Number(beneficiaries.value || 0) <= 0) return;

                if (allPpeRows().length === 0 || ppeAutoManaged) {
                    fillStandardPpeSet();
                    ppeAutoManaged = true;
                }
            }

            days.addEventListener('input', syncLongTermPpe);
            beneficiaries.addEventListener('input', syncLongTermPpe);
            ppeWorkClassification?.addEventListener('change', () => {
                if (ppeAutoManaged) fillStandardPpeSet();
            });
            // The beneficiaries are set from the barangay allocations, so also
            // check whenever the wizard moves (e.g. onto the PPE & Cost step).
            document.getElementById('projectForm')?.addEventListener('wizard:change', syncLongTermPpe);

            autoFillPpeSet?.addEventListener('click', function() {
                const dayValue = Number(days.value || 0);
                const beneficiaryValue = Number(beneficiaries.value || 0);

                if (!dayValue || !beneficiaryValue) {
                    const missing = !dayValue ? days : document.getElementById('location');

                    Promise.resolve(window.TupadConfirm?.({
                        title: 'Standard PPE set needs more details',
                        message: !dayValue
                            ? 'Enter the Number of Days first. The standard set depends on whether the project is Short-Term or Long-Term.'
                            : 'Encode the barangay beneficiary allocations in Project Location first. They set the Total Beneficiaries covered by the PPE.',
                        confirmText: !dayValue ? 'Go to Number of Days' : 'Go to Project Location',
                        cancelText: 'Stay here',
                    })).then(function(go) {
                        if (!go) return;
                        document.getElementById('projectForm')?.tupadWizard?.reveal(missing);
                        missing?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        (missing === days ? days : document.getElementById('province_id'))?.focus({ preventScroll: true });
                    });
                    return;
                }

                fillStandardPpeSet();
                ppeAutoManaged = false;
            });

            allocationSelect?.addEventListener('change', calculate);

            calculate();
            syncLongTermPpe();

            const locationSection = document.getElementById('location');

            function focusLocationProblem() {
                const target = locationAllocationStatus?.offsetParent
                    ? locationAllocationStatus
                    : document.getElementById('province_id');

                target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target?.focus({ preventScroll: true });
            }

            // Wizard: the Location step can't be left until every barangay
            // allocation is complete (form-wizard.js "wizard:validate").
            locationSection?.closest('[data-wizard-step]')?.addEventListener('wizard:validate', function(event) {
                if (!updateAllocationValidation()) {
                    event.preventDefault();
                    window.setTimeout(focusLocationProblem, 0);
                }
            });

            // Fallback when the wizard is not running (the wizard checks first otherwise).
            document.getElementById('projectForm')?.addEventListener(
                'submit',
                function(event) {
                    if (!updateAllocationValidation()) {
                        event.preventDefault();
                        event.currentTarget.tupadWizard?.reveal(locationSection);
                        focusLocationProblem();
                    }
                }
            );
        });
    </script>
@endpush
