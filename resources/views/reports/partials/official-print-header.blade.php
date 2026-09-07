@php
    $printOnly = (bool) ($printOnly ?? false);
@endphp

@once
    <style>
        .dole-official-letterhead {
            width: 100%;
            border-bottom: 3px solid #0d95b8;
            padding: 8px 0 10px;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif !important;
            background: #fff;
        }

        .dole-official-letterhead *,
        .dole-official-letterhead p,
        .report-print-meta-strip * {
            font-family: Arial, Helvetica, sans-serif !important;
        }

        .dole-official-letterhead__inner {
            display: grid;
            grid-template-columns: 105px minmax(0, 1fr) 175px;
            align-items: center;
            gap: 14px;
            width: 100%;
        }

        .dole-official-letterhead__side {
            display: flex;
            min-width: 0;
            align-items: center;
        }

        .dole-official-letterhead__side--left { justify-content: center; }
        .dole-official-letterhead__side--right { justify-content: flex-end; gap: 6px; }

        .dole-official-letterhead__center {
            min-width: 0;
            text-align: center;
            line-height: 1.13;
        }

        .dole-official-letterhead__republic {
            margin: 0;
            font-size: 8.5pt;
            font-weight: 400;
        }

        .dole-official-letterhead__department {
            margin: 2px 0 0;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: .05px;
        }

        .dole-official-letterhead__region {
            margin: 2px 0 0;
            font-size: 8.7pt;
            font-weight: 600;
        }

        .dole-official-letterhead__address,
        .dole-official-letterhead__contact,
        .dole-official-letterhead__email {
            margin: 2px 0 0;
            font-size: 7.2pt;
            line-height: 1.15;
        }

        .dole-official-letterhead__address,
        .dole-official-letterhead__contact { font-style: italic; }
        .dole-official-letterhead__email { text-decoration: underline; }

        .dole-official-letterhead__dole-logo {
            display: block;
            width: 72px;
            max-height: 72px;
            object-fit: contain;
        }

        .dole-official-letterhead__bagong-logo {
            display: block;
            width: 49px;
            max-height: 58px;
            object-fit: contain;
        }

        .dole-official-letterhead__iso-logo {
            display: block;
            width: 105px;
            max-height: 58px;
            object-fit: contain;
        }

        .report-print-meta-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            width: 100%;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 6px 9px;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .report-print-meta-strip__item {
            display: flex;
            min-width: 0;
            align-items: baseline;
            gap: 5px;
            font-size: 7.2px;
            line-height: 1.25;
        }

        .report-print-meta-strip__item--date {
            flex: 0 0 auto;
            justify-content: flex-end;
            text-align: right;
            white-space: nowrap;
        }

        .report-print-meta-strip__label {
            font-weight: 700;
            color: #334155;
        }

        .report-print-meta-strip__value {
            min-width: 0;
            font-weight: 600;
            color: #0f172a;
        }

        @media screen {
            .dole-official-letterhead--print-only { display: none !important; }
        }

        @media print {
            .dole-official-letterhead,
            .report-print-meta-strip {
                break-inside: avoid;
                page-break-inside: avoid;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
@endonce

<header class="dole-official-letterhead{{ $printOnly ? ' dole-official-letterhead--print-only' : '' }}" aria-label="DOLE Regional Office V official letterhead">
    <div class="dole-official-letterhead__inner">
        <div class="dole-official-letterhead__side dole-official-letterhead__side--left">
            <img src="{{ asset('images/print/mainlogo.png') }}" alt="DOLE Bicol"
                class="dole-official-letterhead__dole-logo" onerror="this.style.display='none'">
        </div>

        <div class="dole-official-letterhead__center">
            <p class="dole-official-letterhead__republic">Republic of the Philippines</p>
            <p class="dole-official-letterhead__department">DEPARTMENT OF LABOR AND EMPLOYMENT</p>
            <p class="dole-official-letterhead__region">Regional Office No. 5</p>
            <p class="dole-official-letterhead__address">DOLE RO5 Bldg., Doña Aurora St., Old Albay, Legazpi City</p>
            <p class="dole-official-letterhead__contact">
                ORD: 0981-461-8788&nbsp;&nbsp; TSSD: 0963-206-0008&nbsp;&nbsp; IMSD: 0912-330-4751
            </p>
            <p class="dole-official-letterhead__email">ro5@dole.gov.ph</p>
        </div>

        <div class="dole-official-letterhead__side dole-official-letterhead__side--right">
            <img src="{{ asset('images/print/Bagong_Pilipinas.png') }}" alt="Bagong Pilipinas"
                class="dole-official-letterhead__bagong-logo" onerror="this.style.display='none'">
            <img src="{{ asset('images/print/iso-bureau-veritas.jpg') }}" alt="ISO Bureau Veritas Certification"
                class="dole-official-letterhead__iso-logo" onerror="this.style.display='none'">
        </div>
    </div>
</header>

@if (isset($report))
    @php
        $printReportTitle = (string) ($report['official_title'] ?? $report['title'] ?? 'TUPAD Official Report');
        $printGeneratedAt = $report['generated_at'] ?? now('Asia/Manila');
    @endphp

    <section class="report-print-meta-strip" aria-label="Printed report type and date">
        <div class="report-print-meta-strip__item">
            <span class="report-print-meta-strip__label">Report Type:</span>
            <strong class="report-print-meta-strip__value">{{ $printReportTitle }}</strong>
        </div>

        <div class="report-print-meta-strip__item report-print-meta-strip__item--date">
            <span class="report-print-meta-strip__label">Date:</span>
            <strong class="report-print-meta-strip__value">{{ $printGeneratedAt->format('F d, Y') }}</strong>
        </div>
    </section>
@endif
