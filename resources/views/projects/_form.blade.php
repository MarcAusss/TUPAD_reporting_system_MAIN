@php
    $editing = isset($draft);

    $existingPpeItems = old(
        'ppe_items',
        $editing
            ? $draft->ppeItems
                ->map(
                    fn($item) => [
                        'ppe_type' => $item->ppe_type->value,
                        'product' => $item->product,
                        'beneficiary_count' => $item->beneficiary_count,
                        'unit_amount' => $item->unit_amount,
                    ],
                )
                ->values()
                ->all()
            : [],
    );
@endphp

<div class="space-y-5">

    {{-- <------------------------------------------- Allocation ------------------------------/> --}}

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                ADL Allocation
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Selecting an allocation does not reserve funds while this record remains a draft.
            </p>

        </div>

        <div class="p-6">

            <label class="mb-2 block text-sm font-semibold text-slate-700">
                Allocation
            </label>

            <select name="adl_allocation_id" required
                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                <option value="">
                    Select allocation
                </option>

                @foreach ($allocations as $allocation)
                    <option value="{{ $allocation->id }}" @selected(old('adl_allocation_id', $editing ? $draft->adl_allocation_id : null) == $allocation->id)>
                        {{ $allocation->adl->adl_number }}
                        —
                        {{ $allocation->location }}
                        —
                        ₱{{ number_format($allocation->amount, 2) }}
                    </option>
                @endforeach

            </select>

        </div>

    </section>

    {{-- <------------------------------------------- General ------------------------------/> --}}

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

                <input name="date_received" type="date" required
                    value="{{ old('date_received', $editing ? $draft->date_received->format('Y-m-d') : now()->format('Y-m-d')) }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Project Title
                </label>

                <input name="project_title" required
                    value="{{ old('project_title', $editing ? $draft->project_title : '') }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div class="md:col-span-2">

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Nature of Work
                </label>

                <textarea name="nature_of_work" rows="3" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('nature_of_work', $editing ? $draft->nature_of_work : '') }}</textarea>

            </div>

        </div>

    </section>

    {{-- <------------------------------------------- Location ------------------------------/> --}}

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Project Location
            </h2>

        </div>

        <div class="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-5">

            @foreach ([
        'province' => 'Province',
        'district' => 'District',
        'municipality' => 'Municipality',
        'barangay' => 'Barangay',
        'income_class' => 'Income Class',
    ] as $field => $label)
                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        {{ $label }}
                    </label>

                    <input name="{{ $field }}" value="{{ old($field, $editing ? $draft->{$field} : '') }}"
                        @if ($field !== 'income_class') required @endif
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>
            @endforeach

        </div>

    </section>

    {{-- <------------------------------------------- Implementation ------------------------------/> --}}

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
                        <option value="{{ $mode->value }}" @selected(old('implementation_mode', $editing ? $draft->implementation_mode->value : '') === $mode->value)>
                            {{ $mode->label() }}
                        </option>
                    @endforeach

                </select>

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Number of Days
                </label>

                <input id="numberOfDays" name="number_of_days" type="number" min="10" max="90" required
                    value="{{ old('number_of_days', $editing ? $draft->number_of_days : '') }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Term
                </label>

                <input id="termPreview" readonly
                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">

            </div>

        </div>

    </section>

    {{-- <------------------------------------------- Beneficiaries & Wage ------------------------------/> --}}

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

                <input id="beneficiariesTotal" name="beneficiaries_total" type="number" min="1" required
                    value="{{ old('beneficiaries_total', $editing ? $draft->beneficiaries_total : '') }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Female Beneficiaries
                </label>

                <input name="beneficiaries_female" type="number" min="0" required
                    value="{{ old('beneficiaries_female', $editing ? $draft->beneficiaries_female : 0) }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Wage Rate
                </label>

                <input id="wageRate" name="wage_rate" type="number" min="0.01" step="0.01" required
                    value="{{ old('wage_rate', $editing ? $draft->wage_rate : '') }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Computed Wages
                </label>

                <input id="wagesPreview" readonly
                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

            </div>

        </div>

    </section>

    {{-- <------------------------------------------- PPE ------------------------------/> --}}

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">

            <div>

                <h2 class="text-sm font-semibold text-slate-900">
                    PPE Requirements
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    PPE values remain draft data until TC confirmation.
                </p>

            </div>

            <button id="addPpeItem" type="button"
                class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Add PPE Item
            </button>

        </div>

        <div class="p-6">

            <div id="ppeItems" class="space-y-3"></div>

            <div class="mt-5 text-right">

                <div class="text-xs text-slate-500">
                    PPE Total
                </div>

                <div id="ppeTotalPreview" class="mt-1 text-lg font-bold text-slate-900">
                    ₱0.00
                </div>

            </div>

        </div>

    </section>

    {{-- <------------------------------------------- Insurance & Total ------------------------------/> --}}

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-6 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Insurance & Total Project Cost
            </h2>

        </div>

        <div class="grid gap-5 p-6 md:grid-cols-3">

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Insurance Rate
                </label>

                <input id="insuranceRate" name="insurance_rate" type="number" min="0" step="0.01"
                    required value="{{ old('insurance_rate', $editing ? $draft->insurance_rate : 50) }}"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Insurance Total
                </label>

                <input id="insurancePreview" readonly
                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold">

            </div>

            <div>

                <label class="mb-2 block text-sm font-semibold text-slate-700">
                    Total Project Cost
                </label>

                <input id="projectTotalPreview" readonly
                    class="h-11 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-bold">

            </div>

        </div>

    </section>

    {{-- <------------------------------------------- Remarks ------------------------------/> --}}

    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

        <label class="mb-2 block text-sm font-semibold text-slate-700">
            Remarks
        </label>

        <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $editing ? $draft->remarks : '') }}</textarea>

    </section>

