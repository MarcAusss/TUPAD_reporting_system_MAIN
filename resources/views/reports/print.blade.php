<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }} | TUPAD</title>

    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 22px; font-family: Arial, sans-serif; font-size: 10px; color: #111827; }
        .toolbar { margin-bottom: 16px; text-align: right; }
        .toolbar button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; padding: 8px 14px; cursor: pointer; }
        .masthead { border-bottom: 2px solid #153e75; padding-bottom: 12px; text-align: center; }
        .agency { font-size: 9px; font-weight: bold; letter-spacing: .12em; text-transform: uppercase; color: #475569; }
        h1 { margin: 5px 0 0; font-size: 18px; color: #153e75; }
        .subtitle { margin-top: 5px; color: #475569; }
        .criteria { margin-top: 12px; border: 1px solid #cbd5e1; padding: 8px 10px; }
        .criteria span { display: inline-block; margin: 2px 14px 2px 0; }
        .summary { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 6px; margin: 12px 0; }
        .summary-item { border: 1px solid #cbd5e1; padding: 7px; }
        .summary-label { color: #64748b; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .summary-value { margin-top: 4px; font-size: 12px; font-weight: bold; }
        .warning { margin: 10px 0; border: 1px solid #f59e0b; background: #fffbeb; padding: 8px; color: #78350f; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e8eef7; color: #153e75; font-size: 8px; text-align: left; text-transform: uppercase; }
        .right { text-align: right; white-space: nowrap; }
        .empty { padding: 20px; text-align: center; color: #64748b; }
        .footer { margin-top: 12px; color: #64748b; font-size: 8px; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            @page { size: A4 landscape; margin: 9mm; }
        }
    </style>
</head>

<body>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Print Report</button>
    </div>

    <header class="masthead">
        <div class="agency">Department of Labor and Employment | TUPAD Reporting System</div>
        <h1>{{ $report['title'] }}</h1>
        <div class="subtitle">
            Grouped by {{ $report['dimension']->label() }} · Generated
            {{ $report['generated_at']->format('F d, Y h:i A') }}
        </div>
    </header>

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

    <footer class="footer">
        {{ number_format($report['rows']->count()) }} reporting row(s). Generated from the validated
        Phase 8 reporting data layer; no project reference values were accepted from the browser.
    </footer>
</body>

</html>
