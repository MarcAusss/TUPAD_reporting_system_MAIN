@extends('layouts.app')

@section('title', 'Payment of Wages — '.$project->project_title)

@section('content')
@php
    $money = fn (int $cents): string => number_format($cents / 100, 2);
@endphp

<x-page-header
    eyebrow="Payment of Wages"
    :title="$project->project_title"
    description="Manage obligation tranches and record the corresponding wage disbursements."
>
    <x-slot:actions>
        <div class="flex flex-wrap gap-2">
            <a
                href="{{ route('payments.index') }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                ← Payment Queue
            </a>
            <a
                href="{{ route('projects.show', $project) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
            >
                Open Project Record
            </a>
        </div>
    </x-slot:actions>
</x-page-header>

@if($errors->any())
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
        <div class="text-sm font-semibold text-rose-900">Payment action was not saved</div>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-700">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($project->status === \App\Enums\ProjectStatus::COMPLETED)
    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
        <div class="text-sm font-semibold text-emerald-900">Payment completed</div>
        <p class="mt-1 text-xs leading-5 text-emerald-700">
            This project has reached Completed status. New payment writes are locked.
        </p>
    </div>
@endif

@if($project->payout)
    <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-sm font-semibold text-slate-900">Release of Assistance</div>
        <p class="mt-1 text-xs leading-5 text-slate-600">
            {{ $project->payout->payout_mode }} payout on
            <span class="font-semibold">{{ $project->payout->payout_date->format('F d, Y') }}</span>
            at {{ $project->payout->venue }}
            @if($project->payout->recorder)
                · Recorded by {{ $project->payout->recorder->name }}
            @endif
        </p>
    </div>
@endif

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Project Payment Summary</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Official reference values are read from the approved project and cannot be edited here.
                </p>
            </div>
            <x-status-badge :tone="$project->status === \App\Enums\ProjectStatus::COMPLETED ? 'success' : 'info'">
                {{ $project->status->label() }}
            </x-status-badge>
        </div>
    </div>

    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Project Code' => $project->approval?->project_code ?: '—',
            'ADL Number' => $project->allocation->adl->adl_number,
            'Fund Sponsor' => $project->fund_sponsor ?: $project->allocation->fund_sponsor,
            'Partner' => $project->partner ?: $project->allocation->partner,
            'Term' => $project->term->label(),
            'Beneficiaries' => number_format($project->beneficiaries_total),
            'Female Beneficiaries' => number_format($project->beneficiaries_female),
        ] as $label => $value)
            <div class="bg-white px-5 py-4">
                <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                    {{ $label }}
                </div>
                <div class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $value ?: '—' }}
                </div>
            </div>
        @endforeach

        <div class="bg-white px-5 py-4 sm:col-span-2 xl:col-span-1">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Project Location
            </div>
            <div class="mt-1 text-sm font-semibold leading-5 text-slate-900">
                {{ $project->payment_location_summary ?: '—' }}
            </div>
        </div>
    </div>
</section>

<section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['Total Project Cost', $summary['payable_cents'], 'text-slate-900'],
            ['Total Obligated', $summary['obligated_cents'], 'text-blue-800'],
            ['Total Disbursed', $summary['disbursed_cents'], 'text-emerald-700'],
            ['Unobligated', $summary['unobligated_cents'], 'text-amber-700'],
            ['Remaining Balance', $summary['remaining_cents'], 'text-rose-700'],
        ] as [$label, $amount, $tone])
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                    {{ $label }}
                </div>
                <div class="mt-2 text-lg font-bold {{ $tone }}">
                    ₱{{ $money($amount) }}
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-5">
        <div class="mb-2 flex items-center justify-between gap-4 text-xs">
            <span class="font-semibold text-slate-700">Disbursement Progress</span>
            <span class="font-bold text-[#063b86]">{{ $summary['progress_percent'] }}%</span>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
            <div
                class="h-full rounded-full bg-[#063b86] transition-all"
                style="width: {{ $summary['progress_percent'] }}%"
            ></div>
        </div>
    </div>
