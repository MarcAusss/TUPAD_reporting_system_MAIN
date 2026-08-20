@extends('layouts.app')

@section('title', $adl->adl_number)

@section('content')

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <a href="{{ route('adl.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← ADL Management
            </a>

            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                {{ $adl->adl_number }}
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Financial breakdown and allocation monitoring.
            </p>

        </div>

        <a href="{{ route('adl.edit', $adl) }}"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Edit ADL
        </a>

    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

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

    {{-- <------------------------------------------- Financial Summary ------------------------------/> --}}

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Original Grants
            </div>

            <div class="mt-3 text-2xl font-bold text-slate-900">
                ₱{{ number_format($adl->grants, 2) }}
            </div>

        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Re-alignment
            </div>

            <div
                class="mt-3 text-2xl font-bold
            {{ $adl->total_realignment < 0 ? 'text-red-600' : 'text-slate-900' }}">
                ₱{{ number_format($adl->total_realignment, 2) }}
            </div>

        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Adjusted Grants
            </div>

            <div class="mt-3 text-2xl font-bold text-slate-900">
                ₱{{ number_format($adl->adjusted_grants, 2) }}
            </div>

        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Remaining
            </div>

            <div
                class="mt-3 text-2xl font-bold
            {{ $adl->remaining_balance <= 0 ? 'text-red-600' : 'text-emerald-700' }}">
                ₱{{ number_format($adl->remaining_balance, 2) }}
            </div>

        </div>

    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">

        {{-- <------------------------------------------- Re-alignment ------------------------------/> --}}

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Record Re-alignment
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Use a positive value for additional funds and a negative value for deductions.
                </p>

            </div>

            <form method="POST" action="{{ route('adl.realignments.store', $adl) }}" class="space-y-4 p-5">

                @csrf

                <div class="grid gap-4 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Re-alignment Amount
                        </label>

                        <input name="amount" type="number" step="0.01" required placeholder="-500000.00 or 500000.00"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date
                        </label>

                        <input name="realignment_date" type="date" value="{{ now()->format('Y-m-d') }}" required
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    </div>

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Reference Number
                    </label>

                    <input name="reference_number" type="text"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Reason / Remarks
                    </label>

                    <textarea name="reason" rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"></textarea>

                </div>

                <div class="flex justify-end">

                    <button type="submit"
                        class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        Save Re-alignment
                    </button>

                </div>

            </form>

        </section>

        {{-- <------------------------------------------- Allocation ------------------------------/> --}}

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Add Allocation
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Remaining available grant: ₱{{ number_format($adl->remaining_balance, 2) }}
                </p>

            </div>

            <form method="POST" action="{{ route('adl.allocations.store', $adl) }}" class="space-y-4 p-5">

                @csrf

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Fund Sponsor
                    </label>

                    <input name="fund_sponsor" type="text" required
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                </div>

                <div class="grid gap-4 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Partner
                        </label>

                        <input name="partner" type="text" required
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Location
                        </label>

                        <input name="location" type="text" required
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    </div>

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Amount
                    </label>

                    <input name="amount" type="number" step="0.01" min="0.01" required
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Remarks
                    </label>

                    <textarea name="remarks" rows="2"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200"></textarea>

                </div>

                <div class="flex justify-end">

                    <button type="submit"
                        class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        Save Allocation
                    </button>

                </div>

            </form>

        </section>

    </div>

    {{-- <------------------------------------------- Allocation History ------------------------------/> --}}

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Allocation Breakdown
            </h2>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Sponsor
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Partner
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Location
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Amount
                        </th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($adl->allocations as $allocation)
                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $allocation->fund_sponsor }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $allocation->partner }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $allocation->location }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                ₱{{ number_format($allocation->amount, 2) }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">
                                No allocation records yet.
                            </td>
                        </tr>
                    @endforelse

                </tbody>

                @if ($adl->allocations->isNotEmpty())
                    <tfoot class="border-t border-slate-200 bg-slate-50">

                        <tr>

                            <td colspan="3" class="px-5 py-4 text-right text-sm font-semibold text-slate-600">
                                Total Allocated
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-bold text-slate-900">
                                ₱{{ number_format($adl->total_allocated, 2) }}
                            </td>

                        </tr>

                    </tfoot>
                @endif

            </table>

        </div>

    </section>

    {{-- <------------------------------------------- Re-alignment History ------------------------------/> --}}

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Re-alignment History
            </h2>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Reference
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Reason
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Amount
                        </th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($adl->realignments as $realignment)
                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $realignment->realignment_date->format('M d, Y') }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $realignment->reference_number ?: '—' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-500">
                                {{ $realignment->reason ?: '—' }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold
                            {{ $realignment->amount < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                {{ $realignment->amount > 0 ? '+' : '' }}
                                ₱{{ number_format($realignment->amount, 2) }}
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-400">
                                No re-alignment has been recorded.
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endsection
