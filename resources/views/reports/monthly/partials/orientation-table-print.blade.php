{{-- List of Orientations Conducted: official print/PDF in the approved template layout. --}}
@php
    $orientation = $report['orientation_template'];
    $orientationNumber = static fn (mixed $value): string => number_format((int) $value);
@endphp

@once
    <style>
        .orientation-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 6px; }
        .orientation-table th, .orientation-table td { border: 1px solid #4b5563; padding: 3px 5px; vertical-align: middle; font-size: 8px; line-height: 1.25; overflow-wrap: anywhere; }
        .orientation-table th { background: #ffd966; color: #111827; font-weight: 800; text-align: center; }
        .orientation-table .orientation-month td { background: #fff2cc; font-weight: 800; }
        .orientation-table .orientation-quarter td { background: #5f5f5f; color: #fff; font-weight: 800; }
        .orientation-table .orientation-total td { background: #3f3f3f; color: #fff; font-weight: 800; }
        .orientation-table .orientation-center { text-align: center; }
    </style>
@endonce

<table class="orientation-table" aria-label="List of Orientations Conducted">
    <colgroup>
        <col style="width: 4%"><col style="width: 9%"><col style="width: 12%"><col style="width: 13%">
        <col style="width: 9%"><col style="width: 9%"><col style="width: 14%"><col style="width: 14%"><col style="width: 16%">
    </colgroup>
    <thead>
        <tr>
            <th rowspan="2">No.</th>
            <th rowspan="2">Date of Activity</th>
            <th rowspan="2">Venue</th>
            <th rowspan="2">Learning Modality (AVP/Face-to-Face)</th>
            <th colspan="2">No. of Beneficiaries Oriented</th>
            <th colspan="2">Topics Discussed</th>
            <th rowspan="2">Name of Facilitator/s*</th>
        </tr>
        <tr>
            <th>Total</th>
            <th>Female</th>
            <th>AlkanSSSya Program</th>
            <th>YAKAP Program</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($orientation['sections'] as $section)
            @if ($section['type'] === 'month')
                <tr class="orientation-month"><td colspan="9">{{ $section['label'] }}</td></tr>
                @forelse ($section['rows'] as $row)
                    <tr>
                        <td class="orientation-center">{{ $row['no'] }}</td>
                        <td class="orientation-center">{{ $row['date'] }}</td>
                        <td>{{ $row['venue'] }}</td>
                        <td class="orientation-center">{{ $row['modality'] }}</td>
                        <td class="orientation-center">{{ $orientationNumber($row['total']) }}</td>
                        <td class="orientation-center">{{ $orientationNumber($row['female']) }}</td>
                        <td class="orientation-center">{{ $row['alkansssya'] ? '✓' : '' }}</td>
                        <td class="orientation-center">{{ $row['yakap'] ? '✓' : '' }}</td>
                        <td>{{ $row['facilitator'] }}</td>
                    </tr>
                @empty
                    @if ($section['elapsed'] ?? false)
                        {{-- Month has ended with no orientation conducted. --}}
                        <tr>
                            <td colspan="4">&nbsp;</td>
                            <td class="orientation-center">0</td>
                            <td class="orientation-center">0</td>
                            <td colspan="3">&nbsp;</td>
                        </tr>
                    @else
                        <tr><td colspan="9" class="orientation-center">&nbsp;</td></tr>
                    @endif
                @endforelse
            @else
                <tr class="orientation-quarter">
                    <td colspan="4">{{ $section['label'] }}</td>
                    @if (($section['elapsed'] ?? true) || $section['totals']['total'] > 0)
                        <td class="orientation-center">{{ $orientationNumber($section['totals']['total']) }}</td>
                        <td class="orientation-center">{{ $orientationNumber($section['totals']['female']) }}</td>
                    @else
                        <td></td>
                        <td></td>
                    @endif
                    <td colspan="3"></td>
                </tr>
            @endif
        @endforeach
    </tbody>
    <tfoot>
        <tr class="orientation-total">
            <td colspan="4">{{ count($orientation['sections']) > 1 ? 'GRAND TOTAL' : 'TOTAL' }}</td>
            <td class="orientation-center">{{ $orientationNumber($orientation['totals']['total']) }}</td>
            <td class="orientation-center">{{ $orientationNumber($orientation['totals']['female']) }}</td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>