</section>

@if($summary['obligations_completed'])
    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
        <div class="text-sm font-semibold text-emerald-900">Obligation tranches completed</div>
        <p class="mt-1 text-xs leading-5 text-emerald-700">
            Completed on {{ $project->obligations_completed_at->format('F d, Y h:i A') }}
            @if($project->obligationsCompleter)
                by {{ $project->obligationsCompleter->name }}
            @endif
            · Tranches are locked. Record the remaining disbursements below; the TUPAD Coordinator records the Release of Assistance.
        </p>
    </div>
@endif

@if($canEditTranches)
    @php
        $initialTranches = old('tranches') ?: ($summary['unobligated_cents'] <= 0 ? [] : [[
            'beneficiaries_total' => $remainingDefaults['beneficiaries_total'] ?: '',
            'beneficiaries_female' => $remainingDefaults['beneficiaries_female'],
            'wages_amount' => $remainingDefaults['wages_amount'],
            'insurance_amount' => $remainingDefaults['insurance_amount'],
            'ppe_amount' => $remainingDefaults['ppe_amount'],
            'obligation_date' => now()->toDateString(),
            'payee' => '',
            'remarks' => '',
        ]]);
    @endphp

    <form
        id="obligationTrancheForm"
        method="POST"
        action="{{ route('projects.payment.store', $project) }}"
        class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm"
        data-next-tranche="{{ $nextTranche }}"
        data-obligated-cents="{{ $summary['obligated_cents'] }}"
        data-payable-cents="{{ $summary['payable_cents'] }}"
        data-today="{{ now()->toDateString() }}"
    >
        @csrf

        <div class="flex flex-col gap-3 border-b border-blue-100 bg-blue-50 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-blue-950">Obligation Tranches</h2>
                <p class="mt-1 text-xs text-blue-700">
                    Encode each tranche's beneficiaries and amounts. Total Overall is calculated automatically.
                    Remaining to obligate: <span class="font-semibold" data-tranche-remaining>₱{{ $money($summary['unobligated_cents']) }}</span>
                </p>
            </div>

            <button
                type="button"
                data-add-tranche
                class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-blue-300 bg-white px-4 text-sm font-semibold text-blue-800 hover:bg-blue-100"
            >
                <span aria-hidden="true">+</span> Add Tranche
            </button>
        </div>

        <div data-tranche-list class="space-y-4 p-5"></div>

        <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p data-complete-hint class="text-xs text-slate-500"></p>

            <div class="flex flex-wrap justify-end gap-2">
                <button
                    type="submit"
                    name="intent"
                    value="save"
                    class="inline-flex h-10 items-center rounded-lg border border-[#063b86] bg-white px-5 text-sm font-semibold text-[#063b86] hover:bg-blue-50"
                >
                    Save Tranches
                </button>
                <button
                    type="submit"
                    name="intent"
                    value="complete"
                    data-complete-tranches
                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]"
                >
                    Complete
                </button>
            </div>
        </div>
    </form>

    <script type="application/json" id="obligationTrancheInitial">@json((object) $initialTranches)</script>
    <script type="application/json" id="obligationTrancheErrors">@json(collect($errors->getMessages())->filter(fn ($messages, $key) => str_starts_with($key, 'tranches.')))</script>
