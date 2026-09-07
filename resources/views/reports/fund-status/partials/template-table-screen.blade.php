@php
    $template = $report['fund_status_template'];
    $templateMoney = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
    $templatePercent = static fn (mixed $value): string => number_format((float) $value, 2) . '%';
    $templateNumber = static fn (mixed $value): string => number_format((int) $value);
@endphp

<div class="overflow-x-auto">
    @if ($template['kind'] === 'utilization')
        <table class="tupad-system-table tupad-report-screen-table min-w-[920px] w-full text-xs" aria-label="Fund Utilization Report">
            <thead>
                <tr class="bg-[#071d44] text-white">
                    <th rowspan="2" class="border border-[#26456f] px-4 py-3 text-left font-bold">Province</th>
                    <th rowspan="2" class="border border-[#26456f] px-4 py-3 text-right font-bold">TUPAD Allocation</th>
                    <th colspan="3" class="border border-[#26456f] px-4 py-3 text-center font-bold">Accomplishment</th>
                    <th rowspan="2" class="border border-[#26456f] px-4 py-3 text-right font-bold">Balance</th>
                </tr>
                <tr class="bg-[#dbe8f7] text-[#10294f]">
                    <th class="border border-[#b8cbe2] px-4 py-2 text-right font-bold">Amount</th>
                    <th class="border border-[#b8cbe2] px-4 py-2 text-right font-bold">%</th>
                    <th class="border border-[#b8cbe2] px-4 py-2 text-right font-bold">Ben.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($template['rows'] as $row)
                    <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-blue-50/70">
                        <td class="border-x border-slate-200 px-4 py-3 font-semibold text-slate-800">{{ $row['province'] }}</td>
                        <td class="border-x border-slate-200 px-4 py-3 text-right tabular-nums text-slate-700">{{ $templateMoney($row['allocation_cents']) }}</td>
                        <td class="border-x border-slate-200 px-4 py-3 text-right tabular-nums text-slate-700">{{ $templateMoney($row['accomplishment_cents']) }}</td>
                        <td class="border-x border-slate-200 px-4 py-3 text-right tabular-nums text-slate-700">{{ $templatePercent($row['accomplishment_rate']) }}</td>
                        <td class="border-x border-slate-200 px-4 py-3 text-right tabular-nums text-slate-700">{{ $templateNumber($row['accomplishment_beneficiaries']) }}</td>
                        <td class="border-x border-slate-200 px-4 py-3 text-right tabular-nums font-semibold text-slate-800">{{ $templateMoney($row['balance_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No fund utilization rows matched the selected criteria.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-[#10294f] font-bold text-white">
                    <td class="border border-[#26456f] px-4 py-3">TOTAL</td>
                    <td class="border border-[#26456f] px-4 py-3 text-right tabular-nums">{{ $templateMoney($template['totals']['allocation_cents']) }}</td>
                    <td class="border border-[#26456f] px-4 py-3 text-right tabular-nums">{{ $templateMoney($template['totals']['accomplishment_cents']) }}</td>
                    <td class="border border-[#26456f] px-4 py-3 text-right tabular-nums">{{ $templatePercent($template['totals']['accomplishment_rate']) }}</td>
                    <td class="border border-[#26456f] px-4 py-3 text-right tabular-nums">{{ $templateNumber($template['totals']['accomplishment_beneficiaries']) }}</td>
                    <td class="border border-[#26456f] px-4 py-3 text-right tabular-nums">{{ $templateMoney($template['totals']['balance_cents']) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <table class="tupad-system-table tupad-report-screen-table tupad-wide-table min-w-[1450px] w-full text-xs" aria-label="{{ $template['title'] }}">
            <thead>
                <tr class="bg-[#071d44] text-white">
                    <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">ADL No.</th>
                    <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Fund Sponsor</th>
                    <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Partner</th>
                    <th colspan="3" class="border border-[#26456f] px-3 py-3 text-center font-bold">Location</th>
                    <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-right font-bold">TUPAD Allocation</th>
                    <th colspan="3" class="border border-[#26456f] px-3 py-3 text-center font-bold">Accomplishment</th>
                    <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-right font-bold">Balance</th>
                </tr>
                <tr class="bg-[#dbe8f7] text-[#10294f]">
                    <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">Municipality</th>
                    <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">District</th>
                    <th class="border border-[#b8cbe2] px-3 py-2 text-left font-bold">Province</th>
                    <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Amount</th>
                    <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">%</th>
                    <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Ben.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($template['groups'] as $group)
                    @foreach ($group['rows'] as $row)
                        <tr class="odd:bg-white even:bg-slate-50/70 hover:bg-blue-50/70">
                            <td class="border border-slate-200 px-3 py-3 font-semibold text-slate-800">{{ $row['adl_number'] }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-slate-700">{{ $row['fund_sponsor'] }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-slate-700">{{ $row['partner'] }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-slate-700">{{ $row['municipality'] }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-slate-700">{{ $row['district'] }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-slate-700">{{ $row['province'] }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-right tabular-nums text-slate-700">{{ $templateMoney($row['allocation_cents']) }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-right tabular-nums text-slate-700">{{ $templateMoney($row['accomplishment_cents']) }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-right tabular-nums text-slate-700">{{ $templatePercent($row['accomplishment_rate']) }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-right tabular-nums text-slate-700">{{ $templateNumber($row['accomplishment_beneficiaries']) }}</td>
                            <td class="border border-slate-200 px-3 py-3 text-right tabular-nums font-semibold text-slate-800">{{ $templateMoney($row['balance_cents']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-[#e7f0fb] font-bold text-[#10294f]">
                        <td colspan="6" class="border border-[#b8cbe2] px-3 py-2.5">Sub-total</td>
                        <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $templateMoney($group['subtotal']['allocation_cents']) }}</td>
                        <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $templateMoney($group['subtotal']['accomplishment_cents']) }}</td>
                        <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $templatePercent($group['subtotal']['accomplishment_rate']) }}</td>
                        <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $templateNumber($group['subtotal']['accomplishment_beneficiaries']) }}</td>
                        <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ $templateMoney($group['subtotal']['balance_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-5 py-12 text-center text-sm text-slate-500">No allocation breakdown matched the selected criteria.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-[#10294f] font-bold text-white">
                    <td colspan="6" class="border border-[#26456f] px-3 py-3">TOTAL</td>
                    <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $templateMoney($template['totals']['allocation_cents']) }}</td>
                    <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $templateMoney($template['totals']['accomplishment_cents']) }}</td>
                    <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $templatePercent($template['totals']['accomplishment_rate']) }}</td>
                    <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $templateNumber($template['totals']['accomplishment_beneficiaries']) }}</td>
                    <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ $templateMoney($template['totals']['balance_cents']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
