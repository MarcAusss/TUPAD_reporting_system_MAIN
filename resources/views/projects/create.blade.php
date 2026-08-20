@extends('layouts.app')

@section('title', 'Add Project')

@section('content')

    <div class="mx-auto max-w-6xl">

        <div class="mb-6">

            <a href="{{ route('projects.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Project Management
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                Add Official Project
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Create an official TUPAD project profile.
            </p>

        </div>

        @if ($errors->any())

            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-4">

                <div class="text-sm font-semibold text-red-700">
                    Please correct the following:
                </div>

                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-600">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>

            </div>

        @endif

        <form method="POST" action="{{ route('projects.store') }}" class="space-y-5" id="projectForm">

            @csrf

            {{-- ADL / Allocation --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

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
                                {{ $allocation->fund_sponsor }}
                                /
                                {{ $allocation->partner }}
                                —
                                ₱{{ number_format($allocation->amount, 2) }}
                            </option>
                        @endforeach

                    </select>

                    <p class="mt-2 text-xs text-slate-500">
                        Fund Sponsor and Partner are inherited from the selected ADL allocation.
                    </p>

                </div>

            </section>

            {{-- General --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

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

            {{-- Location --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-4">

                    <h2 class="text-sm font-semibold text-slate-900">
                        Project Location
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Income Class mapping will later be automated using the municipality reference data.
                    </p>

                </div>

                <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-5">

                    <input name="province" placeholder="Province" value="{{ old('province') }}" required
                        class="h-11 rounded-lg border border-slate-300 px-3 text-sm">

                    <input name="district" placeholder="District" value="{{ old('district') }}" required
                        class="h-11 rounded-lg border border-slate-300 px-3 text-sm">

                    <input name="municipality" placeholder="Municipality" value="{{ old('municipality') }}" required
                        class="h-11 rounded-lg border border-slate-300 px-3 text-sm">

                    <input name="barangay" placeholder="Barangay" value="{{ old('barangay') }}" required
                        class="h-11 rounded-lg border border-slate-300 px-3 text-sm">

                    <input name="income_class" placeholder="Income Class" value="{{ old('income_class') }}"
                        class="h-11 rounded-lg border border-slate-300 px-3 text-sm">

                </div>

            </section>

            {{-- Implementation --}}
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

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
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

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
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

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
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

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
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Remarks
                </label>

                <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

            </section>

            <div class="flex justify-end gap-3">

                <a href="{{ route('projects.index') }}"
                    class="inline-flex h-11 items-center rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-600">
                    Cancel
                </a>

                <button type="submit"
                    class="inline-flex h-11 items-center rounded-lg bg-slate-900 px-6 text-sm font-semibold text-white hover:bg-slate-800">
                    Save Project
                </button>

            </div>

        </form>

    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
