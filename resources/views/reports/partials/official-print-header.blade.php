@php
    $generatedAt = $report['generated_at'];
    $dimensionLabel = isset($report['dimension']) ? $report['dimension']->label() : null;
    $reportTitle = $report['official_title'] ?? $report['title'];
    $reportKicker = $report['official_kicker'] ?? 'TUPAD Reporting System';
    $reportCode = $report['official_code'] ?? ($dimensionLabel ? 'Grouped by '.$dimensionLabel : 'OFFICIAL REPORT');
    $reportPeriod = $report['official_period'] ?? null;
@endphp
<header class="official-print-header" aria-label="Official report header">
    <div class="official-print-header__brand">
        <img src="{{ asset('images/tupad-print-brand.jpg') }}" alt="TUPAD" class="official-print-header__logo">
        <div class="official-print-header__system">{{ $reportKicker }}</div>
        <div class="official-print-header__office">DOLE Regional Office V</div>
    </div>

    <div class="official-print-header__title">
        <div class="official-print-header__agency">Department of Labor and Employment</div>
        <div class="official-print-header__report">{{ $reportTitle }}</div>
        <div class="official-print-header__scope">{{ $reportCode }}</div>
        @if ($reportPeriod)
            <div class="official-print-header__period">{{ $reportPeriod }}</div>
        @endif
    </div>

    <div class="official-print-header__meta">
        <div class="official-print-header__meta-row">
            <span>Generated</span>
            <strong>{{ $generatedAt->format('M d, Y') }}</strong>
        </div>
        <div class="official-print-header__meta-row">
            <span>Time</span>
            <strong>{{ $generatedAt->format('h:i A') }}</strong>
        </div>
        <div class="official-print-header__meta-row">
            <span>Output</span>
            <strong>Official Report</strong>
        </div>
    </div>
</header>
