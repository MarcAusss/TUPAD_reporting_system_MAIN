{{-- Fund utilization per ADL (Focal and Admin dashboards). --}}
@php
    $adlRows = $adlUtilization ?? collect();
    $adlSpec = [
        'type' => 'bar',
        'horizontal' => true,
        'money' => true,
        'labels' => $adlRows->pluck('label')->all(),
        'datasets' => [
            ['label' => 'Budget (Adjusted Grants)', 'data' => $adlRows->pluck('budget')->all(), 'backgroundColor' => '#cbd5e1'],
            ['label' => 'Allocated', 'data' => $adlRows->pluck('allocated')->all(), 'backgroundColor' => '#063b86'],
            ['label' => 'Approved Amount', 'data' => $adlRows->pluck('project_cost')->all(), 'backgroundColor' => '#7dd3fc'],
            ['label' => 'Actual Amount', 'data' => $adlRows->pluck('actual_cost')->all(), 'backgroundColor' => '#10b981'],
        ],
    ];
    $adlTotals = [
        'budget' => (float) $adlRows->sum('budget'),
        'allocated' => (float) $adlRows->sum('allocated'),
        'project_cost' => (float) $adlRows->sum('project_cost'),
        'actual_cost' => (float) $adlRows->sum('actual_cost'),
    ];
@endphp

@if ($adlRows->isNotEmpty())
    <section class="tupad-card mt-5 overflow-hidden" data-dashboard-adl-utilization>
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Fund Utilization per ADL</h2>
                <p class="mt-1 text-xs text-slate-500">Budget vs allocated vs approved and actual project amounts for each ADL.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-[11px] font-semibold">
                <span class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-slate-600">
                    Allocated {{ $adlTotals['budget'] > 0 ? number_format($adlTotals['allocated'] / $adlTotals['budget'] * 100, 1) : '0.0' }}%
                </span>
                <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-emerald-700">
                    Actual {{ $adlTotals['budget'] > 0 ? number_format($adlTotals['actual_cost'] / $adlTotals['budget'] * 100, 1) : '0.0' }}%
                </span>
                @if (Route::has('fund-monitoring.per-adl-current'))
                    <a href="{{ route('fund-monitoring.per-adl-current') }}" class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[#063b86] hover:bg-blue-100">Per ADL details →</a>
                @endif
            </div>
        </div>
        <div class="px-4 pb-4 pt-3 sm:px-5" style="height: {{ min(560, max(220, 70 + $adlRows->count() * 44)) }}px">
            <canvas data-tupad-chart='@json($adlSpec)' role="img" aria-label="Fund utilization per ADL chart"></canvas>
        </div>
    </section>
@endif
