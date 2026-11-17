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
        .fund-sheet2-table .sheet2-province-total td { background: #f4b183; }
        .fund-sheet2-table .sheet2-district-subtotal td { background: #fff2cc; color: #111827; font-weight: 700; }
        .fund-sheet2-table td.sheet2-sponsor { background: #fff; font-weight: 800; vertical-align: top; }
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
        .fund-sheet2-table--district .col-representative { width: 19%; }
        .fund-sheet2-table--district .col-district { width: 7%; }
        .fund-sheet2-table--district .col-adl { width: 10%; }
        .fund-sheet2-table--district .col-money { width: 12.8%; }
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
@elseif ($template['kind'] === 'district')
    <table class="fund-sheet2-table fund-sheet2-table--district" aria-label="{{ $template['title'] }}">
        <thead>
            <tr>
                <th class="sheet2-head col-representative">Representative</th>
                <th class="sheet2-head col-district">District</th>
                <th class="sheet2-head col-adl">ADL No.</th>
                <th class="sheet2-head col-money">Original Allocation (A)</th>
                <th class="sheet2-head col-money">Realigned to GIP (B)</th>
                <th class="sheet2-head col-money">TUPAD Allocation (C=A-B)</th>
                <th class="sheet2-head col-money">Implemented (D)</th>
                <th class="sheet2-head col-money">Balance (E=C-D)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($template['groups'] as $group)
                @foreach ($group['rows'] as $district)
                    @foreach ($district['adls'] as $row)
                        <tr>
                            @if ($loop->first)
                                <td rowspan="{{ count($district['adls']) }}">{{ $district['representative'] }}</td>
                                <td rowspan="{{ count($district['adls']) }}" class="sheet2-center">{{ $district['district_label'] }}</td>
                            @endif
                            <td>{{ $row['adl_number'] }}</td>
                            <td class="sheet2-right">{{ $templateMoney($row['original_cents']) }}</td>
                            <td class="sheet2-right">{{ $templateMoney($row['realigned_cents']) }}</td>
                            <td class="sheet2-right">{{ $templateMoney($row['tupad_cents']) }}</td>
                            <td class="sheet2-right">{{ $templateMoney($row['implemented_cents']) }}</td>
                            <td class="sheet2-right">{{ $templateMoney($row['balance_cents']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="sheet2-district-subtotal">
                        <td colspan="3">Sub-total</td>
                        <td class="sheet2-right">{{ $templateMoney($district['subtotal']['original_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($district['subtotal']['realigned_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($district['subtotal']['tupad_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($district['subtotal']['implemented_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($district['subtotal']['balance_cents']) }}</td>
                    </tr>
                @endforeach
                <tr class="sheet2-subtotal">
                    <td colspan="3">{{ $group['label'] }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['original_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['realigned_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['tupad_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['implemented_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($group['subtotal']['balance_cents']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="sheet2-empty">No district rows matched the selected criteria.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="sheet2-total">
                <td colspan="3">TOTAL</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['original_cents']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['realigned_cents']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['tupad_cents']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['implemented_cents']) }}</td>
                <td class="sheet2-right">{{ $templateMoney($template['totals']['balance_cents']) }}</td>
            </tr>
        </tfoot>
    </table>
@elseif ($template['kind'] === 'sponsor')
    <table class="fund-sheet2-table fund-sheet2-table--detail" aria-label="{{ $template['title'] }}">
        <thead>
            <tr>
                <th rowspan="2" class="sheet2-head col-sponsor">Fund Sponsor</th>
                <th rowspan="2" class="sheet2-head col-adl">ADL No.</th>
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
                @foreach ($group['adl_groups'] as $adlGroup)
                    @foreach ($adlGroup['rows'] as $row)
                        <tr>
                            @if ($loop->parent->first && $loop->first)
                                <td rowspan="{{ $group['row_span'] }}" class="sheet2-sponsor">{{ $group['label'] }}</td>
                            @endif
                            <td>{{ $row['adl_number'] }}</td>
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
                        <td colspan="5">Sub-total</td>
                        <td class="sheet2-right">{{ $templateMoney($adlGroup['subtotal']['allocation_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($adlGroup['subtotal']['accomplishment_cents']) }}</td>
                        <td class="sheet2-right">{{ $templatePercent($adlGroup['subtotal']['accomplishment_rate']) }}</td>
                        <td class="sheet2-right">{{ $templateNumber($adlGroup['subtotal']['accomplishment_beneficiaries']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($adlGroup['subtotal']['balance_cents']) }}</td>
                    </tr>
                @endforeach
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
                @foreach ($group['adl_groups'] ?? [$group] as $adlGroup)
                @foreach ($adlGroup['rows'] as $row)
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
                    <td class="sheet2-right">{{ $templateMoney($adlGroup['subtotal']['allocation_cents']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($adlGroup['subtotal']['accomplishment_cents']) }}</td>
                    <td class="sheet2-right">{{ $templatePercent($adlGroup['subtotal']['accomplishment_rate']) }}</td>
                    <td class="sheet2-right">{{ $templateNumber($adlGroup['subtotal']['accomplishment_beneficiaries']) }}</td>
                    <td class="sheet2-right">{{ $templateMoney($adlGroup['subtotal']['balance_cents']) }}</td>
                </tr>
                @endforeach
                @isset($group['adl_groups'])
                    <tr class="sheet2-subtotal sheet2-province-total">
                        <td colspan="6">{{ strtoupper($group['label']) }} TOTAL</td>
                        <td class="sheet2-right">{{ $templateMoney($group['subtotal']['allocation_cents']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($group['subtotal']['accomplishment_cents']) }}</td>
                        <td class="sheet2-right">{{ $templatePercent($group['subtotal']['accomplishment_rate']) }}</td>
                        <td class="sheet2-right">{{ $templateNumber($group['subtotal']['accomplishment_beneficiaries']) }}</td>
                        <td class="sheet2-right">{{ $templateMoney($group['subtotal']['balance_cents']) }}</td>
                    </tr>
                @endisset
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
