@php
    $template = $report['fund_status_template'];
    $templateMoney = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
    $templatePercent = static fn (mixed $value): string => number_format((float) $value, 2) . '%';
    $templateNumber = static fn (mixed $value): string => number_format((int) $value);
@endphp

@once
    <style>
        .fund-sheet2-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 8px; }
        .fund-sheet2-table th, .fund-sheet2-table td { border: 1px solid #4b5563; padding: 4px 5px; vertical-align: middle; font-size: 7px; line-height: 1.25; }
        .fund-sheet2-table th { text-transform: none; }
        .fund-sheet2-table .sheet2-head { background: #ffd966; color: #111827; font-weight: 800; text-align: center; }
        .fund-sheet2-table .sheet2-head-left { text-align: left; }
        .fund-sheet2-table .sheet2-subtotal td { background: #ffe699; color: #111827; font-weight: 800; }
        .fund-sheet2-table .sheet2-total td { background: #3f3f3f; color: #fff; font-weight: 800; }
        .fund-sheet2-table .sheet2-right { text-align: right; white-space: nowrap; }
        .fund-sheet2-table .sheet2-left { text-align: left; }
        .fund-sheet2-table .sheet2-center { text-align: center; }
        .fund-sheet2-table .sheet2-empty { padding: 18px; text-align: center; color: #64748b; }
        .fund-sheet2-table--detail .col-adl { width: 9%; }
        .fund-sheet2-table--detail .col-sponsor { width: 10%; }
        .fund-sheet2-table--detail .col-partner { width: 10%; }
        .fund-sheet2-table--detail .col-municipality { width: 9%; }
        .fund-sheet2-table--detail .col-district { width: 8%; }
        .fund-sheet2-table--detail .col-province { width: 8%; }
        .fund-sheet2-table--detail .col-allocation { width: 11%; }
        .fund-sheet2-table--detail .col-amount { width: 11%; }
        .fund-sheet2-table--detail .col-rate { width: 6%; }
        .fund-sheet2-table--detail .col-ben { width: 6%; }
        .fund-sheet2-table--detail .col-balance { width: 12%; }
        .fund-sheet2-table--utilization .col-province { width: 24%; }
        .fund-sheet2-table--utilization .col-money { width: 19%; }
        .fund-sheet2-table--utilization .col-rate { width: 9%; }
        .fund-sheet2-table--utilization .col-ben { width: 10%; }
    </style>
@endonce

@if ($template['kind'] === 'utilization')
    <table class="fund-sheet2-table fund-sheet2-table--utilization" aria-label="Fund Utilization Report">
        <thead>
            <tr>
                <th rowspan="2" class="sheet2-head sheet2-head-left col-province">Province</th>
                <th rowspan="2" class="sheet2-head col-money">TUPAD Allocation</th>
                <th colspan="3" class="sheet2-head">Accomplishment</th>
                <th rowspan="2" class="sheet2-head col-money">Balance</th>
            </tr>
            <tr>
                <th class="sheet2-head col-money">Amount</th>
                <th class="sheet2-head col-rate">%</th>
                <th class="sheet2-head col-ben">Ben.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($template['rows'] as $row)
                <tr>
                    <td class="sheet2-left">{{ $row['province'] }}</td>
                    <td class="sheet2-right">{{ $templateMoney($row['allocation_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($row['accomplishment_cents']) }}</td>
                    <td class="sheet2-right">{{ $templatePercent($row['accomplishment_rate']) }}</td>
                    <td class="sheet2-right">{{ $templateNumber($row['accomplishment_beneficiaries']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($row['balance_cents']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="sheet2-empty">No fund utilization rows matched the selected criteria.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="sheet2-total">
                <td>TOTAL</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['allocation_cents']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['accomplishment_cents']) }}</td>
                <td class="sheet2-right">{{ $templatePercent($template['totals']['accomplishment_rate']) }}</td>
                <td class="sheet2-right">{{ $templateNumber($template['totals']['accomplishment_beneficiaries']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['balance_cents']) }}</td>
            </tr>
        </tfoot>
    </table>
@else
    <table class="fund-sheet2-table fund-sheet2-table--detail" aria-label="{{ $template['title'] }}">
        <thead>
            <tr>
                <th rowspan="2" class="sheet2-head col-adl">ADL No.</th>
                <th rowspan="2" class="sheet2-head col-sponsor">Fund Sponsor</th>
                <th rowspan="2" class="sheet2-head col-partner">Partner</th>
                <th colspan="3" class="sheet2-head">Location</th>
                <th rowspan="2" class="sheet2-head col-allocation">TUPAD Allocation</th>
                <th colspan="3" class="sheet2-head">Accomplishment</th>
                <th rowspan="2" class="sheet2-head col-balance">Balance</th>
            </tr>
            <tr>
                <th class="sheet2-head col-municipality">Municipality</th>
                <th class="sheet2-head col-district">District</th>
                <th class="sheet2-head col-province">Province</th>
                <th class="sheet2-head col-amount">Amount</th>
                <th class="sheet2-head col-rate">%</th>
                <th class="sheet2-head col-ben">Ben.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($template['groups'] as $group)
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['adl_number'] }}</td>
                        <td>{{ $row['fund_sponsor'] }}</td>
                        <td>{{ $row['partner'] }}</td>
                        <td>{{ $row['municipality'] }}</td>
                        <td>{{ $row['district'] }}</td>
                        <td>{{ $row['province'] }}</td>
                        <td class="sheet2-right">{{ $templateMoney($row['allocation_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($row['accomplishment_cents']) }}</td>
                        <td class="sheet2-right">{{ $templatePercent($row['accomplishment_rate']) }}</td>
                        <td class="sheet2-right">{{ $templateNumber($row['accomplishment_beneficiaries']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($row['balance_cents']) }}</td>
                    </tr>
                @endforeach
                <tr class="sheet2-subtotal">
                    <td colspan="6">Sub-total</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['allocation_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['accomplishment_cents']) }}</td>
                    <td class="sheet2-right">{{ $templatePercent($group['subtotal']['accomplishment_rate']) }}</td>
                    <td class="sheet2-right">{{ $templateNumber($group['subtotal']['accomplishment_beneficiaries']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['balance_cents']) }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="sheet2-empty">No allocation breakdown matched the selected criteria.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="sheet2-total">
                <td colspan="6">TOTAL</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['allocation_cents']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['accomplishment_cents']) }}</td>
                <td class="sheet2-right">{{ $templatePercent($template['totals']['accomplishment_rate']) }}</td>
                <td class="sheet2-right">{{ $templateNumber($template['totals']['accomplishment_beneficiaries']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['balance_cents']) }}</td>
            </tr>
        </tfoot>
    </table>
@endif
