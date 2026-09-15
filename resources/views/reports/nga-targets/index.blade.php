@extends('layouts.app')

@section('title', 'NGA Target Accomplishment')

@section('content')
    @php
        $money = static fn (mixed $value): string => '₱'.number_format((float) $value, 2);
        $number = static fn (mixed $value): string => number_format((int) $value);

        $summaryCards = [
            ['label' => 'Projects', 'value' => $number($projectCount)],
            ['label' => 'Target Benefs', 'value' => $number($totals['target_beneficiaries'])],
            ['label' => 'Target Amount', 'value' => $money($totals['target_amount'])],
            ['label' => 'Accomplished Benefs', 'value' => $number($totals['accomplished_beneficiaries'])],
            ['label' => 'Accomplished Amount', 'value' => $money($totals['accomplished_amount'])],
            ['label' => 'Balance Benefs', 'value' => $number($totals['balance_beneficiaries'])],
            ['label' => 'Balance Amount', 'value' => $money($totals['balance_amount'])],
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header
            eyebrow="Official Reporting"
            title="NGA Target Accomplishment"
            description="Agency, Program, Target, Accomplished, and Balance figures per project."
        />
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
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
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="agency" class="mb-2 block text-xs font-semibold text-slate-700">Agency</label>
                <select id="agency" name="agency" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">All agencies</option>
                    @foreach($agencyOptions as $option)
                        <option value="{{ $option }}" @selected($agencyFilter === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="program" class="mb-2 block text-xs font-semibold text-slate-700">Program</label>
                <select id="program" name="program" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">All programs</option>
                    @foreach($programOptions as $option)
                        <option value="{{ $option }}" @selected($programFilter === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2 xl:col-span-2">
                <label for="nga-report-search" class="mb-2 block text-xs font-semibold text-slate-700">Search Municipality / Province</label>
                <input id="nga-report-search" name="q" value="{{ $search }}"
                    placeholder="Search municipality or province..."
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit" class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Apply
            </button>

            @if($search !== '' || $agencyFilter !== '' || $programFilter !== '')
                <a href="{{ route('reports.workspace.nga-targets') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
            @endif
        </div>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Agency &middot; Program &middot; Target &middot; Accomplished &middot; Balance</h2>
            <p class="mt-1 text-xs text-slate-500">
                Rows are arranged by Agency, and within each Agency by province in the official Bicol order: Albay,
                Camarines Norte, Camarines Sur, Catanduanes, Masbate, Sorsogon.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-max border-collapse text-[11px] text-slate-800">
                <thead>
                    <tr>
                        <th rowspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-left font-extrabold text-slate-900">AGENCY</th>
                        <th rowspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-left font-extrabold text-slate-900">PROGRAM</th>
                        <th rowspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-left font-extrabold text-slate-900">MUNICIPALITY</th>
                        <th rowspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-left font-extrabold text-slate-900">PROVINCE</th>
                        <th rowspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-center font-extrabold text-slate-900">NO. OF<br>DAYS</th>
                        <th colspan="2" class="border border-slate-700 bg-[#8fb8de] px-3 py-2 text-center font-extrabold text-slate-900">TARGET</th>
                        <th colspan="2" class="border border-slate-700 bg-[#e08070] px-3 py-2 text-center font-extrabold text-slate-900">ACCOMPLISHED</th>
                        <th colspan="2" class="border border-slate-700 bg-[#c090c0] px-3 py-2 text-center font-extrabold text-slate-900">BALANCE</th>
                    </tr>
                    <tr>
                        <th class="border border-slate-700 bg-[#d7e5f4] px-3 py-1.5 text-center font-bold">BENEFS</th>
                        <th class="border border-slate-700 bg-[#d7e5f4] px-3 py-1.5 text-center font-bold">AMOUNT</th>
                        <th class="border border-slate-700 bg-[#f3d9d3] px-3 py-1.5 text-center font-bold">BENEFS</th>
                        <th class="border border-slate-700 bg-[#f3d9d3] px-3 py-1.5 text-center font-bold">AMOUNT</th>
                        <th class="border border-slate-700 bg-[#ecdcec] px-3 py-1.5 text-center font-bold">BENEFS</th>
                        <th class="border border-slate-700 bg-[#ecdcec] px-3 py-1.5 text-center font-bold">AMOUNT</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)
                        @include('reports.nga-targets._row', ['row' => $row, 'number' => $number, 'money' => $money])
                    @empty
                        <tr>
                            <td colspan="11" class="border border-slate-200 px-5 py-12 text-center">
                                <div class="text-sm font-semibold text-slate-700">No projects match this filter yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if($rows->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-900 font-extrabold text-white">
                            <td colspan="5" class="border border-slate-700 px-3 py-2">TOTAL</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number($totals['target_beneficiaries']) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money($totals['target_amount']) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number($totals['accomplished_beneficiaries']) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money($totals['accomplished_amount']) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number($totals['balance_beneficiaries']) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money($totals['balance_amount']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-[11px] leading-5 text-slate-600">
            <strong class="text-slate-700">Reporting basis:</strong>
            Agency is the project's Partner. Target is the project's own declared Beneficiaries / Total Project Cost.
            Accomplished totals actual disbursements (Direct Administration) or the ACP payment (Through ACP); once
            any amount is actually paid, all Target beneficiaries are counted as accomplished, since per-payment
            beneficiary counts are not separately tracked. Balance is Target less Accomplished.
        </div>
    </section>
@endsection
