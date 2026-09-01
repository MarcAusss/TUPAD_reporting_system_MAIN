@extends('layouts.app')

@section('title', 'Physical & Financial Accomplishment')

@section('content')
    @php
        $money = static fn (mixed $cents): string => '₱' . number_format(((int) $cents) / 100, 2);
        $number = static fn (mixed $value): string => number_format((int) $value);

        $summaryCards = [
            ['label' => 'Projects', 'value' => $number($matrixTotal['project_count'] ?? 0)],
            ['label' => 'Physical Target', 'value' => $number(data_get($matrixTotal, 'target.physical', 0))],
            ['label' => 'Financial Target', 'value' => $money(data_get($matrixTotal, 'target.financial_cents', 0))],
            ['label' => 'Physical Accomplishment', 'value' => $number(data_get($matrixTotal, 'accomplishment.physical', 0))],
            ['label' => 'Financial Accomplishment', 'value' => $money(data_get($matrixTotal, 'accomplishment.financial_cents', 0))],
            ['label' => 'Physical Balance', 'value' => $number(data_get($matrixTotal, 'balance.physical', 0))],
            ['label' => 'Financial Balance', 'value' => $money(data_get($matrixTotal, 'balance.financial_cents', 0))],
        ];
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header
            eyebrow="Official Reporting"
            title="Physical & Financial Accomplishment"
            description="Official province-level physical and financial accomplishment tables based on the supplied reporting template."
        />

        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('reports.index', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Detailed Data
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

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Physical and financial report tables">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($views as $key => $tab)
                <a href="{{ route('reports.workspace.physical-financial', array_merge($commonQuery, ['view' => $key])) }}"
                    class="{{ $viewKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.15em] opacity-70">
                        Table {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                    </div>
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
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">{{ $viewConfig['label'] }}</div>
                <p class="mt-1 text-sm text-slate-500">{{ $viewConfig['description'] }}</p>
            </div>
            <div class="text-xs font-semibold text-slate-500">Period basis: Project Date Received</div>
        </div>

        <form method="GET" action="{{ route('reports.workspace.physical-financial') }}" class="p-5">
            <input type="hidden" name="view" value="{{ $viewKey }}">

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100"
                        value="{{ $filters['fiscal_year'] ?? '' }}"
                        placeholder="{{ $viewKey === 'overall' ? 'All years' : now('Asia/Manila')->year }}"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                </div>

                <div>
                    <label for="implementation_mode" class="mb-2 block text-xs font-semibold text-slate-700">Implementation Mode</label>
                    <select id="implementation_mode" name="implementation_mode" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All modes</option>
                        @foreach ($options['implementation_modes'] as $mode)
                            <option value="{{ $mode->value }}" @selected(($filters['implementation_mode'] ?? null) === $mode->value)>
                                {{ $mode->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="mb-2 block text-xs font-semibold text-slate-700">Project Status</label>
                    <select id="status" name="status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($options['statuses'] as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>
                                {{ $status->label() }}
                            </option>
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
                            <option value="{{ $province->id }}" @selected((string) ($filters['province_id'] ?? '') === (string) $province->id)>
                                {{ $province->name }}
                            </option>
                        @endforeach
                    </select>
                    @if ($provinceLocked && !empty($filters['province_id']))
                        <input type="hidden" name="province_id" value="{{ $filters['province_id'] }}">
                    @endif
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                        Apply
                    </button>
                    <a href="{{ route('reports.workspace.physical-financial', ['view' => $viewKey]) }}"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </section>

    <section class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="grid sm:grid-cols-2 xl:grid-cols-7">
            @foreach ($summaryCards as $card)
                <div class="border-b border-slate-200 px-4 py-3 last:border-b-0 sm:border-r xl:border-b-0">
                    <div class="text-[9px] font-extrabold uppercase tracking-[0.12em] text-slate-500">{{ $card['label'] }}</div>
                    <div class="mt-1 text-base font-extrabold tracking-tight text-slate-900">{{ $card['value'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">{{ $viewConfig['label'] }} Table</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Table structure follows the supplied Physical and Financial Accomplishment template. Horizontal scrolling is available for wide quarterly and monthly matrices.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.export.excel', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('reports.export.csv', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
            </div>
        </div>

        <div class="overflow-x-auto p-4">
            <table class="w-full min-w-max border-collapse text-[11px] text-slate-800">
                @if ($viewKey === 'overall')
                    <thead>
                        <tr>
                            <th rowspan="2" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-left font-extrabold text-white">Province</th>
                            <th colspan="2" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-center font-extrabold text-white">Reformulated Target</th>
                            <th colspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-center font-extrabold text-slate-900">Accomplishment</th>
                            <th colspan="2" class="border border-slate-700 bg-[#f0b27a] px-3 py-2 text-center font-extrabold text-slate-900">Balance</th>
                        </tr>
                        <tr>
                            @foreach (['Physical', 'Financial', 'Physical', 'Financial', 'Physical', 'Financial'] as $heading)
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                @elseif ($viewKey === 'semester')
                    <thead>
                        <tr>
                            <th rowspan="3" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-left font-extrabold text-white">Province</th>
                            <th rowspan="2" colspan="2" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-center font-extrabold text-white">Reformulated Target</th>
                            <th colspan="4" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-center font-extrabold text-slate-900">
                                CY{{ $matrix['fiscal_year'] ?? now('Asia/Manila')->year }}
                            </th>
                            <th rowspan="2" colspan="2" class="border border-slate-700 bg-[#f0b27a] px-3 py-2 text-center font-extrabold text-slate-900">Balance</th>
                        </tr>
                        <tr>
                            @foreach ($matrix['periods'] as $period)
                                <th colspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-1.5 text-center font-bold">{{ $period['label'] }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach (range(1, 4) as $pair)
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">Physical</th>
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">Financial</th>
                            @endforeach
                        </tr>
                    </thead>
                @elseif ($viewKey === 'quarter')
                    <thead>
                        <tr>
                            <th rowspan="3" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-left font-extrabold text-white">Province</th>
                            <th rowspan="2" colspan="2" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-center font-extrabold text-white">Reformulated Target</th>
                            <th colspan="8" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-center font-extrabold text-slate-900">Accomplishment</th>
                            <th rowspan="2" colspan="2" class="border border-slate-700 bg-[#f0b27a] px-3 py-2 text-center font-extrabold text-slate-900">Balance</th>
                        </tr>
                        <tr>
                            @foreach ($matrix['periods'] as $period)
                                <th colspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-1.5 text-center font-bold">{{ $period['label'] }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach (range(1, 6) as $pair)
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">Physical</th>
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">Financial</th>
                            @endforeach
                        </tr>
                    </thead>
                @else
                    <thead>
                        <tr>
                            <th rowspan="2" class="border border-slate-700 bg-[#cb3f1d] px-3 py-2 text-left font-extrabold text-white">Province</th>
                            @foreach ($matrix['periods'] as $period)
                                <th colspan="2" class="border border-slate-700 bg-[#f8d45b] px-3 py-2 text-center font-extrabold text-slate-900">{{ $period['label'] }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($matrix['periods'] as $period)
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">Physical</th>
                                <th class="border border-slate-700 bg-[#fff7db] px-3 py-1.5 text-center font-bold">Financial</th>
                            @endforeach
                        </tr>
                    </thead>
                @endif

                <tbody>
                    @foreach ($matrix['rows'] as $row)
                        <tr class="hover:bg-slate-50">
                            <td class="border border-slate-700 px-3 py-2 font-semibold">{{ $row['province'] }}</td>

                            @if ($viewKey === 'overall')
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'target.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'target.financial_cents', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'accomplishment.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'accomplishment.financial_cents', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'balance.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'balance.financial_cents', 0)) }}</td>
                            @elseif (in_array($viewKey, ['semester', 'quarter'], true))
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'target.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'target.financial_cents', 0)) }}</td>

                                @foreach ($matrix['periods'] as $period)
                                    <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'periods.'.$period['key'].'.physical', 0)) }}</td>
                                    <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'periods.'.$period['key'].'.financial_cents', 0)) }}</td>
                                @endforeach

                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'balance.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'balance.financial_cents', 0)) }}</td>
                            @else
                                @foreach ($matrix['periods'] as $period)
                                    <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $number(data_get($row, 'periods.'.$period['key'].'.physical', 0)) }}</td>
                                    <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $money(data_get($row, 'periods.'.$period['key'].'.financial_cents', 0)) }}</td>
                                @endforeach
                            @endif
                        </tr>
                    @endforeach

                    @php($total = $matrix['total'])
                    <tr class="bg-[#3f3f3f] font-extrabold text-white">
                        <td class="border border-slate-700 px-3 py-2">TOTAL</td>

                        @if ($viewKey === 'overall')
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'target.physical', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'target.financial_cents', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'accomplishment.physical', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'accomplishment.financial_cents', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'balance.physical', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'balance.financial_cents', 0)) }}</td>
                        @elseif (in_array($viewKey, ['semester', 'quarter'], true))
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'target.physical', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'target.financial_cents', 0)) }}</td>
                            @foreach ($matrix['periods'] as $period)
                                <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'periods.'.$period['key'].'.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'periods.'.$period['key'].'.financial_cents', 0)) }}</td>
                            @endforeach
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'balance.physical', 0)) }}</td>
                            <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'balance.financial_cents', 0)) }}</td>
                        @else
                            @foreach ($matrix['periods'] as $period)
                                <td class="border border-slate-700 px-3 py-2 text-right">{{ $number(data_get($total, 'periods.'.$period['key'].'.physical', 0)) }}</td>
                                <td class="border border-slate-700 px-3 py-2 text-right">{{ $money(data_get($total, 'periods.'.$period['key'].'.financial_cents', 0)) }}</td>
                            @endforeach
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-[11px] leading-5 text-slate-600">
            <strong class="text-slate-700">Reporting basis:</strong> {{ $matrix['basis_note'] }}
        </div>
    </section>
@endsection
