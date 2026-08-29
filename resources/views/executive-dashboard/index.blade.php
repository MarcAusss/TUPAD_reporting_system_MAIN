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
            ? max(1, (int) $dashboard['financial_position']['allocation_cents'], (int) $dashboard['financial_position']['obligated_cents'], (int) $dashboard['financial_position']['disbursed_cents'], (int) $dashboard['financial_position']['balance_cents'])
            : 1;
    @endphp

    <x-page-header eyebrow="Executive Reporting" title="Executive Dashboard"
        description="Read-only program overview using the Phase 8 reporting data layer and the same validated filters used across executive indicators and visualizations.">
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

    <section class="tupad-card mb-5 overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Dashboard Filters</h2>
                    <p class="mt-1 text-xs text-slate-500">Quarter and month require a fiscal year. Month and quarter cannot be used together.</p>
                </div>
                <div class="text-[11px] text-slate-500">
                    Generated {{ $dashboard['generated_at']->format('M d, Y g:i A') }} · Asia/Manila
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('executive-dashboard.index') }}" data-executive-filters class="p-5">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-xs font-semibold text-slate-700">
                    Fiscal Year
                    <select name="fiscal_year" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All fiscal years</option>
                        @foreach ($options['fiscal_years'] as $year)
                            <option value="{{ $year }}" @selected((string) request('fiscal_year') === (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Quarter
                    <select name="quarter" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All quarters</option>
                        @foreach (range(1, 4) as $quarter)
                            <option value="{{ $quarter }}" @selected((string) request('quarter') === (string) $quarter)>Q{{ $quarter }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Month
                    <select name="month" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All months</option>
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((string) request('month') === (string) $month)>
                                {{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Term
                    <select name="term" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All terms</option>
                        @foreach ($options['terms'] as $term)
                            <option value="{{ $term->value }}" @selected(request('term') === $term->value)>{{ $term->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Implementation Mode
                    <select name="implementation_mode" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All implementation modes</option>
                        @foreach ($options['implementation_modes'] as $mode)
                            <option value="{{ $mode->value }}" @selected(request('implementation_mode') === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    ADL
                    <select name="adl_id" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All ADLs</option>
                        @foreach ($options['adls'] as $adl)
                            <option value="{{ $adl->id }}" @selected((string) request('adl_id') === (string) $adl->id)>{{ $adl->adl_number }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Province
                    <select name="province_id" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All provinces</option>
                        @foreach ($options['provinces'] as $province)
                            <option value="{{ $province->id }}" @selected((string) request('province_id') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    District
                    <select name="district" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All districts</option>
                        @foreach ($options['districts'] as $district)
                            <option value="{{ $district->district }}" data-province-id="{{ $district->province_id }}" @selected(request('district') === $district->district)>
                                {{ $district->district }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Municipality / City
                    <select name="municipality_id" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All municipalities / cities</option>
                        @foreach ($options['municipalities'] as $municipality)
                            <option value="{{ $municipality->id }}" data-province-id="{{ $municipality->province_id }}" data-district="{{ $municipality->district }}" @selected((string) request('municipality_id') === (string) $municipality->id)>
                                {{ $municipality->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Barangay
                    <select name="barangay_id" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All barangays</option>
                        @foreach ($options['barangays'] as $barangay)
                            <option value="{{ $barangay->id }}"
                                data-municipality-id="{{ $barangay->municipality_id }}"
                                data-province-id="{{ $barangay->municipality?->province_id }}"
                                data-district="{{ $barangay->municipality?->district }}"
                                @selected((string) request('barangay_id') === (string) $barangay->id)>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Project Status
                    <select name="status" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($options['statuses'] as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Sponsor
                    <select name="sponsor" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All sponsors</option>
                        @foreach ($options['sponsors'] as $sponsor)
                            <option value="{{ $sponsor }}" @selected(request('sponsor') === $sponsor)>{{ $sponsor }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Partner / NGA
                    <select name="partner" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All partners / NGAs</option>
                        @foreach ($options['partners'] as $partner)
                            <option value="{{ $partner }}" @selected(request('partner') === $partner)>{{ $partner }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Project Code
                    <input type="text" name="project_code" value="{{ request('project_code') }}" maxlength="255"
                        placeholder="Exact approved project code" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Sector
                    <select name="sector" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All sectors</option>
                        @foreach ($options['sectors'] as $sector)
                            <option value="{{ $sector->value }}" @selected(request('sector') === $sector->value)>{{ $sector->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Intervention Focus
                    <select name="intervention_focus" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All intervention focuses</option>
                        @foreach ($options['intervention_focuses'] as $focus)
                            <option value="{{ $focus->value }}" @selected(request('intervention_focus') === $focus->value)>{{ $focus->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="text-xs font-semibold text-slate-700">
                    Labor Market Program
                    <select name="labor_market_program" class="mt-1 h-10 w-full rounded-lg border px-3 text-sm">
                        <option value="">All labor market programs</option>
                        @foreach ($options['labor_market_programs'] as $program)
                            <option value="{{ $program->value }}" @selected(request('labor_market_program') === $program->value)>{{ $program->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white">
                    Apply Filters
                </button>
                <a href="{{ route('executive-dashboard.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">
                    Clear Filters
                </a>
            </div>
        </form>
    </section>

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

    <section aria-labelledby="executive-kpis-heading">
        <div class="mb-3 flex items-center justify-between">
            <h2 id="executive-kpis-heading" class="text-sm font-bold text-slate-900">Key Indicators</h2>
            <span class="text-[11px] text-slate-500">Project period basis: projects.date_received</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            @foreach ([
                ['Total Projects', number_format($kpi['total_projects']), 'executive-kpi-total-projects'],
                ['Completed Projects', number_format($kpi['completed_projects']), 'executive-kpi-completed-projects'],
                ['Ongoing Implementation', number_format($kpi['ongoing_implementation']), 'executive-kpi-ongoing'],
                ['For Payment', number_format($kpi['for_payment']), 'executive-kpi-for-payment'],
                ['For Check Release', number_format($kpi['for_check_release']), 'executive-kpi-for-check-release'],
                ['For Liquidation', number_format($kpi['for_liquidation']), 'executive-kpi-for-liquidation'],
                ['Partially Liquidated', number_format($kpi['partially_liquidated']), 'executive-kpi-partial-liquidation'],
                ['Total Beneficiaries', number_format($kpi['beneficiaries_total']), 'executive-kpi-beneficiaries'],
                ['Female Beneficiaries', number_format($kpi['beneficiaries_female']), 'executive-kpi-female'],
                ['Total Project Cost', $money($kpi['project_cost_cents']), 'executive-kpi-project-cost'],
                ['TUPAD Allocation', $money($kpi['allocation_cents']), 'executive-kpi-allocation'],
                ['Total Obligated', $money($kpi['obligated_cents']), 'executive-kpi-obligated'],
                ['Total Disbursed', $money($kpi['disbursed_cents']), 'executive-kpi-disbursed'],
                ['Remaining Balance', $money($kpi['balance_cents']), 'executive-kpi-balance'],
                ['Physical Accomplishment', $percent($kpi['physical_accomplishment_percent']), 'executive-kpi-physical-percent'],
                ['Financial Accomplishment', $kpi['financial_accomplishment_percent'] === null ? 'Not available' : $percent($kpi['financial_accomplishment_percent']), 'executive-kpi-financial-percent'],
            ] as [$label, $value, $testId])
                <article class="tupad-card p-5" data-testid="{{ $testId }}">
                    <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">{{ $value }}</div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="tupad-card p-5">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Projects by Status</h2>
                <p class="mt-1 text-xs text-slate-500">Current project status from the consolidated status engine.</p>
            </div>
            <div class="space-y-3">
                @foreach ($dashboard['projects_by_status'] as $row)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-bold text-slate-900">{{ number_format($row['project_count']) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, ($row['project_count'] / $maxStatus) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="tupad-card p-5">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Physical Accomplishment by {{ $dashboard['physical_trend_dimension']->label() }}</h2>
                <p class="mt-1 text-xs text-slate-500">Project counts use projects.date_received for the selected reporting period.</p>
            </div>
            <div class="space-y-3">
                @forelse ($dashboard['physical_trend'] as $row)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                            <span class="font-bold text-slate-900">{{ number_format($row['project_count']) }} projects</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, ($row['project_count'] / $maxTrend) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No projects match the selected period.</p>
                @endforelse
            </div>
        </section>

        <section class="tupad-card p-5">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Short-Term versus Long-Term Projects</h2>
                <p class="mt-1 text-xs text-slate-500">Term classification is sourced from the official project record.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($dashboard['projects_by_term'] as $row)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-bold text-slate-600">{{ $row['label'] }}</div>
                        <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ number_format($row['beneficiaries_total']) }} beneficiaries</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="tupad-card p-5">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Projects by Implementation Mode</h2>
                <p class="mt-1 text-xs text-slate-500">Direct Administration and Through ACP remain separate workflow and financial paths.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($dashboard['projects_by_implementation_mode'] as $row)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-bold text-slate-600">{{ $row['label'] }}</div>
                        <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ number_format($row['beneficiaries_total']) }} beneficiaries</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="tupad-card p-5">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Allocation, Obligation, Disbursement and Balance</h2>
                <p class="mt-1 text-xs text-slate-500">Balance basis: allocation less total disbursed. ACP liquidation is tracked separately.</p>
            </div>
            @if ($dashboard['financial_position'])
                <div class="space-y-4">
                    @foreach ([
                        ['Allocation', $dashboard['financial_position']['allocation_cents']],
                        ['Obligated', $dashboard['financial_position']['obligated_cents']],
                        ['Disbursed', $dashboard['financial_position']['disbursed_cents']],
                        ['Balance', $dashboard['financial_position']['balance_cents']],
                    ] as [$label, $value])
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="font-semibold text-slate-700">{{ $label }}</span>
                                <span class="font-bold text-slate-900">{{ $money((int) $value) }}</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, (max(0, (int) $value) / $financialMax) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ([
                        ['DA Obligated', $dashboard['financial_position']['direct_admin_obligated_cents']],
                        ['DA Disbursed', $dashboard['financial_position']['direct_admin_disbursed_cents']],
                        ['ACP Payment Recorded', $dashboard['financial_position']['acp_payment_cents']],
                        ['ACP Check Released', $dashboard['financial_position']['acp_check_released_cents']],
                        ['ACP Liquidated', $dashboard['financial_position']['acp_liquidated_cents']],
                        ['ACP Liquidation Progress', $kpi['acp_liquidation_percent'] === null ? 'Not available' : $percent($kpi['acp_liquidation_percent'])],
                    ] as [$label, $value])
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</div>
                            <div class="mt-1 text-sm font-extrabold text-slate-900">{{ is_int($value) ? $money($value) : $value }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[11px] leading-5 text-slate-500">{{ $dashboard['financial_basis_note'] }}</p>
            @else
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5 text-sm leading-6 text-slate-600">
                    {{ $dashboard['financial_note'] }}
                </div>
            @endif
        </section>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        <section class="tupad-card p-5" data-testid="executive-geography">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Beneficiaries by Province</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $dashboard['geography_note'] }}</p>
            </div>
            <div class="space-y-3">
                @forelse ($dashboard['beneficiaries_by_province'] as $row)
                    <div>
                        <div class="mb-1.5 flex items-start justify-between gap-3 text-xs">
                            <div>
                                <span class="font-semibold text-slate-700">{{ $row['label'] }}</span>
                                @if (! $row['has_complete_exact_allocation'])
                                    <span class="ml-1 text-amber-700">Exact allocation incomplete</span>
                                @endif
                            </div>
                            <span class="font-bold text-slate-900">{{ number_format($row['beneficiaries_total']) }}</span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, ($row['beneficiaries_total'] / $maxProvince) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No exact geographic beneficiary allocations match the selected filters.</p>
                @endforelse
            </div>
        </section>

        <section class="tupad-card p-5">
            <div class="mb-5">
                <h2 class="text-sm font-bold text-slate-900">Total versus Female Beneficiaries</h2>
                <p class="mt-1 text-xs text-slate-500">Aggregate project beneficiary totals for the filtered project cohort.</p>
            </div>
            <div class="space-y-5">
                <div>
                    <div class="flex items-end justify-between gap-4">
                        <span class="text-sm font-semibold text-slate-700">Total Beneficiaries</span>
                        <span class="text-3xl font-extrabold text-slate-900">{{ number_format($kpi['beneficiaries_total']) }}</span>
                    </div>
                    <div class="mt-2 h-4 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-[#063b86]" style="width:100%"></div></div>
                </div>
                <div>
                    <div class="flex items-end justify-between gap-4">
                        <span class="text-sm font-semibold text-slate-700">Female Beneficiaries</span>
                        <span class="text-3xl font-extrabold text-slate-900">{{ number_format($kpi['beneficiaries_female']) }}</span>
                    </div>
                    <div class="mt-2 h-4 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full bg-blue-600" style="width: {{ $kpi['beneficiaries_total'] > 0 ? min(100, ($kpi['beneficiaries_female'] / $kpi['beneficiaries_total']) * 100) : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        @foreach ([['Priority / Vulnerable Sectors', $dashboard['sector_priority'], $maxPriority], ['Occupational / Livelihood Sectors', $dashboard['sector_occupational'], $maxOccupational]] as [$title, $rows, $maximum])
            <section class="tupad-card p-5">
                <div class="mb-5">
                    <h2 class="text-sm font-bold text-slate-900">{{ $title }}</h2>
                    <p class="mt-1 text-xs text-amber-700">{{ $dashboard['sector_note'] }}</p>
                </div>
                <div class="space-y-3">
                    @foreach ($rows as $row)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3 text-xs">
                                <span class="font-semibold text-slate-700">{{ $row['sector_label'] }}</span>
                                <span class="font-bold text-slate-900">{{ number_format($row['beneficiaries_total']) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, ($row['beneficiaries_total'] / $maximum) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <section class="tupad-card mt-5 p-5">
        <div class="mb-5">
            <h2 class="text-sm font-bold text-slate-900">Intervention Focus</h2>
            <p class="mt-1 text-xs text-slate-500">Primary intervention classification per project.</p>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($dashboard['intervention_focus'] as $row)
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-bold leading-5 text-slate-700">{{ $row['intervention_focus_label'] }}</div>
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <span class="text-2xl font-extrabold text-slate-900">{{ number_format($row['project_count']) }}</span>
                        <span class="text-[11px] text-slate-500">projects</span>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, ($row['project_count'] / $maxIntervention) * 100) }}%"></div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="tupad-card mt-5 p-5" data-testid="executive-labor-market">
        <div class="mb-5 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Active Labor Market Referrals</h2>
                <p class="mt-1 max-w-4xl text-xs leading-5 text-slate-500">{{ $dashboard['labor_market_note'] }}</p>
            </div>
            <div class="text-[11px] font-semibold text-blue-800">Reporting month basis</div>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Interested / Referred</div>
                <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($dashboard['labor_market_overall']['interested_referred_total']) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Provided Intervention</div>
                <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($dashboard['labor_market_overall']['provided_intervention_total']) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Amount Released</div>
                <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $money((int) $dashboard['labor_market_overall']['amount_released_cents']) }}</div>
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
                        <div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, ($row['interested_referred_total'] / $maxLabor) * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white px-5 py-4 text-[11px] leading-5 text-slate-500">
        <strong class="text-slate-700">Data notes:</strong>
        Project-period indicators use <code>projects.date_received</code>. Labor-market indicators use <code>project_labor_market_referrals.reporting_month</code>.
        Sector classifications may overlap. Fine geographic beneficiary totals use exact pivot allocations and never receive fabricated project financial allocations.
    </div>
@endsection
