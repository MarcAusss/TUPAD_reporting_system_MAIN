@extends('layouts.app')

@section('title', 'Physical & Financial Accomplishment')

@section('content')
    @php
        $money = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
        $metricCards = [
            ['label' => 'Projects', 'value' => number_format((int) ($overallRow['project_count'] ?? 0)), 'hint' => 'Projects in the selected reporting scope'],
            ['label' => 'Completed', 'value' => number_format((int) ($overallRow['completed_project_count'] ?? 0)), 'hint' => 'Projects currently marked Completed'],
            ['label' => 'Beneficiaries', 'value' => number_format((int) ($overallRow['beneficiaries_total'] ?? 0)), 'hint' => 'Aggregate encoded beneficiaries'],
            ['label' => 'Female', 'value' => number_format((int) ($overallRow['beneficiaries_female'] ?? 0)), 'hint' => 'Female beneficiaries in the same cohort'],
            ['label' => 'Project Cost', 'value' => $money($overallRow['project_cost_cents'] ?? 0), 'hint' => 'Approved/encoded project cost total'],
            ['label' => 'Obligated', 'value' => $money($overallRow['obligated_cents'] ?? 0), 'hint' => 'DA obligations plus ACP payment-stage records'],
            ['label' => 'Disbursed', 'value' => $money($overallRow['disbursed_cents'] ?? 0), 'hint' => 'DA disbursements plus ACP released checks'],
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header eyebrow="Official Reporting" title="Physical & Financial Accomplishment"
            description="Review overall, quarterly, monthly, short-term, and long-term accomplishment from the existing audited Phase 8/9 reporting data." />

        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('reports.index', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Detailed Generator
            </a>
            <a href="{{ route('reports.print', $exportQuery) }}" target="_blank"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Print
            </a>
            <a href="{{ route('reports.export.pdf', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                PDF
            </a>
        </div>
    </div>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Physical and financial report views">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($views as $key => $tab)
                <a href="{{ route('reports.workspace.physical-financial', array_merge($commonQuery, ['view' => $key])) }}"
                    class="{{ $viewKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.15em] opacity-70">View {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="mt-1 text-sm font-bold leading-5">{{ $tab['label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <div class="font-semibold">The report criteria could not be applied.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">{{ $viewConfig['label'] }}</div>
                    <p class="mt-1 text-sm text-slate-500">{{ $viewConfig['description'] }}</p>
                </div>
                <div class="text-xs font-semibold text-slate-500">Period basis: Project Date Received</div>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.workspace.physical-financial') }}" class="p-5">
            <input type="hidden" name="view" value="{{ $viewKey }}">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100"
                        value="{{ $filters['fiscal_year'] ?? '' }}" placeholder="All years"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                </div>

                @if ($viewKey === 'quarter')
                    <div>
                        <label for="quarter" class="mb-2 block text-xs font-semibold text-slate-700">Quarter</label>
                        <select id="quarter" name="quarter" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All quarters</option>
                            @foreach ([1, 2, 3, 4] as $quarter)
                                <option value="{{ $quarter }}" @selected((string) ($filters['quarter'] ?? '') === (string) $quarter)>Quarter {{ $quarter }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($viewKey === 'month')
                    <div>
                        <label for="month" class="mb-2 block text-xs font-semibold text-slate-700">Month</label>
                        <select id="month" name="month" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All months</option>
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((string) ($filters['month'] ?? '') === (string) $month)>
                                    {{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="hidden xl:block"></div>
                @endif

                <div>
                    <label for="implementation_mode" class="mb-2 block text-xs font-semibold text-slate-700">Implementation Mode</label>
                    <select id="implementation_mode" name="implementation_mode" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All modes</option>
                        @foreach ($options['implementation_modes'] as $mode)
                            <option value="{{ $mode->value }}" @selected(($filters['implementation_mode'] ?? null) === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="mb-2 block text-xs font-semibold text-slate-700">Project Status</label>
                    <select id="status" name="status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($options['statuses'] as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="province_id" class="mb-2 block text-xs font-semibold text-slate-700">Province</label>
                    <select id="province_id" name="province_id"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100 disabled:text-slate-600"
                        @disabled($provinceLocked)>
                        @unless ($provinceLocked)
                            <option value="">All provinces</option>
                        @endunless
                        @foreach ($options['provinces'] as $province)
                            <option value="{{ $province->id }}" @selected((string) ($filters['province_id'] ?? '') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                    @if ($provinceLocked && !empty($filters['province_id']))
                        <input type="hidden" name="province_id" value="{{ $filters['province_id'] }}">
                    @endif
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">Apply</button>
                    <a href="{{ route('reports.workspace.physical-financial', ['view' => $viewKey]) }}"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
        @foreach ($metricCards as $card)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[10px] font-extrabold uppercase tracking-[0.13em] text-slate-500">{{ $card['label'] }}</div>
                <div class="mt-2 text-xl font-extrabold tracking-tight text-slate-900">{{ $card['value'] }}</div>
                <div class="mt-2 text-[11px] leading-4 text-slate-500">{{ $card['hint'] }}</div>
            </article>
        @endforeach
    </section>

    <section class="mb-5 grid gap-4 lg:grid-cols-3">
        @foreach ([
            ['label' => 'Completed Projects', 'value' => $ratios['completion'], 'hint' => 'Completed projects ÷ projects in scope'],
            ['label' => 'Female Share', 'value' => $ratios['female_share'], 'hint' => 'Female beneficiaries ÷ total beneficiaries'],
            ['label' => 'Disbursement vs Obligation', 'value' => $ratios['disbursement_vs_obligation'], 'hint' => 'Recorded disbursement ÷ recorded obligation'],
        ] as $indicator)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold text-slate-800">{{ $indicator['label'] }}</div>
                        <div class="mt-1 text-[11px] text-slate-500">{{ $indicator['hint'] }}</div>
                    </div>
                    <div class="text-2xl font-extrabold text-[#063b86]">{{ number_format($indicator['value'], 1) }}%</div>
                </div>
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, max(0, $indicator['value'])) }}%"></div>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">{{ $viewConfig['label'] }} Data</h2>
                <p class="mt-1 text-xs text-slate-500">The same validated cohort is used by screen, Print, PDF, Excel, and CSV outputs.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.export.excel', $exportQuery) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('reports.export.csv', $exportQuery) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
            </div>
        </div>

        @if ($report['display_rows']->isEmpty())
            <div class="px-5 py-12 text-center">
                <div class="text-sm font-semibold text-slate-700">No accomplishment data matched the selected criteria.</div>
                <div class="mt-1 text-xs text-slate-500">Adjust the reporting period or filters and try again.</div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <th class="whitespace-nowrap px-4 py-3 font-bold uppercase tracking-wide text-slate-600">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($report['display_rows'] as $row)
                            <tr class="hover:bg-slate-50/70">
                                @foreach ($report['columns'] as $column)
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $row[$column['key']] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Workflow Status Breakdown</h2>
                <p class="mt-1 text-xs text-slate-500">Status distribution within the same selected cohort.</p>
            </div>
            @if ($statusReport['rows']->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-slate-500">No project status data available.</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($statusReport['rows'] as $row)
                        <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-4 px-5 py-3 text-xs">
                            <div class="font-semibold text-slate-700">{{ $row['label'] }}</div>
                            <div class="text-right text-slate-500">{{ number_format((int) $row['project_count']) }} project(s)</div>
                            <div class="min-w-24 text-right font-bold text-slate-800">{{ number_format((int) $row['beneficiaries_total']) }} ben.</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Financial Composition</h2>
                <p class="mt-1 text-xs text-slate-500">Encoded project cost components for the selected cohort.</p>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2">
                @foreach ([
                    'Wages' => $overallRow['wages_cents'] ?? 0,
                    'PPE' => $overallRow['ppe_cents'] ?? 0,
                    'Insurance' => $overallRow['insurance_cents'] ?? 0,
                    'Total Project Cost' => $overallRow['project_cost_cents'] ?? 0,
                ] as $label => $value)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                        <div class="mt-1 text-lg font-extrabold text-slate-900">{{ $money($value) }}</div>
                    </div>
                @endforeach
            </div>
            <div class="border-t border-slate-100 px-5 py-4 text-xs leading-5 text-slate-500">
                These screen indicators do not redefine the official government report formula. The official print/PDF layout will follow the supplied reference during the dedicated print-layout phase.
            </div>
        </article>
    </section>
@endsection
