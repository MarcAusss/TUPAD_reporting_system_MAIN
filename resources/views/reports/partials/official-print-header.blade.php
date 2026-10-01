{{--
    Official DOLE Regional Office V print letterhead (all print views).
    Layout: DOLE Bicol logo · office block · Bagong Pilipinas · ISO 9001 Bureau Veritas · Rating Guide.
    Optional: $report (prints its official title under the letterhead), $printTitle / $printSubtitle
    to override it, $printOnly (hide on screen).
--}}
@php
    $printOnly = (bool) ($printOnly ?? false);
    $printTitle = $printTitle ?? (isset($report) ? (string) ($report['official_title'] ?? $report['title'] ?? '') : null);
    $printDate = isset($report) ? ($report['generated_at'] ?? now('Asia/Manila')) : now('Asia/Manila');

    // One identifying line under the title: the official form + period (periodic
    // reports) or the active filters, then the as-of date.
    if (! isset($printSubtitle)) {
        $isPeriodicForm = isset($report) && ($report['official_layout'] ?? false) && filled($report['official_period'] ?? null);
        $subtitleParts = $isPeriodicForm
            ? array_values(array_filter([$report['official_code'] ?? null, $report['official_period']]))
            : collect(isset($report) ? (array) ($report['criteria'] ?? []) : [])
                ->except(['Report Type'])
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value, $label) => $label.': '.$value)
                ->values()
                ->all();
        $subtitleParts[] = 'As of '.$printDate->format('F d, Y');
        $printSubtitle = implode(' · ', $subtitleParts);
    }
@endphp

@once
    <style>
        .dole-official-letterhead {
            width: 100%;
            padding: 2mm 0 3mm;
            color: #000;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif !important;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .dole-official-letterhead * { font-family: Arial, Helvetica, sans-serif !important; }

        .dole-official-letterhead__inner {
            display: flex;
            align-items: center;
            gap: 4mm;
            width: 100%;
        }

        .dole-official-letterhead__dole-logo {
            flex: 0 0 auto;
            display: block;
            height: 22mm;
            width: auto;
            object-fit: contain;
        }

        .dole-official-letterhead__center {
            flex: 1 1 auto;
            min-width: 0;
            text-align: center;
            line-height: 1.2;
        }

        .dole-official-letterhead__center p { margin: 0; }
        .dole-official-letterhead__republic { font-size: 10pt; font-style: italic; }
        .dole-official-letterhead__department { font-size: 11pt; font-weight: 700; letter-spacing: .1px; }
        .dole-official-letterhead__region { font-size: 11pt; font-weight: 700; }
        .dole-official-letterhead__address { font-size: 9.5pt; }
        .dole-official-letterhead__email { font-size: 9.5pt; }

        .dole-official-letterhead__logos {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 3mm;
        }

        .dole-official-letterhead__bagong-logo { display: block; height: 22mm; width: auto; object-fit: contain; }
        .dole-official-letterhead__iso-logo { display: block; height: 24mm; width: auto; margin: -3mm 0; object-fit: contain; }

        .dole-official-letterhead__rating {
            flex: 0 0 auto;
            align-self: stretch;
            min-width: 27mm;
            border: 1px solid #000;
            padding: 1.5mm 2mm;
            font-size: 6.5pt;
            line-height: 1.25;
        }

        .dole-official-letterhead__rating strong { display: block; margin-bottom: .5mm; font-size: 6.8pt; }

        .report-print-title {
            margin: 1mm 0 3mm;
            text-align: center;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            break-after: avoid;
            page-break-after: avoid;
        }

        .report-print-title__main { margin: 0; font-size: 11pt; font-weight: 700; text-transform: uppercase; }
        .report-print-title__sub { margin: .5mm 0 0; font-size: 8.5pt; }

        /* Portrait pages (Letter): same letterhead, scaled to the narrower width. */
        .print-portrait .dole-official-letterhead__inner { gap: 2.5mm; }
        .print-portrait .dole-official-letterhead__dole-logo,
        .print-portrait .dole-official-letterhead__bagong-logo { height: 17mm; }
        .print-portrait .dole-official-letterhead__iso-logo { height: 18mm; margin: -2.5mm 0; }
        .print-portrait .dole-official-letterhead__republic { font-size: 8pt; }
        .print-portrait .dole-official-letterhead__department,
        .print-portrait .dole-official-letterhead__region { font-size: 8.8pt; }
        .print-portrait .dole-official-letterhead__address,
        .print-portrait .dole-official-letterhead__email { font-size: 7.6pt; }
        .print-portrait .dole-official-letterhead__rating { min-width: 21mm; padding: 1mm 1.5mm; font-size: 5.4pt; }
        .print-portrait .dole-official-letterhead__rating strong { font-size: 5.8pt; }
        .print-portrait .report-print-title__main { font-size: 9.5pt; }

        @media screen {
            .dole-official-letterhead--print-only,
            .report-print-title--print-only { display: none !important; }
        }

        @media print {
            .dole-official-letterhead,
            .report-print-title {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
@endonce

<header class="dole-official-letterhead{{ $printOnly ? ' dole-official-letterhead--print-only' : '' }}" aria-label="DOLE Regional Office V official letterhead">
    <div class="dole-official-letterhead__inner">
        <img src="{{ asset('images/print/mainlogo.png') }}" alt="DOLE Bicol"
            class="dole-official-letterhead__dole-logo" onerror="this.style.display='none'">

        <div class="dole-official-letterhead__center">
            <p class="dole-official-letterhead__republic">Republic of the Philippines</p>
            <p class="dole-official-letterhead__department">DEPARTMENT OF LABOR AND EMPLOYMENT</p>
            <p class="dole-official-letterhead__region">Regional Office No. 5</p>
            <p class="dole-official-letterhead__address">DOLE 5 Bldg., Doña Aurora Street, Old Albay, Legazpi City</p>
            <p class="dole-official-letterhead__email">ro5@dole.gov.ph</p>
        </div>

        <div class="dole-official-letterhead__logos">
            <img src="{{ asset('images/print/Bagong_Pilipinas.png') }}" alt="Bagong Pilipinas"
                class="dole-official-letterhead__bagong-logo" onerror="this.style.display='none'">
            <img src="{{ asset('images/print/iso-bureau-veritas.jpg') }}" alt="ISO 9001 Bureau Veritas Certification"
                class="dole-official-letterhead__iso-logo" onerror="this.style.display='none'">
        </div>

        <div class="dole-official-letterhead__rating" aria-label="Rating guide">
            <strong>Rating Guide:</strong>
            1 – Poor<br>
            2 – Good<br>
            3 – Satisfactory<br>
            4 – Very Satisfactory<br>
            5 – Excellent
        </div>
    </div>
</header>

@if (filled($printTitle))
    <section class="report-print-title{{ $printOnly ? ' report-print-title--print-only' : '' }}" aria-label="Report title">
        <p class="report-print-title__main">{{ $printTitle }}</p>
        <p class="report-print-title__sub">{{ $printSubtitle }}</p>
    </section>
@endif
