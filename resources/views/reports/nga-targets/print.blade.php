<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGA Target Accomplishment | TUPAD</title>

    @php
        $money = static fn (mixed $value): string => '₱'.number_format((float) $value, 2);
        $number = static fn (mixed $value): string => number_format((int) $value);
        // Same rule as the screen: Municipality / Province columns only once the report is filtered.
        $showLocation = $search !== '' || $agencyFilter !== '' || $programFilter !== '';
        $columnCount = $showLocation ? 11 : 9;
    @endphp

    {{-- Official print layout: letterhead, report title, the table, and signatories. --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; background: #fff; }
        .toolbar { margin-bottom: 14px; text-align: right; }
        .toolbar button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; padding: 8px 14px; cursor: pointer; }

        .report-table-wrap { margin-top: 2mm; }
        table.nga-matrix { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        tr { break-inside: avoid; }
        th, td { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; overflow-wrap: anywhere; }
        th { font-size: 7.5px; font-weight: 800; text-align: center; text-transform: uppercase; color: #000; }
        td { font-size: 8px; }
        .left { text-align: left; }
        .center { text-align: center; }
        .right { text-align: right; white-space: nowrap; }
        .agency { font-weight: 700; }
        .empty { padding: 18px; text-align: center; color: #475569; }

        .nga-head-base { background: #f8d45b; }
        .nga-head-target { background: #8fb8de; }
        .nga-head-target-leaf { background: #d7e5f4; }
        .nga-head-accomplished { background: #e08070; }
        .nga-head-accomplished-leaf { background: #f3d9d3; }
        .nga-head-balance { background: #c090c0; }
        .nga-head-balance-leaf { background: #ecdcec; }
        .nga-total td { background: #3f3f3f; color: #fff; font-weight: 800; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            th, td, .nga-total td { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            @page { size: Legal landscape; margin: 8mm; }
        }
    </style>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon.png') }}">
</head>

<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Report</button>
    </div>

    @include('reports.partials.official-print-header', ['report' => $report])

    <div class="report-table-wrap">
        <table class="nga-matrix">
            <thead>
                <tr>
                    <th rowspan="2" class="nga-head-base left">Agency</th>
                    <th rowspan="2" class="nga-head-base left">Program</th>
                    @if ($showLocation)
                        <th rowspan="2" class="nga-head-base left">Municipality</th>
                        <th rowspan="2" class="nga-head-base left">Province</th>
                    @endif
                    <th rowspan="2" class="nga-head-base">No. of<br>Days</th>
                    <th colspan="2" class="nga-head-target">Target</th>
                    <th colspan="2" class="nga-head-accomplished">Accomplished</th>
                    <th colspan="2" class="nga-head-balance">Balance</th>
                </tr>
                <tr>
                    <th class="nga-head-target-leaf">Benefs</th>
                    <th class="nga-head-target-leaf">Amount</th>
                    <th class="nga-head-accomplished-leaf">Benefs</th>
                    <th class="nga-head-accomplished-leaf">Amount</th>
                    <th class="nga-head-balance-leaf">Benefs</th>
                    <th class="nga-head-balance-leaf">Amount</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="agency">{{ $row['agency'] }}</td>
                        <td>{{ $row['program'] }}</td>
                        @if ($showLocation)
                            <td>{{ $row['municipality'] }}</td>
                            <td>{{ $row['province'] }}</td>
                        @endif
                        <td class="center">{{ $number($row['number_of_days']) }}</td>
                        <td class="right">{{ $number($row['target']['beneficiaries']) }}</td>
                        <td class="right">{{ $money($row['target']['amount']) }}</td>
                        <td class="right">{{ $number($row['accomplished']['beneficiaries']) }}</td>
                        <td class="right">{{ $money($row['accomplished']['amount']) }}</td>
                        <td class="right">{{ $number($row['balance']['beneficiaries']) }}</td>
                        <td class="right">{{ $money($row['balance']['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="empty">No projects match the selected filters.</td>
                    </tr>
                @endforelse
            </tbody>

            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="nga-total">
                        <td colspan="{{ $showLocation ? 5 : 3 }}">TOTAL</td>
                        <td class="right">{{ $number($totals['target_beneficiaries']) }}</td>
                        <td class="right">{{ $money($totals['target_amount']) }}</td>
                        <td class="right">{{ $number($totals['accomplished_beneficiaries']) }}</td>
                        <td class="right">{{ $money($totals['accomplished_amount']) }}</td>
                        <td class="right">{{ $number($totals['balance_beneficiaries']) }}</td>
                        <td class="right">{{ $money($totals['balance_amount']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    @include('reports.partials.signatories', ['report' => $report])
</body>

</html>
