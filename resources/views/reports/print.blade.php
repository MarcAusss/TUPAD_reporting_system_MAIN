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
    @endphp

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #111827; background: #fff; }
        .toolbar { margin-bottom: 14px; text-align: right; }
        .toolbar button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; padding: 8px 14px; cursor: pointer; }


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
        .pf-print-page:last-of-type { break-after: auto; }
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

        .sprs-matrix { table-layout: fixed; }
        .sprs-matrix th, .sprs-matrix td { text-align: center; font-size: 6.8px; padding: 4px 3px; }
        .sprs-matrix .sprs-label { width: 86px; text-align: left; font-weight: 700; }
        .sprs-matrix .sprs-overall { background: #d9edf3; font-weight: 800; }
        .sprs-matrix .sprs-province { background: #e8f1f8; font-weight: 700; }
        .sprs-matrix .sprs-meta { width: 105px; text-align: left; }
        .sprs-matrix .sprs-quarter td { background: #f8fafc; font-weight: 800; }
        .sprs-matrix .sprs-grand-total td { background: #3f3f3f; color: #fff; font-weight: 800; }
        .sprs-matrix .sprs-future td { color: #94a3b8; background: #f8fafc; }
        .sprs-note { margin-top: 7px; font-size: 6.8px; line-height: 1.4; color: #64748b; }

        .pf-print-page .dole-official-letterhead__inner {
            grid-template-columns: 82px minmax(0, 1fr) 132px;
            gap: 9px;
        }
        .pf-print-page .dole-official-letterhead__dole-logo { width: 58px; max-height: 58px; }
        .pf-print-page .dole-official-letterhead__bagong-logo { width: 36px; max-height: 46px; }
        .pf-print-page .dole-official-letterhead__iso-logo { width: 88px; max-height: 46px; }
        .pf-print-page .dole-official-letterhead__republic { font-size: 7.2pt; }
        .pf-print-page .dole-official-letterhead__department { font-size: 8.4pt; }
        .pf-print-page .dole-official-letterhead__region { font-size: 7.5pt; }
        .pf-print-page .dole-official-letterhead__address,
        .pf-print-page .dole-official-letterhead__contact,
        .pf-print-page .dole-official-letterhead__email { font-size: 6.2pt; }
        .pf-print-page .report-print-meta-strip { padding: 5px 7px; }
        .pf-print-page .report-print-meta-strip__item { font-size: 6.8px; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .dole-official-letterhead, .report-print-meta-strip { break-inside: avoid; }
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
                        <strong>Portrait layout:</strong> one reporting period per Letter-size portrait page.
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

            <div class="sprs-note">
                <strong>Reporting basis:</strong> {{ $sprsMatrix['basis_note'] }}
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

            <div class="footer">
                {{ $laborMatrix['basis_note'] }}
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

            <footer class="footer">
                {{ number_format($report['rows']->count()) }} reporting row(s). Generated from the validated
                validated reporting records; no project reference values were accepted from the browser.
            </footer>
        @endif
    @endif

    @include('reports.partials.signatories', ['report' => $report])
</body>

</html>
