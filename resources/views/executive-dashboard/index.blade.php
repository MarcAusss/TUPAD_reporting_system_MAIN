@extends('layouts.app')

@section('title', 'Executive Dashboard')

@section('content')
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
            ? max(
                1,
                (int) $dashboard['financial_position']['allocation_cents'],
                (int) $dashboard['financial_position']['obligated_cents'],
                (int) $dashboard['financial_position']['disbursed_cents'],
                (int) $dashboard['financial_position']['balance_cents']
            )
            : 1;

        $completionRate = $kpi['total_projects'] > 0
            ? ($kpi['completed_projects'] / $kpi['total_projects']) * 100
            : 0;
        $femaleShare = $kpi['beneficiaries_total'] > 0
            ? ($kpi['beneficiaries_female'] / $kpi['beneficiaries_total']) * 100
            : 0;

        $advancedFilterCount = collect([
            request('month'),
            request('term'),
            request('adl_id'),
            request('district'),
            request('municipality_id'),
            request('barangay_id'),
            request('status'),
            request('sponsor'),
            request('partner'),
            request('project_code'),
            request('sector'),
            request('intervention_focus'),
            request('labor_market_program'),
        ])->filter(fn ($value) => filled($value))->count();
    @endphp

    <x-page-header eyebrow="Executive Reporting" title="Executive Dashboard"
        description="A concise regional view of program delivery, financial position, and geographic performance.">
        <x-slot:actions>
            <a href="{{ route('executive-dashboard.presentation', $query) }}"
                class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                Presentation Mode
            </a>
            <a href="{{ route('reports.index', $query) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Open Reports
            </a>
        </x-slot:actions>
    </x-page-header>

    <details data-executive-scope-panel class="group tupad-card mb-5 overflow-hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
            <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Dashboard Scope</div>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-900">Focus the executive view</h2>
                    @if (count($dashboard['active_filters']) > 0)
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-blue-800">
                            {{ count($dashboard['active_filters']) }} active
                        </span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                            Regional view
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-500">Expand only when you need to change the reporting scope.</p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden text-right text-[10px] leading-4 text-slate-400 sm:block">
                    Generated {{ $dashboard['generated_at']->format('M d, Y g:i A') }}<br>
                    Asia/Manila
                </span>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500">
                    <svg class="h-4 w-4 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"></path>
                    </svg>
                </span>
            </div>
        </summary>

        <form method="GET" action="{{ route('executive-dashboard.index') }}" data-executive-filters class="p-5">
            <div class="grid gap-4 xl:grid-cols-12">
                <label class="xl:col-span-2 text-xs font-semibold text-slate-700">
                    Fiscal Year
                    <select name="fiscal_year" class="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All years</option>
                        @foreach ($options['fiscal_years'] as $year)
                            <option value="{{ $year }}" @selected((string) request('fiscal_year') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="xl:col-span-4">
                    <div class="text-xs font-semibold text-slate-700">Quarter</div>
                    <input type="hidden" name="quarter" value="{{ request('quarter') }}" data-executive-quarter-input>
                    <div class="mt-1.5 grid grid-cols-5 overflow-hidden rounded-lg border border-slate-300 bg-white" role="group" aria-label="Quarter filter">
                        @foreach (['' => 'All', '1' => 'Q1', '2' => 'Q2', '3' => 'Q3', '4' => 'Q4'] as $value => $label)
                            <button type="button" data-executive-quarter="{{ $value }}"
                                aria-pressed="{{ (string) request('quarter') === (string) $value ? 'true' : 'false' }}"
                                class="h-11 border-r border-slate-200 px-2 text-xs font-semibold transition last:border-r-0 {{ (string) request('quarter') === (string) $value ? 'bg-[#0f3b72] text-white' : 'bg-white text-slate-600 hover:bg-slate-50' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-[10px] leading-4 text-slate-400">Choose a fiscal year before selecting a quarter.</p>
                </div>

                <label class="xl:col-span-3 text-xs font-semibold text-slate-700">
                    Province
                    <select name="province_id" class="mt-1.5 h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All Bicol provinces</option>
                        @foreach ($options['provinces'] as $province)
                            <option value="{{ $province->id }}" @selected((string) request('province_id') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="xl:col-span-3">
                    <div class="text-xs font-semibold text-slate-700">Implementation Mode</div>
                    <div class="mt-1.5 grid grid-cols-3 overflow-hidden rounded-lg border border-slate-300 bg-white">
                        <label class="cursor-pointer border-r border-slate-200 last:border-r-0">
                            <input class="peer sr-only" type="radio" name="implementation_mode" value="" @checked(!request('implementation_mode'))>
                            <span class="flex h-11 items-center justify-center px-2 text-center text-[11px] font-semibold text-slate-600 transition peer-checked:bg-[#0f3b72] peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300">All</span>
                        </label>
                        @foreach ($options['implementation_modes'] as $mode)
                            <label class="cursor-pointer border-r border-slate-200 last:border-r-0">
                                <input class="peer sr-only" type="radio" name="implementation_mode" value="{{ $mode->value }}" @checked(request('implementation_mode') === $mode->value)>
                                <span class="flex h-11 items-center justify-center px-2 text-center text-[11px] font-semibold text-slate-600 transition peer-checked:bg-[#0f3b72] peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300">
                                    {{ $mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION ? 'Direct' : 'Through ACP' }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0f3b72] px-4 text-sm font-semibold text-white transition hover:bg-[#0b315f]">
                        Apply View
                    </button>
                    <a href="{{ route('executive-dashboard.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Reset
                    </a>
                </div>
                <div class="text-[11px] text-slate-500">
                    {{ count($dashboard['active_filters']) }} active filter(s)
                </div>
            </div>

            <details data-executive-more-filters class="group mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50/60" @if ($advancedFilterCount > 0) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"></path></svg>
                        More Filters
                        @if ($advancedFilterCount > 0)
                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800">{{ $advancedFilterCount }} active</span>
                        @endif
                    </span>
                    <svg class="h-4 w-4 transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg>
                </summary>

                <div class="border-t border-slate-200 bg-white p-4">
                    <div class="grid gap-5 xl:grid-cols-2">
                        <section class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-3">
                                <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-slate-500">Period & Project</h3>
                                <p class="mt-1 text-[11px] text-slate-400">Use these only when the executive view requires a specific reporting window or project subset.</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-semibold text-slate-700">
                                    Month
                                    <select name="month" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All months</option>
                                        @foreach (range(1, 12) as $month)
                                            <option value="{{ $month }}" @selected((string) request('month') === (string) $month)>{{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Term
                                    <select name="term" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All terms</option>
                                        @foreach ($options['terms'] as $term)
                                            <option value="{{ $term->value }}" @selected(request('term') === $term->value)>{{ $term->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    ADL
                                    <select name="adl_id" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All ADLs</option>
                                        @foreach ($options['adls'] as $adl)
                                            <option value="{{ $adl->id }}" @selected((string) request('adl_id') === (string) $adl->id)>{{ $adl->adl_number }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Project Status
                                    <select name="status" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All statuses</option>
                                        @foreach ($options['statuses'] as $status)
                                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700 sm:col-span-2">
                                    Project Code
                                    <input type="text" name="project_code" value="{{ request('project_code') }}" maxlength="255" placeholder="Exact approved project code"
                                        class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                </label>
                            </div>
                        </section>

                        <section class="rounded-lg border border-slate-200 p-4">
                            <div class="mb-3">
                                <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-slate-500">Detailed Geography</h3>
                                <p class="mt-1 text-[11px] text-slate-400">Province remains in Quick View; use these fields only for deeper geographic filtering.</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="text-xs font-semibold text-slate-700">
                                    District
                                    <select name="district" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All districts</option>
                                        @foreach ($options['districts'] as $district)
                                            <option value="{{ $district->district }}" data-province-id="{{ $district->province_id }}" @selected(request('district') === $district->district)>{{ $district->district }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Municipality / City
                                    <select name="municipality_id" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All municipalities / cities</option>
                                        @foreach ($options['municipalities'] as $municipality)
                                            <option value="{{ $municipality->id }}" data-province-id="{{ $municipality->province_id }}" data-district="{{ $municipality->district }}" @selected((string) request('municipality_id') === (string) $municipality->id)>{{ $municipality->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700 sm:col-span-2">
                                    Barangay
                                    <select name="barangay_id" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All barangays</option>
                                        @foreach ($options['barangays'] as $barangay)
                                            <option value="{{ $barangay->id }}" data-municipality-id="{{ $barangay->municipality_id }}" data-province-id="{{ $barangay->municipality?->province_id }}" data-district="{{ $barangay->municipality?->district }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </section>

                        <section class="rounded-lg border border-slate-200 p-4 xl:col-span-2">
                            <div class="mb-3">
                                <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-slate-500">Organization & Program</h3>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <label class="text-xs font-semibold text-slate-700">
                                    Sponsor
                                    <select name="sponsor" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All sponsors</option>
                                        @foreach ($options['sponsors'] as $sponsor)
                                            <option value="{{ $sponsor }}" @selected(request('sponsor') === $sponsor)>{{ $sponsor }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Partner / NGA
                                    <select name="partner" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All partners / NGAs</option>
                                        @foreach ($options['partners'] as $partner)
                                            <option value="{{ $partner }}" @selected(request('partner') === $partner)>{{ $partner }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Sector
                                    <select name="sector" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All sectors</option>
                                        @foreach ($options['sectors'] as $sector)
                                            <option value="{{ $sector->value }}" @selected(request('sector') === $sector->value)>{{ $sector->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700">
                                    Intervention Focus
                                    <select name="intervention_focus" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All intervention focuses</option>
                                        @foreach ($options['intervention_focuses'] as $focus)
                                            <option value="{{ $focus->value }}" @selected(request('intervention_focus') === $focus->value)>{{ $focus->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-semibold text-slate-700 lg:col-span-2">
                                    Labor Market Program
                                    <select name="labor_market_program" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">All labor market programs</option>
                                        @foreach ($options['labor_market_programs'] as $program)
                                            <option value="{{ $program->value }}" @selected(request('labor_market_program') === $program->value)>{{ $program->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        </section>
                    </div>
                </div>
            </details>
        </form>
    </details>

    @if ($dashboard['active_filters'])
        <div class="mb-5 flex flex-wrap gap-2" aria-label="Active dashboard filters">
            @foreach ($dashboard['active_filters'] as $label => $value)
                <span class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-[11px] font-semibold text-blue-900">
                    {{ $label }}: {{ $value }}
                </span>
            @endforeach
        </div>
    @endif

    @if ($dashboard['financial_note'])
        <div class="tupad-feedback tupad-feedback-error mb-5" role="note">
            <div class="tupad-feedback-icon">i</div>
            <div>
                <div class="tupad-feedback-title">Financial geography safeguard</div>
                <div class="tupad-feedback-message">{{ $dashboard['financial_note'] }}</div>
            </div>
        </div>
    @endif

    <section aria-labelledby="executive-snapshot-heading">
        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">Executive Snapshot</div>
                <h2 id="executive-snapshot-heading" class="mt-1 text-base font-bold text-slate-900">Regional performance at a glance</h2>
            </div>
            <span class="text-[11px] text-slate-500">Project period basis: projects.date_received</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="tupad-card p-5" data-testid="executive-kpi-total-projects">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Total Projects</div>
                        <div class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($kpi['total_projects']) }}</div>
                    </div>
                    <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-blue-800">{{ number_format($kpi['completed_projects']) }} completed</span>
                </div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, $completionRate) }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-[10px] text-slate-500">
                    <span>Completion</span>
                    <span>{{ $percent($completionRate) }}</span>
                </div>
            </article>

            <article class="tupad-card p-5" data-testid="executive-kpi-beneficiaries">
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Beneficiaries</div>
                <div class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">{{ number_format($kpi['beneficiaries_total']) }}</div>
                <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                    <span class="text-slate-500">Female beneficiaries</span>
                    <span class="font-bold text-slate-800" data-testid="executive-kpi-female">{{ number_format($kpi['beneficiaries_female']) }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, $femaleShare) }}%"></div>
                </div>
                <div class="mt-2 text-right text-[10px] text-slate-500">{{ $percent($femaleShare) }} female share</div>
            </article>

            <article class="tupad-card p-5" data-testid="executive-kpi-physical-percent">
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Physical Accomplishment</div>
                <div class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">{{ $percent($kpi['physical_accomplishment_percent']) }}</div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, (float) ($kpi['physical_accomplishment_percent'] ?? 0)) }}%"></div>
                </div>
                <p class="mt-3 text-[11px] leading-5 text-slate-500">Validated physical delivery for the selected project cohort.</p>
            </article>

            <article class="tupad-card p-5" data-testid="executive-kpi-financial-percent">
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Financial Accomplishment</div>
                <div class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900">
                    {{ $kpi['financial_accomplishment_percent'] === null ? 'Not available' : $percent($kpi['financial_accomplishment_percent']) }}
                </div>
                @if ($kpi['financial_accomplishment_percent'] !== null)
                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, (float) $kpi['financial_accomplishment_percent']) }}%"></div>
                    </div>
                @endif
                <p class="mt-3 text-[11px] leading-5 text-slate-500">Financial progress uses the authoritative reporting basis for the selected geography.</p>
            </article>
        </div>
    </section>

    <section class="tupad-card mt-5 overflow-hidden" aria-labelledby="executive-pulse-heading">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Operational Pulse</div>
                    <h2 id="executive-pulse-heading" class="mt-1 text-sm font-bold text-slate-900">Items that need executive attention</h2>
                </div>
                <span class="text-[10px] text-slate-400">Compact status summary</span>
            </div>
        </div>

        <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-6">
            @foreach ([
                ['Completed', $kpi['completed_projects'], 'executive-kpi-completed-projects'],
                ['Ongoing', $kpi['ongoing_implementation'], 'executive-kpi-ongoing'],
                ['For Payment', $kpi['for_payment'], 'executive-kpi-for-payment'],
                ['Check Release', $kpi['for_check_release'], 'executive-kpi-for-check-release'],
                ['Liquidation', $kpi['for_liquidation'], 'executive-kpi-for-liquidation'],
                ['Partial Liquidation', $kpi['partially_liquidated'], 'executive-kpi-partial-liquidation'],
            ] as [$label, $value, $testId])
                <div class="px-4 py-4" data-testid="{{ $testId }}">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</div>
                    <div class="mt-1 text-xl font-extrabold text-slate-900">{{ number_format($value) }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-[1.05fr_.95fr]">
        <section class="tupad-card p-5">
            <div class="mb-5">
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Program Position</div>
                <h2 class="mt-1 text-sm font-bold text-slate-900">Projects by Status</h2>
                <p class="mt-1 text-xs text-slate-500">Current position from the consolidated project status engine.</p>
            </div>
            <div class="space-y-3">
                @foreach ($dashboard['projects_by_status'] as $row)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-bold text-slate-900">{{ number_format($row['project_count']) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, ($row['project_count'] / $maxStatus) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="tupad-card p-5">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Fund Position</div>
                    <h2 class="mt-1 text-sm font-bold text-slate-900">Allocation and utilization</h2>
                    <p class="mt-1 text-xs text-slate-500">High-level financial position only. Detailed DA/ACP values are available below.</p>
                </div>
                <span class="text-right text-[10px] text-slate-400">Read-only</span>
            </div>

            @if ($dashboard['financial_position'])
                <div class="space-y-4">
                    @foreach ([
                        ['Allocation', $dashboard['financial_position']['allocation_cents'], 'executive-kpi-allocation'],
                        ['Obligated', $dashboard['financial_position']['obligated_cents'], 'executive-kpi-obligated'],
                        ['Disbursed', $dashboard['financial_position']['disbursed_cents'], 'executive-kpi-disbursed'],
                        ['Balance', $dashboard['financial_position']['balance_cents'], 'executive-kpi-balance'],
                    ] as [$label, $value, $testId])
                        <div data-testid="{{ $testId }}">
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="font-semibold text-slate-700">{{ $label }}</span>
                                <span class="font-bold text-slate-900">{{ $money((int) $value) }}</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, (max(0, (int) $value) / $financialMax) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-3" data-testid="executive-kpi-project-cost">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Total Project Cost</div>
                        <div class="mt-1 text-sm font-extrabold text-slate-900">{{ $money($kpi['project_cost_cents']) }}</div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Financial Basis</div>
                        <div class="mt-1 text-[11px] leading-5 text-slate-600">Allocation less disbursement; ACP liquidation remains separately tracked.</div>
                    </div>
                </div>

                <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50/70">
                    <summary class="cursor-pointer list-none px-4 py-3 text-xs font-bold text-slate-700 marker:content-none [&::-webkit-details-marker]:hidden">
                        View DA / ACP financial details
                    </summary>
                    <div class="grid gap-3 border-t border-slate-200 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ([
                            ['DA Obligated', $dashboard['financial_position']['direct_admin_obligated_cents']],
                            ['DA Disbursed', $dashboard['financial_position']['direct_admin_disbursed_cents']],
                            ['ACP Payment Recorded', $dashboard['financial_position']['acp_payment_cents']],
                            ['ACP Check Released', $dashboard['financial_position']['acp_check_released_cents']],
                            ['ACP Liquidated', $dashboard['financial_position']['acp_liquidated_cents']],
                            ['ACP Liquidation Progress', $kpi['acp_liquidation_percent'] === null ? 'Not available' : $percent($kpi['acp_liquidation_percent'])],
                        ] as [$label, $value])
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</div>
                                <div class="mt-1 text-sm font-extrabold text-slate-900">{{ is_int($value) ? $money($value) : $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @else
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5 text-sm leading-6 text-slate-600">
                    {{ $dashboard['financial_note'] }}
                </div>
            @endif
        </section>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="tupad-card p-5">
            <div class="mb-5">
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Delivery Trend</div>
                <h2 class="mt-1 text-sm font-bold text-slate-900">Physical Accomplishment by {{ $dashboard['physical_trend_dimension']->label() }}</h2>
                <p class="mt-1 text-xs text-slate-500">Project counts for the selected reporting period.</p>
            </div>
            <div class="space-y-3">
                @forelse ($dashboard['physical_trend'] as $row)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-bold text-slate-900">{{ number_format($row['project_count']) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, ($row['project_count'] / $maxTrend) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No projects match the selected period.</p>
                @endforelse
            </div>
        </section>

        <section class="tupad-card p-5" data-testid="executive-geography">
            <div class="mb-5">
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Geographic Delivery</div>
                <h2 class="mt-1 text-sm font-bold text-slate-900">Beneficiaries by Province</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $dashboard['geography_note'] }}</p>
            </div>
            <div class="space-y-3">
                @forelse ($dashboard['beneficiaries_by_province'] as $row)
                    <div>
                        <div class="mb-1.5 flex items-start justify-between gap-3 text-xs">
                            <div>
                                <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                                @if (! $row['has_complete_exact_allocation'])
                                    <span class="ml-1 text-amber-700">Incomplete exact allocation</span>
                                @endif
                            </div>
                            <span class="font-bold text-slate-900">{{ number_format($row['beneficiaries_total']) }}</span>
                        </div>
                        <div class="grid gap-1">
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, ($row['beneficiaries_total'] / $maxProvince) * 100) }}%"></div>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-400" style="width: {{ min(100, (($row['beneficiaries_female'] ?? 0) / $maxProvince) * 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No exact geographic beneficiary allocations match the selected filters.</p>
                @endforelse
            </div>
            <div class="mt-4 flex items-center gap-4 border-t border-slate-100 pt-3 text-[10px] text-slate-500">
                <span class="font-semibold text-slate-700">Total</span>
                <span>Female shown as the thinner secondary bar</span>
            </div>
        </section>
    </div>

    <section class="tupad-card mt-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Project Mix</div>
            <h2 class="mt-1 text-sm font-bold text-slate-900">Term and implementation mode</h2>
        </div>
        <div class="grid divide-y divide-slate-100 md:grid-cols-2 md:divide-x md:divide-y-0">
            <div class="p-5">
                <div class="mb-3 text-xs font-bold text-slate-600">Project Term</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($dashboard['projects_by_term'] as $row)
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $row['label'] }}</div>
                            <div class="mt-1 text-xl font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</div>
                            <div class="mt-1 text-[10px] text-slate-500">{{ number_format($row['beneficiaries_total']) }} beneficiaries</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="p-5">
                <div class="mb-3 text-xs font-bold text-slate-600">Implementation Mode</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($dashboard['projects_by_implementation_mode'] as $row)
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $row['label'] }}</div>
                            <div class="mt-1 text-xl font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</div>
                            <div class="mt-1 text-[10px] text-slate-500">{{ number_format($row['beneficiaries_total']) }} beneficiaries</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <details data-executive-analytics class="tupad-card mt-5 overflow-hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Detailed Program Analytics</div>
                <h2 class="mt-1 text-sm font-bold text-slate-900">Sectors, intervention focus, and labor market referrals</h2>
                <p class="mt-1 text-xs text-slate-500">Collapsed by default to keep the executive view concise.</p>
            </div>
            <span class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-600">Expand details</span>
        </summary>

        <div class="space-y-5 border-t border-slate-200 bg-slate-50/40 p-5">
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ([['Priority / Vulnerable Sectors', $dashboard['sector_priority'], $maxPriority], ['Occupational / Livelihood Sectors', $dashboard['sector_occupational'], $maxOccupational]] as [$title, $rows, $maximum])
                    <section class="rounded-xl border border-slate-200 bg-white p-5">
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-slate-900">{{ $title }}</h3>
                            <p class="mt-1 text-[11px] leading-5 text-amber-700">{{ $dashboard['sector_note'] }}</p>
                        </div>
                        <div class="space-y-3">
                            @foreach ($rows as $row)
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                        <span class="font-semibold text-slate-700">{{ $row['sector_label'] }}</span>
                                        <span class="font-bold text-slate-900">{{ number_format($row['beneficiaries_total']) }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, ($row['beneficiaries_total'] / $maximum) * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="mb-4">
                    <h3 class="text-sm font-bold text-slate-900">Intervention Focus</h3>
                    <p class="mt-1 text-xs text-slate-500">Primary intervention classification per project.</p>
                </div>
                <div class="space-y-3">
                    @foreach ($dashboard['intervention_focus'] as $row)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="font-semibold text-slate-700">{{ $row['intervention_focus_label'] }}</span>
                                <span class="font-bold text-slate-900">{{ number_format($row['project_count']) }} projects</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, ($row['project_count'] / $maxIntervention) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5" data-testid="executive-labor-market">
                <div class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Active Labor Market Referrals</h3>
                        <p class="mt-1 max-w-4xl text-xs leading-5 text-slate-500">{{ $dashboard['labor_market_note'] }}</p>
                    </div>
                    <span class="text-[11px] font-semibold text-blue-800">Reporting month basis</span>
                </div>

                <div class="mb-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Interested / Referred</div>
                        <div class="mt-1 text-xl font-extrabold text-slate-900">{{ number_format($dashboard['labor_market_overall']['interested_referred_total']) }}</div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Provided Intervention</div>
                        <div class="mt-1 text-xl font-extrabold text-slate-900">{{ number_format($dashboard['labor_market_overall']['provided_intervention_total']) }}</div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Amount Released</div>
                        <div class="mt-1 text-xl font-extrabold text-slate-900">{{ $money((int) $dashboard['labor_market_overall']['amount_released_cents']) }}</div>
                    </div>
                </div>

                <div class="space-y-4">
                    @foreach ($dashboard['labor_market_programs'] as $row)
                        <div>
                            <div class="mb-1.5 flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between">
                                <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                                <span class="text-slate-500">
                                    {{ number_format($row['interested_referred_total']) }} referred ·
                                    {{ number_format($row['provided_intervention_total']) }} provided ·
                                    {{ $money((int) $row['amount_released_cents']) }} released
                                </span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#0f3b72]" style="width: {{ min(100, ($row['interested_referred_total'] / $maxLabor) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-[11px] leading-5 text-slate-500">
                <strong class="text-slate-700">Data notes:</strong>
                Project-period indicators use <code>projects.date_received</code>. Labor-market indicators use <code>project_labor_market_referrals.reporting_month</code>.
                Sector classifications may overlap. Fine geographic beneficiary totals use exact pivot allocations and never receive fabricated project financial allocations.
            </div>
        </div>
    </details>
@endsection
