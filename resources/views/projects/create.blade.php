@extends('layouts.app')

@section('title', 'Add Project')

@section('content')

    <div class="mx-auto max-w-330">

        <x-page-header eyebrow="Project Management" title="Add Official Project"
            description="Encode the official project profile in sections. Required fields are marked automatically, and project cost previews update while you work.">
            <x-slot:actions>
                <a href="{{ route('projects.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
            <div class="text-xs font-bold uppercase tracking-widest text-blue-700">
                Encoding Guide
            </div>

            <p class="mt-1 text-xs leading-5 text-blue-800">
                Complete the sections from top to bottom. Location fields load in sequence,
                while Term, Wages, Insurance, PPE, and Total Project Cost are calculated as you encode.
            </p>
        </div>

        <form method="POST" action="{{ route('projects.store') }}" class="space-y-5" id="projectForm">

            @csrf

            <div class="grid gap-6 xl:grid-cols-[230px_minmax(0,1fr)]">

                <aside class="hidden xl:block">

                    <div class="sticky top-22 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

                        <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                            Project Sections
                        </div>

                        <nav class="mt-3 space-y-1" aria-label="Project form sections">

                            @foreach ([['allocation', '1', 'ADL Allocation'], ['general', '2', 'General Information'], ['funding', '3', 'Funding Information'], ['verification', '4', 'Series & TEVS'], ['location', '5', 'Project Location'], ['implementation', '6', 'Implementation'], ['beneficiaries', '7', 'Beneficiaries & Wage'], ['ppe', '8', 'PPE Requirements'], ['costing', '9', 'Insurance & Cost'], ['remarks', '10', 'Remarks']] as [$sectionId, $sectionNumber, $sectionLabel])
                                <a href="#{{ $sectionId }}"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-950">
                                    <span
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-500 group-hover:border-slate-300">
                                        {{ $sectionNumber }}
                                    </span>

                                    <span>{{ $sectionLabel }}</span>
                                </a>
                            @endforeach

                        </nav>

                        <div class="mt-4 border-t border-slate-100 pt-4 text-[11px] leading-5 text-slate-500">
                            Fields marked <span class="font-bold text-red-500">*</span> are required.
                        </div>

                    </div>

                </aside>

                <div class="min-w-0 space-y-5">

                    {{-- ADL / Allocation --}}
                    <section id="allocation" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="border-b border-slate-200 px-6 py-4">
                            <h2 class="text-sm font-semibold text-slate-900">
                                ADL Allocation
                            </h2>
                        </div>

                        <div class="p-6">

                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Allocation
                            </label>

                            <select name="adl_allocation_id" required
                                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                                <option value="">
                                    Select allocation
                                </option>

                                @foreach ($allocations as $allocation)
                                    <option value="{{ $allocation->id }}" @selected(old('adl_allocation_id') == $allocation->id)>
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

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Date Received
                                </label>

                                <input name="date_received" type="date"
                                    value="{{ old('date_received', now()->format('Y-m-d')) }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Project Title
                                </label>

                                <input name="project_title" value="{{ old('project_title') }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div class="md:col-span-2">

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Nature of Work
                                </label>

                                <textarea name="nature_of_work" rows="3" required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('nature_of_work') }}</textarea>

                            </div>

                        </div>

                    </section>

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

                                <select id="fund_sponsor" name="fund_sponsor" required
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

                            <div>
                                <label for="partner" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Partner
                                </label>

                                <select id="partner" name="partner" required
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

                            <div id="partnerOtherWrap" class="{{ old('partner') === '__other__' ? '' : 'hidden' }}">
                                <label for="partner_other" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Other Partner
                                </label>

                                <input id="partner_other" name="partner_other" value="{{ old('partner_other') }}"
                                    maxlength="255" placeholder="Enter partner not listed above"
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Project-specific only. It does not automatically become an official reusable reference.
                                </p>
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

                                <input id="project_series" name="project_series" value="{{ old('project_series') }}"
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

                                <input id="tevs_date_verified" name="tevs_date_verified" type="date"
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
                                    class="rounded-full bg-blue-50 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
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

                                        <select id="province_id" name="province_id" required
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

                                        </div>

                                        <button id="addProjectLocation" type="button" disabled
                                            class="inline-flex h-10 items-center justify-center rounded-lg border border-blue-300 bg-white px-4 text-xs font-semibold text-blue-800 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40">
                                            + Add Another Location
                                        </button>

                                    </div>

                                    <div id="projectLocations" class="mt-4 space-y-4"></div>

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
                                            <div id="locationSummaryCount" class="mt-1 text-[11px] text-emerald-700">
                                            </div>
                                        </div>

                                        <div class="mt-4 border-t border-slate-100 pt-4">
                                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                                                Province</div>
                                            <div id="locationSummaryProvince"
                                                class="mt-1 text-sm font-semibold text-slate-800"></div>
                                        </div>

                                        <div id="locationSummaryItems" class="mt-4 space-y-3"></div>

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

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Mode
                                </label>

                                <select name="implementation_mode" required
                                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                    <option value="">
                                        Select mode
                                    </option>

                                    @foreach ($implementationModes as $mode)
                                        <option value="{{ $mode->value }}">
                                            {{ $mode->label() }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Number of Days
                                </label>

                                <input id="numberOfDays" name="number_of_days" type="number" min="10"
                                    max="90" value="{{ old('number_of_days') }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
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
                        </div>

                        <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-4">

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Total Beneficiaries
                                </label>

                                <input id="beneficiariesTotal" name="beneficiaries_total" type="number" min="1"
                                    value="{{ old('beneficiaries_total') }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Female Beneficiaries
                                </label>

                                <input name="beneficiaries_female" type="number" min="0"
                                    value="{{ old('beneficiaries_female', 0) }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Wage Rate
                                </label>

                                <input id="wageRate" name="wage_rate" type="number" step="0.01" min="0.01"
                                    value="{{ old('wage_rate') }}" required
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Computed Wages
                                </label>

                                <input id="wagesPreview" readonly value="₱0.00"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

                            </div>

                        </div>

                    </section>

                    {{-- PPE --}}
                    <section id="ppe" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">
                                    PPE Requirements
                                </h2>

                                <p class="mt-1 text-xs text-slate-500">
                                    PPE Inventory System integration will be implemented in a later phase.
                                </p>
                            </div>

                            <button type="button" id="addPpeItem"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                Add PPE Item
                            </button>

                        </div>

                        <div class="p-6">

                            <div id="ppeItems" class="space-y-3"></div>

                            <div class="mt-5 flex justify-end">

                                <div class="text-right">

                                    <div class="text-xs text-slate-500">
                                        PPE Total
                                    </div>

                                    <div id="ppeTotalPreview" class="mt-1 text-lg font-bold text-slate-900">
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
                                    step="0.01" value="{{ old('insurance_rate', 50) }}" required
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
                                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Enter only beneficiaries who require project-funded insurance.
                                    This cannot exceed Total Beneficiaries.
                                </p>

                                @error('insurance_beneficiaries')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Insurance Total
                                </label>

                                <input id="insurancePreview" readonly value="₱0.00"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Insurance Rate × Insurance Beneficiaries
                                </p>

                            </div>

                            <div>

                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Total Project Cost
                                </label>

                                <input id="projectTotalPreview" readonly value="₱0.00"
                                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-bold">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Wages + PPE + Insurance
                                </p>

                            </div>

                        </div>

                    </section>

                    {{-- Remarks --}}
                    <section id="remarks"
                        class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

                    </section>

                    <div
                        class="sticky bottom-3 z-20 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                            <div>
                                <div class="text-xs font-semibold text-slate-700">
                                    Ready to create the official project?
                                </div>

                                <p class="mt-1 text-[11px] text-slate-500">
                                    Review calculated totals before saving. Validation will keep you on this page if
                                    required information is missing.
                                </p>
                            </div>

                            <div class="flex shrink-0 gap-2">

                                <a href="{{ route('projects.index') }}"
                                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                                    Cancel
                                </a>

                                <button type="submit"
                                    class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                                    Save Official Project
                                </button>

                            </div>

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
                () =>
                toggleOtherFundingInput(
                    partnerSelect,
                    partnerOtherWrap,
                    partnerOtherInput
                )
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

            const provinceSelect = document.getElementById('province_id');
            const projectLocations = document.getElementById('projectLocations');
            const addProjectLocation = document.getElementById('addProjectLocation');

            const locationSummary = document.getElementById('locationSummary');
            const locationSummaryEmpty = document.getElementById('locationSummaryEmpty');
            const locationSummaryCount = document.getElementById('locationSummaryCount');
            const locationSummaryProvince = document.getElementById('locationSummaryProvince');
            const locationSummaryItems = document.getElementById('locationSummaryItems');

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

                        <div class="selected-barangays mt-2 flex flex-wrap gap-2"></div>
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
                selected.innerHTML = '';

                card
                    .querySelectorAll(
                        '.barangay-options input[type="checkbox"]:checked'
                    )
                    .forEach(checkbox => {
                        const chip = document.createElement('span');

                        chip.className =
                            'inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-800';

                        chip.textContent = checkbox.dataset.name;
                        selected.appendChild(chip);
                    });
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

            function updateLocationSummary() {
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
                        ).map(input => input.dataset.name);

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

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    ${item.barangays.map(
                                        barangay =>
                                            `<span class="rounded-md bg-white px-2 py-1 text-[10px] font-medium text-slate-600 shadow-sm">${escapeHtml(barangay)}</span>`
                                    ).join('')}
                                </div>
                            </div>
                        `
                    )
                    .join('');
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

            const termPreview = document.getElementById('termPreview');
            const wagesPreview = document.getElementById('wagesPreview');
            const insurancePreview = document.getElementById('insurancePreview');
            const ppeTotalPreview = document.getElementById('ppeTotalPreview');
            const projectTotalPreview = document.getElementById('projectTotalPreview');

            const ppeItems = document.getElementById('ppeItems');
            const addPpeItem = document.getElementById('addPpeItem');

            let ppeIndex = 0;

            function currency(value) {
                return new Intl.NumberFormat('en-PH', {
                    style: 'currency',
                    currency: 'PHP',
                }).format(value || 0);
            }

            function calculate() {
                const dayValue = Number(days.value || 0);
                const beneficiaryValue = Number(beneficiaries.value || 0);
                const wageValue = Number(wageRate.value || 0);
                const insuranceValue = Number(insuranceRate.value || 0);
                const insuranceBeneficiaryValue = Number(
                    insuranceBeneficiaries.value || 0
                );

                insuranceBeneficiaries.max = String(
                    Math.max(0, beneficiaryValue)
                );

                if (insuranceBeneficiaryValue > beneficiaryValue) {
                    insuranceBeneficiaries.setCustomValidity(
                        'Insurance beneficiaries cannot exceed total project beneficiaries.'
                    );
                } else {
                    insuranceBeneficiaries.setCustomValidity('');
                }

                if (dayValue >= 10 && dayValue <= 30) {
                    termPreview.value = 'Short-Term';
                } else if (dayValue >= 31 && dayValue <= 90) {
                    termPreview.value = 'Long-Term';
                } else {
                    termPreview.value = '';
                }

                const wages = dayValue * beneficiaryValue * wageValue;

                const insurance =
                    insuranceBeneficiaryValue *
                    insuranceValue;

                let ppeTotal = 0;

                document
                    .querySelectorAll('[data-ppe-row]')
                    .forEach(function(row) {
                        const count = Number(
                            row.querySelector('[data-ppe-count]').value || 0
                        );

                        const amount = Number(
                            row.querySelector('[data-ppe-unit]').value || 0
                        );

                        const total = count * amount;

                        row.querySelector('[data-ppe-total]').value =
                            currency(total);

                        ppeTotal += total;
                    });

                wagesPreview.value = currency(wages);
                insurancePreview.value = currency(insurance);

                ppeTotalPreview.textContent = currency(ppeTotal);

                projectTotalPreview.value = currency(
                    wages + insurance + ppeTotal
                );
            }

            function addRow() {
                const index = ppeIndex++;

                const row = document.createElement('div');

                row.dataset.ppeRow = 'true';

                row.className =
                    'grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-[1fr_1.5fr_1fr_1fr_1fr_auto]';

                row.innerHTML = `
            <select
                name="ppe_items[${index}][ppe_type]"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >
                <option value="non_hazardous">Non-Hazardous</option>
                <option value="hazardous">Hazardous</option>
            </select>

            <input
                name="ppe_items[${index}][product]"
                placeholder="PPE Product"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >

            <input
                data-ppe-count
                name="ppe_items[${index}][beneficiary_count]"
                type="number"
                min="1"
                placeholder="Beneficiaries"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >

            <input
                data-ppe-unit
                name="ppe_items[${index}][unit_amount]"
                type="number"
                min="0"
                step="0.01"
                placeholder="Unit Amount"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >

            <input
                data-ppe-total
                readonly
                value="₱0.00"
                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold"
            >

            <button
                type="button"
                data-remove-ppe
                class="h-10 rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50"
            >
                Remove
            </button>
        `;

                ppeItems.appendChild(row);

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
            }

            [
                days,
                beneficiaries,
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

            calculate();
        });
    </script>
@endpush
