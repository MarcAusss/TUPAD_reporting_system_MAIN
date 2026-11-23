{{--
    Focal dashboard overview: fund position, projects by status, beneficiaries,
    implementation timing, financial flow and recent activity.
    Data: App\Services\Dashboards\FocalDashboardOverviewService.
--}}
@php
    $overview = $focalOverview;
    $finance = $overview['finance'];
    $status = $overview['status'];
    $people = $overview['beneficiaries'];
    $timing = $overview['implementation'];

    $peso = fn (float $value): string => '₱'.number_format($value, 2);
    $compact = function (float $value): string {
        $abs = abs($value);

        return match (true) {
            $abs >= 1_000_000_000 => '₱'.number_format($value / 1_000_000_000, 2).'B',
            $abs >= 1_000_000 => '₱'.number_format($value / 1_000_000, 2).'M',
            $abs >= 1_000 => '₱'.number_format($value / 1_000, 1).'K',
            default => '₱'.number_format($value, 0),
        };
    };
    $share = fn (float $part, float $whole): float => $whole > 0 ? min(100, max(0, $part / $whole * 100)) : 0;

    $icons = [
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h12v4"/><path d="M3 7v10a2 2 0 0 0 2 2h14V9H5a2 2 0 0 1-2-2Z"/><circle cx="16" cy="14" r="1.2"/>',
        'pie' => '<path d="M21 12A9 9 0 1 1 12 3v9Z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15Z"/>',
        'doc' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'bank' => '<path d="m3 9 9-5 9 5"/><path d="M5 10v7M9.5 10v7M14.5 10v7M19 10v7M3 20h18"/>',
        'trend' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    ];

    $adlCount = $finance['adl_count'] ?? $totalAdls;
    $kpis = [
        ['Total ADL Allocation', $finance['budget'], 'wallet', 'Program funds across '.number_format($adlCount).' ADL'.($adlCount === 1 ? '' : 's')],
        ['Total Allocated', $finance['allocated'], 'pie', number_format($share($finance['allocated'], $finance['budget']), 1).'% of the ADL allocation'],
        ['Total Obligated', $finance['obligated'], 'doc', number_format($share($finance['obligated'], $finance['allocated']), 1).'% of the allocated amount'],
        ['Total Paid', $finance['paid'], 'card', number_format($share($finance['paid'], $finance['obligated']), 1).'% of the obligated amount'],
        ['Fund Balance', $finance['balance'], 'bank', 'ADL allocation not yet allocated'],
    ];

    $flow = [
        ['Total Allocation', $finance['budget'], '#063b86'],
        ['Total Allocated', $finance['allocated'], '#1765d8'],
        ['Total Obligated', $finance['obligated'], '#4f8ef0'],
        ['Total Paid', $finance['paid'], '#16864b'],
        ['Fund Balance', $finance['balance'], '#94a3b8'],
    ];
    $flowMax = max(1, ...array_map(fn ($row) => $row[1], $flow));

    $statusRows = collect($status['rows']);

    // Only Focal sets the beneficiary targets and opens Fund Monitoring.
    $canEditTargets = auth()->user()?->isFocal() ?? false;
@endphp

