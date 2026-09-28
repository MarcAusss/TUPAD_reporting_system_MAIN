@php
    $deductionService = app(\App\Services\Projects\ProjectBeneficiaryDeductionService::class);
    $deductionShortfall = $deductionService->shortfall($deductionProject) ?? ['total' => 0, 'female' => 0];
    $deductionRows = $deductionService->rows($deductionProject);
    $deductionSource = $deductionService->source($deductionProject);
    $deductionRecorded = $deductionService->isRecorded($deductionProject);
    $askFemale = $deductionShortfall['female'] > 0;
    $obligatedBeneficiaries = (int) $deductionProject->obligations->sum('beneficiaries_total');
    $obligatedFemale = (int) $deductionProject->obligations->sum('beneficiaries_female');
    $deductionTotals = [
        'mapped_total' => array_sum(array_column($deductionRows, 'mapped_total')),
        'mapped_female' => array_sum(array_column($deductionRows, 'mapped_female')),
        'deducted_total' => array_sum(array_column($deductionRows, 'deducted_total')),
        'deducted_female' => array_sum(array_column($deductionRows, 'deducted_female')),
        'actual_total' => array_sum(array_column($deductionRows, 'actual_total')),
        'actual_female' => array_sum(array_column($deductionRows, 'actual_female')),
    ];
    $deductionInputClass = 'h-9 w-24 rounded-lg border border-slate-300 bg-white px-2 text-right text-sm disabled:bg-slate-100 disabled:text-slate-400';
@endphp

