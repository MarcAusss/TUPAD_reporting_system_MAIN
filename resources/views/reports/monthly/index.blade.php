@extends('layouts.app')

@section('title', 'Monthly Reports')

@section('content')
    @php
        $money = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
        $monthName = \Carbon\CarbonImmutable::create((int) $filters['fiscal_year'], (int) $filters['month'], 1)->format('F Y');
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
                    <select id="month" name="month" required class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected((int) $filters['month'] === $month)>{{ \Carbon\CarbonImmutable::create(2000, $month, 1)->format('F') }}</option>
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
                ['Projects', $summary['project_count'], 'Projects with an SPRS date in the selected month'],
                ['Beneficiaries', $summary['beneficiaries_total'], 'Encoded beneficiaries for the same SPRS cohort'],
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

        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Project Cost', $summary['project_cost_cents']],
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
                ['Orientation Records', $orientationCounts['orientation_records'], 'Recorded orientations in the selected month'],
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
                    Only projects with an encoded SPRS monitoring date inside {{ $monthName }} are listed. Financial values are the current audited values of those projects.
                @else
                    Program coverage is reported only when explicitly encoded on the orientation record. Legacy remarks are not parsed or guessed.
                @endif
            </p>
        </div>

        @if ($rows->isEmpty())
            <div class="px-5 py-12 text-center">
                <div class="text-sm font-semibold text-slate-700">No monthly report data matched the selected criteria.</div>
                <div class="mt-1 text-xs text-slate-500">Change the month or filters and try again.</div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
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
        Official Print/PDF now uses the Phase 14F government report layout while preserving the source dates and encoded program classifications shown on this screen.
    </div>
@endsection