</div>

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

            const existingPpeItems = @json($existingPpeItems);

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

                const wages =
                    dayValue *
                    beneficiaryValue *
                    wageValue;

                const insurance =
                    beneficiaryValue *
                    insuranceValue;

                let ppeTotal = 0;

                document
                    .querySelectorAll('[data-ppe-row]')
                    .forEach(function(row) {
                        const count = Number(
                            row.querySelector('[data-ppe-count]').value || 0
                        );

                        const unit = Number(
                            row.querySelector('[data-ppe-unit]').value || 0
                        );

                        const total = count * unit;

                        row.querySelector(
                            '[data-ppe-total]'
                        ).value = currency(total);

                        ppeTotal += total;
                    });

                wagesPreview.value = currency(wages);
                insurancePreview.value = currency(insurance);
                ppeTotalPreview.textContent = currency(ppeTotal);

                projectTotalPreview.value = currency(
                    wages +
                    insurance +
                    ppeTotal
                );
            }

            function addRow(item = null) {
                const index = ppeIndex++;

                const row = document.createElement('div');

                row.dataset.ppeRow = 'true';

                row.className =
                    'grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-2 xl:grid-cols-[1fr_1.5fr_1fr_1fr_1fr_auto]';

                const selectedType =
                    item?.ppe_type ?? 'non_hazardous';

                row.innerHTML = `
            <select
                name="ppe_items[${index}][ppe_type]"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >
                <option
                    value="non_hazardous"
                    ${selectedType === 'non_hazardous' ? 'selected' : ''}
                >
                    Non-Hazardous
                </option>

                <option
                    value="hazardous"
                    ${selectedType === 'hazardous' ? 'selected' : ''}
                >
                    Hazardous
                </option>
            </select>

            <input
                name="ppe_items[${index}][product]"
                value="${item?.product ?? ''}"
                placeholder="PPE Product"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >

            <input
                data-ppe-count
                name="ppe_items[${index}][beneficiary_count]"
                type="number"
                min="1"
                value="${item?.beneficiary_count ?? ''}"
                placeholder="Beneficiaries"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >

            <input
                data-ppe-unit
                name="ppe_items[${index}][unit_amount]"
                type="number"
                min="0"
                step="0.01"
                value="${item?.unit_amount ?? ''}"
                placeholder="Unit Amount"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >

            <input
                data-ppe-total
                readonly
                class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold"
            >

            <button
                data-remove-ppe
                type="button"
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
                    .addEventListener(
                        'click',
                        function() {
                            row.remove();
                            calculate();
                        }
                    );
            }

            addPpeItem.addEventListener(
                'click',
                function() {
                    addRow();
                }
            );

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

            existingPpeItems.forEach(function(item) {
                addRow(item);
            });

            calculate();
        });
    </script>
@endpush
