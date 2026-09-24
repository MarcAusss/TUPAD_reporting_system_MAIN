@php
    $bcbMoney = fn (int $cents) => '₱' . number_format($cents / 100, 2);
@endphp

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-barangay-cost-breakdown>
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Connected to Beneficiary Mapping Source
            </div>
            <h2 class="mt-1 text-sm font-semibold text-slate-900">Barangay Cost &amp; PPE Classification</h2>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                Wages, insurance, and PPE cost broken down per beneficiary barangay, with hazardous vs non-hazardous
                worker counts derived from the declared PPE distribution.
            </p>
        </div>

        @if ($barangayCostBreakdown['basis'] === 'estimated')
            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-800">
                Estimated
            </span>
        @endif
    </div>

    @if ($barangayCostBreakdown['status']['no_addresses'])
        <div class="p-5">
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-xs text-slate-500">
                Save the Beneficiary Mapping Source above to see the cost per barangay.
            </div>
        </div>
    @else
        <div class="border-b border-slate-200 bg-slate-50/60 px-5 py-4">
            @if ($barangayCostBreakdown['basis'] === 'estimated')
                {{-- <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-5 text-amber-800">
                    This breakdown is estimated by spreading each PPE item and hazardous headcount across barangays
                    proportionally. Admin/TC: encode the PPE distribution in the Beneficiary Mapping Source card above
                    for an exact, per-barangay breakdown.
                </div> --}}
            @endif

            @if ($barangayCostBreakdown['status']['addresses_out_of_date'])
                <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-5 text-amber-800">
                    The beneficiary address allocation no longer matches this project's declared Total/Female
                    Beneficiaries. Re-save the Beneficiary Mapping Source above to bring it back in sync.
                </div>
            @endif

            @if ($barangayCostBreakdown['status']['distribution_invalid'])
                <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-[11px] leading-5 text-red-700">
                    The saved PPE distribution no longer fits the current beneficiary allocation (a barangay's
                    recipients or hazardous headcount exceeds its current total). Re-save the distribution above to
                    correct it.
                </div>
            @endif

            @php($bcbTotalDiff = $barangayCostBreakdown['reconciliation']['total']['difference_cents'])
            @if ($bcbTotalDiff === 0)
                <div class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-700">
                    <span aria-hidden="true">✓</span>
                    Matches Total Project Cost {{ $bcbMoney($barangayCostBreakdown['reconciliation']['total']['stored_cents']) }}
                </div>
            @else
                <div class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800">
                    <span aria-hidden="true">⚠</span>
                    Computed total {{ $bcbMoney($barangayCostBreakdown['reconciliation']['total']['computed_cents']) }}
                    differs from Total Project Cost {{ $bcbMoney($barangayCostBreakdown['reconciliation']['total']['stored_cents']) }}
                    by {{ $bcbMoney(abs($bcbTotalDiff)) }}
                </div>
            @endif

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Working Days</div>
                    <div class="mt-1 text-sm font-bold text-slate-900">{{ number_format($barangayCostBreakdown['rates']['working_days']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Wage / Beneficiary</div>
                    <div class="mt-1 flex flex-wrap items-baseline gap-x-1.5">
                        <span class="text-xs font-medium text-slate-500">
                            {{ $bcbMoney($barangayCostBreakdown['rates']['wage_rate_cents']) }} &times; {{ number_format($barangayCostBreakdown['rates']['working_days']) }} =
                        </span>
                        <span class="text-base font-bold text-slate-900">{{ $bcbMoney($barangayCostBreakdown['rates']['wage_per_beneficiary_cents']) }}</span>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Insurance / Beneficiary</div>
                    <div class="mt-1 text-sm font-bold text-slate-900">{{ $bcbMoney($barangayCostBreakdown['rates']['insurance_per_beneficiary_cents']) }}</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Hazardous / Non-hazardous</div>
                    <div class="mt-1 text-sm font-bold text-slate-900">
                        {{ number_format($barangayCostBreakdown['totals']['hazardous']) }} /
                        {{ number_format($barangayCostBreakdown['totals']['non_hazardous']) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="tupad-system-table min-w-190 w-full">
                <thead class="bg-slate-50">
                    <tr class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                        <th class="px-4 py-2 text-left" colspan="4">Location</th>
                        <th class="px-4 py-2 text-center border-l border-slate-200" colspan="2">Total</th>
                        <th class="px-4 py-2 text-center border-l border-slate-200" colspan="2">Female</th>
                    </tr>
                    <tr class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                        <th class="px-4 py-2 text-left">Barangay</th>
                        <th class="px-4 py-2 text-left">Municipality</th>
                        <th class="px-4 py-2 text-left">District</th>
                        <th class="px-4 py-2 text-left">Province</th>
                        <th class="px-4 py-2 text-right border-l border-slate-200">Bene</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right border-l border-slate-200">Bene</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($barangayCostBreakdown['rows'] as $bcbRow)
                        <tr>
                            <td class="px-4 py-3 text-xs font-semibold text-slate-800">{{ $bcbRow['barangay'] }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $bcbRow['municipality'] }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $bcbRow['district'] }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $bcbRow['province'] }}</td>
                            <td class="px-4 py-3 text-right text-xs font-semibold text-slate-900 border-l border-slate-100">{{ number_format($bcbRow['beneficiaries']) }}</td>
                            <td class="px-4 py-3 text-right text-xs font-bold text-slate-950">{{ $bcbMoney($bcbRow['total_cents']) }}</td>
                            <td class="px-4 py-3 text-right text-xs font-semibold text-slate-900 border-l border-slate-100">{{ number_format($bcbRow['female']) }}</td>
                            <td class="px-4 py-3 text-right text-xs font-bold text-slate-950">{{ $bcbMoney($bcbRow['female_amount_cents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-slate-200 bg-slate-50">
                    <tr class="text-xs font-bold text-slate-900">
                        <td class="px-4 py-3" colspan="4">Totals</td>
                        <td class="px-4 py-3 text-right border-l border-slate-200">{{ number_format($barangayCostBreakdown['totals']['beneficiaries']) }}</td>
                        <td class="px-4 py-3 text-right">{{ $bcbMoney($barangayCostBreakdown['totals']['total_cents']) }}</td>
                        <td class="px-4 py-3 text-right border-l border-slate-200">{{ number_format($barangayCostBreakdown['totals']['female']) }}</td>
                        <td class="px-4 py-3 text-right">{{ $bcbMoney($barangayCostBreakdown['totals']['female_amount_cents']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</section>