@endif

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Payment Tranches</h2>
        <p class="mt-1 text-xs text-slate-500">
            Each disbursement is attached to its obligation tranche and retained with recorder and timestamp details.
        </p>
    </div>

    <div class="divide-y divide-slate-200">
        @forelse($project->obligations as $obligation)
            @php
                $obligationCents = $paymentService->obligationCents($obligation);
                $trancheDisbursed = $paymentService->disbursedForObligationCents($obligation);
                $trancheRemaining = max(0, $obligationCents - $trancheDisbursed);

                $trancheState = match (true) {
                    $trancheDisbursed === $obligationCents =>
                        ['Fully Disbursed', 'success'],
                    $trancheDisbursed > 0 =>
                        ['Partially Disbursed', 'warning'],
                    default => ['Awaiting Disbursement', 'info'],
                };
            @endphp

            <article id="tranche-{{ $obligation->tranche_number }}" class="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-blue-700">
                            Tranche {{ $obligation->tranche_number }}
                        </div>
                        <div class="mt-1 text-xl font-bold text-slate-900">
                            ₱{{ $money($obligationCents) }}
                        </div>
                    </div>
                    <x-status-badge :tone="$trancheState[1]">
                        {{ $trancheState[0] }}
                    </x-status-badge>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach([
                        'Beneficiaries' => number_format($obligation->beneficiaries_total).' ('.number_format($obligation->beneficiaries_female).' female)',
                        'Wages' => '₱'.number_format((float) $obligation->wages_amount, 2),
                        'Insurance' => '₱'.number_format((float) $obligation->insurance_amount, 2),
                        'PPE' => '₱'.number_format((float) $obligation->ppe_amount, 2),
                        'Obligation Date' => $obligation->obligation_date->format('F d, Y'),
                        'Payee' => $obligation->payee,
                        'Obligation Status' => $summary['obligations_completed'] ? 'Completed' : 'Recorded',
                        'Disbursed' => '₱'.$money($trancheDisbursed),
                        'Tranche Balance' => '₱'.$money($trancheRemaining),
                    ] as $label => $value)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                {{ $label }}
                            </div>
                            <div class="mt-1 text-xs font-semibold leading-5 text-slate-800">
                                {{ $value }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 text-[11px] text-slate-400">
                    Recorded by {{ $obligation->recorder->name }} ·
                    {{ $obligation->created_at->format('F d, Y h:i A') }}
                </div>

                @if($obligation->remarks)
                    <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-xs leading-5 text-slate-600">
                        {{ $obligation->remarks }}
                    </div>
                @endif

                @if($obligation->disbursements->isNotEmpty())
                    <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                        <table class="tupad-system-table min-w-full text-xs">
                            <thead class="bg-slate-50 text-slate-500">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Date Disbursed</th>
                                    <th class="px-3 py-2 text-left font-semibold">LDAP / Check Number</th>
                                    <th class="px-3 py-2 text-right font-semibold">Amount</th>
                                    <th class="px-3 py-2 text-left font-semibold">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($obligation->disbursements as $disbursement)
                                    <tr>
                                        <td class="px-3 py-2 text-slate-700">
                                            {{ $disbursement->date_disbursed->format('F d, Y') }}
                                        </td>
                                        <td class="px-3 py-2 font-semibold text-slate-900">
                                            {{ $disbursement->ldap_check_number }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-semibold text-emerald-700">
                                            ₱{{ number_format($disbursement->amount, 2) }}
                                        </td>
                                        <td class="px-3 py-2 text-slate-600">
                                            {{ $disbursement->recorder->name }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(
                    $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT
                    && $trancheRemaining > 0
                )
                    <form
                        method="POST"
                        action="{{ route('projects.payment.disbursements.store', [$project, $obligation]) }}"
                        class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4"
                    >
                        @csrf
                        <div class="mb-3">
                            <div class="text-xs font-bold text-emerald-950">Record Disbursement</div>
                            <div class="mt-1 text-[11px] text-emerald-700">
                                Remaining for this tranche: ₱{{ $money($trancheRemaining) }}
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Amount Disbursed</label>
                                <input
                                    name="amount"
                                    type="number"
                                    min="0.01"
                                    max="{{ $paymentService->centsToDecimal($trancheRemaining) }}"
                                    step="0.01"
                                    data-money-input
                                    required
                                    value="{{ $paymentService->centsToDecimal($trancheRemaining) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">Date Disbursed</label>
                                <input
                                    name="date_disbursed"
                                    type="date"
                                    required
                                    value="{{ now()->toDateString() }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                >
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-700">LDAP No. / Check No.</label>
                                <input
                                    name="ldap_check_number"
                                    required
                                    maxlength="150"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                >
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex h-9 items-center rounded-lg bg-emerald-700 px-4 text-xs font-semibold text-white hover:bg-emerald-800"
                            >
                                Record Disbursement
                            </button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <x-empty-state
                title="No obligation tranches recorded"
                message="Add the first obligation to begin Payment of Wages processing."
            />
        @endforelse
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('obligationTrancheForm');
        if (!form) return;

        const list = form.querySelector('[data-tranche-list]');
        const remainingLabel = form.querySelector('[data-tranche-remaining]');
        const completeHint = form.querySelector('[data-complete-hint]');
        const nextTranche = Number(form.dataset.nextTranche || 1);
        const obligatedCents = Number(form.dataset.obligatedCents || 0);
        const payableCents = Number(form.dataset.payableCents || 0);
        const today = form.dataset.today;

        const readJson = (id, fallback) => {
            try {
                return JSON.parse(document.getElementById(id)?.textContent || '') ?? fallback;
            } catch {
                return fallback;
            }
        };

        const initialRows = readJson('obligationTrancheInitial', []);
        const errors = readJson('obligationTrancheErrors', {});
        const peso = (cents) => '₱' + (cents / 100).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        const toCents = (value) => {
            const clean = window.TupadMoney ? window.TupadMoney.unformat(value) : String(value ?? '');
            const [whole, fraction = ''] = clean.split('.');
            return (Number(whole || 0) * 100) + Number((fraction + '00').slice(0, 2));
        };
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[char]);

        let rowIndex = 0;

        const inputClass = 'h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm';
        const labelClass = 'mb-1 block text-xs font-semibold text-slate-700';

        function field(index, name, label, value, attributes = '') {
            const messages = errors[`tranches.${index}.${name}`] || [];
            return `
                <label class="block min-w-0">
                    <span class="${labelClass}">${label}</span>
                    <input name="tranches[${index}][${name}]" value="${escapeHtml(value)}" ${attributes}
                        class="${inputClass} ${messages.length ? 'border-rose-400' : ''}">
                    ${messages.map((message) => `<span class="mt-1 block text-xs text-rose-600">${escapeHtml(message)}</span>`).join('')}
                </label>`;
        }

        function addCard(values = {}, forcedIndex = null) {
            const index = forcedIndex ?? rowIndex;
            rowIndex = Math.max(rowIndex, index + 1);
            const card = document.createElement('article');
            card.dataset.trancheCard = '';
            card.className = 'rounded-xl border border-slate-200 bg-slate-50 p-4';

            card.innerHTML = `
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="text-xs font-bold uppercase tracking-widest text-blue-700" data-tranche-label></div>
                    <button type="button" data-remove-tranche
                        class="inline-flex h-8 items-center rounded-lg border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50">
                        Remove
                    </button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    ${field(index, 'beneficiaries_total', 'No. of Beneficiaries', values.beneficiaries_total ?? '', 'type="number" min="1" step="1"')}
                    ${field(index, 'beneficiaries_female', 'Female', values.beneficiaries_female ?? '', 'type="number" min="0" step="1"')}
                    ${field(index, 'wages_amount', 'Wages', values.wages_amount ?? '', 'type="number" min="0" step="0.01" data-money-input data-tranche-amount')}
                    ${field(index, 'insurance_amount', 'Insurance', values.insurance_amount ?? '', 'type="number" min="0" step="0.01" data-money-input data-tranche-amount')}
                    ${field(index, 'ppe_amount', 'PPE (Total Amount)', values.ppe_amount ?? '', 'type="number" min="0" step="0.01" data-money-input data-tranche-amount')}
                    <label class="block min-w-0">
                        <span class="${labelClass}">Total Overall</span>
                        <input readonly tabindex="-1" data-tranche-total value="₱0.00"
                            class="h-10 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-bold text-slate-900">
                    </label>
                </div>

                <div class="mt-3 grid gap-3 border-t border-slate-200 pt-3 md:grid-cols-[200px_minmax(0,1fr)_minmax(0,1fr)]">
                    ${field(index, 'obligation_date', 'Obligation Date', values.obligation_date || today, 'type="date"')}
                    ${field(index, 'payee', 'Payee', values.payee ?? '', 'maxlength="255"')}
                    ${field(index, 'remarks', 'Remarks <span class="font-normal text-slate-400">(optional)</span>', values.remarks ?? '', 'maxlength="3000"')}
                </div>
            `;

            list.appendChild(card);
            window.TupadMoney?.initialize(card);

            card.querySelectorAll('[data-tranche-amount]').forEach((input) => {
                input.addEventListener('input', refresh);
            });
            card.querySelector('[data-remove-tranche]').addEventListener('click', () => {
                card.remove();
                refresh();
            });

            refresh();
            return card;
        }

        function refresh() {
            const cards = Array.from(list.querySelectorAll('[data-tranche-card]'));
            let enteredCents = 0;

            cards.forEach((card, position) => {
                card.querySelector('[data-tranche-label]').textContent = `Tranche ${nextTranche + position}`;
                card.querySelector('[data-remove-tranche]').classList.toggle('hidden', cards.length === 1 && position === 0);

                const total = Array.from(card.querySelectorAll('[data-tranche-amount]'))
                    .reduce((sum, input) => sum + toCents(input.value), 0);

                card.querySelector('[data-tranche-total]').value = peso(total);
                enteredCents += total;
            });

            if (cards.length === 0) {
                list.innerHTML = '';
                const empty = document.createElement('p');
                empty.dataset.trancheEmpty = '';
                empty.className = 'rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-xs text-slate-500';
                empty.textContent = 'No new tranche entered. Click "Add Tranche" to add one, or click Complete if all tranches are saved.';
                list.appendChild(empty);
            } else {
                list.querySelector('[data-tranche-empty]')?.remove();
            }

            const remaining = payableCents - obligatedCents - enteredCents;
            remainingLabel.textContent = peso(Math.max(0, payableCents - obligatedCents));

            if (remaining === 0) {
                completeHint.className = 'text-xs font-semibold text-emerald-700';
                completeHint.textContent = `Saved and entered tranches equal the Total Project Cost (${peso(payableCents)}). Ready to complete.`;
            } else if (remaining > 0) {
                completeHint.className = 'text-xs text-amber-700';
                completeHint.textContent = `${peso(remaining)} still needs to be obligated before the tranches can be completed.`;
            } else {
                completeHint.className = 'text-xs font-semibold text-rose-700';
                completeHint.textContent = `Entered tranches exceed the Total Project Cost by ${peso(-remaining)}.`;
            }
        }

        form.querySelector('[data-add-tranche]').addEventListener('click', () => {
            addCard({ obligation_date: today }).querySelector('input')?.focus();
        });

        form.querySelector('[data-complete-tranches]').addEventListener('click', (event) => {
            const confirmed = window.confirm(
                'Complete the obligation tranches?\n\nAny tranche entered on this form will be saved first. ' +
                'After completion the tranches are locked and the project moves on to the Release of Assistance.'
            );

            if (!confirmed) {
                event.preventDefault();
                return;
            }

            // Blank cards are ignored by the server on Complete, so skip
            // browser validation of empty required fields.
            form.noValidate = true;
        });

        Object.entries(initialRows).forEach(([key, row]) => addCard(row, Number(key)));
        refresh();
    });
</script>
@endpush
