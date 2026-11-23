{{--
    Insurance and PPE (Payment of Wages), saved separately from the tranches.
    Laid out like the Obligation Tranches form above it.

    Every field is auto-computed from the tranche beneficiaries (saved + still
    being entered above) and can still be edited:
      not included = project beneficiaries − paid beneficiaries
      insurance    = planned − not included × insurance rate
      each PPE     = planned − not included × quantity × unit amount
--}}
@php
    $ip = $insurancePpe;
    $ipRecord = $project->paymentInsurancePpe;
    $ipDecimal = fn (int $cents): string => number_format($cents / 100, 2, '.', ',');
    $ipPeso = fn (int $cents): string => '₱'.number_format($cents / 100, 2);
    $ipEditable = $canEditInsurancePpe;

    // Saved (possibly edited) values win while they are for the same number
    // of paid beneficiaries; otherwise show the fresh computation.
    $ipUseRecord = $ipRecord && $ipRecord->beneficiaries_paid === $ip['beneficiaries_paid'];
    $ipRecordPpe = collect($ipUseRecord ? $ipRecord->ppe_breakdown : [])->keyBy('ppe_item_id');

    $ipInsuredValue = old('insurance_beneficiaries', $ipUseRecord ? $ipRecord->insurance_beneficiaries : $ip['insurance']['beneficiaries']);
    $ipInsuranceValue = old('insurance_amount', $ipDecimal($ipUseRecord
        ? (int) round((float) $ipRecord->insurance_amount * 100)
        : $ip['insurance']['amount_cents']));

    // Same field styles as the Obligation Tranches cards.
    $ipInput = 'h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100 disabled:text-slate-600';
    $ipReadonly = 'h-10 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-700';
    $ipLabel = 'mb-1 block text-xs font-semibold text-slate-700';
    $ipAuto = '<span class="font-normal text-slate-400">(auto)</span>';

    // Rule: the Insurance and PPE fields open only when beneficiaries were
    // deducted. With none deducted, insurance and PPE stay as planned.
    $ipHasDeduction = $ip['beneficiaries_paid'] > 0 && $ip['beneficiaries_deducted'] > 0;
@endphp

