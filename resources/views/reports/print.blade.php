<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        TUPAD Project Report
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111827;
            background: #ffffff;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 11px;
            color: #4b5563;
        }

        .filters {
            margin-bottom: 18px;
            border: 1px solid #d1d5db;
            padding: 10px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 18px;
        }

        .summary-item {
            border: 1px solid #d1d5db;
            padding: 8px;
        }

        .summary-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .summary-value {
            margin-top: 4px;
            font-weight: bold;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        .number {
            text-align: right;
        }

        .footer {
            margin-top: 18px;
            font-size: 9px;
            color: #6b7280;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }

            @page {
                size: landscape;
                margin: 10mm;
            }
        }
    </style>

</head>

<body>

    <div class="no-print" style="margin-bottom: 16px; text-align: right;">

        <button onclick="window.print()"
            style="
                border: 1px solid #9ca3af;
                background: white;
                padding: 8px 14px;
                cursor: pointer;
            ">
            Print
        </button>

    </div>

    <header class="header">

        <h1>
            TUPAD Project Monitoring Report
        </h1>

        <p>
            Generated:
            {{ now()->format('F d, Y g:i A') }}
        </p>

    </header>

    @if (filled($filters['province'] ?? null) ||
            filled($filters['municipality'] ?? null) ||
            filled($filters['barangay'] ?? null) ||
            filled($filters['status'] ?? null) ||
            filled($filters['date_from'] ?? null) ||
            filled($filters['date_to'] ?? null))

        <div class="filters">

            <strong>
                Applied Filters:
            </strong>

            @if (filled($filters['province'] ?? null))
                Province:
                {{ $filters['province'] }};
            @endif

            @if (filled($filters['municipality'] ?? null))
                Municipality:
                {{ $filters['municipality'] }};
            @endif

            @if (filled($filters['barangay'] ?? null))
                Barangay:
                {{ $filters['barangay'] }};
            @endif

            @if (filled($filters['status'] ?? null))
                @php
                    $status = \App\Enums\ProjectStatus::tryFrom($filters['status']);
                @endphp

                Status:
                {{ $status?->label() ?? $filters['status'] }};
            @endif

            @if (filled($filters['date_from'] ?? null))
                From:
                {{ $filters['date_from'] }};
            @endif

            @if (filled($filters['date_to'] ?? null))
                To:
                {{ $filters['date_to'] }};
            @endif

        </div>

    @endif

    <div class="summary">

        <div class="summary-item">

            <div class="summary-label">
                Projects
            </div>

            <div class="summary-value">
                {{ number_format($summary['projects']) }}
            </div>

        </div>

        <div class="summary-item">

            <div class="summary-label">
                Beneficiaries
            </div>

            <div class="summary-value">
                {{ number_format($summary['beneficiaries']) }}
            </div>

        </div>

        <div class="summary-item">

            <div class="summary-label">
                Female
            </div>

            <div class="summary-value">
                {{ number_format($summary['female_beneficiaries']) }}
            </div>

        </div>

        <div class="summary-item">

            <div class="summary-label">
                Total Project Cost
            </div>

            <div class="summary-value">
                ₱{{ number_format($summary['project_cost'], 2) }}
            </div>

        </div>

    </div>

    <table>

        <thead>

            <tr>

                <th>
                    Project Code
                </th>

                <th>
                    Project
                </th>

                <th>
                    ADL / Partner
                </th>

                <th>
                    Location
                </th>

                <th class="number">
                    Beneficiaries
                </th>

                <th class="number">
                    Registered
                </th>

                <th class="number">
                    Project Cost
                </th>

                <th>
                    Status
                </th>

            </tr>

        </thead>

        <tbody>

            @forelse($projects as $project)
                <tr>

                    <td>
                        {{ $project->approval?->project_code ?? '—' }}
                    </td>

                    <td>
                        {{ $project->project_title }}
                    </td>

                    <td>
                        {{ $project->allocation->adl->adl_number }}
                        <br>
                        {{ $project->allocation->partner }}
                    </td>

                    <td>
                        {{ $project->full_location }}
                    </td>

                    <td class="number">
                        {{ number_format($project->beneficiaries_total) }}
                    </td>

                    <td class="number">
                        {{ number_format($project->beneficiaries_count) }}
                    </td>

                    <td class="number">
                        ₱{{ number_format($project->total_project_cost, 2) }}
                    </td>

                    <td>
                        {{ $project->status->label() }}
                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="8" style="text-align: center; padding: 20px;">
                        No records match the selected filters.
                    </td>

                </tr>
            @endforelse

        </tbody>

    </table>

    <div class="footer">
        TUPAD Reporting System — Official project report
    </div>

</body>

</html>