<div class="space-y-5" data-focal-overview>

    {{-- Fund position --}}
    <section data-focal-operations-overview aria-labelledby="focal-fund-position-title">
        <h2 id="focal-fund-position-title" class="sr-only">Fund Position</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6" data-focal-fund-position>
            @foreach ($kpis as [$label, $value, $icon, $note])
                <article class="tupad-kpi group">
                    <span class="tupad-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
                    </span>
                    <div class="min-w-0">
                        <div class="tupad-kpi-label">{{ $label }}</div>
                        <div class="tupad-kpi-value" title="{{ $peso($value) }}">{{ $peso($value) }}</div>
                        <div class="tupad-kpi-note">{{ $note }}</div>
                    </div>
                </article>
            @endforeach

            <article class="tupad-kpi">
                <span class="tupad-kpi-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['trend'] !!}</svg>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="tupad-kpi-label">Utilization Rate</div>
                    <div class="tupad-kpi-value">{{ number_format($finance['utilization'], 2) }}%</div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar"
                        aria-label="Utilization rate" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($finance['utilization'], 1) }}">
                        <div class="h-full rounded-full bg-linear-to-r from-[#1765d8] to-[#063b86]" style="width: {{ $finance['utilization'] }}%"></div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-12">

        {{-- Projects by status (status colours match the workflow pills used across the system) --}}
        <section class="tupad-panel xl:col-span-5" data-focal-status-chart aria-labelledby="focal-status-title">
            <header class="tupad-panel-header">
                <div>
                    <p class="tupad-panel-eyebrow">Workflow</p>
                    <h2 id="focal-status-title" class="tupad-panel-title">Projects by Status</h2>
                </div>
                <a href="{{ route('projects.index') }}" class="tupad-panel-link">Project Registry →</a>
            </header>

            <div class="grid items-center gap-6 p-5 sm:grid-cols-[11rem_minmax(0,1fr)]">
                <div class="relative mx-auto h-44 w-44 shrink-0 rounded-full shadow-[inset_0_0_0_1px_rgba(15,35,71,.06)]"
                    style="background: {{ $status['gradient'] }};" role="img"
                    data-chart-donut="{{ json_encode($statusRows->map(fn ($row) => ['label' => $row['label'], 'value' => $row['count'], 'display' => number_format($row['count']).' '.($row['count'] === 1 ? 'project' : 'projects'), 'percent' => $row['percent'], 'color' => $row['color']])->values()) }}"
                    data-chart-donut-hole="0.64"
                    aria-label="Projects by status: {{ $statusRows->where('count', '>', 0)->map(fn ($row) => $row['label'].' '.$row['count'])->implode(', ') ?: 'no projects yet' }}">
                    <div class="absolute inset-[18%] flex flex-col items-center justify-center rounded-full bg-white text-center shadow-sm">
                        <span class="text-3xl font-extrabold leading-none tracking-tight text-[#0f2347] tabular-nums">{{ number_format($status['total']) }}</span>
                        <span class="mt-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Projects</span>
                    </div>
                </div>

                <ul class="grid gap-x-5 gap-y-1.5 text-xs sm:grid-cols-1 2xl:grid-cols-2">
                    @foreach ($statusRows as $row)
                        <li class="flex items-center justify-between gap-3 rounded-md px-1 {{ $row['count'] === 0 ? 'opacity-55' : 'hover:bg-slate-50' }}"
                            data-chart-tip="{{ $row['label'] }}" data-chart-tip-color="{{ $row['color'] }}"
                            data-chart-tip-value="{{ number_format($row['count']) }} {{ $row['count'] === 1 ? 'project' : 'projects' }} · {{ number_format($row['percent'], 1) }}%">
                            <span class="flex min-w-0 items-center gap-2 text-slate-700">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                                <span class="truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                            </span>
                            <span class="shrink-0 tabular-nums">
                                <span class="font-bold text-slate-900">{{ number_format($row['count']) }}</span>
                                <span class="ml-1 text-slate-500">{{ number_format($row['percent'], 1) }}%</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- Beneficiary overview: totals, sex split, and term targets (editable by Focal) --}}
        @php
            $targets = $people['targets'];
            $targetYear = $targets['year'];
            $femaleShare = $share($people['female'], $people['total']);
            $maleShare = $people['total'] > 0 ? 100 - $femaleShare : 0;
            $termCards = [
                ['short_term', 'Short-Term', '10–30 days', $people['short_term'], '#1765d8'],
                ['long_term', 'Long-Term', '31–90 days', $people['long_term'], '#063b86'],
            ];
        @endphp
        <section class="tupad-panel xl:col-span-4" data-focal-beneficiaries aria-labelledby="focal-beneficiaries-title">
            <header class="tupad-panel-header">
                <div>
                    <p class="tupad-panel-eyebrow">People</p>
                    <h2 id="focal-beneficiaries-title" class="tupad-panel-title">Beneficiary Overview</h2>
                </div>
                @if ($canEditTargets)
                <button type="button" class="tupad-panel-link inline-flex items-center gap-1.5" data-wizard-open="beneficiaryTargetsDialog" data-beneficiary-targets-open>
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3"/></svg>
                    Set targets
                </button>
                @endif
            </header>

            <div class="p-5">
                <div class="flex items-end justify-between gap-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Total Beneficiaries</div>
                    <div class="text-4xl font-extrabold leading-none tracking-tight text-[#0f2347] tabular-nums">{{ number_format($people['total']) }}</div>
                </div>

                <div class="mt-5 flex h-3 overflow-hidden rounded-full bg-slate-100" role="img"
                    aria-label="{{ number_format($people['male']) }} male and {{ number_format($people['female']) }} female">
                    <div class="h-full bg-[#1765d8]" style="width: {{ $maleShare }}%"
                        data-chart-tip="Male" data-chart-tip-color="#1765d8"
                        data-chart-tip-value="{{ number_format($people['male']) }} · {{ number_format($maleShare, 1) }}%"></div>
                    <div class="h-full bg-[#db2777]" style="width: {{ $femaleShare }}%"
                        data-chart-tip="Female" data-chart-tip-color="#db2777"
                        data-chart-tip-value="{{ number_format($people['female']) }} · {{ number_format($femaleShare, 1) }}%"></div>
                </div>
                <dl class="mt-2.5 flex justify-between gap-3 text-xs">
                    <div>
                        <dt class="flex items-center gap-1.5 font-semibold uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-full bg-[#1765d8]"></span>Male</dt>
                        <dd class="mt-0.5 text-lg font-bold text-slate-900 tabular-nums">{{ number_format($people['male']) }}</dd>
                    </div>
                    <div class="text-right">
                        <dt class="flex items-center justify-end gap-1.5 font-semibold uppercase tracking-wide text-slate-500"><span class="h-2 w-2 rounded-full bg-[#db2777]"></span>Female</dt>
                        <dd class="mt-0.5 text-lg font-bold text-slate-900 tabular-nums">{{ number_format($people['female']) }}</dd>
                    </div>
                </dl>

                <div class="mt-5 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4" data-beneficiary-term-targets>
                    @foreach ($termCards as [$termKey, $termLabel, $termDays, $termValue, $termColor])
                        @php($term = $targets['terms'][$termKey])
                        <article class="overflow-hidden rounded-xl border border-slate-200 bg-[#f8fafd]">
                            <div class="px-3.5 pt-3">
                                <div class="flex items-baseline justify-between gap-2">
                                    <h3 class="text-xs font-bold text-slate-700">{{ $termLabel }}</h3>
                                    <span class="text-[11px] text-slate-500">{{ $termDays }}</span>
                                </div>
                                <div class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 tabular-nums">{{ number_format($termValue) }}</div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200/70" role="progressbar"
                                    aria-label="{{ $termLabel }} FY{{ $targetYear }} progress toward target"
                                    aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($term['percent'], 1) }}"
                                    data-chart-tip="{{ $termLabel }} · FY{{ $targetYear }}" data-chart-tip-color="{{ $termColor }}"
                                    data-chart-tip-value="{{ $term['target'] === null ? 'No target set' : number_format($term['achieved']).' of '.number_format($term['target']).' · '.number_format($term['percent'], 1).'%' }}">
                                    <div class="h-full rounded-full" style="width: {{ $term['percent'] }}%; background: {{ $termColor }}"></div>
                                </div>
                            </div>
                            <dl class="mt-3 grid grid-cols-2 divide-x divide-slate-200 border-t border-slate-200 bg-white text-center">
                                <div class="px-2 py-2">
                                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Target</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums {{ $term['target'] === null ? 'text-slate-400' : 'text-slate-900' }}">
                                        {{ $term['target'] === null ? '—' : number_format($term['target']) }}
                                    </dd>
                                </div>
                                <div class="px-2 py-2">
                                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Balance</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums {{ $term['balance'] === null ? 'text-slate-400' : ($term['balance'] === 0 ? 'text-emerald-600' : 'text-amber-600') }}">
                                        {{ $term['balance'] === null ? '—' : number_format($term['balance']) }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-slate-500">Target and balance are region-wide for FY{{ $targetYear }}.</p>
            </div>
        </section>

        @if ($canEditTargets)
        {{-- Target editor (modal) --}}
        @php($targetErrors = $errors->getBag('beneficiaryTargets'))
        <dialog id="beneficiaryTargetsDialog" data-wizard-dialog @if ($targetErrors->any()) data-wizard-open-on-load @endif
            class="tupad-dialog w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl"
            aria-labelledby="beneficiary-targets-title">
            <form method="POST" action="{{ route('dashboard.beneficiary-targets.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="fiscal_year" value="{{ $targetYear }}">

                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 id="beneficiary-targets-title" class="text-base font-bold text-[#0f2347]">Beneficiary Targets · FY{{ $targetYear }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">Region-wide number of beneficiaries to reach this year. Change the Year filter to set another year.</p>
                    </div>
                    <button type="button" data-wizard-close class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4 px-5 py-5">
                    @foreach ($termCards as [$termKey, $termLabel, $termDays])
                        @php($term = $targets['terms'][$termKey])
                        <div>
                            <label for="target-{{ $termKey }}" class="mb-1.5 flex items-baseline justify-between gap-3 text-sm font-semibold text-slate-700">
                                <span>{{ $termLabel }} target <span class="font-normal text-slate-500">({{ $termDays }})</span></span>
                                <span class="text-xs font-normal text-slate-500">{{ number_format($term['achieved']) }} reached</span>
                            </label>
                            <input id="target-{{ $termKey }}" name="{{ $termKey }}" type="number" min="0" max="10000000" step="1" required inputmode="numeric"
                                value="{{ old($termKey, $term['target'] ?? '') }}" placeholder="e.g. 5000"
                                @if ($targetErrors->has($termKey)) aria-invalid="true" aria-describedby="target-{{ $termKey }}-error" @endif
                                class="h-11 w-full rounded-lg border {{ $targetErrors->has($termKey) ? 'border-rose-400' : 'border-slate-300' }} px-3 text-sm tabular-nums text-slate-900 outline-none focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/20">
                            @if ($targetErrors->has($termKey))
                                <p id="target-{{ $termKey }}-error" class="mt-1 text-xs text-rose-600">{{ $targetErrors->first($termKey) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3.5">
                    <button type="button" data-wizard-close class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d] focus:outline-none focus:ring-2 focus:ring-blue-300">Save targets</button>
                </div>
            </form>
        </dialog>
        @endif

        {{-- Implementation timing --}}
        <section class="tupad-panel xl:col-span-3" data-focal-implementation aria-labelledby="focal-implementation-title">
            <header class="tupad-panel-header">
                <div>
                    <p class="tupad-panel-eyebrow">Field Work</p>
                    <h2 id="focal-implementation-title" class="tupad-panel-title">Implementation</h2>
                </div>
            </header>

            <ul class="space-y-5 p-5">
                @php($timingMax = max(1, $timing['ongoing'], $timing['post_docs']))
                @foreach ([
                    ['Ongoing implementation', $timing['ongoing'], '#7c3aed', 'Projects currently in their work period', $share($timing['ongoing'], $timingMax)],
                    ['Ending soon', $timing['ending_soon'], '#d97706', 'Work period ends within '.$timing['ending_soon_days'].' days', $share($timing['ending_soon'], max(1, $timing['ongoing']))],
                    [\App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS->label(), $timing['post_docs'], '#f97316', 'Work done, waiting for post-implementation documents', $share($timing['post_docs'], $timingMax)],
                ] as [$label, $value, $color, $hint, $width])
                    <li>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-xs font-semibold text-slate-700">{{ $label }}</span>
                            <span class="text-2xl font-extrabold tabular-nums" style="color: {{ $color }}">{{ number_format($value) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"
                            data-chart-tip="{{ $label }}" data-chart-tip-color="{{ $color }}"
                            data-chart-tip-value="{{ number_format($value) }} {{ $value === 1 ? 'project' : 'projects' }}">
                            <div class="h-full rounded-full" style="background: {{ $color }}; width: {{ $width }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $hint }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Financial flow --}}
        <section class="tupad-panel xl:col-span-8" data-focal-financial-overview aria-labelledby="focal-financial-title">
            <header class="tupad-panel-header">
                <div>
                    <p class="tupad-panel-eyebrow">Fund Flow</p>
                    <h2 id="focal-financial-title" class="tupad-panel-title">Financial Overview</h2>
                </div>
                @if ($canEditTargets && Route::has('fund-monitoring.summary-current'))
                    <a href="{{ route('fund-monitoring.summary-current') }}" class="tupad-panel-link">Fund Monitoring →</a>
                @endif
            </header>

            <div class="overflow-x-auto p-5">
                <div class="grid h-64 min-w-120 grid-cols-5 items-end gap-4 border-b border-slate-200 bg-[linear-gradient(to_top,#eef2f7_1px,transparent_1px)] bg-size-[100%_25%] px-2"
                    role="img" aria-label="Financial overview: {{ collect($flow)->map(fn ($row) => $row[0].' '.$peso($row[1]))->implode(', ') }}">
                    @foreach ($flow as [$label, $value, $color])
                        <div class="flex h-full flex-col items-center justify-end"
                            data-chart-tip="{{ $label }}" data-chart-tip-value="{{ $peso($value) }}{{ $label !== 'Total Allocation' && $finance['budget'] > 0 ? ' · '.number_format($share($value, $finance['budget']), 1).'% of allocation' : '' }}"
                            data-chart-tip-color="{{ $color }}">
                            <span class="mb-1.5 text-xs font-bold text-slate-800 tabular-nums">{{ $compact($value) }}</span>
                            <div class="w-full max-w-18 rounded-t-lg transition-[height] duration-500"
                                style="height: {{ max($value > 0 ? 2 : 0, $value / $flowMax * 100) }}%; background: linear-gradient(to top, {{ $color }}, {{ $color }}cc);"></div>
                        </div>
                    @endforeach
                </div>
                <div class="grid min-w-120 grid-cols-5 gap-4 px-2 pt-2 text-center">
                    @foreach ($flow as [$label])
                        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Recent activity --}}
        <section class="tupad-panel xl:col-span-4" data-focal-recent-activity aria-labelledby="focal-activity-title">
            <header class="tupad-panel-header">
                <div>
                    <p class="tupad-panel-eyebrow">Latest Changes</p>
                    <h2 id="focal-activity-title" class="tupad-panel-title">Recent Activity</h2>
                </div>
            </header>

            @if ($overview['activity'] === [])
                <p class="px-5 py-10 text-center text-xs text-slate-500">No recorded activity yet.</p>
            @else
                <ol class="divide-y divide-slate-100 px-5">
                    @foreach ($overview['activity'] as $item)
                        @php($tone = ['emerald' => 'bg-emerald-50 text-emerald-600', 'rose' => 'bg-rose-50 text-rose-600', 'blue' => 'bg-blue-50 text-[#1765d8]'][$item['tone']])
                        <li class="flex items-start gap-3 py-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $tone }}" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    @if ($item['tone'] === 'emerald')<path d="M12 5v14M5 12h14"/>@elseif ($item['tone'] === 'rose')<path d="M5 12h14"/>@else<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3"/>@endif
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-900">{{ $item['title'] }}</p>
                                <p class="truncate text-[11px] text-slate-500">By {{ $item['by'] }}</p>
                            </div>
                            @if ($item['when'])
                                <time class="shrink-0 text-[11px] text-slate-500" datetime="{{ $item['when']->toIso8601String() }}" title="{{ $item['when']->format('M j, Y g:i A') }}">
                                    {{ $item['when']->diffForHumans(short: true) }}
                                </time>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

    </div>
</div>
