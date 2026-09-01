<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} | TUPAD</title>

    @php
        $isPhysicalFinancial = ($report['type'] ?? null) === \App\Enums\ReportType::PHYSICAL_FINANCIAL
            && is_array($report['physical_financial_matrix'] ?? null);
    @endphp

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #111827; background: #fff; }
        .toolbar { margin-bottom: 14px; text-align: right; }
        .toolbar button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; padding: 8px 14px; cursor: pointer; }

        .official-print-header { display: grid; grid-template-columns: 180px minmax(0, 1fr) 170px; min-height: 82px; border-top: 4px solid #0d9bc0; border-bottom: 1px solid #cbd5e1; background: #fff; }
        .official-print-header__brand { padding: 10px 14px 9px 10px; border-right: 1px solid #e2e8f0; }
        .official-print-header__logo { display: block; width: 118px; height: auto; object-fit: contain; object-position: left center; }
        .official-print-header__system { margin-top: 4px; font-size: 8px; font-weight: 700; text-transform: uppercase; color: #334155; }
        .official-print-header__office { margin-top: 1px; font-size: 7px; color: #64748b; }
        .official-print-header__title { display: flex; min-width: 0; flex-direction: column; align-items: center; justify-content: center; padding: 8px 16px; text-align: center; }
        .official-print-header__agency { font-size: 8px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #475569; }
        .official-print-header__report { margin-top: 4px; font-size: 15px; line-height: 1.15; font-weight: 800; color: #0f172a; }
        .official-print-header__scope { margin-top: 4px; font-size: 8px; font-weight: 600; color: #64748b; }
        .official-print-header__period { margin-top: 2px; font-size: 7px; font-weight: 700; color: #0d7490; }
        .official-print-header__meta { padding: 8px 10px; border-left: 1px solid #e2e8f0; background: #f8fafc; }
        .official-print-header__meta-row { display: flex; justify-content: space-between; gap: 10px; padding: 4px 0; border-bottom: 1px solid #e2e8f0; font-size: 7px; }
        .official-print-header__meta-row:last-child { border-bottom: 0; }
        .official-print-header__meta-row span { color: #64748b; }
        .official-print-header__meta-row strong { color: #0f172a; text-align: right; }

        .criteria { margin-top: 9px; border: 1px solid #cbd5e1; padding: 7px 9px; background: #f8fafc; }
        .criteria span { display: inline-block; margin: 2px 13px 2px 0; }
        .summary { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 5px; margin: 9px 0; }
        .summary-item { border: 1px solid #cbd5e1; padding: 6px; }
        .summary-label { color: #64748b; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .summary-value { margin-top: 3px; font-size: 11px; font-weight: bold; }
        .warning { margin: 8px 0; border: 1px solid #d97706; background: #fffbeb; padding: 7px; color: #78350f; }
        .report-table-wrap { margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th, td { border: 1px solid #64748b; padding: 5px; vertical-align: middle; overflow-wrap: anywhere; }
        th { background: #d9edf3; color: #0f172a; font-size: 7px; text-align: left; text-transform: uppercase; }
        .right { text-align: right; white-space: nowrap; }
        .empty { padding: 18px; text-align: center; color: #64748b; }
        .footer { margin-top: 9px; border-top: 1px solid #cbd5e1; padding-top: 6px; color: #64748b; font-size: 7px; }

        .pf-print-page { break-after: page; }
        .pf-print-page:last-child { break-after: auto; }
        .pf-print-page .official-print-header { grid-template-columns: 135px minmax(0, 1fr) 130px; min-height: 72px; }
        .pf-print-page .official-print-header__logo { width: 92px; }
        .pf-print-page .official-print-header__report { font-size: 12px; }
        .pf-print-page .criteria { font-size: 7px; }
        .pf-period-title { margin: 10px 0 6px; text-align: center; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .pf-matrix { table-layout: fixed; }
        .pf-matrix th, .pf-matrix td { text-align: center; font-size: 8px; }
        .pf-matrix .province { text-align: left; font-weight: 700; }
        .pf-head-target { background: #cb3f1d; color: #fff; font-weight: 800; }
        .pf-head-accomplishment { background: #f8d45b; color: #111827; font-weight: 800; }
        .pf-head-balance { background: #f0b27a; color: #111827; font-weight: 800; }
        .pf-head-leaf { background: #fff7db; color: #111827; font-weight: 800; }
        .pf-total td { background: #3f3f3f; color: #fff; font-weight: 800; }
        .pf-note { margin-top: 7px; font-size: 6.8px; line-height: 1.35; color: #64748b; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .official-print-header { break-inside: avoid; }
            @if ($isPhysicalFinancial)
                @page { size: Letter portrait; margin: 9mm; }
            @else
                @page { size: A4 landscape; margin: 8mm; }
            @endif
        }
    </style>
</head>

<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Report</button>
    </div>

    @if ($isPhysicalFinancial)
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

                <section class="criteria">
                    @foreach ($report['criteria'] as $label => $value)
                        <span><strong>{{ $label }}:</strong> {{ $value }}</span>
                    @endforeach
                </section>

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

                <div class="pf-note"><strong>Basis:</strong> {{ $matrix['basis_note'] }}</div>
            </section>
        @else
            @foreach ($matrix['periods'] as $period)
                <section class="pf-print-page">
                    @include('reports.partials.official-print-header', ['report' => $report])

                    <section class="criteria">
                        @foreach ($report['criteria'] as $label => $value)
                            <span><strong>{{ $label }}:</strong> {{ $value }}</span>
                        @endforeach
                    </section>

                    <div class="pf-period-title">
                        {{ $period['label'] }} Physical & Financial Accomplishment
                    </div>

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

                    <div class="pf-note">
                        <strong>Portrait layout:</strong> one reporting period per Letter-size portrait page. Short-Term and Long-Term subdivisions were removed as requested.
                    </div>
                </section>
            @endforeach
        @endif
    @else
        @include('reports.partials.official-print-header', ['report' => $report])

        <section class="criteria">
            @foreach ($report['criteria'] as $label => $value)
                <span><strong>{{ $label }}:</strong> {{ $value }}</span>
            @endforeach
        </section>

        <section class="summary">
            @foreach ($report['summary_cards'] as $card)
                <div class="summary-item">
                    <div class="summary-label">{{ $card['label'] }}</div>
                    <div class="summary-value">{{ $card['display_value'] }}</div>
                </div>
            @endforeach
        </section>

        @if ($report['warning'])
            <div class="warning"><strong>Data note:</strong> {{ $report['warning'] }}</div>
        @endif

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

        <footer class="footer">
            {{ number_format($report['rows']->count()) }} reporting row(s). Generated from the validated
            Phase 8 reporting data layer; no project reference values were accepted from the browser.
        </footer>
    @endif
</body>

</html>
