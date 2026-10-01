<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} | TUPAD</title>

    @php
        $isPhysicalFinancial = ($report['type'] ?? null) === \App\Enums\ReportType::PHYSICAL_FINANCIAL
            && is_array($report['physical_financial_matrix'] ?? null);
        $isLaborMarketMatrix = is_array($report['labor_market_print_matrix'] ?? null);
        $isSprsMatrix = is_array($report['sprs_print_matrix'] ?? null);
        $isFundStatusTemplate = ($report['type'] ?? null) === \App\Enums\ReportType::FUND_STATUS
            && is_array($report['fund_status_template'] ?? null);
    @endphp

    {{--
        Official print layout: letterhead, report title, the table, and signatories.
        Nothing else is printed (no criteria strip, summary cards, notes or footers).
    --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; background: #fff; }
        .toolbar { margin-bottom: 14px; text-align: right; }
        .toolbar button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; padding: 8px 14px; cursor: pointer; }

        .report-table-wrap { margin-top: 2mm; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th, td { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; overflow-wrap: anywhere; }
        th { background: #d9edf3; color: #000; font-size: 7.5px; font-weight: 700; text-align: center; text-transform: uppercase; }
        td { font-size: 8px; }
        .right { text-align: right; white-space: nowrap; }
        .empty { padding: 18px; text-align: center; color: #475569; }

        .pf-print-page { break-after: page; }
        .pf-print-page:last-of-type { break-after: auto; }
        .pf-matrix { table-layout: fixed; }
        .pf-matrix th, .pf-matrix td { text-align: center; font-size: 8px; }
        .pf-matrix td.right { text-align: right; }
        .pf-matrix .province { text-align: left; font-weight: 700; }
        .pf-head-target { background: #cb3f1d; color: #fff; font-weight: 800; }
        .pf-head-accomplishment { background: #f8d45b; color: #000; font-weight: 800; }
        .pf-head-balance { background: #f0b27a; color: #000; font-weight: 800; }
        .pf-head-leaf { background: #fff7db; color: #000; font-weight: 800; }
        .pf-total td { background: #3f3f3f; color: #fff; font-weight: 800; }

        .sprs-matrix { table-layout: fixed; }
        .sprs-matrix th, .sprs-matrix td { text-align: center; font-size: 6.8px; padding: 4px 3px; }
        .sprs-matrix .sprs-label { width: 86px; text-align: left; font-weight: 700; }
        .sprs-matrix .sprs-overall { background: #d9edf3; font-weight: 800; }
        .sprs-matrix .sprs-province { background: #e8f1f8; font-weight: 700; }
        .sprs-matrix .sprs-meta { width: 105px; text-align: left; }
        .sprs-matrix .sprs-quarter td { background: #f8fafc; font-weight: 800; }
        .sprs-matrix .sprs-grand-total td { background: #3f3f3f; color: #fff; font-weight: 800; }
        .sprs-matrix .sprs-future td { color: #94a3b8; background: #f8fafc; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            th, td, .pf-total td, .sprs-grand-total td { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            @if ($isPhysicalFinancial)
                @page { size: Letter portrait; margin: 9mm; }
            @else
                @page { size: A4 landscape; margin: 8mm; }
            @endif
        }
    </style>
    {{-- TUPAD Reporting System favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon.png') }}">
</head>

<body class="{{ $isPhysicalFinancial ? 'print-portrait' : 'print-landscape' }}">
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Report</button>
    </div>

    @if ($isFundStatusTemplate)
        @include('reports.partials.official-print-header', ['report' => $report])
        @include('reports.fund-status.partials.template-table-print', ['report' => $report])
    @elseif ($isPhysicalFinancial)
        @php
            $matrix = $report['physical_financial_matrix'];
            $dimension = $report['dimension'];
            $matrixMoney = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
            $matrixNumber = static fn (mixed $value): string => number_format((int) $value);
            $total = $matrix['total'];
        @endphp

        @if ($dimension === \App\Enums\ReportDimension::OVERALL)
            <section class="pf-print-page">
                @include('reports.partials.official-print-header', ['report' => $report])

                <div class="report-table-wrap">
                    <table class="pf-matrix">
                        <thead>
                            <tr>
                                <th rowspan="2" class="pf-head-target province">Province</th>
                                <th colspan="2" class="pf-head-target">Reformulated Target</th>
                                <th colspan="2" class="pf-head-accomplishment">Accomplishment</th>
                                <th colspan="2" class="pf-head-balance">Balance</th>
                            </tr>
                            <tr>
                                @foreach (range(1, 3) as $pair)
                                    <th class="pf-head-leaf">Physical</th>
                                    <th class="pf-head-leaf">Financial</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matrix['rows'] as $row)
                                <tr>
                                    <td class="province">{{ $row['province'] }}</td>
                                    <td class="right">{{ $matrixNumber(data_get($row, 'target.physical', 0)) }}</td>
                                    <td class="right">{{ $matrixMoney(data_get($row, 'target.financial_cents', 0)) }}</td>
                                    <td class="right">{{ $matrixNumber(data_get($row, 'accomplishment.physical', 0)) }}</td>
                                    <td class="right">{{ $matrixMoney(data_get($row, 'accomplishment.financial_cents', 0)) }}</td>
                                    <td class="right">{{ $matrixNumber(data_get($row, 'balance.physical', 0)) }}</td>
                                    <td class="right">{{ $matrixMoney(data_get($row, 'balance.financial_cents', 0)) }}</td>
                                </tr>
                            @endforeach
                            <tr class="pf-total">
                                <td class="province">TOTAL</td>
                                <td class="right">{{ $matrixNumber(data_get($total, 'target.physical', 0)) }}</td>
                                <td class="right">{{ $matrixMoney(data_get($total, 'target.financial_cents', 0)) }}</td>
                                <td class="right">{{ $matrixNumber(data_get($total, 'accomplishment.physical', 0)) }}</td>
                                <td class="right">{{ $matrixMoney(data_get($total, 'accomplishment.financial_cents', 0)) }}</td>
                                <td class="right">{{ $matrixNumber(data_get($total, 'balance.physical', 0)) }}</td>
                                <td class="right">{{ $matrixMoney(data_get($total, 'balance.financial_cents', 0)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @include('reports.partials.signatories', ['report' => $report])
            </section>
        @else
            @foreach ($matrix['periods'] as $period)
                <section class="pf-print-page">
                    @include('reports.partials.official-print-header', [
                        'report' => $report,
                        'printTitle' => $period['label'].' Physical & Financial Accomplishment',
                    ])

                    <div class="report-table-wrap">
                        <table class="pf-matrix">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="pf-head-target province" style="width: 40%;">Province</th>
                                    <th colspan="2" class="pf-head-accomplishment">{{ $period['label'] }}</th>
                                </tr>
                                <tr>
                                    <th class="pf-head-leaf">Physical</th>
                                    <th class="pf-head-leaf">Financial</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($matrix['rows'] as $row)
                                    <tr>
                                        <td class="province">{{ $row['province'] }}</td>
                                        <td class="right">{{ $matrixNumber(data_get($row, 'periods.'.$period['key'].'.physical', 0)) }}</td>
                                        <td class="right">{{ $matrixMoney(data_get($row, 'periods.'.$period['key'].'.financial_cents', 0)) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="pf-total">
                                    <td class="province">TOTAL</td>
                                    <td class="right">{{ $matrixNumber(data_get($total, 'periods.'.$period['key'].'.physical', 0)) }}</td>
                                    <td class="right">{{ $matrixMoney(data_get($total, 'periods.'.$period['key'].'.financial_cents', 0)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    @if ($loop->last)
                        @include('reports.partials.signatories', ['report' => $report])
                    @endif
                </section>
            @endforeach
        @endif
    @else
        @include('reports.partials.official-print-header', ['report' => $report])

        @if ($isSprsMatrix)
            @php
                $sprsMatrix = $report['sprs_print_matrix'];
                $sprsNumber = static fn (mixed $value): string => number_format((int) $value);
            @endphp

            <div class="report-table-wrap">
                <table class="sprs-matrix" aria-label="Statistical Performance Reporting System province and month matrix">
                    <thead>
                        <tr>
                            <th rowspan="2" class="sprs-label">Province/<br>Month</th>
                            <th colspan="2" class="sprs-overall">Overall</th>
                            @foreach ($sprsMatrix['province_headers'] as $provinceLabel)
                                <th colspan="2" class="sprs-province">{{ $provinceLabel }}</th>
                            @endforeach
                            <th rowspan="2" class="sprs-meta">Date<br>Accomplished</th>
                            <th rowspan="2" class="sprs-meta">Remarks</th>
                        </tr>
                        <tr>
                            <th>Total</th>
                            <th>Female</th>
                            @foreach ($sprsMatrix['province_headers'] as $provinceLabel)
                                <th>Total</th>
                                <th>Female</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sprsMatrix['rows'] as $row)
                            @php
                                $rowClasses = collect([
                                    $row['row_type'] === 'quarter' ? 'sprs-quarter' : null,
                                    $row['row_type'] === 'grand_total' ? 'sprs-grand-total' : null,
                                    !$row['included'] ? 'sprs-future' : null,
                                ])->filter()->implode(' ');
                            @endphp
                            <tr
                                class="{{ $rowClasses }}"
                                data-sprs-row="{{ $row['slug'] }}"
                                data-sprs-included="{{ $row['included'] ? '1' : '0' }}"
                            >
                                <td class="sprs-label">{{ $row['label'] }}</td>
                                <td data-sprs-cell="{{ $row['slug'] }}-overall-total">
                                    {{ $row['included'] ? $sprsNumber(data_get($row, 'overall.total', 0)) : '' }}
                                </td>
                                <td data-sprs-cell="{{ $row['slug'] }}-overall-female">
                                    {{ $row['included'] ? $sprsNumber(data_get($row, 'overall.female', 0)) : '' }}
                                </td>

                                @foreach ($sprsMatrix['province_headers'] as $provinceKey => $provinceLabel)
                                    <td data-sprs-cell="{{ $row['slug'] }}-{{ str_replace('_', '-', $provinceKey) }}-total">
                                        {{ $row['included'] ? $sprsNumber(data_get($row, 'provinces.'.$provinceKey.'.total', 0)) : '' }}
                                    </td>
                                    <td data-sprs-cell="{{ $row['slug'] }}-{{ str_replace('_', '-', $provinceKey) }}-female">
                                        {{ $row['included'] ? $sprsNumber(data_get($row, 'provinces.'.$provinceKey.'.female', 0)) : '' }}
                                    </td>
                                @endforeach

                                <td class="sprs-meta">{{ $row['date_accomplished'] }}</td>
                                <td class="sprs-meta">{{ $row['remarks'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($isLaborMarketMatrix)
            @php
                $laborMatrix = $report['labor_market_print_matrix'];
                $laborMoney = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
            @endphp

            <div class="report-table-wrap">
                <table class="labor-market-matrix">
                    <thead>
                        <tr>
                            <th>Intervention</th>
                            <th class="right">No. of Interested TUPAD Beneficiaries Reffered</th>
                            <th class="right">Female</th>
                            <th class="right">No. of Reffered TUPAD Beneficiaries Provided with Intervention</th>
                            <th class="right">Female</th>
                            <th>Amount Released under the Intervention</th>
                            <th>Types of Skills Training/Livelihood/Employment Services Availed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($laborMatrix['rows'] as $row)
                            <tr data-labor-program="{{ $row['program'] }}" data-labor-has-data="{{ $row['has_data'] ? '1' : '0' }}">
                                <td>{{ $row['label'] }}</td>
                                <td class="right">{{ $row['has_data'] ? number_format((int) $row['interested_referred_total']) : '' }}</td>
                                <td class="right">{{ $row['has_data'] ? number_format((int) $row['interested_referred_female']) : '' }}</td>
                                <td class="right">{{ $row['has_data'] ? number_format((int) $row['provided_intervention_total']) : '' }}</td>
                                <td class="right">{{ $row['has_data'] ? number_format((int) $row['provided_intervention_female']) : '' }}</td>
                                <td>{{ $row['has_data'] ? $laborMoney($row['amount_released_cents']) : '' }}</td>
                                <td>{{ $row['has_data'] ? $row['services_availed'] : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="report-table-wrap">
                <table>
                    <thead>
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <th class="{{ $column['align'] === 'right' ? 'right' : '' }}">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['display_rows'] as $row)
                            <tr>
                                @foreach ($report['columns'] as $column)
                                    <td class="{{ $column['align'] === 'right' ? 'right' : '' }}">
                                        {{ $row[$column['key']] ?? '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="empty" colspan="{{ count($report['columns']) }}">
                                    No records match the selected report criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @include('reports.partials.signatories', ['report' => $report])
    @endif
</body>

</html>
