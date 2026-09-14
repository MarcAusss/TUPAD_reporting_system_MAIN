@extends('layouts.app')

@section('title', 'NGA Target Accomplishment')

@section('content')
    @php
        $summaryCards = [
            ['label' => 'NGAs', 'value' => number_format($targets->count())],
            ['label' => 'Total Beneficiaries (Target)', 'value' => number_format($totals['total_beneficiaries'])],
            ['label' => 'Amount (Target)', 'value' => '₱'.number_format($totals['amount'], 2)],
            ['label' => 'Accomplishments', 'value' => number_format($totals['accomplishments'])],
            ['label' => 'Balance', 'value' => number_format($totals['balance'])],
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header
            eyebrow="Official Reporting"
            title="NGA Target Accomplishment"
            description="Target, accomplished, and balance per National Government Agency / Partner, mirroring the Overall Accomplishment report."
        />

        @if(auth()->user()->isAdmin() || auth()->user()->isFocal())
            <a href="{{ route('targets.index') }}"
                class="inline-flex h-10 shrink-0 items-center rounded-lg border border-blue-200 bg-blue-50 px-4 text-sm font-semibold text-[#063b86] hover:bg-blue-100">
                Manage Targets
            </a>
        @endif
    </div>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($summaryCards as $card)
                <div class="rounded-lg border border-slate-200 px-4 py-3">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.1em] text-slate-400">
                        {{ $card['label'] }}
                    </div>
                    <div class="mt-1 text-lg font-bold text-slate-900">
                        {{ $card['value'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <form method="GET" action="{{ route('reports.workspace.nga-targets') }}" class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <label for="nga-report-search" class="sr-only">Search NGA</label>
                <input id="nga-report-search" name="q" value="{{ $search }}"
                    placeholder="Search NGA / Partner..."
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>

            <button type="submit" class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Search
            </button>

            @if($search !== '')
                <a href="{{ route('reports.workspace.nga-targets') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Target &middot; Accomplished &middot; Balance</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="tupad-system-table min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">NGA / Partner</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Target (Beneficiaries)</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Target (Amount)</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Accomplished</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Balance</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">% Accomplished</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($targets as $target)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4 text-sm font-semibold text-slate-900">{{ $target->nga }}</td>
                            <td class="px-5 py-4 text-right text-sm text-slate-600">{{ number_format($target->total_beneficiaries) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-slate-600">₱{{ number_format($target->amount, 2) }}</td>
                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800">{{ number_format($target->accomplishments) }}</td>
                            <td class="px-5 py-4 text-right text-sm font-semibold {{ $target->balance <= 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ number_format($target->balance) }}
                            </td>
                            <td class="px-5 py-4 text-right text-sm text-slate-600">{{ $target->accomplishmentPercent() }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="text-sm font-semibold text-slate-700">No NGA targets recorded yet.</div>
                                @if(auth()->user()->isAdmin() || auth()->user()->isFocal())
                                    <p class="mt-1 text-xs text-slate-400">
                                        <a href="{{ route('targets.create') }}" class="font-semibold text-blue-700 hover:underline">Create the first target</a>
                                        to see it reflected here.
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($targets->isNotEmpty())
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td class="px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500">Total</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-slate-900">{{ number_format($totals['total_beneficiaries']) }}</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-slate-900">₱{{ number_format($totals['amount'], 2) }}</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-slate-900">{{ number_format($totals['accomplishments']) }}</td>
                            <td class="px-5 py-3 text-right text-sm font-bold text-slate-900">{{ number_format($totals['balance']) }}</td>
                            <td class="px-5 py-3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@endsection
