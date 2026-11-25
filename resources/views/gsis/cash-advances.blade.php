@extends('layouts.app')

@section('title', 'CA (Balance Calculation)')

@php
    $peso = fn (int $cents): string => '₱'.number_format($cents / 100, 2);
    $voucherTotal = $voucherEnrollments->sum(fn ($enrollment) => $enrollment->totalCents());
    $caTotal = $cashAdvances->sum(fn ($cashAdvance) => $cashAdvance->amountCents());
    $disbursedTotal = $cashAdvances->sum(fn ($cashAdvance) => $cashAdvance->disbursedCents());
@endphp

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        title="CA (Balance Calculation)"
        description="Each Cash Advance and the CA-series transmittals paid from it. The balance is what remains for liquidation. Voucher-paid transmittals are listed separately."
    >
        <x-slot:actions>
            @include('gsis.partials.year-filter', ['route' => 'gsis.cash-advances'])
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Cash Advances FY '.$fiscalYear, $peso((int) $caTotal)],
            ['CA Disbursements', $peso((int) $disbursedTotal)],
            ['CA Balance (for Liquidation)', $peso((int) ($caTotal - $disbursedTotal))],
            ['Voucher Payments', $peso((int) $voucherTotal)],
        ] as [$label, $value])
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $label }}</div>
                <div class="mt-1.5 text-xl font-extrabold text-slate-900">{{ $value }}</div>
            </article>
        @endforeach
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_22rem]">
        <div class="space-y-5">
            @forelse ($cashAdvances as $cashAdvance)
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-[#f4f7fc] px-5 py-3">
                        <div>
                            <h2 class="text-sm font-bold text-[#17325c]">{{ $cashAdvance->label() }}</h2>
                            @if ($cashAdvance->reference || $cashAdvance->remarks)
                                <p class="text-xs text-slate-500">{{ collect([$cashAdvance->reference, $cashAdvance->remarks])->filter()->implode(' · ') }}</p>
                            @endif
                        </div>
                        <div class="text-right text-xs">
                            <div class="text-slate-500">Cash Advance <b class="text-slate-900">{{ $peso($cashAdvance->amountCents()) }}</b></div>
                            <div class="{{ $cashAdvance->balanceCents() < 0 ? 'text-red-700' : 'text-emerald-700' }}">Balance <b>{{ $peso($cashAdvance->balanceCents()) }}</b> · Liquidation ({{ number_format($cashAdvance->balanceCents() / 100, 2) }})</div>
                        </div>
                    </div>
                    @php
                        $caErrors = $errors->getBag('cash_advance_'.$cashAdvance->id);
                    @endphp
                    <details class="border-b border-slate-200 px-5 py-2" @if ($caErrors->any()) open @endif>
                        <summary class="cursor-pointer text-xs font-semibold text-[#063b86]">Edit Cash Advance</summary>
                        <form method="POST" action="{{ route('gsis.cash-advances.update', $cashAdvance) }}" class="grid gap-3 py-3 sm:grid-cols-4">
                            @csrf
                            @method('PUT')
                            @foreach ($caErrors->all() as $caError)
                                <p class="text-xs text-red-700 sm:col-span-4">{{ $caError }}</p>
                            @endforeach
                            <label class="block text-xs font-semibold text-slate-700">CA Date
                                <input type="date" name="cash_advance_date" required
                                    value="{{ $caErrors->any() ? old('cash_advance_date') : $cashAdvance->cash_advance_date->toDateString() }}"
                                    class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2 text-sm">
                            </label>
                            <label class="block text-xs font-semibold text-slate-700">Amount
                                <input type="number" step="0.01" min="0.01" name="amount" required
                                    value="{{ $caErrors->any() ? old('amount') : number_format((float) $cashAdvance->amount, 2, '.', '') }}"
                                    class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2 text-sm">
                            </label>
                            <label class="block text-xs font-semibold text-slate-700">Reference
                                <input name="reference" maxlength="150"
                                    value="{{ $caErrors->any() ? old('reference') : $cashAdvance->reference }}"
                                    class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2 text-sm">
                            </label>
                            <label class="block text-xs font-semibold text-slate-700">Remarks
                                <input name="remarks" maxlength="3000"
                                    value="{{ $caErrors->any() ? old('remarks') : $cashAdvance->remarks }}"
                                    class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2 text-sm">
                            </label>
                            <div class="text-right sm:col-span-4">
                                <button class="inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">Save Changes</button>
                            </div>
                        </form>
                    </details>
                    <div class="overflow-x-auto"><table class="tupad-system-table min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="p-3 text-left">GPAI No.</th>
                                <th class="p-3 text-left">Transmittal Date</th>
                                <th class="p-3 text-right">Beneficiaries</th>
                                <th class="p-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cashAdvance->enrollments as $enrollment)
                                <tr>
                                    <td class="p-3"><a href="{{ route('gsis.enrollments.edit', $enrollment) }}" class="font-semibold text-[#063b86] hover:underline">{{ $enrollment->gpai_number }}</a></td>
                                    <td class="p-3">{{ $enrollment->transmittal_date->format('M j, Y') }}</td>
                                    <td class="p-3 text-right">{{ number_format($enrollment->beneficiaries()) }}</td>
                                    <td class="p-3 text-right">{{ $peso($enrollment->totalCents()) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-5 text-center text-xs text-slate-400">No CA transmittals charged yet.</td></tr>
                            @endforelse
                            <tr class="font-bold">
                                <td colspan="3" class="p-3">DISBURSEMENTS</td>
                                <td class="p-3 text-right">{{ $peso($cashAdvance->disbursedCents()) }}</td>
                            </tr>
                        </tbody>
                    </table></div>
                </section>
            @empty
                <section class="rounded-xl border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500 shadow-sm">
                    No cash advance for FY {{ $fiscalYear }} yet. Add one to enroll projects through CA.
                </section>
            @endforelse
        </div>

        <aside class="space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Add Cash Advance</h2>
                </div>
                <form method="POST" action="{{ route('gsis.cash-advances.store') }}" class="space-y-3 p-5">
                    @csrf
                    <div>
                        <label for="cash_advance_date" class="mb-1 block text-xs font-semibold text-slate-700">CA Date</label>
                        <input id="cash_advance_date" type="date" name="cash_advance_date" required value="{{ old('cash_advance_date', now()->toDateString()) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                    <div>
                        <label for="amount" class="mb-1 block text-xs font-semibold text-slate-700">Amount</label>
                        <input id="amount" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                    <div>
                        <label for="reference" class="mb-1 block text-xs font-semibold text-slate-700">Reference <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="reference" name="reference" maxlength="150" value="{{ old('reference') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                    <div>
                        <label for="ca_remarks" class="mb-1 block text-xs font-semibold text-slate-700">Remarks</label>
                        <textarea id="ca_remarks" name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('remarks') }}</textarea>
                    </div>
                    <div class="text-right">
                        <button class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">Save Cash Advance</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Voucher</h2>
                    <span class="text-sm font-bold text-slate-900">{{ $peso((int) $voucherTotal) }}</span>
                </div>
                <div class="overflow-x-auto"><table class="tupad-system-table min-w-full text-sm">
                    <tbody>
                        @forelse ($voucherEnrollments as $enrollment)
                            <tr>
                                <td class="p-3"><a href="{{ route('gsis.enrollments.edit', $enrollment) }}" class="font-semibold text-[#063b86] hover:underline">{{ $enrollment->gpai_number }}</a></td>
                                <td class="p-3 text-right">{{ $peso($enrollment->totalCents()) }}</td>
                            </tr>
                        @empty
                            <tr><td class="p-5 text-center text-xs text-slate-400">No voucher transmittals for FY {{ $fiscalYear }}.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </section>
        </aside>
    </div>
@endsection
