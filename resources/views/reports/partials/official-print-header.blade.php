@php
    $printOnly = (bool) ($printOnly ?? false);
@endphp

@once
    <style>
        .dole-official-letterhead {
            /* display: grid; */
            /* grid-template-columns: 100px minmax(0, 1fr) 210px; */
            align-items: center;
            width: 100%;
            border-bottom: 1px solid #94a3b8;
            padding-bottom: 10px;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif !important;
        }

        .dole-official-letterhead *,
        .dole-official-letterhead p {
            font-family: Arial, Helvetica, sans-serif !important;
        }

        .dole-official-letterhead__side {
            display: flex;
            min-height: 60px;
            align-items: center;
        }

        .dole-official-letterhead__side--left {
            justify-content: flex-start;
        }

        .dole-official-letterhead__side--right {
            justify-content: flex-end;
        }

        .dole-official-letterhead__center {
            text-align: center;
            line-height: 1.16;
        }

        .dole-official-letterhead__republic {
            margin: 0;
            font-size: 10pt;
            font-weight: 400;
        }

        .dole-official-letterhead__department {
            margin: 3px 0 0;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 0.1px;
        }

        .dole-official-letterhead__region {
            margin: 3px 0 0;
            font-size: 10pt;
            font-weight: 600;
        }

        .dole-official-letterhead__address {
            margin: 5px 0 0;
            font-size: 9pt;
            font-style: italic;
        }

        .dole-official-letterhead__contact {
            margin: 3px 0 0;
            font-size: 9pt;
            font-style: italic;
        }

        .dole-official-letterhead__email {
            margin: 3px 0 0;
            font-size: 9pt;
            text-decoration: underline;
        }

        .dole-official-letterhead__dole-logo {
            display: block;
            width: 160px;
            max-height: 80px;
            object-fit: contain;
        }

        .dole-official-letterhead__bagong-logo {
            display: block;
            width: 65px;
            max-height: 80px;
            margin-right: 4px;
            object-fit: contain;
        }

        .dole-official-letterhead__iso-logo {
            display: block;
            width: 140px;
            max-height: 200px;
            object-fit: contain;
        }
        .container-print{
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            gap: 50px;
        }

        @media screen {
            .dole-official-letterhead--print-only {
                display: none !important;
            }
        }

        @media print {
            .dole-official-letterhead {
                break-inside: avoid;
                page-break-inside: avoid;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
@endonce

<header class="dole-official-letterhead{{ $printOnly ? ' dole-official-letterhead--print-only' : '' }}">
    <div class="container-print">
        <div class="dole-official-letterhead__side dole-official-letterhead__side--left">
            <img src="{{ asset('images/print/mainlogo.png') }}" alt="DOLE Logo" class="dole-official-letterhead__dole-logo"
                onerror="this.style.display='none'">
        </div>

        <div class="dole-official-letterhead__center">
            <p class="dole-official-letterhead__republic">Republic of the Philippines</p>
            <p class="dole-official-letterhead__department">DEPARTMENT OF LABOR AND EMPLOYMENT</p>
            <p class="dole-official-letterhead__region">Regional Office No. 5</p>
            <p class="dole-official-letterhead__address">
                DOLE RO5 Bldg., Doña Aurora St., Old Albay, Legazpi City
            </p>
            <p class="dole-official-letterhead__contact">
                ORD: 0981-461-8788&nbsp;&nbsp;
                TSSD: 0963-206-0008&nbsp;&nbsp;
                IMSD: 0912-330-4751
            </p>
            <p class="dole-official-letterhead__email">ro5@dole.gov.ph</p>
        </div>

        <div class="dole-official-letterhead__side dole-official-letterhead__side--right">
            <img src="{{ asset('images/print/Bagong_Pilipinas.png') }}" alt="Bagong Pilipinas"
                class="dole-official-letterhead__bagong-logo" onerror="this.style.display='none'">
            <img src="{{ asset('images/print/iso-bureau-veritas.jpg') }}" alt="ISO Bureau Veritas"
                class="dole-official-letterhead__iso-logo" onerror="this.style.display='none'">
        </div>
    </div>
</header>
