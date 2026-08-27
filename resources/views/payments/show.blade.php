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

@if(
    $project->payout
    && $project->status === \App\Enums\ProjectStatus::COMPLETED
    && ! $summary['is_fully_paid']
)
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <div class="text-sm font-semibold text-amber-900">Legacy release record retained</div>
        <p class="mt-1 text-xs leading-5 text-amber-800">
            This project was completed under the former Release of Assistance workflow on
            {{ $project->payout->payout_date->format('F d, Y') }}. The historical record remains read-only.
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
            ['Project Payable Amount', $summary['payable_cents'], 'text-slate-900'],
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

@if($canAddTranche)
    <section id="add-obligation" class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
        <div class="border-b border-blue-100 bg-blue-50 px-5 py-4">
            <h2 class="text-sm font-semibold text-blue-950">
                {{ $nextTranche === 1 ? 'Add First Obligation' : 'Add Next Tranche' }}
            </h2>
            <p class="mt-1 text-xs text-blue-700">
                Tranche {{ $nextTranche }} of 5 · Maximum amount available to obligate:
                ₱{{ $money($summary['unobligated_cents']) }}
            </p>
        </div>

        <form method="POST" action="{{ route('projects.payment.store', $project) }}" class="p-5">
            @csrf
            <input type="hidden" name="tranche_number" value="{{ $nextTranche }}">

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Tranche Number
                    </label>
                    <input
                        value="Tranche {{ $nextTranche }}"
                        disabled
                        class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-600"
                    >
                    @error('tranche_number')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="amount" class="mb-2 block text-xs font-semibold text-slate-700">
                        Amount
                    </label>
                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        min="0.01"
                        max="{{ $paymentService->centsToDecimal($summary['unobligated_cents']) }}"
                        step="0.01"
                        required
                        value="{{ old('amount', $paymentService->centsToDecimal($summary['unobligated_cents'])) }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                    @error('amount')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="obligation_date" class="mb-2 block text-xs font-semibold text-slate-700">
                        Obligation Date
                    </label>
                    <input
                        id="obligation_date"
                        name="obligation_date"
                        type="date"
                        required
                        value="{{ old('obligation_date', now()->toDateString()) }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                    @error('obligation_date')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="payee" class="mb-2 block text-xs font-semibold text-slate-700">
                        Payee
                    </label>
                    <input
                        id="payee"
                        name="payee"
                        required
                        maxlength="255"
                        value="{{ old('payee') }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                    @error('payee')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="remarks" class="mb-2 block text-xs font-semibold text-slate-700">
                        Remarks <span class="font-normal text-slate-400">(optional)</span>
                    </label>
                    <input
                        id="remarks"
                        name="remarks"
                        maxlength="3000"
                        value="{{ old('remarks') }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]"
                >
                    Save Tranche {{ $nextTranche }} Obligation
                </button>
            </div>
        </form>
    </section>
@elseif(
    $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT
    && $summary['obligated_cents'] === $summary['payable_cents']
)
    <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        The full project payable amount is obligated. Record the remaining tranche disbursements below.
    </div>
@elseif(
    $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT
    && $project->obligations->count() >= 5
)
    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        The five-tranche maximum has been reached. Existing obligations must be disbursed; no additional tranche can be added.
    </div>
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
                        'Obligation Date' => $obligation->obligation_date->format('F d, Y'),
                        'Payee' => $obligation->payee,
                        'Obligation Status' => 'Recorded',
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
                        <table class="min-w-full text-xs">
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
