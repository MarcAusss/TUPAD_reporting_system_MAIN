<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $enrollment->gpai_number }} Transmittal | TUPAD</title>
    <style>
        @page { size: Letter portrait; margin: 0.6in; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #111; margin: 0; padding: 24px; background: #fff; }
        .sheet { max-width: 7.4in; margin: 0 auto; }
        .dole-official-letterhead { border-bottom: 1px solid #333; margin-bottom: 4px; }
        h1 { text-align: center; font-size: 12.5pt; margin: 16px 0 4px; letter-spacing: .04em; }
        .number { text-align: center; font-weight: bold; margin-bottom: 14px; }
        .date { margin: 10px 0 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #333; padding: 5px 6px; vertical-align: top; }
        th { background: #eef2f7; }
        .r { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .noborder td { border: none; }
        .note { margin-top: 12px; font-size: 10pt; }
        .lines { margin-top: 16px; font-size: 10pt; }
        .lines div { margin: 6px 0; }
        .sign { margin-top: 28px; font-size: 10pt; }
        .sign .name { margin-top: 26px; font-weight: bold; text-transform: uppercase; }
        .refs { margin-top: 22px; text-align: right; font-size: 10pt; line-height: 1.9; }
        .toolbar { max-width: 7.4in; margin: 0 auto 16px; text-align: right; }
        .toolbar button { padding: 8px 16px; font-weight: bold; border: 0; border-radius: 6px; background: #063b86; color: #fff; cursor: pointer; }
        @media print { body { padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print</button></div>

    <div class="sheet print-portrait">
        {{-- Official DOLE RO5 letterhead with logos (same as the printed reports). --}}
        @include('reports.partials.official-print-header', ['printTitle' => null])

        <h1>ENROLLMENT TO GPAI TRANSMITTAL</h1>
        <div class="number">{{ $enrollment->gpai_number }}</div>

        <div class="date">{{ $enrollment->transmittal_date->format('j F Y') }}</div>

        <table>
            <thead>
                <tr>
                    <th class="c" style="width: 6%">No.</th>
                    <th style="width: 15%">ADL No.</th>
                    <th style="width: 19%">Work Period</th>
                    <th>Particulars</th>
                    <th class="r" style="width: 9%">No.</th>
                    <th class="r" style="width: 13%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($enrollment->items as $item)
                    <tr>
                        <td class="c">{{ $loop->iteration }}</td>
                        <td>{{ $item->adl_number }}</td>
                        <td>
                            @if ($loop->first)
                                {{ $enrollment->coverage_start->format('F j, Y') }} to {{ $enrollment->coverage_end->format('F j, Y') }}
                            @endif
                        </td>
                        <td>{{ $item->particulars }}</td>
                        <td class="r">{{ number_format($item->beneficiaries) }}</td>
                        <td class="r">{{ number_format($item->premiumCents() / 100, 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" class="r"><b>Sub-total</b></td>
                    <td class="r"><b>{{ number_format($enrollment->beneficiaries()) }}</b></td>
                    <td class="r"><b>{{ number_format($enrollment->premiumCents() / 100, 2) }}</b></td>
                </tr>
                <tr>
                    <td colspan="4" class="r">Add Documentary Stamp Tax</td>
                    <td></td>
                    <td class="r">{{ number_format($enrollment->docStampCents() / 100, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="r"><b>TOTAL</b></td>
                    <td class="r"><b>{{ number_format($enrollment->beneficiaries()) }}</b></td>
                    <td class="r"><b>{{ number_format($enrollment->totalCents() / 100, 2) }}</b></td>
                </tr>
            </tbody>
        </table>

        <div class="note">
            Note: {{ $enrollment->payment_mode === \App\Models\GpaiEnrollment::MODE_CASH_ADVANCE ? 'direct payment in cash (Cash Advance)' : 'payment through voucher' }}; with attached endorsement and GMIE Form/s
        </div>

        <div class="lines">
            <div><b>ADVANCE COPY (sent thru e-mail):</b></div>
            <div>SUBJECT: ____________________________________________________</div>
            <div>DATE &amp; TIME SENT: {{ $enrollment->billing_emailed_at ? $enrollment->billing_emailed_at->format('F j, Y g:i A') : '____________________________________________' }}</div>
        </div>

        <div class="sign">
            Prepared by:
            <div class="name">{{ $enrollment->creator?->name }}</div>
            <div>{{ $enrollment->creator?->position ?: 'GSIS Enrollment Officer' }}</div>
        </div>

        <div class="refs">
            OR No. {{ $enrollment->or_number ?: '____________________' }}<br>
            Policy ID {{ $enrollment->policy_id ?: '____________________' }}
        </div>
    </div>
</body>
</html>
