<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPAI Summary {{ $fiscalYear }} | TUPAD</title>
    <style>
        @page { size: Letter portrait; margin: 0.5in; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #111; margin: 0; padding: 24px; background: #fff; }
        .agency { text-align: center; line-height: 1.35; font-size: 9.5pt; }
        .agency b { font-size: 11pt; }
        h1 { text-align: center; font-size: 12pt; margin: 14px 0 2px; }
        .asof { text-align: center; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th, td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
        th { background: #eef2f7; }
        .text-right { text-align: right; white-space: nowrap; }
        .text-left { text-align: left; }
        tr[data-subtotal] td { background: #f6f8fb; font-weight: bold; }
        tr[data-total] td { background: #e8eef7; font-weight: bold; }
        .sign { margin-top: 28px; }
        .sign .name { margin-top: 26px; font-weight: bold; text-transform: uppercase; }
        .toolbar { text-align: right; margin-bottom: 12px; }
        .toolbar button { padding: 8px 16px; font-weight: bold; border: 0; border-radius: 6px; background: #063b86; color: #fff; cursor: pointer; }
        @media print { body { padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print</button></div>

    <div class="agency">
        Republic of the Philippines<br>
        <b>DEPARTMENT OF LABOR AND EMPLOYMENT</b><br>
        Regional Office No. 5<br>
        DOLE RO5 Bldg., Doña Aurora St., Old Albay, Legazpi City
    </div>

    <h1>SUMMARY OF ENROLLED TUPAD FY{{ $fiscalYear }} BENEFICIARIES TO GPAI</h1>
    <div class="asof">as of {{ now()->format('F j, Y') }}</div>

    @include('gsis.partials.summary-table')

    <div class="sign">
        Prepared by:
        <div class="name">{{ auth()->user()?->name }}</div>
        <div>{{ auth()->user()?->position ?: 'GSIS Enrollment Officer' }}</div>
    </div>
</body>
</html>