<section id="insurance-ppe" class="mt-5 scroll-mt-24 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm" data-insurance-ppe>
    <form method="POST" action="{{ route('projects.payment.insurance-ppe.store', $project) }}" data-ip-form @if(! $ipEditable) inert @endif>
        @csrf

        <div class="flex flex-col gap-3 border-b border-blue-100 bg-blue-50 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-blue-950">Insurance and PPE</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-blue-700">
                    Fills in automatically from the Obligation Tranches: beneficiaries not included are deducted from the insurance
                    and from every PPE item indicated in the project. Every value can still be edited. Saved separately from the tranches.
                </p>
            </div>

            <div class="shrink-0">
                @if (! $ipRecord && ! $ipHasDeduction)
                    <x-status-badge tone="info">No deduction · as planned</x-status-badge>
                @elseif (! $ipRecord)
                    <x-status-badge tone="warning">Not saved yet</x-status-badge>
                @elseif ($ipUseRecord)
                    <x-status-badge tone="success">Saved · {{ number_format($ipRecord->beneficiaries_paid) }} beneficiaries</x-status-badge>
                @else
                    <x-status-badge tone="warning">Saved for {{ number_format($ipRecord->beneficiaries_paid) }} · update needed</x-status-badge>
                @endif
            </div>
        </div>

        {{-- Auto-computed payment total across both forms --}}
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">
                Payment Total (Tranches + Insurance and PPE)
            </div>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-4" data-ip-summary>
                @foreach ([
                    ['wages', 'Wages (Tranches)'],
                    ['insurance', 'Insurance'],
                    ['ppe', 'PPE'],
                    ['total', 'Total'],
                ] as [$key, $label])
                    <div class="rounded-lg border px-3 py-2 {{ $key === 'total' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
                        <div class="text-[11px] font-bold uppercase tracking-wide opacity-70">{{ $label }}</div>
                        <div class="mt-0.5 text-sm font-bold" data-ip-sum="{{ $key }}">—</div>
                        <div class="text-[11px]" data-ip-sum-note="{{ $key }}"></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-4 p-5">
            {{-- Beneficiaries --}}
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-widest text-blue-700">Beneficiaries</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block min-w-0">
                        <span class="{{ $ipLabel }}">Project Beneficiaries</span>
                        <input value="{{ number_format($ip['beneficiaries_project']) }}" readonly tabindex="-1" class="{{ $ipReadonly }}">
                    </label>
                    <label class="block min-w-0">
                        <span class="{{ $ipLabel }}">No. of Beneficiaries Paid {!! $ipAuto !!}</span>
                        <input name="beneficiaries_paid" type="number" min="1" max="{{ $ip['beneficiaries_project'] }}" step="1"
                            value="{{ old('beneficiaries_paid', $ip['beneficiaries_paid']) }}" data-ip-paid @disabled(! $ipEditable)
                            class="{{ $ipInput }} @error('beneficiaries_paid') border-rose-400 @enderror">
                        <span class="mt-1 block text-[11px] text-slate-400">From the tranches (saved + entered)</span>
                        @error('beneficiaries_paid')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block min-w-0">
                        <span class="{{ $ipLabel }}">Not Included (Deducted) {!! $ipAuto !!}</span>
                        <input value="{{ number_format($ip['beneficiaries_deducted']) }}" readonly tabindex="-1" data-ip-not-included class="{{ $ipReadonly }}">
                    </label>
                </div>
            </article>

            {{-- Shown while no beneficiary is deducted --}}
            <div data-ip-no-deduction @class(['rounded-lg border border-dashed border-slate-300 bg-white px-4 py-5 text-center', 'hidden' => $ipHasDeduction])>
                <div class="text-sm font-semibold text-slate-700" data-ip-no-deduction-title>
                    {{ $ip['beneficiaries_paid'] > 0 ? 'No beneficiaries deducted' : 'Enter the tranche beneficiaries first' }}
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Insurance ({{ $ipPeso($ip['insurance']['planned_cents']) }}) and PPE ({{ $ipPeso($ip['ppe']['planned_cents']) }}) stay as planned in the project.
                    This form opens only when beneficiaries are not included in the Obligation Tranches.
                </p>
            </div>

            <fieldset data-ip-fields @disabled(! $ipHasDeduction) @class(['space-y-4', 'hidden' => ! $ipHasDeduction])>
            {{-- Insurance --}}
            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="mb-3 text-xs font-bold uppercase tracking-widest text-blue-700">Insurance</div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block min-w-0">
                        <span class="{{ $ipLabel }}">Insured Beneficiaries {!! $ipAuto !!}</span>
                        <input name="insurance_beneficiaries" type="number" min="0" max="{{ $ip['insurance']['beneficiaries_planned'] }}" step="1"
                            value="{{ $ipInsuredValue }}" data-ip-insured @disabled(! $ipEditable)
                            class="{{ $ipInput }} @error('insurance_beneficiaries') border-rose-400 @enderror">
                        <span class="mt-1 block text-[11px] text-slate-400">Project: {{ number_format($ip['insurance']['beneficiaries_planned']) }}</span>
                        @error('insurance_beneficiaries')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="block min-w-0">
                        <span class="{{ $ipLabel }}">Insurance Rate</span>
                        <input value="{{ $ipPeso($ip['insurance']['rate_cents']) }}" readonly tabindex="-1" class="{{ $ipReadonly }}">
                        <span class="mt-1 block text-[11px] text-slate-400">Per beneficiary</span>
                    </label>
                    <label class="block min-w-0">
                        <span class="{{ $ipLabel }}">Insurance Amount {!! $ipAuto !!}</span>
                        <input name="insurance_amount" type="text" inputmode="decimal" value="{{ $ipInsuranceValue }}" data-ip-insurance-amount data-money-input @disabled(! $ipEditable)
                            class="{{ $ipInput }} @error('insurance_amount') border-rose-400 @enderror">
                        <span class="mt-1 block text-[11px] text-slate-400" data-ip-insurance-note></span>
                        @error('insurance_amount')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                </div>
            </article>

            {{-- PPE: one card per item indicated in the project --}}
            @forelse ($ip['ppe_items'] as $item)
                @php
                    $itemId = $item['ppe_item_id'];
                    $saved = $ipRecordPpe->get($itemId);
                    $itemBeneficiaries = old("ppe.$itemId.beneficiaries", $saved
                        ? $item['beneficiary_count'] - (int) $saved['beneficiaries_deducted']
                        : $item['beneficiary_count'] - $item['beneficiaries_deducted']);
                    $itemAmount = old("ppe.$itemId.amount", $ipDecimal($saved ? (int) $saved['amount_cents'] : $item['amount_cents']));
                @endphp
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4" data-ip-ppe="{{ $itemId }}">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs font-bold uppercase tracking-widest text-blue-700">PPE · {{ $item['product'] }}</div>
                        @if ($item['ppe_type'])
                            <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">{{ $item['ppe_type'] }}</span>
                        @endif
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="block min-w-0">
                            <span class="{{ $ipLabel }}">Beneficiaries {!! $ipAuto !!}</span>
                            <input name="ppe[{{ $itemId }}][beneficiaries]" type="number" min="0" max="{{ $item['beneficiary_count'] }}" step="1"
                                value="{{ $itemBeneficiaries }}" data-ip-ppe-beneficiaries @disabled(! $ipEditable)
                                class="{{ $ipInput }} @error("ppe.$itemId.beneficiaries") border-rose-400 @enderror">
                            <span class="mt-1 block text-[11px] text-slate-400">Project: {{ number_format($item['beneficiary_count']) }}</span>
                            @error("ppe.$itemId.beneficiaries")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                        <label class="block min-w-0">
                            <span class="{{ $ipLabel }}">Quantity × Unit Amount</span>
                            <input value="{{ number_format($item['quantity']) }} × {{ $ipPeso($item['unit_cents']) }}" readonly tabindex="-1" class="{{ $ipReadonly }}">
                            <span class="mt-1 block text-[11px] text-slate-400">Per beneficiary</span>
                        </label>
                        <label class="block min-w-0">
                            <span class="{{ $ipLabel }}">Amount {!! $ipAuto !!}</span>
                            <input name="ppe[{{ $itemId }}][amount]" type="text" inputmode="decimal" value="{{ $itemAmount }}" data-ip-ppe-amount data-money-input @disabled(! $ipEditable)
                                class="{{ $ipInput }} @error("ppe.$itemId.amount") border-rose-400 @enderror">
                            <span class="mt-1 block text-[11px] text-slate-400" data-ip-ppe-note></span>
                            @error("ppe.$itemId.amount")<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    </div>
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-xs text-slate-500">
                    No PPE items are indicated in this project.
                </p>
            @endforelse

            @if ($ipEditable)
                <label class="block min-w-0">
                    <span class="{{ $ipLabel }}">Remarks <span class="font-normal text-slate-400">(optional)</span></span>
                    <input name="insurance_ppe_remarks" maxlength="3000" value="{{ old('insurance_ppe_remarks', $ipRecord?->remarks) }}" class="{{ $ipInput }}">
                </label>
            @elseif ($ipRecord?->remarks)
                <div class="rounded-lg border border-slate-200 bg-white p-3 text-xs leading-5 text-slate-600">{{ $ipRecord->remarks }}</div>
            @endif
            </fieldset>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-xs text-slate-500">
                <p data-ip-hint></p>
                @if ($ipRecord)
                    <p class="mt-0.5 text-[11px] text-slate-400">
                        Last saved for {{ number_format($ipRecord->beneficiaries_paid) }} beneficiaries
                        @if ($ipRecord->recorder) by {{ $ipRecord->recorder->name }} @endif
                        · {{ $ipRecord->updated_at->format('F d, Y h:i A') }}.
                        {{ $summary['obligations_completed'] ? 'Locked with the completed tranches.' : 'Recomputed automatically if the tranches are completed with a different number of beneficiaries.' }}
                    </p>
                @elseif ($ipEditable)
                    <p class="mt-0.5 text-[11px] text-slate-400">If not saved earlier, it is saved automatically when the tranches are completed.</p>
                @endif
            </div>

            @if ($ipEditable)
                <button type="submit" data-ip-submit @disabled(! $ipHasDeduction)
                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b] disabled:cursor-not-allowed disabled:opacity-50">
                    Save Insurance and PPE
                </button>
            @endif
        </div>
    </form>
