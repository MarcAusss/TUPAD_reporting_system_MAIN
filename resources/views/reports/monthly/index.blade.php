@extends('layouts.app')

@section('title', 'Monthly Reports')

@section('content')
    @php
        $money = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
        $monthName = filled($filters['month'] ?? null)
            ? \Carbon\CarbonImmutable::create((int) $filters['fiscal_year'], (int) $filters['month'], 1)->format('F Y')
            : 'January – December ' . (int) $filters['fiscal_year'];
        $commonQuery = array_filter([
            'fiscal_year' => $filters['fiscal_year'] ?? null,
            'month' => $filters['month'] ?? null,
            'status' => $filters['status'] ?? null,
            'implementation_mode' => $filters['implementation_mode'] ?? null,
            'province_id' => $filters['province_id'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');
        $officialQuery = array_merge($commonQuery, ['form' => $viewKey]);
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header eyebrow="Official Reporting" title="Monthly Reports"
            description="Review monthly SPRS monitoring and the recorded AlkanSSSya / YAKAP orientation activity for TUPAD beneficiaries." />
        <div class="flex flex-wrap items-start gap-2">
            <a href="{{ route('reports.periodic.print', $officialQuery) }}" target="_blank"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Official Print</a>
            <a href="{{ route('reports.periodic.export.pdf', $officialQuery) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">Official PDF</a>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-right shadow-sm">
                <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Reporting Period</div>
                <div class="mt-1 text-sm font-bold text-slate-900">{{ $monthName }}</div>
            </div>
        </div>
    </div>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Monthly report views">
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($views as $key => $tab)
                <a href="{{ route('reports.workspace.monthly', array_merge($commonQuery, ['view' => $key])) }}"
                    class="{{ $viewKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] opacity-70">Monthly Report {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
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

        <form method="GET" action="{{ route('reports.workspace.monthly') }}" class="p-5">
            <input type="hidden" name="view" value="{{ $viewKey }}">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100" required
                        value="{{ $filters['fiscal_year'] }}" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                </div>
                <div>
                    <label for="month" class="mb-2 block text-xs font-semibold text-slate-700">Month</label>
                    <select id="month" name="month" @required($viewKey !== 'orientations') class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        @if ($viewKey === 'orientations')
                            <option value="">All months</option>
                        @endif
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((int) ($filters['month'] ?? 0) === $month)>{{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>
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
                    <select id="province_id" name="province_id"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100 disabled:text-slate-600" @disabled($provinceLocked)>
                        @unless ($provinceLocked)<option value="">All provinces</option>@endunless
                        @foreach ($options['provinces'] as $province)
                            <option value="{{ $province->id }}" @selected((string) ($filters['province_id'] ?? '') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                    @if ($provinceLocked)<input type="hidden" name="province_id" value="{{ $filters['province_id'] ?? '' }}">@endif
                </div>
                <div class="flex items-end gap-2">
                    <button class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">Apply</button>
                    <a href="{{ route('reports.workspace.monthly', ['view' => $viewKey]) }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700">Reset</a>
                </div>
            </div>
        </form>
    </section>

    @if ($viewKey === 'sprs')
        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Projects', $summary['project_count'], 'Projects reported in the selected month'],
                ['Beneficiaries', $summary['beneficiaries_total'], 'Beneficiaries for the same SPRS cohort'],
                ['Female Beneficiaries', $summary['beneficiaries_female'], 'Female beneficiaries in the same cohort'],
                ['Completed Projects', $summary['completed_project_count'], 'Projects currently marked Completed'],
            ] as [$label, $value, $hint])
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $value) }}</div>
                    <p class="mt-2 text-[11px] leading-4 text-slate-500">{{ $hint }}</p>
                </article>
            @endforeach
        </section>

        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['Approved Amount', $summary['proposed_cost_cents'] ?? 0],
                ['Actual Amount', $summary['actual_cost_cents'] ?? 0],
                ['TUPAD Allocation', $summary['allocation_cents']],
                ['Obligated', $summary['obligated_cents']],
                ['Disbursed', $summary['disbursed_cents']],
            ] as [$label, $value])
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-xl font-extrabold text-slate-900">{{ $money($value) }}</div>
                </article>
            @endforeach
        </section>
    @else
        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Orientation Records', $orientationCounts['orientation_records'], 'Recorded orientations in ' . $monthName],
                ['AlkanSSSya', $orientationCounts['alkansssya'], 'Orientations explicitly marked with AlkanSSSya coverage'],
                ['YAKAP', $orientationCounts['yakap'], 'Orientations explicitly marked with YAKAP coverage'],
                ['Program Unspecified', $orientationCounts['program_unspecified'], 'Legacy or unclassified orientation records; no program is inferred'],
            ] as [$label, $value, $hint])
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) $value) }}</div>
                    <p class="mt-2 text-[11px] leading-4 text-slate-500">{{ $hint }}</p>
                </article>
            @endforeach
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-bold text-slate-900">{{ $viewConfig['label'] }}</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                @if ($viewKey === 'sprs')
                    Beneficiaries per province from January up to {{ $monthName }}. A project counts in the month of its SPRS date, otherwise the month its obligations were completed or its ACP payment was recorded. Later months stay blank.
                @else
                    Every orientation conducted in {{ $monthName }}, listed under its month with a sub-total after each quarter. Choose a month to view only that month. Topics are marked only when explicitly encoded on the orientation record.
                @endif
            </p>
        </div>

        @if ($viewKey === 'sprs')
            @php
                $sprsNumber = static fn (mixed $value): string => number_format((int) $value);
                $sprsRowClass = static fn (string $type): string => match ($type) {
                    'quarter' => 'tupad-report-subtotal-row tupad-sprs-quarter-row bg-[#c9dcf2] font-extrabold text-[#071d44]',
                    'grand_total' => 'tupad-report-total-row bg-[#10294f] font-extrabold text-white',
                    default => 'bg-white text-slate-700 hover:bg-blue-50/70',
                };
            @endphp
            <div class="overflow-x-auto">
                <table class="tupad-report-table tupad-system-table tupad-report-screen-table tupad-wide-table min-w-[1200px] w-full text-xs" aria-label="SPRS province and month matrix">
                    <thead>
                        <tr class="bg-[#071d44] text-white">
                            <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Province / Month</th>
                            <th colspan="2" class="border border-[#26456f] bg-[#063b86] px-3 py-2 text-center font-bold">Overall</th>
                            @foreach ($sprsMatrix['province_headers'] as $provinceLabel)
                                <th colspan="2" class="border border-[#26456f] px-3 py-2 text-center font-bold">{{ $provinceLabel }}</th>
                            @endforeach
                        </tr>
                        <tr class="bg-[#dbe8f7] text-[#10294f]">
                            @foreach (range(0, count($sprsMatrix['province_headers'])) as $column)
                                <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Total</th>
                                <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Female</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sprsMatrix['rows'] as $row)
                            @php($isMonth = $row['row_type'] === 'month')
                            <tr class="{{ $sprsRowClass($row['row_type']) }}">
                                <td class="whitespace-nowrap border border-slate-200 px-3 py-2">{{ $row['label'] }}</td>
                                <td @class(['border border-slate-200 px-3 py-2 text-right tabular-nums font-semibold', 'bg-[#eef3fa]' => $isMonth])>{{ $row['included'] ? $sprsNumber($row['overall']['total']) : '' }}</td>
                                <td @class(['border border-slate-200 px-3 py-2 text-right tabular-nums', 'bg-[#eef3fa]' => $isMonth])>{{ $row['included'] ? $sprsNumber($row['overall']['female']) : '' }}</td>
                                @foreach ($sprsMatrix['province_headers'] as $provinceKey => $provinceLabel)
                                    @php($value = $row['provinces'][$provinceKey])
                                    {{-- Months that have ended show 0 when empty; the current/future months stay blank when zero; sub-totals always show. --}}
                                    @php($show = $row['included'] && (! $isMonth || ($row['elapsed'] ?? false) || $value['total'] > 0))
                                    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $show ? $sprsNumber($value['total']) : '' }}</td>
                                    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $show ? $sprsNumber($value['female']) : '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($viewKey === 'orientations')
            <div class="overflow-x-auto">
                <table class="tupad-report-table tupad-system-table tupad-report-screen-table tupad-wide-table min-w-[1100px] w-full text-xs" aria-label="List of Orientations Conducted">
                    <thead>
                        <tr class="bg-[#071d44] text-white">
                            <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-center font-bold">No.</th>
                            <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Date of Activity</th>
                            <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Venue</th>
                            <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Learning Modality<br><span class="text-[10px] font-medium text-blue-200">(AVP / Face-to-Face)</span></th>
                            <th colspan="2" class="border border-[#26456f] px-3 py-2 text-center font-bold">No. of Beneficiaries Oriented</th>
                            <th colspan="2" class="border border-[#26456f] px-3 py-2 text-center font-bold">Topics Discussed</th>
                            <th rowspan="2" class="border border-[#26456f] px-3 py-3 text-left font-bold">Name of Facilitator/s</th>
                        </tr>
                        <tr class="bg-[#dbe8f7] text-[#10294f]">
                            <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Total</th>
                            <th class="border border-[#b8cbe2] px-3 py-2 text-right font-bold">Female</th>
                            <th class="border border-[#b8cbe2] px-3 py-2 text-center font-bold">AlkanSSSya Program</th>
                            <th class="border border-[#b8cbe2] px-3 py-2 text-center font-bold">YAKAP Program</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orientationTable['sections'] as $section)
                            @if ($section['type'] === 'month')
                                <tr class="bg-[#eef3fa] text-[#10294f]">
                                    <td colspan="9" class="border border-[#b8cbe2] px-3 py-2 text-[11px] font-extrabold tracking-[0.08em]">{{ $section['label'] }}</td>
                                </tr>
                                @forelse ($section['rows'] as $row)
                                    <tr class="bg-white hover:bg-blue-50/70">
                                        <td class="border border-slate-200 px-3 py-2.5 text-center font-semibold text-slate-800">{{ $row['no'] }}</td>
                                        <td class="whitespace-nowrap border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['date'] }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-slate-700">
                                            {{ $row['venue'] }}
                                            <div class="mt-0.5 text-[11px] text-slate-400">{{ $row['project'] }}</div>
                                        </td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['modality'] }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-right tabular-nums font-semibold text-slate-800">{{ number_format($row['total']) }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-right tabular-nums text-slate-700">{{ number_format($row['female']) }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-center font-bold text-[#063b86]">{{ $row['alkansssya'] ? '✓' : '' }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-center font-bold text-[#063b86]">{{ $row['yakap'] ? '✓' : '' }}</td>
                                        <td class="border border-slate-200 px-3 py-2.5 text-slate-700">{{ $row['facilitator'] }}</td>
                                    </tr>
                                @empty
                                    <tr class="bg-white"><td colspan="9" class="border border-slate-200 px-3 py-2 text-center text-[11px] text-slate-400">No orientations recorded</td></tr>
                                @endforelse
                            @else
                                <tr class="bg-[#c9dcf2] font-bold text-[#071d44]">
                                    <td colspan="4" class="border border-[#b8cbe2] px-3 py-2.5">{{ $section['label'] }}</td>
                                    <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ number_format($section['totals']['total']) }}</td>
                                    <td class="border border-[#b8cbe2] px-3 py-2.5 text-right tabular-nums">{{ number_format($section['totals']['female']) }}</td>
                                    <td colspan="3" class="border border-[#b8cbe2] px-3 py-2.5"></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-[#10294f] font-bold text-white">
                            <td colspan="4" class="border border-[#26456f] px-3 py-3">{{ count($orientationTable['sections']) > 1 ? 'GRAND TOTAL' : 'TOTAL' }}</td>
                            <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ number_format($orientationTable['totals']['total']) }}</td>
                            <td class="border border-[#26456f] px-3 py-3 text-right tabular-nums">{{ number_format($orientationTable['totals']['female']) }}</td>
                            <td colspan="3" class="border border-[#26456f] px-3 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @elseif ($rows->isEmpty())
            <div class="px-5 py-12 text-center">
                <div class="text-sm font-semibold text-slate-700">No monthly report data matched the selected criteria.</div>
                <div class="mt-1 text-xs text-slate-500">Change the month or filters and try again.</div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="tupad-report-table tupad-system-table tupad-report-screen-table min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-bold">Date</th>
                            <th class="px-4 py-3 font-bold">Project Code</th>
                            <th class="min-w-64 px-4 py-3 font-bold">Project</th>
                            <th class="px-4 py-3 font-bold">Province / Municipality</th>
                            @if ($viewKey === 'sprs')
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3 font-bold">Beneficiaries</th>
                            @else
                                <th class="min-w-64 px-4 py-3 font-bold">Program Coverage</th>
                                <th class="px-4 py-3 font-bold">Project Beneficiaries</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @foreach ($rows as $row)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">{{ ($viewKey === 'sprs' ? $row['report_date'] : $row['orientation_date'])?->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $row['project_code'] ?: 'Pending code' }}</td>
                                <td class="px-4 py-3"><div class="font-semibold text-slate-900">{{ $row['project_title'] }}</div></td>
                                <td class="px-4 py-3">{{ collect([$row['province'], $row['municipality']])->filter()->implode(' · ') ?: 'Not encoded' }}</td>
                                @if ($viewKey === 'sprs')
                                    <td class="whitespace-nowrap px-4 py-3">{{ $row['status']->label() }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ number_format($row['beneficiaries_total']) }} <span class="text-slate-400">/ {{ number_format($row['beneficiaries_female']) }} female</span></td>
                                @else
                                    <td class="px-4 py-3">
                                        @if (count($row['programs']))
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($row['programs'] as $program)
                                                    <span class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-blue-800">{{ $program }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-amber-700">Program coverage not specified</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">{{ number_format($row['beneficiaries_total']) }} <span class="text-slate-400">/ {{ number_format($row['beneficiaries_female']) }} female</span></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-900">
        Official Print/PDF uses the approved government report layout while preserving the source dates and encoded program classifications shown on this screen.
    </div>
@endsection
