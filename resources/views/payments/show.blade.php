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
        data-wage-per-beneficiary-cents="{{ $wagePerBeneficiaryCents }}"
        data-insurance-per-beneficiary-cents="{{ $insurancePerBeneficiaryCents }}"
        data-today="{{ now()->toDateString() }}"
    >
        @csrf

        <div class="flex flex-col gap-3 border-b border-blue-100 bg-blue-50 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-blue-950">Obligation Tranches</h2>
                <p class="mt-1 text-xs text-blue-700">
                    Wages fill in automatically as beneficiaries × ₱{{ $money($wagePerBeneficiaryCents) }}
                    (₱{{ number_format((float) $project->wage_rate, 2) }} wage rate × {{ number_format((int) $project->number_of_days) }} days),
                    and Insurance as beneficiaries × ₱{{ $money($insurancePerBeneficiaryCents) }} insurance rate. Both can be adjusted.
                    Tranches may total less than the project data, but not more.
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

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Project Data vs Obligated (saved + entered)
            </div>
            <div data-limit-summary class="mt-2 grid gap-2 sm:grid-cols-3 xl:grid-cols-6"></div>
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

    <script type="application/json" id="obligationTrancheLimits">@json(['limits' => $obligationLimits, 'obligated' => $obligatedTotals])</script>
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

                $releaseState = $paymentService->trancheReleaseState($obligation);
                $trancheState = match (true) {
                    $releaseState === 'released' => ['Released', 'success'],
                    $releaseState === 'release_scheduled' => ['Released · Payout Date Pending', 'info'],
                    $releaseState === 'ready_for_release' => ['Disbursed · Awaiting TC Release', 'warning'],
                    $trancheDisbursed > 0 => ['Partially Disbursed', 'warning'],
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

                @if($obligation->isReleased())
                    <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs leading-5 text-emerald-900">
                        <span class="font-semibold">Release of Assistance:</span>
                        {{ $obligation->release_mode }} ·
                        {{ $obligation->release_date->format('F d, Y') }} ·
                        {{ $obligation->release_venue }}
                        @if($obligation->releaser)
                            · Recorded by {{ $obligation->releaser->name }}
                        @endif
                    </div>
                @elseif($releaseState === 'ready_for_release')
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                        Fully disbursed. The TUPAD Coordinator has been notified to record the Release of Assistance for this tranche.
                    </div>
                @endif

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
        const limitSummary = form.querySelector('[data-limit-summary]');
        const completeHint = form.querySelector('[data-complete-hint]');
        const nextTranche = Number(form.dataset.nextTranche || 1);
        const wagePerBeneficiaryCents = Number(form.dataset.wagePerBeneficiaryCents || 0);
        const insurancePerBeneficiaryCents = Number(form.dataset.insurancePerBeneficiaryCents || 0);
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
        const { limits, obligated } = readJson('obligationTrancheLimits', { limits: {}, obligated: {} });

        // Each project figure a tranche is checked against. `input` is the
        // card field that shows the warning (Total Overall for the total).
        const LIMIT_FIELDS = [
            { key: 'beneficiaries_total', label: 'Beneficiaries', input: 'beneficiaries_total', money: false },
            { key: 'beneficiaries_female', label: 'Female', input: 'beneficiaries_female', money: false },
            { key: 'wages', label: 'Wages', input: 'wages_amount', money: true },
            { key: 'insurance', label: 'Insurance', input: 'insurance_amount', money: true },
            { key: 'ppe', label: 'PPE', input: 'ppe_amount', money: true },
            { key: 'total', label: 'Total Project Cost', input: 'total', money: true },
        ];

        const peso = (cents) => '₱' + (cents / 100).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        const formatValue = (value, money) => money ? peso(value) : Number(value).toLocaleString('en-PH');
        const toCents = (value) => {
            const clean = window.TupadMoney ? window.TupadMoney.unformat(value) : String(value ?? '');
            const [whole, fraction = ''] = clean.split('.');
            return (Number(whole || 0) * 100) + Number((fraction + '00').slice(0, 2));
        };
        const toInt = (value) => Math.max(0, parseInt(value, 10) || 0);
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[char]);

        let rowIndex = 0;
        let exceededMessages = [];

        const inputClass = 'h-10 w-full min-w-0 rounded-lg border border-slate-300 bg-white px-3 text-sm';
        const labelClass = 'mb-1 block text-xs font-semibold text-slate-700';

        function field(index, name, label, value, attributes = '') {
            const messages = errors[`tranches.${index}.${name}`] || [];
            return `
                <label class="block min-w-0">
                    <span class="${labelClass}">${label}</span>
                    <input name="tranches[${index}][${name}]" data-field="${name}" value="${escapeHtml(value)}" ${attributes}
                        class="${inputClass} ${messages.length ? 'border-rose-400' : ''}">
                    ${messages.map((message) => `<span class="mt-1 block text-xs text-rose-600">${escapeHtml(message)}</span>`).join('')}
                    <p data-live-warning="${name}" class="mt-1 hidden text-xs font-semibold text-rose-600"></p>
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
                    ${field(index, 'wages_amount', 'Wages <span class="font-normal text-slate-400">(auto)</span>', values.wages_amount ?? '', 'type="number" min="0" step="0.01" data-money-input')}
                    ${field(index, 'insurance_amount', 'Insurance <span class="font-normal text-slate-400">(auto)</span>', values.insurance_amount ?? '', 'type="number" min="0" step="0.01" data-money-input')}
                    ${field(index, 'ppe_amount', 'PPE (Total Amount)', values.ppe_amount ?? '', 'type="number" min="0" step="0.01" data-money-input')}
                    <label class="block min-w-0">
                        <span class="${labelClass}">Total Overall</span>
                        <input readonly tabindex="-1" data-tranche-total value="₱0.00"
                            class="h-10 w-full min-w-0 rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-bold text-slate-900">
                        <p data-live-warning="total" class="mt-1 hidden text-xs font-semibold text-rose-600"></p>
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

            const beneficiariesInput = card.querySelector('[data-field="beneficiaries_total"]');
            const wagesInput = card.querySelector('[data-field="wages_amount"]');
            const insuranceInput = card.querySelector('[data-field="insurance_amount"]');
            const setMoney = (input, cents) => {
                const decimal = (cents / 100).toFixed(2);
                input.value = window.TupadMoney ? window.TupadMoney.format(decimal) : decimal;
            };

            // Wages = beneficiaries × wage rate × number of days, and
            // Insurance = beneficiaries × insurance rate. Recomputed whenever
            // beneficiaries change; the user may still adjust either one.
            // Auto-filled values are capped at what the project has left so
            // the auto-fill itself never causes an "exceeds" block.
            beneficiariesInput.addEventListener('input', () => {
                const beneficiaries = toInt(beneficiariesInput.value);
                const before = obligatedBefore(card);
                const cap = (key, cents) => Math.max(0, Math.min(cents, Number(limits[key] || 0) - before[key]));

                setMoney(wagesInput, cap('wages', beneficiaries * wagePerBeneficiaryCents));
                setMoney(insuranceInput, cap('insurance', beneficiaries * insurancePerBeneficiaryCents));
            });

            card.querySelectorAll('input[data-field]').forEach((input) => {
                input.addEventListener('input', refresh);
            });
            card.querySelector('[data-remove-tranche]').addEventListener('click', () => {
                card.remove();
                refresh();
            });

            refresh();
            return card;
        }

        // Saved tranches plus every card above the given one.
        function obligatedBefore(card) {
            const running = { ...obligated };

            for (const other of list.querySelectorAll('[data-tranche-card]')) {
                if (other === card) break;

                const values = cardValues(other);
                Object.keys(running).forEach((key) => {
                    running[key] = Number(running[key] || 0) + (values[key] || 0);
                });
            }

            return running;
        }

        // A card counts as blank when nothing but the prefilled date is set;
        // blank cards are skipped by Complete.
        function isBlankCard(card) {
            return ['beneficiaries_total', 'beneficiaries_female', 'wages_amount', 'insurance_amount', 'ppe_amount', 'payee']
                .every((name) => (card.querySelector(`[data-field="${name}"]`)?.value ?? '').trim() === '');
        }

        function missingFields(card, trancheLabel) {
            const value = (name) => (card.querySelector(`[data-field="${name}"]`)?.value ?? '').trim();
            const values = cardValues(card);
            const missing = [];

            if (values.beneficiaries_total < 1) missing.push('no. of beneficiaries');
            if (values.wages <= 0 && values.insurance <= 0 && values.ppe <= 0) missing.push('at least one of wages, insurance, or PPE');
            if (value('obligation_date') === '') missing.push('obligation date');
            if (value('payee') === '') missing.push('payee');

            return missing.length ? `${trancheLabel} – missing ${missing.join(', ')}.` : null;
        }

        function cardValues(card) {
            const value = (name) => card.querySelector(`[data-field="${name}"]`)?.value ?? '';
            const wages = toCents(value('wages_amount'));
            const insurance = toCents(value('insurance_amount'));
            const ppe = toCents(value('ppe_amount'));

            return {
                beneficiaries_total: toInt(value('beneficiaries_total')),
                beneficiaries_female: toInt(value('beneficiaries_female')),
                wages,
                insurance,
                ppe,
                total: wages + insurance + ppe,
            };
        }

        function setWarning(card, name, message) {
            const warning = card.querySelector(`[data-live-warning="${name}"]`);
            const input = name === 'total'
                ? card.querySelector('[data-tranche-total]')
                : card.querySelector(`[data-field="${name}"]`);

            warning?.classList.toggle('hidden', !message);
            if (warning) warning.textContent = message || '';
            input?.classList.toggle('border-rose-400', Boolean(message));
            input?.classList.toggle('ring-1', Boolean(message));
            input?.classList.toggle('ring-rose-300', Boolean(message));
        }

        function renderLimitSummary(running) {
            limitSummary.innerHTML = LIMIT_FIELDS.map(({ key, label, money }) => {
                const limit = Number(limits[key] || 0);
                const used = Number(running[key] || 0);
                const tone = used > limit
                    ? 'border-rose-300 bg-rose-50 text-rose-800'
                    : (used === limit ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-50 text-slate-700');
                const note = used > limit
                    ? `Exceeds by ${formatValue(used - limit, money)}`
                    : `Remaining ${formatValue(limit - used, money)}`;

                return `
                    <div class="rounded-lg border px-3 py-2 ${tone}">
                        <div class="text-[10px] font-bold uppercase tracking-wide opacity-70">${label}</div>
                        <div class="mt-0.5 text-xs font-semibold">${formatValue(used, money)} / ${formatValue(limit, money)}</div>
                        <div class="text-[11px]">${note}</div>
                    </div>`;
            }).join('');
        }

        function refresh() {
            const cards = Array.from(list.querySelectorAll('[data-tranche-card]'));
            const running = { ...obligated };
            exceededMessages = [];

            cards.forEach((card, position) => {
                const trancheLabel = `Tranche ${nextTranche + position}`;
                card.querySelector('[data-tranche-label]').textContent = trancheLabel;
                card.querySelector('[data-remove-tranche]').classList.toggle('hidden', cards.length === 1 && position === 0);

                const values = cardValues(card);
                card.querySelector('[data-tranche-total]').value = peso(values.total);

                LIMIT_FIELDS.forEach(({ key, label, input, money }) => {
                    const limit = Number(limits[key] || 0);
                    const before = Number(running[key] || 0);
                    const after = before + values[key];

                    if (values[key] > 0 && after > limit) {
                        setWarning(
                            card,
                            input,
                            `Exceeds the project's ${label.toLowerCase()} by ${formatValue(after - limit, money)}. `
                                + `Remaining for this tranche: ${formatValue(Math.max(0, limit - before), money)}.`
                        );
                        exceededMessages.push(`${trancheLabel} – ${label}: ${formatValue(after, money)} obligated vs ${formatValue(limit, money)} in the project.`);
                    } else {
                        setWarning(card, input, '');
                    }

                    running[key] = after;
                });

                if (values.beneficiaries_total > 0 && values.beneficiaries_female > values.beneficiaries_total) {
                    setWarning(card, 'beneficiaries_female', 'Female beneficiaries cannot exceed this tranche\'s total beneficiaries.');
                    exceededMessages.push(`${trancheLabel} – Female beneficiaries exceed the tranche's total beneficiaries.`);
                }
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

            renderLimitSummary(running);

            const below = Number(limits.total || 0) - running.total;

            if (exceededMessages.length > 0) {
                completeHint.className = 'text-xs font-semibold text-rose-700';
                completeHint.textContent = 'Some entries exceed the project data. Adjust them before saving.';
            } else if (below > 0) {
                completeHint.className = 'text-xs text-amber-700';
                completeHint.textContent = `Tranches total ${peso(running.total)}, ${peso(below)} below the Total Project Cost. You can still save or complete.`;
            } else {
                completeHint.className = 'text-xs font-semibold text-emerald-700';
                completeHint.textContent = `Tranches equal the Total Project Cost (${peso(Number(limits.total || 0))}).`;
            }

            return running;
        }

        form.querySelector('[data-add-tranche]').addEventListener('click', () => {
            addCard({ obligation_date: today }).querySelector('input')?.focus();
        });

        form.addEventListener('submit', (event) => {
            const running = refresh();
            const completing = event.submitter?.value === 'complete';
            const cards = Array.from(list.querySelectorAll('[data-tranche-card]'));
            const filledCards = cards.filter((card) => !isBlankCard(card));

            // Blank cards are ignored; every other card needs beneficiaries,
            // at least one amount, an obligation date, and a payee.
            const missing = filledCards
                .map((card) => missingFields(card, card.querySelector('[data-tranche-label]').textContent))
                .filter(Boolean);

            if (!completing && filledCards.length === 0) {
                missing.push('Enter at least one tranche before saving.');
            }

            if (missing.length > 0) {
                event.preventDefault();
                window.alert('The tranches cannot be saved yet:\n\n' + missing.join('\n'));
                return;
            }

            if (exceededMessages.length > 0) {
                event.preventDefault();
                window.alert(
                    'The tranches exceed the project data and cannot be saved:\n\n'
                    + exceededMessages.join('\n')
                    + '\n\nTotals may be below the project data, but not above it.'
                );
                return;
            }

            if (event.submitter?.value !== 'complete') {
                return;
            }

            const below = Number(limits.total || 0) - running.total;
            const confirmed = window.confirm(
                'Complete the obligation tranches?\n\n'
                + (below > 0
                    ? `The tranches total ${peso(running.total)}, which is ${peso(below)} below the Total Project Cost.\n\n`
                    : '')
                + 'Any tranche entered on this form will be saved first. After completion the tranches are locked '
                + 'and the project moves on to the Release of Assistance.'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });

        // The submit handler above checks required fields itself (and skips
        // blank cards), so native browser validation would only block silently.
        form.noValidate = true;

        Object.entries(initialRows).forEach(([key, row]) => addCard(row, Number(key)));
        refresh();
    });
</script>
@endpush
