<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Executive Presentation | {{ config('app.name', 'TUPAD Reporting System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eef3f9] text-[#0f2347] antialiased">
    @php
        $kpi = $dashboard['kpis'];
        $money = fn (?int $cents) => $cents === null ? 'Not available' : '₱'.number_format($cents / 100, 2);
        $percent = fn ($value) => number_format((float) ($value ?? 0), 1).'%';
        $maxStatus = max(1, collect($dashboard['projects_by_status'])->max('project_count'));
        $maxTrend = max(1, collect($dashboard['physical_trend'])->max('project_count'));
        $maxProvince = max(1, collect($dashboard['beneficiaries_by_province'])->max('beneficiaries_total'));
        $maxPriority = max(1, collect($dashboard['sector_priority'])->max('beneficiaries_total'));
        $maxOccupational = max(1, collect($dashboard['sector_occupational'])->max('beneficiaries_total'));
        $maxIntervention = max(1, collect($dashboard['intervention_focus'])->max('project_count'));
        $maxLabor = max(1, collect($dashboard['labor_market_programs'])->max('interested_referred_total'));
        $financialMax = $dashboard['financial_position']
            ? max(1, (int) $dashboard['financial_position']['allocation_cents'], (int) $dashboard['financial_position']['obligated_cents'], (int) $dashboard['financial_position']['disbursed_cents'], (int) $dashboard['financial_position']['balance_cents'])
            : 1;
    @endphp

    <main data-presentation-mode class="mx-auto flex min-h-screen max-w-[1920px] flex-col px-4 py-4 sm:px-6 lg:px-8">
        <header class="mb-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm lg:px-7">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex items-center gap-4">
                    <div class="grid h-12 w-12 shrink-0 grid-cols-2 gap-1 rounded-xl bg-[#063b86] p-2.5">
                        <span class="rounded-sm bg-white"></span><span class="rounded-sm bg-white/75"></span>
                        <span class="rounded-sm bg-white/75"></span><span class="rounded-sm bg-white"></span>
                    </div>
                    <div>
                        <div class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-blue-700">Department of Labor and Employment</div>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#071d44] lg:text-3xl">TUPAD Executive Presentation</h1>
                        <p class="mt-1 text-xs text-slate-500 lg:text-sm">Read-only briefing view · Generated {{ $dashboard['generated_at']->format('M d, Y g:i A') }} · Asia/Manila</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-presentation-fullscreen class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Full Screen
                    </button>
                    <a href="{{ route('executive-dashboard.index', $query) }}" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                        Exit Presentation
                    </a>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3" aria-label="Active presentation filters">
                @forelse ($dashboard['active_filters'] as $label => $value)
                    <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-900">{{ $label }}: {{ $value }}</span>
                @empty
                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">All program records</span>
                @endforelse
            </div>
        </header>

        <div class="min-h-0 flex-1">
            <section data-presentation-slide tabindex="-1" class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6 flex items-end justify-between gap-4">
                    <div>
                        <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 1</div>
                        <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Executive Overview</h2>
                    </div>
                    <div class="text-sm font-semibold text-slate-500">Phase 8 reporting data</div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['Total Projects', number_format($kpi['total_projects'])],
                        ['Completed Projects', number_format($kpi['completed_projects'])],
                        ['Ongoing Implementation', number_format($kpi['ongoing_implementation'])],
                        ['For Payment', number_format($kpi['for_payment'])],
                        ['For Check Release', number_format($kpi['for_check_release'])],
                        ['For Liquidation', number_format($kpi['for_liquidation'])],
                        ['Total Beneficiaries', number_format($kpi['beneficiaries_total'])],
                        ['Female Beneficiaries', number_format($kpi['beneficiaries_female'])],
                        ['Physical Accomplishment', $percent($kpi['physical_accomplishment_percent'])],
                        ['Financial Accomplishment', $kpi['financial_accomplishment_percent'] === null ? 'Not available' : $percent($kpi['financial_accomplishment_percent'])],
                    ] as [$label, $value])
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5 lg:p-6">
                            <div class="text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                            <div class="mt-3 text-4xl font-extrabold tracking-tight text-slate-900 lg:text-5xl">{{ $value }}</div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section data-presentation-slide tabindex="-1" hidden class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 2</div>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Physical Accomplishment</h2>
                    <p class="mt-2 text-sm text-slate-500">Project status is sourced from the consolidated status engine. Period trend uses projects.date_received.</p>
                </div>
                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <h3 class="mb-5 text-lg font-bold text-slate-900">Projects by Status</h3>
                        <div class="space-y-3">
                            @foreach ($dashboard['projects_by_status'] as $row)
                                <div>
                                    <div class="mb-1.5 flex justify-between gap-4 text-sm"><span class="font-semibold text-slate-700">{{ $row['label'] }}</span><span class="font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</span></div>
                                    <div class="h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#063b86]" style="width:{{ min(100, ($row['project_count'] / $maxStatus) * 100) }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <h3 class="mb-5 text-lg font-bold text-slate-900">By {{ $dashboard['physical_trend_dimension']->label() }}</h3>
                        <div class="space-y-3">
                            @forelse ($dashboard['physical_trend'] as $row)
                                <div>
                                    <div class="mb-1.5 flex justify-between gap-4 text-sm"><span class="font-semibold text-slate-700">{{ $row['label'] }}</span><span class="font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</span></div>
                                    <div class="h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width:{{ min(100, ($row['project_count'] / $maxTrend) * 100) }}%"></div></div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">No projects match the selected period.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section data-presentation-slide tabindex="-1" hidden class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 3</div>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Financial Accomplishment</h2>
                </div>
                @if ($dashboard['financial_position'])
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ([
                            ['TUPAD Allocation', $dashboard['financial_position']['allocation_cents']],
                            ['Total Obligated', $dashboard['financial_position']['obligated_cents']],
                            ['Total Disbursed', $dashboard['financial_position']['disbursed_cents']],
                            ['Remaining Balance', $dashboard['financial_position']['balance_cents']],
                        ] as [$label, $value])
                            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                                <div class="text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                                <div class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 xl:text-4xl">{{ $money((int) $value) }}</div>
                                <div class="mt-5 h-4 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-[#063b86]" style="width:{{ min(100, (max(0, (int) $value) / $financialMax) * 100) }}%"></div></div>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900">Financial accomplishment: <strong>{{ $percent($kpi['financial_accomplishment_percent']) }}</strong>. Balance basis is allocation less total disbursed.</div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        @foreach ([
                            ['DA Obligated', $dashboard['financial_position']['direct_admin_obligated_cents'], true],
                            ['DA Disbursed', $dashboard['financial_position']['direct_admin_disbursed_cents'], true],
                            ['ACP Payment Recorded', $dashboard['financial_position']['acp_payment_cents'], true],
                            ['ACP Check Released', $dashboard['financial_position']['acp_check_released_cents'], true],
                            ['ACP Liquidated', $dashboard['financial_position']['acp_liquidated_cents'], true],
                        ] as [$label, $value, $isMoney])
                            <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                                <div class="mt-2 text-xl font-extrabold text-slate-900">{{ $isMoney ? $money((int) $value) : $value }}</div>
                            </article>
                        @endforeach
                    </div>
                    <p class="mt-4 text-xs leading-5 text-slate-500">{{ $dashboard['financial_basis_note'] }}</p>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-lg leading-8 text-amber-900">{{ $dashboard['financial_note'] }}</div>
                @endif
            </section>

            <section data-presentation-slide tabindex="-1" hidden class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 4</div>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Geographic Accomplishment</h2>
                    <p class="mt-2 max-w-5xl text-sm text-slate-500">{{ $dashboard['geography_note'] }}</p>
                </div>
                <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @forelse ($dashboard['beneficiaries_by_province'] as $row)
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">{{ $row['label'] }}</h3>
                                    <p class="mt-1 text-xs text-slate-500">{{ number_format($row['project_count']) }} matching project(s)</p>
                                </div>
                                <div class="text-4xl font-extrabold text-[#063b86]">{{ number_format($row['beneficiaries_total']) }}</div>
                            </div>
                            <div class="mt-5 h-4 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-blue-600" style="width:{{ min(100, ($row['beneficiaries_total'] / $maxProvince) * 100) }}%"></div></div>
                            @if (! $row['has_complete_exact_allocation'])
                                <p class="mt-3 text-xs font-semibold text-amber-700">Legacy allocation incomplete; missing pivot values were not guessed.</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-base text-slate-500">No exact geographic beneficiary allocations match the selected filters.</p>
                    @endforelse
                </div>
            </section>

            <section data-presentation-slide tabindex="-1" hidden class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 5</div>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Beneficiary Classification</h2>
                    <p class="mt-2 text-sm font-semibold text-amber-700">{{ $dashboard['sector_note'] }}</p>
                </div>
                <div class="grid gap-5 xl:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                        <h3 class="text-lg font-bold text-slate-900">Total vs Female</h3>
                        <div class="mt-5 text-5xl font-extrabold text-slate-900">{{ number_format($kpi['beneficiaries_total']) }}</div>
                        <div class="mt-1 text-sm text-slate-500">Total beneficiaries</div>
                        <div class="mt-6 text-4xl font-extrabold text-blue-700">{{ number_format($kpi['beneficiaries_female']) }}</div>
                        <div class="mt-1 text-sm text-slate-500">Female beneficiaries</div>
                    </div>
                    @foreach ([['Priority / Vulnerable Sectors', $dashboard['sector_priority'], $maxPriority], ['Occupational / Livelihood Sectors', $dashboard['sector_occupational'], $maxOccupational]] as [$title, $rows, $maximum])
                        <div class="rounded-2xl border border-slate-200 p-5">
                            <h3 class="mb-4 text-lg font-bold text-slate-900">{{ $title }}</h3>
                            <div class="space-y-3">
                                @foreach ($rows as $row)
                                    <div>
                                        <div class="mb-1 flex justify-between gap-3 text-xs"><span class="font-semibold text-slate-700">{{ $row['sector_label'] }}</span><span class="font-extrabold text-slate-900">{{ number_format($row['beneficiaries_total']) }}</span></div>
                                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-[#063b86]" style="width:{{ min(100, ($row['beneficiaries_total'] / $maximum) * 100) }}%"></div></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section data-presentation-slide tabindex="-1" hidden class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 6</div>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Intervention Focus</h2>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($dashboard['intervention_focus'] as $row)
                        <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                            <h3 class="min-h-14 text-lg font-bold leading-7 text-slate-800">{{ $row['intervention_focus_label'] }}</h3>
                            <div class="mt-4 text-5xl font-extrabold text-[#063b86]">{{ number_format($row['project_count']) }}</div>
                            <div class="mt-1 text-sm text-slate-500">projects · {{ number_format($row['beneficiaries_total']) }} beneficiaries</div>
                            <div class="mt-5 h-4 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-blue-600" style="width:{{ min(100, ($row['project_count'] / $maxIntervention) * 100) }}%"></div></div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section data-presentation-slide tabindex="-1" hidden class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:p-8">
                <div class="mb-6">
                    <div class="text-xs font-extrabold uppercase tracking-[0.15em] text-blue-700">Section 7</div>
                    <h2 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900 lg:text-4xl">Active Labor Market Referrals</h2>
                    <p class="mt-2 max-w-5xl text-sm text-slate-500">{{ $dashboard['labor_market_note'] }}</p>
                </div>
                <div class="mb-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><div class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Referred</div><div class="mt-2 text-4xl font-extrabold text-slate-900">{{ number_format($dashboard['labor_market_overall']['interested_referred_total']) }}</div></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><div class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Provided Intervention</div><div class="mt-2 text-4xl font-extrabold text-slate-900">{{ number_format($dashboard['labor_market_overall']['provided_intervention_total']) }}</div></div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5"><div class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Amount Released</div><div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $money((int) $dashboard['labor_market_overall']['amount_released_cents']) }}</div></div>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($dashboard['labor_market_programs'] as $row)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <div class="flex items-start justify-between gap-4"><h3 class="font-bold text-slate-800">{{ $row['label'] }}</h3><span class="text-xl font-extrabold text-[#063b86]">{{ number_format($row['interested_referred_total']) }}</span></div>
                            <div class="mt-2 text-xs text-slate-500">{{ number_format($row['provided_intervention_total']) }} provided intervention · {{ $money((int) $row['amount_released_cents']) }} released</div>
                            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-[#063b86]" style="width:{{ min(100, ($row['interested_referred_total'] / $maxLabor) * 100) }}%"></div></div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <footer class="mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="text-xs font-semibold text-slate-500">Manual navigation only · Arrow keys supported · No automatic advancement</div>
            <div class="flex items-center gap-3">
                <button type="button" data-presentation-previous class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40">Previous</button>
                <span data-presentation-counter class="min-w-16 text-center text-sm font-extrabold text-slate-700">1 / 7</span>
                <button type="button" data-presentation-next class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Next</button>
            </div>
        </footer>
    </main>
</body>
</html>
