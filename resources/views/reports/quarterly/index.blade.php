@extends('layouts.app')

@section('title', 'Quarterly Reports')

@section('content')
    @php
        $money = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
        $quarterLabel = 'Q' . (int) $filters['quarter'] . ' ' . (int) $filters['fiscal_year'];
        $commonQuery = array_filter([
            'fiscal_year' => $filters['fiscal_year'] ?? null,
            'quarter' => $filters['quarter'] ?? null,
            'status' => $filters['status'] ?? null,
            'implementation_mode' => $filters['implementation_mode'] ?? null,
            'province_id' => $filters['province_id'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
        $officialQuery = array_filter(array_merge($commonQuery, [
            'form' => $viewKey,
            'labor_market_program' => $viewKey === 'labor-market' ? ($filters['labor_market_program'] ?? null) : null,
        ]), static fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header eyebrow="Official Reporting" title="Quarterly Reports"
            description="Review CQPR monitoring and quarterly TUPAD beneficiary referrals to active labor market programs." />
        <div class="flex flex-wrap items-start gap-2">
            <a href="{{ route('reports.periodic.print', $officialQuery) }}" target="_blank" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Official Print</a>
            <a href="{{ route('reports.periodic.export.pdf', $officialQuery) }}" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white">Official PDF</a>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-right shadow-sm">
                <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Reporting Period</div>
                <div class="mt-1 text-sm font-bold text-slate-900">{{ $quarterLabel }}</div>
            </div>
        </div>
    </div>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Quarterly report views">
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($views as $key => $tab)
                <a href="{{ route('reports.workspace.quarterly', array_merge($commonQuery, ['view' => $key])) }}"
                    class="{{ $viewKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] opacity-70">Quarterly Report {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="mt-1 text-sm font-bold">{{ $tab['label'] }}</div>
                    <div class="mt-1 text-xs leading-5 opacity-75">{{ $tab['description'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">{{ $viewConfig['label'] }}</div>
                <p class="mt-1 text-sm text-slate-500">{{ $viewConfig['description'] }}</p>
            </div>
            <div class="rounded-md bg-slate-50 px-3 py-2 text-[11px] font-semibold text-slate-500">Period basis: {{ $periodBasis }}</div>
        </div>

        <form method="GET" action="{{ route('reports.workspace.quarterly') }}" class="p-5">
            <input type="hidden" name="view" value="{{ $viewKey }}">
            <div class="grid gap-4 md:grid-cols-2 {{ $viewKey === 'labor-market' ? 'xl:grid-cols-7' : 'xl:grid-cols-6' }}">
                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100" required value="{{ $filters['fiscal_year'] }}" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                </div>
                <div>
                    <label for="quarter" class="mb-2 block text-xs font-semibold text-slate-700">Quarter</label>
                    <select id="quarter" name="quarter" required class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        @foreach ([1,2,3,4] as $quarter)
                            <option value="{{ $quarter }}" @selected((int) $filters['quarter'] === $quarter)>Quarter {{ $quarter }}</option>
                        @endforeach
                    </select>
                </div>
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
                    <select id="province_id" name="province_id" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100 disabled:text-slate-600" @disabled($provinceLocked)>
                        @unless ($provinceLocked)<option value="">All provinces</option>@endunless
                        @foreach ($options['provinces'] as $province)
                            <option value="{{ $province->id }}" @selected((string) ($filters['province_id'] ?? '') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                    @if ($provinceLocked)<input type="hidden" name="province_id" value="{{ $filters['province_id'] ?? '' }}">@endif
                </div>
                @if ($viewKey === 'labor-market')
                    <div>
                        <label for="labor_market_program" class="mb-2 block text-xs font-semibold text-slate-700">Labor Market Program</label>
                        <select id="labor_market_program" name="labor_market_program" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All programs</option>
                            @foreach ($options['labor_market_programs'] as $program)
                                <option value="{{ $program->value }}" @selected(($filters['labor_market_program'] ?? null) === $program->value)>{{ $program->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="flex items-end gap-2">
                    <button class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white">Apply</button>
                    <a href="{{ route('reports.workspace.quarterly', ['view' => $viewKey]) }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700">Reset</a>
                </div>
            </div>
        </form>
    </section>

    @if ($viewKey === 'cqpr')
        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Projects', $summary['project_count'], false],
                ['Beneficiaries', $summary['beneficiaries_total'], false],
                ['Obligated', $summary['obligated_cents'], true],
                ['Disbursed', $summary['disbursed_cents'], true],
            ] as [$label, $value, $isMoney])
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $isMoney ? $money($value) : number_format((int) $value) }}</div>
                </article>
            @endforeach
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Consolidated Quarterly Progress Report (CQPR) Project Cohort</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Only projects with a CQPR monitoring date inside {{ $quarterLabel }} are included. Official Print/PDF uses the Phase 14F government report layout.</p>
            </div>
            @if ($rows->isEmpty())
                <div class="px-5 py-12 text-center text-sm font-semibold text-slate-600">No CQPR records matched the selected quarter.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                            <tr><th class="px-4 py-3">CQPR Date</th><th class="px-4 py-3">Project Code</th><th class="min-w-64 px-4 py-3">Project</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Beneficiaries</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            @foreach ($rows as $row)
                                <tr><td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{{ $row['report_date']?->format('M d, Y') }}</td><td class="whitespace-nowrap px-4 py-3">{{ $row['project_code'] ?: 'Pending code' }}</td><td class="px-4 py-3 font-semibold text-slate-900">{{ $row['project_title'] }}</td><td class="px-4 py-3">{{ collect([$row['province'], $row['municipality']])->filter()->implode(' · ') }}</td><td class="whitespace-nowrap px-4 py-3">{{ $row['status']->label() }}</td><td class="whitespace-nowrap px-4 py-3">{{ number_format($row['beneficiaries_total']) }} <span class="text-slate-400">/ {{ number_format($row['beneficiaries_female']) }} female</span></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @else
        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Projects with Referrals', $laborOverall['project_count'] ?? 0, false],
                ['Beneficiaries Referred', $laborOverall['interested_referred_total'] ?? 0, false],
                ['Female Referred', $laborOverall['interested_referred_female'] ?? 0, false],
                ['Amount Released', $laborOverall['amount_released_cents'] ?? 0, true],
            ] as [$label, $value, $isMoney])
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ $isMoney ? $money($value) : number_format((int) $value) }}</div>
                </article>
            @endforeach
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Active Labor Market Referral Breakdown</h2>
                    <p class="mt-1 text-xs text-slate-500">Quarterly totals are read directly from encoded referral records. Counts are not inferred from project beneficiaries.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('reports.export.excel', $exportQuery) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">Excel</a>
                    <a href="{{ route('reports.export.csv', $exportQuery) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700">CSV</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                        <tr><th class="px-4 py-3">Program</th><th class="px-4 py-3 text-right">Records</th><th class="px-4 py-3 text-right">Projects</th><th class="px-4 py-3 text-right">Referred</th><th class="px-4 py-3 text-right">Female</th><th class="px-4 py-3 text-right">Provided Intervention</th><th class="px-4 py-3 text-right">Amount Released</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach ($laborRows as $row)
                            <tr><td class="px-4 py-3 font-semibold text-slate-900">{{ $row['label'] }}</td><td class="px-4 py-3 text-right">{{ number_format($row['referral_record_count']) }}</td><td class="px-4 py-3 text-right">{{ number_format($row['project_count']) }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format($row['interested_referred_total']) }}</td><td class="px-4 py-3 text-right">{{ number_format($row['interested_referred_female']) }}</td><td class="px-4 py-3 text-right">{{ number_format($row['provided_intervention_total']) }}</td><td class="px-4 py-3 text-right font-semibold">{{ $money($row['amount_released_cents']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-900">
        Official CQPR and quarterly Print/PDF outputs now use the Phase 14F government layout. Screen totals retain the source-specific reporting periods shown above.
    </div>
@endsection
