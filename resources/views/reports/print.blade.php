<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} | TUPAD</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 18px; font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #111827; background: #fff; }
        .toolbar { margin-bottom: 14px; text-align: right; }
        .toolbar button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; padding: 8px 14px; cursor: pointer; }

        /* Phase 14F: header composition follows the TUPAD PPE Inventory print/header visual language only. */
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
        th, td { border: 1px solid #94a3b8; padding: 4px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #d9edf3; color: #0f172a; font-size: 7px; text-align: left; text-transform: uppercase; }
        .right { text-align: right; white-space: nowrap; }
        .empty { padding: 18px; text-align: center; color: #64748b; }
        .footer { margin-top: 9px; border-top: 1px solid #cbd5e1; padding-top: 6px; color: #64748b; font-size: 7px; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            .official-print-header { break-inside: avoid; }
            @page { size: A4 landscape; margin: 8mm; }
        }
    </style>
</head>

<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Report</button>
    </div>

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
</body>

</html>
