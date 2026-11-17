{{--
    Financial progress chart (Financial tab).
    Direct Administration: Total Project Cost vs Obligated vs Disbursed vs Released.
    Through ACP: Total Project Cost vs ACP Payment vs Liquidated, plus liquidation progress.
--}}
@php
    $chartIsAcp = $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP;
    $chartCost = (float) $project->total_project_cost;

    if ($chartIsAcp) {
        $chartPaid = (float) ($project->acpPayment?->amount ?? 0);
        $chartLiquidated = (float) $project->acpLiquidations->sum('amount');
        $chartLiquidationBase = $chartPaid > 0 ? $chartPaid : $chartCost;
        $chartLiquidationPercent = $chartLiquidationBase > 0 ? min(100, round($chartLiquidated / $chartLiquidationBase * 100, 1)) : 0;

        $liquidationSpec = [
            'type' => 'doughnut',
            'money' => true,
            'labels' => ['Liquidated', 'Remaining'],
            'datasets' => [[
                'data' => [round($chartLiquidated, 2), round(max(0, $chartLiquidationBase - $chartLiquidated), 2)],
                'backgroundColor' => ['#10b981', '#e2e8f0'],
            ]],
        ];

        $chartBars = [
            ['label' => 'Total Project Cost', 'value' => $chartCost, 'color' => '#94a3b8'],
            ['label' => 'ACP Payment', 'value' => $chartPaid, 'color' => '#063b86'],
            ['label' => 'Liquidated', 'value' => $chartLiquidated, 'color' => '#10b981'],
        ];
    } else {
        $chartPayments = app(\App\Services\Payments\ProjectPaymentService::class);
        $chartObligated = $chartPayments->obligatedCents($project) / 100;
        $chartDisbursed = $chartPayments->disbursedCents($project) / 100;
        $chartReleased = $project->obligations
            ->filter(fn ($obligation) => $obligation->isReleased())
            ->sum(fn ($obligation) => $chartPayments->obligationCents($obligation)) / 100;

        $chartBars = [
            ['label' => 'Total Project Cost', 'value' => $chartCost, 'color' => '#94a3b8'],
            ['label' => 'Obligated', 'value' => $chartObligated, 'color' => '#063b86'],
            ['label' => 'Disbursed', 'value' => $chartDisbursed, 'color' => '#0ea5e9'],
            ['label' => 'Released', 'value' => $chartReleased, 'color' => '#10b981'],
        ];
    }

    $chartSpec = [
        'type' => 'bar',
        'horizontal' => true,
        'money' => true,
        'legend' => false,
        'labels' => array_column($chartBars, 'label'),
        'datasets' => [[
            'label' => 'Amount',
            'data' => array_map(fn ($bar) => round($bar['value'], 2), $chartBars),
            'backgroundColor' => array_column($chartBars, 'color'),
        ]],
    ];
@endphp

<section data-workspace-panel="financial"
    class="scroll-mt-32 mt-5 grid gap-4 {{ $chartIsAcp ? 'lg:grid-cols-[minmax(0,1fr)_320px]' : '' }} {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Financial Progress</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ $chartIsAcp ? 'ACP payment and liquidation against the Total Project Cost.' : 'Obligated, disbursed, and released amounts against the Total Project Cost.' }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_220px]">
            <div class="h-56">
                <canvas data-tupad-chart='@json($chartSpec)' aria-label="Financial progress chart" role="img"></canvas>
            </div>

            <dl class="grid content-start gap-2">
                @foreach ($chartBars as $bar)
                    @php $barPercent = $chartCost > 0 ? min(100, round($bar['value'] / $chartCost * 100, 1)) : 0; @endphp
                    <div class="rounded-xl border border-slate-100 px-3 py-2">
                        <dt class="flex items-center gap-2 text-[11px] font-semibold text-slate-500">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $bar['color'] }}"></span>
                            {{ $bar['label'] }}
                        </dt>
                        <dd class="mt-0.5 flex items-baseline justify-between gap-2">
                            <span class="text-sm font-bold text-slate-900">₱{{ number_format($bar['value'], 2) }}</span>
                            @unless ($loop->first)
                                <span class="text-[11px] font-semibold text-slate-500">{{ $barPercent }}%</span>
                            @endunless
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    @if ($chartIsAcp)
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Liquidation Progress</h2>
                <p class="mt-0.5 text-xs text-slate-500">Liquidated vs remaining of the {{ $chartPaid > 0 ? 'ACP payment' : 'project cost' }}.</p>
            </div>
            <div class="relative h-52 p-4">
                <canvas data-tupad-chart='@json($liquidationSpec)' aria-label="Liquidation progress chart" role="img"></canvas>
                <div class="pointer-events-none absolute inset-x-0 top-[38%] text-center">
                    <div class="text-2xl font-extrabold text-slate-900">{{ $chartLiquidationPercent }}%</div>
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">liquidated</div>
                </div>
            </div>
        </div>
    @endif
</section>
