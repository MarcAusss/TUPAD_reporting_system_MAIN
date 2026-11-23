{{-- CQPR in the approved template layout: one table (a selected quarter, or all quarters together). --}}
@php
    $cqprMoney = static fn (mixed $cents): string => number_format(((int) $cents) / 100, 2);
    $cqprNumber = static fn (mixed $value): string => number_format((int) $value);
@endphp

@foreach ($cqpr['sections'] as $section)
    <div @class(['border-t border-slate-200' => ! $loop->first])>
        @if (count($cqpr['sections']) > 1)
            <div class="flex items-center justify-between bg-[#f3f7fc] px-5 py-3">
                <h3 class="text-xs font-extrabold uppercase tracking-[0.12em] text-[#063b86]">Quarter {{ $section['quarter'] }} · {{ $section['label'] }}</h3>
                <span class="text-[11px] font-semibold text-slate-500">{{ $cqprNumber($section['groups']->sum(fn ($group) => $group['rows']->count())) }} project(s)</span>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="tupad-report-table tupad-system-table tupad-report-screen-table tupad-wide-table min-w-[1800px] w-full text-xs" aria-label="CQPR {{ $section['label'] }}">
                <thead>
                    <tr class="bg-[#071d44] text-white">
                        <th rowspan="2" class="min-w-56 border border-[#26456f] px-3 py-3 text-left font-bold">Name &amp; Nature of Project</th>
                        <th rowspan="2" class="min-w-40 border border-[#26456f] px-3 py-3 text-left font-bold">Name of Implementer<br><span class="text-[11px] font-medium text-blue-200">(DOLE Office or Co-partner)</span></th>
                        <th colspan="5" class="border border-[#26456f] px-3 py-2 text-center font-bold">Project Location</th>
                        <th colspan="2" class="border border-[#26456f] px-3 py-2 text-center font-bold">Work Period Category</th>
                        <th colspan="3" class="border border-[#26456f] px-3 py-2 text-center font-bold">Beneficiaries</th>
                        <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-right font-bold">Amount Released (₱)</th>
                        <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Date Released</th>
                        <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Fund Source</th>
                        <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Convergence Project / Partner</th>
                        <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Status</th>
                    </tr>
                    <tr class="bg-[#dbe8f7] text-[#10294f]">
                        <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">Barangay</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">City / Municipality</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">Province</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">District</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">Income Class</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-center font-bold">Short-term</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-center font-bold">Long-term</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Total</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Female</th>
                        <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['groups'] as $group)
                        @foreach ($group['rows'] as $row)
                            <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-blue-50/70">
                                <td class="border border-slate-200 px-3 py-2.5">
                                    <div class="font-semibold text-slate-900">{{ $row['project'] }}</div>
                                    @if (filled($row['nature']))<div class="mt-0.5 text-[11px] text-slate-500">{{ $row['nature'] }}</div>@endif
                                </td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['implementer'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['barangay'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['municipality'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['province'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['district'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['income_class'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-center font-bold text-[#063b86]">{{ $row['short_term'] ? '✓' : '' }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-center font-bold text-[#063b86]">{{ $row['long_term'] ? '✓' : '' }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $cqprNumber($row['beneficiaries']) }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-right tabular-nums text-slate-700">{{ $cqprNumber($row['female']) }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['type'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-right tabular-nums font-semibold text-slate-800">{{ $cqprMoney($row['amount_cents']) }}</td>
                                <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['date_released'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['fund_source'] }}</td>
                                <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['convergence'] }}</td>
                                <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="tupad-report-subtotal-row bg-[#e7f0fb] font-bold text-[#10294f]">
                            <td colspan="1" class="border border-[#b8cbe2] px-3 py-2.5">Sub-Total ({{ $group['label'] }})</td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $cqprNumber($group['subtotal']['beneficiaries']) }}</td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $cqprNumber($group['subtotal']['female']) }}</td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5"></td>
                            <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $cqprMoney($group['subtotal']['amount_cents']) }}</td>
                            <td colspan="4" class="border border-[#b8cbe2] px-3 py-2.5"></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-[#10294f] font-bold text-white">
                        <td colspan="9" class="border border-[#26456f] px-3 py-3">TOTAL</td>
                        <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $cqprNumber($section['totals']['beneficiaries']) }}</td>
                        <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $cqprNumber($section['totals']['female']) }}</td>
                        <td class="border border-[#26456f] px-3 py-3"></td>
                        <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $cqprMoney($section['totals']['amount_cents']) }}</td>
                        <td colspan="4" class="border border-[#26456f] px-3 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endforeach
