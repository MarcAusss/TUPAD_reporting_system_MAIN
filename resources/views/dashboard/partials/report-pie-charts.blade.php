{{--
    Report pie charts (Focal and TUPAD Coordinator dashboards): one pie per
    official report, scoped like the report itself. Drawn with a CSS conic
    gradient, so no chart script is needed; the legend carries the numbers.
--}}
@php
    $reportCharts = $reportCharts ?? ['charts' => [], 'year' => now()->year, 'scope' => 'province'];
@endphp

<section class="mt-5" data-dashboard-report-charts>
    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Reports · FY {{ $reportCharts['year'] }}</div>
            <h2 class="mt-1 text-base font-bold text-slate-900">
                {{ $reportCharts['scope'] === 'municipality' ? 'Your Province by Report' : 'Bicol Region by Report' }}
            </h2>
        </div>
        <p class="text-[11px] text-slate-500 sm:max-w-md sm:text-right">
            Each pie uses the same data as its report{{ $reportCharts['scope'] === 'municipality' ? ', split by municipality of your assigned province' : ', split by province' }}. Open a report for the full table.
        </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($reportCharts['charts'] as $chart)
            <article class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm" data-report-chart="{{ $chart['key'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900">{{ $chart['title'] }}</h3>
                        <p class="text-[11px] text-slate-500">{{ $chart['subtitle'] }}</p>
                    </div>
                    <a href="{{ $chart['url'] }}" class="shrink-0 text-[11px] font-semibold text-blue-700 hover:text-blue-900">Open report →</a>
                </div>

                <div class="mt-4 flex items-center gap-4">
                    {{-- Size and shape are inline so the pie never depends on the compiled CSS. --}}
                    <div role="img"
                        data-chart-donut="{{ json_encode(collect($chart['slices'])->map(fn ($slice) => ['label' => $slice['label'], 'value' => $slice['value'], 'display' => number_format($slice['value']).' '.$chart['unit'], 'percent' => $slice['percent'], 'color' => $slice['color']])->values()) }}"
                        data-chart-donut-hole="0.56"
                        aria-label="{{ $chart['title'] }}: {{ collect($chart['slices'])->map(fn ($slice) => $slice['label'].' '.number_format($slice['value']))->implode(', ') ?: 'no data' }}"
                        style="position: relative; flex: 0 0 auto; width: 8.5rem; height: 8.5rem; border-radius: 9999px; background: {{ $chart['gradient'] ?? '#e2e8f0' }};">
                        {{-- Donut hole with the total. --}}
                        <div style="position: absolute; inset: 22%; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 9999px; background: #fff; text-align: center;">
                            <span class="text-lg font-extrabold leading-none text-slate-900">{{ number_format($chart['total']) }}</span>
                            <span class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $chart['unit'] }}</span>
                        </div>
                    </div>

                    @if ($chart['slices'] === [])
                        <p class="text-xs text-slate-400">No {{ $chart['unit'] }} recorded yet for this report.</p>
                    @else
                        <ul class="min-w-0 flex-1 space-y-1.5">
                            @foreach (array_slice($chart['slices'], 0, 7) as $slice)
                                <li class="rounded-md px-1 text-[11px] hover:bg-slate-50"
                                    data-chart-tip="{{ $slice['label'] }}{{ ! empty($slice['note']) ? ' — '.$slice['note'] : '' }}"
                                    data-chart-tip-color="{{ $slice['color'] }}"
                                    data-chart-tip-value="{{ number_format($slice['value']) }} {{ $chart['unit'] }} · {{ number_format($slice['percent'], 1) }}%">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="flex min-w-0 items-center gap-1.5 text-slate-700">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $slice['color'] }}"></span>
                                            <span class="truncate">{{ $slice['label'] }}</span>
                                        </span>
                                        <span class="shrink-0 font-semibold tabular-nums text-slate-900">
                                            {{ number_format($slice['value']) }}
                                            <span class="font-normal text-slate-400">{{ number_format($slice['percent'], 1) }}%</span>
                                        </span>
                                    </div>
                                    @if (! empty($slice['note']))
                                        <div class="truncate pl-4 text-[11px] text-slate-400">{{ $slice['note'] }}</div>
                                    @endif
                                </li>
                            @endforeach
                            @if (count($chart['slices']) > 7)
                                <li class="pl-4 text-[11px] font-semibold text-slate-400">+ {{ count($chart['slices']) - 7 }} more in the report</li>
                            @endif
                        </ul>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</section>