<div class="mt-3 space-y-3" data-beneficiary-deduction
    data-shortfall-total="{{ $deductionShortfall['total'] }}"
    data-shortfall-female="{{ $deductionShortfall['female'] }}"
    data-ask-female="{{ $askFemale ? '1' : '0' }}">

    <div class="grid gap-2 text-xs sm:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Project Beneficiaries</div>
            <div class="mt-0.5 font-semibold text-slate-800">
                {{ number_format((int) $deductionProject->beneficiaries_total) }} ({{ number_format((int) $deductionProject->beneficiaries_female) }} female)
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Obligated Beneficiaries</div>
            <div class="mt-0.5 font-semibold text-slate-800">
                {{ number_format($obligatedBeneficiaries) }} ({{ number_format($obligatedFemale) }} female)
            </div>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
            <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Not Included (to deduct)</div>
            <div class="mt-0.5 font-semibold text-amber-900">
                {{ number_format($deductionShortfall['total']) }} ({{ number_format($deductionShortfall['female']) }} female)
            </div>
        </div>
    </div>

    @if (! $deductionRecorded)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            The Focal completed the obligations with fewer beneficiaries than the project declared.
            Tick the barangay(s) where the beneficiaries not included came from and enter how many were deducted in each.
        </div>
    @else
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
            Deductions recorded{{ $deductionProject->beneficiaryDeductionsRecorder ? ' by '.$deductionProject->beneficiaryDeductionsRecorder->name : '' }}
            on {{ $deductionProject->beneficiary_deductions_recorded_at->format('F d, Y h:i A') }}.
            Actual Beneficiary Mapping = Beneficiary Mapping − deductions.
        </div>
    @endif

    @if ($deductionSource === \App\Services\Projects\ProjectBeneficiaryDeductionService::SOURCE_PROJECT_LOCATIONS && $deductionRows !== [])
        <p class="text-[11px] text-slate-500">
            Barangays are taken from the project's location allocations because no Beneficiary Mapping Source is encoded yet.
            Saving copies them into the Beneficiary Mapping Source.
        </p>
    @endif

    @if ($bag->any())
        <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
            <ul class="list-disc pl-4">
                @foreach ($bag->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($deductionRows === [])
        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-xs text-slate-500">
            This project has no barangay allocations yet. Encode the Beneficiary Mapping Source (Beneficiaries tab) first.
        </p>
    @else
        <form method="POST" action="{{ route('projects.beneficiary-deductions.update', $deductionProject) }}" data-deduction-form>
            @csrf
            @method('PUT')

            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-xs">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Barangay (Beneficiary Mapping)</th>
                            <th class="px-3 py-2 text-right font-semibold">Mapped</th>
                            <th class="px-3 py-2 text-right font-semibold">Deducted (Not Included)</th>
                            @if ($canEditNow && $askFemale)
                                <th class="px-3 py-2 text-right font-semibold">Deducted Female</th>
                            @endif
                            <th class="px-3 py-2 text-right font-semibold">Actual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($deductionRows as $barangayId => $row)
                            @php
                                $inputTotal = (int) ($usesOld ? old("deductions.{$barangayId}.total", 0) : $row['deducted_total']);
                                $inputFemale = (int) ($usesOld ? old("deductions.{$barangayId}.female", 0) : $row['deducted_female']);
                                $isSelected = $usesOld
                                    ? old("deductions.{$barangayId}.total") !== null
                                    : $row['deducted_total'] > 0 || $row['deducted_female'] > 0;
                                $rowError = $bag->first("deductions.{$barangayId}.total") ?: $bag->first("deductions.{$barangayId}.female");
                            @endphp
                            <tr data-deduction-row data-label="{{ $row['label'] }}"
                                data-mapped-total="{{ $row['mapped_total'] }}" data-mapped-female="{{ $row['mapped_female'] }}"
                                class="{{ $isSelected ? 'bg-amber-50/60' : '' }}">
                                <td class="px-3 py-2">
                                    @if ($canEditNow)
                                        <label class="inline-flex cursor-pointer items-center gap-2 font-semibold text-slate-800">
                                            <input type="checkbox" data-deduction-select @checked($isSelected)
                                                class="h-4 w-4 rounded border-slate-300 text-[#063b86]">
                                            {{ $row['label'] }}
                                        </label>
                                    @else
                                        <span class="font-semibold text-slate-800">{{ $row['label'] }}</span>
                                    @endif
                                    <p data-row-warning class="mt-1 {{ $rowError ? '' : 'hidden' }} text-[11px] font-semibold text-rose-600">{{ $rowError }}</p>
                                </td>
                                <td class="px-3 py-2 text-right text-slate-700">
                                    {{ number_format($row['mapped_total']) }} <span class="text-slate-400">({{ number_format($row['mapped_female']) }} F)</span>
                                </td>
                                @if ($canEditNow)
                                    <td class="px-3 py-2 text-right">
                                        <input type="number" min="0" max="{{ $row['mapped_total'] }}" step="1"
                                            name="deductions[{{ $barangayId }}][total]" value="{{ $isSelected ? $inputTotal : '' }}"
                                            placeholder="0" data-deduction-total @disabled(! $isSelected)
                                            aria-label="Beneficiaries deducted in {{ $row['label'] }}"
                                            class="{{ $deductionInputClass }} {{ $isSelected ? '' : 'hidden' }}">
                                    </td>
                                    @if ($askFemale)
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" min="0" max="{{ $row['mapped_female'] }}" step="1"
                                                name="deductions[{{ $barangayId }}][female]" value="{{ $isSelected ? $inputFemale : '' }}"
                                                placeholder="0" data-deduction-female @disabled(! $isSelected)
                                                aria-label="Female beneficiaries deducted in {{ $row['label'] }}"
                                                class="{{ $deductionInputClass }} {{ $isSelected ? '' : 'hidden' }}">
                                        </td>
                                    @endif
                                @else
                                    <td class="px-3 py-2 text-right text-rose-700">
                                        {{ $row['deducted_total'] ? '−'.number_format($row['deducted_total']) : '0' }}
                                        <span class="text-slate-400">({{ number_format($row['deducted_female']) }} F)</span>
                                    </td>
                                @endif
                                <td class="px-3 py-2 text-right font-semibold text-emerald-800" data-actual-cell>
                                    {{ number_format($row['actual_total']) }} <span class="font-normal text-slate-400">({{ number_format($row['actual_female']) }} F)</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold text-slate-800">
                        <tr>
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right">
                                {{ number_format($deductionTotals['mapped_total']) }} ({{ number_format($deductionTotals['mapped_female']) }} F)
                            </td>
                            <td class="px-3 py-2 text-right text-rose-700" @if ($canEditNow && $askFemale) colspan="2" @endif data-deducted-sum>
                                {{ number_format($deductionTotals['deducted_total']) }} ({{ number_format($deductionTotals['deducted_female']) }} F)
                            </td>
                            <td class="px-3 py-2 text-right text-emerald-800" data-actual-sum>
                                {{ number_format($deductionTotals['actual_total']) }} ({{ number_format($deductionTotals['actual_female']) }} F)
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($canEditNow)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs" data-deduction-hint></p>
                    <button type="submit"
                        class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        {{ $deductionRecorded ? 'Save Deduction Changes' : 'Save Deductions' }}
                    </button>
                </div>
            @endif
        </form>
    @endif
</div>

@once
    <script>
        document.querySelectorAll('[data-beneficiary-deduction]').forEach((root) => {
            const form = root.querySelector('[data-deduction-form]');
            const hint = root.querySelector('[data-deduction-hint]');
            if (!form || !hint) return;

            const shortfallTotal = Number(root.dataset.shortfallTotal || 0);
            const shortfallFemale = Number(root.dataset.shortfallFemale || 0);
            const askFemale = root.dataset.askFemale === '1';
            const rows = Array.from(root.querySelectorAll('[data-deduction-row]'));
            const format = (n) => Number(n).toLocaleString('en-PH');
            const toInt = (input) => (input && !input.disabled ? Math.max(0, parseInt(input.value, 10) || 0) : 0);
            let problems = [];

            const refresh = () => {
                let total = 0;
                let female = 0;
                let actualTotal = 0;
                let actualFemale = 0;
                problems = [];

                rows.forEach((row) => {
                    const totalInput = row.querySelector('[data-deduction-total]');
                    const femaleInput = row.querySelector('[data-deduction-female]');
                    const warning = row.querySelector('[data-row-warning]');
                    const mappedTotal = Number(row.dataset.mappedTotal);
                    const mappedFemale = Number(row.dataset.mappedFemale);
                    const deducted = toInt(totalInput);
                    const deductedFemale = toInt(femaleInput);
                    let message = '';

                    if (deducted > mappedTotal) {
                        message = `Cannot exceed the ${format(mappedTotal)} beneficiaries mapped in this barangay.`;
                    } else if (askFemale && deductedFemale > mappedFemale) {
                        message = `Cannot exceed the ${format(mappedFemale)} female beneficiaries mapped in this barangay.`;
                    } else if (askFemale && deductedFemale > deducted) {
                        message = 'Female deducted cannot be more than the total deducted.';
                    }

                    warning.textContent = message;
                    warning.classList.toggle('hidden', message === '');
                    [totalInput, femaleInput].forEach((input) => {
                        input?.classList.toggle('border-rose-400', message !== '');
                        input?.classList.toggle('ring-1', message !== '');
                        input?.classList.toggle('ring-rose-300', message !== '');
                    });

                    if (message) {
                        problems.push(`${row.dataset.label}: ${message}`);
                    }

                    const rowActual = Math.max(0, mappedTotal - deducted);
                    const rowActualFemale = Math.max(0, mappedFemale - deductedFemale);
                    row.querySelector('[data-actual-cell]').innerHTML =
                        `${format(rowActual)} <span class="font-normal text-slate-400">(${format(rowActualFemale)} F)</span>`;

                    total += deducted;
                    female += deductedFemale;
                    actualTotal += rowActual;
                    actualFemale += rowActualFemale;
                });

                root.querySelector('[data-deducted-sum]').textContent = `${format(total)} (${format(female)} F)`;
                root.querySelector('[data-actual-sum]').textContent = `${format(actualTotal)} (${format(actualFemale)} F)`;

                const remaining = shortfallTotal - total;
                const remainingFemale = shortfallFemale - female;

                if (remaining < 0 || remainingFemale < 0) {
                    problems.push(`Deductions exceed the beneficiaries not included by ${format(Math.max(-remaining, -remainingFemale))}.`);
                    hint.className = 'text-xs font-semibold text-rose-700';
                    hint.textContent = `Too many deducted: only ${format(shortfallTotal)} beneficiaries${askFemale ? ` (${format(shortfallFemale)} female)` : ''} were not included.`;
                } else if (remaining > 0 || remainingFemale > 0) {
                    hint.className = 'text-xs font-semibold text-amber-700';
                    hint.textContent = `Still to deduct: ${format(remaining)} beneficiaries${askFemale ? ` (${format(remainingFemale)} female)` : ''}.`;
                } else {
                    hint.className = 'text-xs font-semibold text-emerald-700';
                    hint.textContent = 'Deductions match the beneficiaries not included. Ready to save.';
                }

                return { remaining, remainingFemale };
            };

            rows.forEach((row) => {
                const checkbox = row.querySelector('[data-deduction-select]');
                const inputs = row.querySelectorAll('[data-deduction-total], [data-deduction-female]');

                checkbox?.addEventListener('change', () => {
                    row.classList.toggle('bg-amber-50/60', checkbox.checked);
                    inputs.forEach((input) => {
                        input.disabled = !checkbox.checked;
                        input.classList.toggle('hidden', !checkbox.checked);
                        if (!checkbox.checked) input.value = '';
                    });

                    if (checkbox.checked) {
                        const totalInput = row.querySelector('[data-deduction-total]');
                        totalInput.value = totalInput.value || '1';
                        totalInput.focus();
                        totalInput.select();
                    }

                    refresh();
                });

                inputs.forEach((input) => input.addEventListener('input', refresh));
            });

            form.addEventListener('submit', (event) => {
                const { remaining, remainingFemale } = refresh();
                const messages = [...problems];

                if (remaining > 0 || remainingFemale > 0) {
                    messages.push(`Still to deduct: ${format(remaining)} beneficiaries${askFemale ? ` (${format(remainingFemale)} female)` : ''}. Tick the barangay(s) and enter how many were not included.`);
                }

                if (messages.length > 0) {
                    event.preventDefault();
                    window.alert('The deductions cannot be saved yet:\n\n' + messages.join('\n'));
                }
            });

            refresh();
        });
    </script>
@endonce
