@extends('layouts.app')

@section('title', 'Add Project')

@section('content')

    <div class="mx-auto max-w-[1320px]">

        <x-page-header
            eyebrow="Project Management"
            title="Add Official Project"
            description="Encode the official project profile in sections. Required fields are marked automatically, and project cost previews update while you work."
        >
            <x-slot:actions>
                <a
                    href="{{ route('projects.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
            <div class="text-xs font-bold uppercase tracking-[0.1em] text-blue-700">
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

                    <div class="sticky top-[88px] rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

                        <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">
                            Project Sections
                        </div>

                        <nav class="mt-3 space-y-1" aria-label="Project form sections">

                            @foreach([
                                ['allocation', '1', 'ADL Allocation'],
                                ['general', '2', 'General Information'],
                                ['funding', '3', 'Funding Information'],
                                ['verification', '4', 'Series & TEVS'],
                                ['location', '5', 'Project Location'],
                                ['implementation', '6', 'Implementation'],
                                ['beneficiaries', '7', 'Beneficiaries & Wage'],
                                ['ppe', '8', 'PPE Requirements'],
                                ['costing', '9', 'Insurance & Cost'],
                                ['remarks', '10', 'Remarks'],
                            ] as [$sectionId, $sectionNumber, $sectionLabel])

                                <a
                                    href="#{{ $sectionId }}"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-950"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-500 group-hover:border-slate-300">
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
                        Sponsor and Partner are project-level information encoded by the TUPAD Coordinator.
                        These values will automatically appear in the Focal ADL breakdown and monitoring views.
                    </p>
                </div>

                <div class="grid gap-5 p-6 md:grid-cols-2">

                    <div>
                        <label for="fund_sponsor" class="mb-2 block text-sm font-semibold text-slate-700">
                            Fund Sponsor
                        </label>

                        <input
                            id="fund_sponsor"
                            name="fund_sponsor"
                            value="{{ old('fund_sponsor') }}"
                            required
                            maxlength="255"
                            placeholder="e.g. Department of Labor and Employment"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                    <div>
                        <label for="partner" class="mb-2 block text-sm font-semibold text-slate-700">
                            Partner
                        </label>

                        <input
                            id="partner"
                            name="partner"
                            value="{{ old('partner') }}"
                            required
                            maxlength="255"
                            placeholder="e.g. LGU / Accredited Co-Partner"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
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
                        Encode the project series and TEVS verification details required for the official project record.
                    </p>
                </div>

                <div class="grid gap-5 p-6 md:grid-cols-2">

                    <div>
                        <label for="project_series" class="mb-2 block text-sm font-semibold text-slate-700">
                            Project Series
                        </label>

                        <input
                            id="project_series"
                            name="project_series"
                            value="{{ old('project_series') }}"
                            required
                            maxlength="100"
                            placeholder="e.g. Regular TUPAD / Series 2026-01"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                    <div>
                        <label for="project_series_remarks" class="mb-2 block text-sm font-semibold text-slate-700">
                            Remarks for Project Series
                        </label>

                        <input
                            id="project_series_remarks"
                            name="project_series_remarks"
                            value="{{ old('project_series_remarks') }}"
                            maxlength="3000"
                            placeholder="Optional remarks"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                    <div>
                        <label for="tevs_date_verified" class="mb-2 block text-sm font-semibold text-slate-700">
                            TEVS Date Verified
                        </label>

                        <input
                            id="tevs_date_verified"
                            name="tevs_date_verified"
                            type="date"
                            value="{{ old('tevs_date_verified') }}"
                            required
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                    <div>
                        <label for="tevs_remarks" class="mb-2 block text-sm font-semibold text-slate-700">
                            Remarks for TEVS Date Verified
                        </label>

                        <input
                            id="tevs_remarks"
                            name="tevs_remarks"
                            value="{{ old('tevs_remarks') }}"
                            maxlength="3000"
                            placeholder="Optional TEVS remarks"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                </div>

            </section>

            {{-- <------------------------------------------- Project Location ------------------------------/> --}}

            <section id="location" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">

                    <h2 class="text-sm font-semibold text-slate-900">
                        Project Location
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Select the official geographic hierarchy for this project.
                    </p>

                </div>

                <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-5">

                    {{-- Province --}}

                    <div>

                        <label for="province_id" class="mb-2 block text-sm font-semibold text-slate-700">
                            Province
                        </label>

                        <select id="province_id" name="province_id" required
                            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                            <option value="">
                                Select province
                            </option>

                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}" @selected(old('province_id') == $province->id)>
                                    {{ $province->name }}
                                </option>
                            @endforeach

                        </select>

                    </div>

                    {{-- Municipality --}}

                    <div>

                        <label for="municipality_id" class="mb-2 block text-sm font-semibold text-slate-700">
                            Municipality / City
                        </label>

                        <select id="municipality_id" name="municipality_id" required disabled
                            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100">

                            <option value="">
                                Select municipality
                            </option>

                        </select>

                    </div>

                    {{-- Barangay --}}

                    <div>

                        <label for="barangay_id" class="mb-2 block text-sm font-semibold text-slate-700">
                            Barangay
                        </label>

                        <select id="barangay_id" name="barangay_id" required disabled
                            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100">

                            <option value="">
                                Select barangay
                            </option>

                        </select>

                    </div>

                    {{-- District --}}

                    <div>

                        <label for="districtPreview" class="mb-2 block text-sm font-semibold text-slate-700">
                            District
                        </label>

                        <input id="districtPreview" type="text" readonly placeholder="Automatically assigned"
                            class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600">

                    </div>

                    {{-- Income Class --}}

                    <div>

                        <label for="incomeClassPreview" class="mb-2 block text-sm font-semibold text-slate-700">
                            Income Class
                        </label>

                        <input id="incomeClassPreview" type="text" readonly placeholder="Not yet assigned"
                            class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600">

                    </div>

                </div>

            </section>

            {{-- Implementation --}}
            <section id="implementation" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

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

                        <input id="numberOfDays" name="number_of_days" type="number" min="10" max="90"
                            value="{{ old('number_of_days') }}" required
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
            <section id="beneficiaries" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white shadow-sm">

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

                <div class="grid gap-5 p-6 md:grid-cols-3">

                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Insurance Rate / Beneficiary
                        </label>

                        <input id="insuranceRate" name="insurance_rate" type="number" min="0" step="0.01"
                            value="{{ old('insurance_rate', 50) }}" required
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                    </div>

                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Insurance Total
                        </label>

                        <input id="insurancePreview" readonly value="₱0.00"
                            class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

                    </div>

                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Total Project Cost
                        </label>

                        <input id="projectTotalPreview" readonly value="₱0.00"
                            class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-bold">

                    </div>

                </div>

            </section>

            {{-- Remarks --}}
            <section id="remarks" class="scroll-mt-24 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Remarks
                </label>

                <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

            </section>

                    <div class="sticky bottom-3 z-20 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                            <div>
                                <div class="text-xs font-semibold text-slate-700">
                                    Ready to create the official project?
                                </div>

                                <p class="mt-1 text-[11px] text-slate-500">
                                    Review calculated totals before saving. Validation will keep you on this page if required information is missing.
                                </p>
                            </div>

                            <div class="flex shrink-0 gap-2">

                                <a
                                    href="{{ route('projects.index') }}"
                                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800"
                                >
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
            const provinceSelect = document.getElementById('province_id');
            const municipalitySelect = document.getElementById('municipality_id');
            const barangaySelect = document.getElementById('barangay_id');

            const districtPreview = document.getElementById('districtPreview');
            const incomeClassPreview = document.getElementById('incomeClassPreview');

            async function loadMunicipalities(provinceId) {
                municipalitySelect.innerHTML =
                    '<option value="">Loading...</option>';

                municipalitySelect.disabled = true;

                barangaySelect.innerHTML =
                    '<option value="">Select barangay</option>';

                barangaySelect.disabled = true;

                districtPreview.value = '';
                incomeClassPreview.value = '';

                if (!provinceId) {
                    municipalitySelect.innerHTML =
                        '<option value="">Select municipality</option>';

                    return;
                }

                const response = await fetch(
                    `/locations/provinces/${provinceId}/municipalities`
                );

                const municipalities = await response.json();

                municipalitySelect.innerHTML =
                    '<option value="">Select municipality</option>';

                municipalities.forEach(function(municipality) {
                    const option = document.createElement('option');

                    option.value = municipality.id;
                    option.textContent = municipality.name;

                    option.dataset.district =
                        municipality.district ?? '';

                    option.dataset.incomeClass =
                        municipality.income_class ?? '';

                    municipalitySelect.appendChild(option);
                });

                municipalitySelect.disabled = false;
            }

            async function loadBarangays(municipalityId) {
                barangaySelect.innerHTML =
                    '<option value="">Loading...</option>';

                barangaySelect.disabled = true;

                if (!municipalityId) {
                    barangaySelect.innerHTML =
                        '<option value="">Select barangay</option>';

                    return;
                }

                const response = await fetch(
                    `/locations/municipalities/${municipalityId}/barangays`
                );

                const barangays = await response.json();

                barangaySelect.innerHTML =
                    '<option value="">Select barangay</option>';

                barangays.forEach(function(barangay) {
                    const option = document.createElement('option');

                    option.value = barangay.id;
                    option.textContent = barangay.name;

                    barangaySelect.appendChild(option);
                });

                barangaySelect.disabled = false;
            }

            provinceSelect.addEventListener(
                'change',
                function() {
                    loadMunicipalities(
                        this.value
                    );
                }
            );

            municipalitySelect.addEventListener(
                'change',
                function() {
                    const selected =
                        this.options[this.selectedIndex];

                    districtPreview.value =
                        selected?.dataset?.district ||
                        'Not Assigned';

                    incomeClassPreview.value =
                        selected?.dataset?.incomeClass ||
                        'Not Assigned';

                    loadBarangays(
                        this.value
                    );
                }
            );
            const days = document.getElementById('numberOfDays');
            const beneficiaries = document.getElementById('beneficiariesTotal');
            const wageRate = document.getElementById('wageRate');
            const insuranceRate = document.getElementById('insuranceRate');

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

                if (dayValue >= 10 && dayValue <= 30) {
                    termPreview.value = 'Short-Term';
                } else if (dayValue >= 31 && dayValue <= 90) {
                    termPreview.value = 'Long-Term';
                } else {
                    termPreview.value = '';
                }

                const wages = dayValue * beneficiaryValue * wageValue;
                const insurance = beneficiaryValue * insuranceValue;

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