</section>

@php
    $ipScriptData = [
        'wage_per_beneficiary_cents' => $wagePerBeneficiaryCents,
        'wages_planned_cents' => (int) $obligationLimits['wages'],
        'saved_wages_cents' => (int) $obligatedTotals['wages'],
        'saved_beneficiaries' => (int) $obligatedTotals['beneficiaries_total'],
        'project_beneficiaries' => $ip['beneficiaries_project'],
        'record_beneficiaries' => $ipRecord?->beneficiaries_paid,
        'insurance' => $ip['insurance'],
        'ppe_items' => $ip['ppe_items'],
    ];
@endphp
<script type="application/json" id="insurancePpeData">@json($ipScriptData)</script>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-insurance-ppe]');
        if (!root) return;

        let data;
        try {
            data = JSON.parse(document.getElementById('insurancePpeData')?.textContent || '{}');
        } catch {
            return;
        }

        const peso = (cents) => '₱' + (cents / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const count = (value) => Number(value || 0).toLocaleString('en-PH');
        const toInt = (value) => Math.max(0, parseInt(value, 10) || 0);
        const toCents = (value) => {
            const clean = window.TupadMoney ? window.TupadMoney.unformat(value) : String(value ?? '').replace(/[,\s]/g, '');
            const [whole, fraction = ''] = clean.split('.');
            return (Number(whole || 0) * 100) + Number((fraction + '00').slice(0, 2));
        };
        const setMoney = (input, cents) => {
            const decimal = (cents / 100).toFixed(2);
            input.value = window.TupadMoney ? window.TupadMoney.format(decimal) : decimal;
        };

        const paidInput = root.querySelector('[data-ip-paid]');
        const notIncludedInput = root.querySelector('[data-ip-not-included]');
        const insuredInput = root.querySelector('[data-ip-insured]');
        const insuranceInput = root.querySelector('[data-ip-insurance-amount]');
        const insuranceNote = root.querySelector('[data-ip-insurance-note]');
        const submit = root.querySelector('[data-ip-submit]');
        const hint = root.querySelector('[data-ip-hint]');
        const fields = root.querySelector('[data-ip-fields]');
        const noDeduction = root.querySelector('[data-ip-no-deduction]');
        const noDeductionTitle = root.querySelector('[data-ip-no-deduction-title]');
        const ins = data.insurance;
        const ppeRows = data.ppe_items.map((item) => {
            const row = root.querySelector(`[data-ip-ppe="${item.ppe_item_id}"]`);
            return {
                item,
                beneficiaries: row?.querySelector('[data-ip-ppe-beneficiaries]'),
                amount: row?.querySelector('[data-ip-ppe-amount]'),
                note: row?.querySelector('[data-ip-ppe-note]'),
            };
        });

        let trancheWages = data.saved_wages_cents;

        // Amount for a number of covered beneficiaries; deductions never exceed the plan.
        const insuranceFor = (insured) => Math.max(0, ins.planned_cents - Math.max(0, ins.beneficiaries_planned - insured) * ins.rate_cents);
        const ppeFor = (item, covered) => Math.max(0, item.planned_cents - Math.max(0, item.beneficiary_count - covered) * item.quantity * item.unit_cents);

        const deductionNote = (node, planned, cents) => {
            if (!node) return;
            const over = cents > planned;
            node.textContent = over
                ? `Exceeds the project's ${peso(planned)}`
                : `Project ${peso(planned)} · −${peso(planned - cents)}`;
            node.className = `mt-1 block text-[11px] ${over ? 'font-semibold text-rose-600' : 'text-slate-400'}`;
        };

        // Same rules as ProjectPaymentService::insurancePpeComputation().
        function autofillFromPaid() {
            const paid = Math.min(toInt(paidInput.value), data.project_beneficiaries);
            const notIncluded = data.project_beneficiaries - paid;

            if (insuredInput && insuranceInput) {
                insuredInput.value = ins.beneficiaries_planned - Math.min(notIncluded, ins.beneficiaries_planned);
                setMoney(insuranceInput, insuranceFor(toInt(insuredInput.value)));
            }

            ppeRows.forEach(({ item, beneficiaries, amount }) => {
                if (!beneficiaries || !amount) return;
                beneficiaries.value = item.beneficiary_count - Math.min(notIncluded, item.beneficiary_count);
                setMoney(amount, ppeFor(item, toInt(beneficiaries.value)));
            });

            refresh();
        }

        function refresh() {
            const paid = Math.min(toInt(paidInput?.value), data.project_beneficiaries);
            if (notIncludedInput) notIncludedInput.value = count(data.project_beneficiaries - paid);
            const deducting = paid > 0 && paid < data.project_beneficiaries;

            // With no deduction, insurance and PPE stay as planned.
            const insuranceCents = deducting ? toCents(insuranceInput?.value) : ins.planned_cents;
            deductionNote(insuranceNote, ins.planned_cents, insuranceCents);

            let ppePlanned = 0;
            let ppeCents = 0;
            ppeRows.forEach(({ item, amount, note }) => {
                const cents = deducting ? toCents(amount?.value) : item.planned_cents;
                ppePlanned += item.planned_cents;
                ppeCents += cents;
                deductionNote(note, item.planned_cents, cents);
            });

            const totals = {
                wages: [trancheWages, data.wages_planned_cents],
                insurance: [insuranceCents, ins.planned_cents],
                ppe: [ppeCents, ppePlanned],
            };
            totals.total = [
                totals.wages[0] + totals.insurance[0] + totals.ppe[0],
                totals.wages[1] + totals.insurance[1] + totals.ppe[1],
            ];

            Object.entries(totals).forEach(([key, [actual, planned]]) => {
                root.querySelector(`[data-ip-sum="${key}"]`).textContent = `${peso(actual)} / ${peso(planned)}`;
                root.querySelector(`[data-ip-sum-note="${key}"]`).textContent = planned > actual
                    ? `Deducted ${peso(planned - actual)}`
                    : 'No deduction';
            });

            // The form opens only when beneficiaries are deducted.
            const hasDeduction = deducting;
            if (fields) {
                fields.disabled = !hasDeduction;
                fields.classList.toggle('hidden', !hasDeduction);
            }
            noDeduction?.classList.toggle('hidden', hasDeduction);
            if (noDeductionTitle) {
                noDeductionTitle.textContent = paid < 1 ? 'Enter the tranche beneficiaries first' : 'No beneficiaries deducted';
            }

            if (submit) submit.disabled = !hasDeduction;
            if (hint) {
                const outdated = data.record_beneficiaries !== null && data.record_beneficiaries !== paid && paid > 0;
                hint.textContent = paid < 1
                    ? 'Enter the tranche beneficiaries first.'
                    : !hasDeduction
                    ? 'No beneficiaries deducted: insurance and PPE stay as planned.'
                    : (outdated
                        ? `Saved for ${count(data.record_beneficiaries)} beneficiaries — save again for ${count(paid)}.`
                        : `Saves Insurance and PPE for ${count(paid)} beneficiaries.`);
                hint.className = outdated ? 'font-semibold text-amber-700' : '';
            }
        }

        // Tranche form changes: wages always follow; when the paid beneficiaries
        // change, every field is auto-filled again (edits are kept otherwise).
        function fromTranches({ beneficiaries_total: beneficiaries, wages }) {
            trancheWages = Number(wages || 0);

            if (paidInput && !paidInput.disabled && toInt(paidInput.value) !== Number(beneficiaries || 0)) {
                paidInput.value = Number(beneficiaries || 0);
                autofillFromPaid();
                return;
            }

            refresh();
        }

        paidInput?.addEventListener('input', autofillFromPaid);
        insuredInput?.addEventListener('input', () => {
            setMoney(insuranceInput, insuranceFor(toInt(insuredInput.value)));
            refresh();
        });
        insuranceInput?.addEventListener('input', refresh);
        ppeRows.forEach(({ item, beneficiaries, amount }) => {
            beneficiaries?.addEventListener('input', () => {
                setMoney(amount, ppeFor(item, toInt(beneficiaries.value)));
                refresh();
            });
            amount?.addEventListener('input', refresh);
        });

        document.addEventListener('payment:tranches-changed', (event) => fromTranches(event.detail));

        window.TupadMoney?.initialize(root);
        if (window.paymentTranchesRunning) {
            fromTranches(window.paymentTranchesRunning);
        } else {
            refresh();
        }
    });
</script>
@endpush
