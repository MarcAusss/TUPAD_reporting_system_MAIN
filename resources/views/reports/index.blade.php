@extends('layouts.app')

@section('title', 'Report Generation')

@section('content')
    @php
        $exportQuery = array_merge([
            'report_type' => $report['type']->value,
            'group_by' => $report['dimension']->value,
        ], $query);
    @endphp

    <x-page-header eyebrow="Official Reporting" title="Report Generation"
        description="Generate consistent TUPAD reports for screen review, printing, PDF, Excel, and CSV." />

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

    <form method="GET" action="{{ route('reports.index') }}"
        class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Report Criteria</h2>
            <p class="mt-1 text-xs text-slate-500">
                The same validated criteria are used by every download format.
            </p>
        </div>

        <div class="p-5">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="xl:col-span-2">
                    <label for="report_type" class="mb-2 block text-xs font-semibold text-slate-700">
                        Report Type
                    </label>
                    <select id="report_type" name="report_type"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm" required>
                        @foreach ($options['report_types'] as $type)
                            <option value="{{ $type->value }}" @selected($report['type'] === $type)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="group_by" class="mb-2 block text-xs font-semibold text-slate-700">
                        Group By
                    </label>
                    <select id="group_by" name="group_by"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm" required>
                        @foreach ($options['dimensions'] as $dimension)
                            <option value="{{ $dimension->value }}" @selected($report['dimension'] === $dimension)>
                                {{ $dimension->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">
                        Fiscal Year
                    </label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100"
                        value="{{ request('fiscal_year') }}" placeholder="All years"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>

                <div>
                    <label for="quarter" class="mb-2 block text-xs font-semibold text-slate-700">
                        Quarter
                    </label>
                    <select id="quarter" name="quarter"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All quarters</option>
                        @foreach ([1, 2, 3, 4] as $quarter)
                            <option value="{{ $quarter }}" @selected((string) request('quarter') === (string) $quarter)>
                                Quarter {{ $quarter }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="month" class="mb-2 block text-xs font-semibold text-slate-700">
                        Month
                    </label>
                    <select id="month" name="month"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All months</option>
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((string) request('month') === (string) $month)>
                                {{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="date_from" class="mb-2 block text-xs font-semibold text-slate-700">
                        Date From
                    </label>
                    <input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>

                <div>
                    <label for="date_to" class="mb-2 block text-xs font-semibold text-slate-700">
                        Date To
                    </label>
                    <input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
            </div>

            <details class="mt-5 rounded-lg border border-slate-200 bg-slate-50/60" @if (collect($query)->except(['report_type', 'group_by', 'fiscal_year', 'quarter', 'month', 'date_from', 'date_to'])->filter()->isNotEmpty()) open @endif>
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">
                    Additional Project and Classification Filters
                </summary>

                <div class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="term" class="mb-2 block text-xs font-semibold text-slate-700">Term</label>
                        <select id="term" name="term"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All terms</option>
                            @foreach ($options['terms'] as $term)
                                <option value="{{ $term->value }}" @selected(request('term') === $term->value)>
                                    {{ $term->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="implementation_mode" class="mb-2 block text-xs font-semibold text-slate-700">Implementation Mode</label>
                        <select id="implementation_mode" name="implementation_mode"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All implementation modes</option>
                            @foreach ($options['implementation_modes'] as $mode)
                                <option value="{{ $mode->value }}" @selected(request('implementation_mode') === $mode->value)>
                                    {{ $mode->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="mb-2 block text-xs font-semibold text-slate-700">Status</label>
                        <select id="status" name="status"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All statuses</option>
                            @foreach ($options['statuses'] as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                    {{ $status->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="adl_id" class="mb-2 block text-xs font-semibold text-slate-700">ADL</label>
                        <select id="adl_id" name="adl_id"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All ADLs</option>
                            @foreach ($options['adls'] as $adl)
                                <option value="{{ $adl->id }}" @selected((string) request('adl_id') === (string) $adl->id)>
                                    {{ $adl->adl_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="project_code" class="mb-2 block text-xs font-semibold text-slate-700">
                            Project Code
                        </label>
                        <input id="project_code" name="project_code" value="{{ request('project_code') }}"
                            placeholder="Exact official code"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>

                    <div>
                        <label for="province_id" class="mb-2 block text-xs font-semibold text-slate-700">Province</label>
                        <select id="province_id" name="province_id"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All provinces</option>
                            @foreach ($options['provinces'] as $province)
                                <option value="{{ $province->id }}" @selected((string) request('province_id') === (string) $province->id)>
                                    {{ $province->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="district" class="mb-2 block text-xs font-semibold text-slate-700">District</label>
                        <select id="district" name="district"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All districts</option>
                            @foreach ($options['districts'] as $district)
                                <option value="{{ $district }}" @selected(request('district') === $district)>{{ $district }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="municipality_id" class="mb-2 block text-xs font-semibold text-slate-700">
                            Municipality
                        </label>
                        <select id="municipality_id" name="municipality_id"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All municipalities</option>
                            @foreach ($options['municipalities'] as $municipality)
                                <option value="{{ $municipality->id }}" @selected((string) request('municipality_id') === (string) $municipality->id)>
                                    {{ $municipality->name }}@if ($municipality->province) — {{ $municipality->province->name }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="barangay_id" class="mb-2 block text-xs font-semibold text-slate-700">Barangay</label>
                        <select id="barangay_id" name="barangay_id"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All barangays</option>
                            @foreach ($options['barangays'] as $barangay)
                                <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>
                                    {{ $barangay->name }}@if ($barangay->municipality) — {{ $barangay->municipality->name }}@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sponsor" class="mb-2 block text-xs font-semibold text-slate-700">Sponsor</label>
                        <select id="sponsor" name="sponsor"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All sponsors</option>
                            @foreach ($options['sponsors'] as $sponsor)
                                <option value="{{ $sponsor }}" @selected(request('sponsor') === $sponsor)>{{ $sponsor }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="partner" class="mb-2 block text-xs font-semibold text-slate-700">Partner / NGA</label>
                        <select id="partner" name="partner"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All partners</option>
                            @foreach ($options['partners'] as $partner)
                                <option value="{{ $partner }}" @selected(request('partner') === $partner)>{{ $partner }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="sector" class="mb-2 block text-xs font-semibold text-slate-700">Beneficiary Sector</label>
                        <select id="sector" name="sector"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All sectors</option>
                            @foreach ($options['sectors'] as $sector)
                                <option value="{{ $sector->value }}" @selected(request('sector') === $sector->value)>
                                    {{ $sector->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="intervention_focus" class="mb-2 block text-xs font-semibold text-slate-700">
                            Intervention Focus
                        </label>
                        <select id="intervention_focus" name="intervention_focus"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All intervention focuses</option>
                            @foreach ($options['intervention_focuses'] as $focus)
                                <option value="{{ $focus->value }}" @selected(request('intervention_focus') === $focus->value)>
                                    {{ $focus->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="labor_market_program" class="mb-2 block text-xs font-semibold text-slate-700">
                            Labor Market Program
                        </label>
                        <select id="labor_market_program" name="labor_market_program"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All programs</option>
                            @foreach ($options['labor_market_programs'] as $program)
                                <option value="{{ $program->value }}" @selected(request('labor_market_program') === $program->value)>
                                    {{ $program->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </details>

            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <a href="{{ route('reports.index') }}"
                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Reset
                </a>
                <button type="submit"
                    class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Generate Report
                </button>
            </div>
        </div>
    </form>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#356ba8]">
                    {{ $report['dimension']->label() }} Report
                </div>
                <h2 class="mt-1 text-lg font-bold text-slate-900">{{ $report['title'] }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ $report['description'] }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.print', $exportQuery) }}" target="_blank"
                    class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Print
                </a>
                <a href="{{ route('reports.export.pdf', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-semibold text-red-700 hover:bg-red-100">
                    PDF
                </a>
                <a href="{{ route('reports.export.excel', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-3 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                    Excel
                </a>
                <a href="{{ route('reports.export.csv', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    CSV
                </a>
            </div>
        </div>

        <div class="grid gap-3 border-b border-slate-200 p-5 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($report['summary_cards'] as $card)
                <article class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        {{ $card['label'] }}
                    </div>
                    <div class="mt-2 text-lg font-bold text-slate-900">{{ $card['display_value'] }}</div>
                </article>
            @endforeach
        </div>

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-600">
                @foreach ($report['criteria'] as $label => $value)
                    <span><strong class="text-slate-800">{{ $label }}:</strong> {{ $value }}</span>
                @endforeach
            </div>

            @if ($report['warning'])
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    {{ $report['warning'] }}
                </div>
            @endif
        </div>

        <div class="tupad-data-scroll overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        @foreach ($report['columns'] as $column)
                            <th @class([
                                'whitespace-nowrap px-4 py-3 text-xs font-semibold text-slate-600',
                                'text-right' => $column['align'] === 'right',
                                'text-left' => $column['align'] !== 'right',
                            ])>
                                {{ $column['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['display_rows'] as $row)
                        <tr class="hover:bg-slate-50/70">
                            @foreach ($report['columns'] as $column)
                                <td @class([
                                    'px-4 py-3 text-xs text-slate-700',
                                    'text-right whitespace-nowrap tabular-nums' => $column['align'] === 'right',
                                    'text-left' => $column['align'] !== 'right',
                                ])>
                                    {{ $row[$column['key']] ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['columns']) }}" class="px-5 py-12 text-center text-sm text-slate-500">
                                No records match the selected report criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">
            {{ number_format($report['rows']->count()) }} reporting row(s) generated
            {{ $report['generated_at']->format('M d, Y h:i A') }}.
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const reportType = document.getElementById('report_type');
            const groupBy = document.getElementById('group_by');
            const allowed = @json(collect($options['report_types'])->mapWithKeys(fn ($type) => [
                $type->value => collect($type->allowedDimensions())->pluck('value')->all(),
            ]));
            const defaults = @json(collect($options['report_types'])->mapWithKeys(fn ($type) => [
                $type->value => $type->defaultDimension()->value,
            ]));

            function refreshDimensions() {
                const valid = allowed[reportType.value] || [];

                Array.from(groupBy.options).forEach(function (option) {
                    option.hidden = !valid.includes(option.value);
                    option.disabled = !valid.includes(option.value);
                });

                if (!valid.includes(groupBy.value)) {
                    groupBy.value = defaults[reportType.value];
                }
            }

            reportType.addEventListener('change', refreshDimensions);
            refreshDimensions();
        });
    </script>
@endsection
