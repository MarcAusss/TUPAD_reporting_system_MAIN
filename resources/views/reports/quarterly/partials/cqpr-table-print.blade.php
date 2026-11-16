{{-- CQPR official print/PDF in the approved template layout. --}}
@php
    $cqpr = $report['cqpr_template'];
    $cqprMoney = static fn (mixed $cents): string => number_format(((int) $cents) / 100, 2);
    $cqprNumber = static fn (mixed $value): string => number_format((int) $value);
@endphp

@once
    <style>
        .cqpr-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6px; }
        .cqpr-table th, .cqpr-table td { border: 1px solid #4b5563; padding: 3px 4px; vertical-align: middle; font-size: 6.5px; line-height: 1.25; overflow-wrap: anywhere; }
        .cqpr-table th { background: #ffd966; color: #111827; font-weight: 800; text-align: center; }
        .cqpr-table .cqpr-hint { display: block; font-size: 5.5px; font-weight: 400; }
        .cqpr-table .cqpr-subtotal td { background: #fff2cc; font-weight: 800; }
        .cqpr-table .cqpr-total td { background: #3f3f3f; color: #fff; font-weight: 800; }
        .cqpr-table .cqpr-right { text-align: right; white-space: nowrap; }
        .cqpr-table .cqpr-center { text-align: center; }
        .cqpr-section-title { margin: 10px 0 2px; font-size: 8px; font-weight: 800; }
        .cqpr-section + .cqpr-section { break-before: page; page-break-before: always; }
    </style>
@endonce

@foreach ($cqpr['sections'] as $section)
    <section class="cqpr-section">
        @if (count($cqpr['sections']) > 1)
            <div class="cqpr-section-title">Quarter {{ $section['quarter'] }} ({{ $section['label'] }})</div>
        @endif
        <table class="cqpr-table" aria-label="CQPR {{ $section['label'] }}">
            <colgroup>
                <col style="width: 13%"><col style="width: 8%">
                <col style="width: 6%"><col style="width: 6%"><col style="width: 5%"><col style="width: 4%"><col style="width: 4.5%">
                <col style="width: 4%"><col style="width: 4%">
                <col style="width: 4%"><col style="width: 4%"><col style="width: 4%">
                <col style="width: 6%"><col style="width: 5.5%"><col style="width: 6%"><col style="width: 6.5%"><col style="width: 5.5%">
            </colgroup>
            <thead>
                <tr>
                    <th rowspan="2">Name &amp; Nature of Project<span class="cqpr-hint">(i.e. declogging of canal, debris clearing, basic repair of infrastructures)</span></th>
                    <th rowspan="2">Name of Implementer<span class="cqpr-hint">(specify the DOLE Office or Name of Co-partner)</span></th>
                    <th colspan="5">Project Location</th>
                    <th colspan="2">Work Period Category</th>
                    <th colspan="3">Beneficiaries</th>
                    <th rowspan="2">Amount Released (Php)</th>
                    <th rowspan="2">Date Released</th>
                    <th rowspan="2">Fund Source<span class="cqpr-hint">Please specify: Regular GAA, Sin Tax, DOLE AMP, others</span></th>
                    <th rowspan="2">Convergence Project/ Partner</th>
                    <th rowspan="2">Status</th>
                </tr>
                <tr>
                    <th>Barangay</th>
                    <th>City/ Municipality</th>
                    <th>Province</th>
                    <th>District</th>
                    <th>Income Class</th>
                    <th>Short-term</th>
                    <th>Long-term</th>
                    <th>Total</th>
                    <th>Female</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($section['groups'] as $group)
                    @foreach ($group['rows'] as $row)
                        <tr>
                            <td>{{ $row['project'] }}@if (filled($row['nature']))<br>{{ $row['nature'] }}@endif</td>
                            <td>{{ $row['implementer'] }}</td>
                            <td>{{ $row['barangay'] }}</td>
                            <td>{{ $row['municipality'] }}</td>
                            <td>{{ $row['province'] }}</td>
                            <td>{{ $row['district'] }}</td>
                            <td>{{ $row['income_class'] }}</td>
                            <td class="cqpr-center">{{ $row['short_term'] ? '✓' : '' }}</td>
                            <td class="cqpr-center">{{ $row['long_term'] ? '✓' : '' }}</td>
                            <td class="cqpr-right">{{ $cqprNumber($row['beneficiaries']) }}</td>
                            <td class="cqpr-right">{{ $cqprNumber($row['female']) }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td class="cqpr-right">{{ $cqprMoney($row['amount_cents']) }}</td>
                            <td>{{ $row['date_released'] }}</td>
                            <td>{{ $row['fund_source'] }}</td>
                            <td>{{ $row['convergence'] }}</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                    <tr class="cqpr-subtotal">
                        <td colspan="9">Sub-Total ({{ $group['label'] }})</td>
                        <td class="cqpr-right">{{ $cqprNumber($group['subtotal']['beneficiaries']) }}</td>
                        <td class="cqpr-right">{{ $cqprNumber($group['subtotal']['female']) }}</td>
                        <td></td>
                        <td class="cqpr-right">{{ $cqprMoney($group['subtotal']['amount_cents']) }}</td>
                        <td colspan="4"></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="cqpr-total">
                    <td colspan="9">TOTAL</td>
                    <td class="cqpr-right">{{ $cqprNumber($section['totals']['beneficiaries']) }}</td>
                    <td class="cqpr-right">{{ $cqprNumber($section['totals']['female']) }}</td>
                    <td></td>
                    <td class="cqpr-right">{{ $cqprMoney($section['totals']['amount_cents']) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
    </section>
@endforeach
