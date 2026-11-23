{{-- SUMMARY OF ENROLLED TUPAD BENEFICIARIES TO GPAI: rows per transmittal, semester sub-totals, and the total. --}}
@php
    $money = fn (int $cents): string => number_format($cents / 100, 2);
    $grand = ['beneficiaries' => 0, 'grants' => 0, 'stamp' => 0, 'total' => 0];
@endphp
<table class="{{ $tableClass ?? '' }}">
    <thead>
        <tr>
            <th class="p-3 text-left">Sheet No.</th>
            <th class="p-3 text-left">Nature of Payment</th>
            <th class="p-3 text-right">No. of Beneficiaries Enrolled</th>
            <th class="p-3 text-right">Grants</th>
            <th class="p-3 text-right">Doc. Stamp</th>
            <th class="p-3 text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($semesters as $semester => $rows)
            @continue($rows->isEmpty())
            @php
                $sub = [
                    'beneficiaries' => $rows->sum(fn ($enrollment) => $enrollment->beneficiaries()),
                    'grants' => $rows->sum(fn ($enrollment) => $enrollment->premiumCents()),
                    'stamp' => $rows->sum(fn ($enrollment) => $enrollment->docStampCents()),
                    'total' => $rows->sum(fn ($enrollment) => $enrollment->totalCents()),
                ];
                foreach ($sub as $key => $value) {
                    $grand[$key] += $value;
                }
            @endphp
            @foreach ($rows as $enrollment)
                <tr>
                    <td class="p-3 whitespace-nowrap">{{ $enrollment->gpai_number }}</td>
                    <td class="p-3">{{ $enrollment->natureOfPayment() }}</td>
                    <td class="p-3 text-right">{{ number_format($enrollment->beneficiaries()) }}</td>
                    <td class="p-3 text-right">{{ $money($enrollment->premiumCents()) }}</td>
                    <td class="p-3 text-right">{{ $money($enrollment->docStampCents()) }}</td>
                    <td class="p-3 text-right">{{ $money($enrollment->totalCents()) }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold" data-subtotal>
                <td class="p-3" colspan="2">Sub-total ({{ $semester }})</td>
                <td class="p-3 text-right">{{ number_format($sub['beneficiaries']) }}</td>
                <td class="p-3 text-right">{{ $money($sub['grants']) }}</td>
                <td class="p-3 text-right">{{ $money($sub['stamp']) }}</td>
                <td class="p-3 text-right">{{ $money($sub['total']) }}</td>
            </tr>
        @endforeach
        @if ($enrollments->isEmpty())
            <tr><td colspan="6" class="p-8 text-center text-sm text-slate-400">No GPAI transmittals for FY {{ $fiscalYear }}.</td></tr>
        @else
            <tr class="font-bold" data-total>
                <td class="p-3" colspan="2">TOTAL</td>
                <td class="p-3 text-right">{{ number_format($grand['beneficiaries']) }}</td>
                <td class="p-3 text-right">{{ $money($grand['grants']) }}</td>
                <td class="p-3 text-right">{{ $money($grand['stamp']) }}</td>
                <td class="p-3 text-right">{{ $money($grand['total']) }}</td>
            </tr>
        @endif
    </tbody>
</table>
