@extends('layouts.app')

@section('title', 'Fund Status Reports')

@section('content')
    @php
        $money = static fn (mixed $cents): string => $cents === null
            ? 'Not allocated'
            : '₱' . number_format(((int) $cents) / 100, 2);
        $allocation = (int) ($overallRow['allocation_cents'] ?? 0);
        $obligated = (int) ($overallRow['obligated_cents'] ?? 0);
        $unobligated = (int) ($overallRow['unobligated_balance_cents'] ?? 0);
        $disbursed = (int) ($overallRow['disbursed_cents'] ?? 0);
        $undisbursed = (int) ($overallRow['undisbursed_obligation_cents'] ?? 0);
        $cashBalance = (int) ($overallRow['balance_cents'] ?? 0);
        $fundStatusTemplate = $report['fund_status_template'] ?? null;
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header eyebrow="Official Reporting" title="Fund Status Reports"
            description="Review TUPAD allocation, accomplishment (obligated), disbursement, and balances using validated audited financial records." />

        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('reports.index', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Detailed Generator</a>
            <a href="{{ route('reports.print', $exportQuery) }}" target="_blank"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print</a>
            <a href="{{ route('reports.export.pdf', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">PDF</a>
        </div>
    </div>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Fund status report views">
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
            @foreach ($views as $key => $tab)
                <a href="{{ route('reports.workspace.fund-status', array_merge($commonQuery, ['view' => $key])) }}"
                    class="{{ $viewKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-3 py-3 transition">
                    <div class="text-[11px] font-extrabold uppercase tracking-[0.14em] opacity-70">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="mt-1 text-xs font-bold leading-5">{{ $tab['label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">{{ $viewConfig['label'] }}</div>
            <p class="mt-1 text-sm text-slate-500">{{ $viewConfig['description'] }}</p>
        </div>
        <form method="GET" action="{{ route('reports.workspace.fund-status') }}" class="p-5">
            <input type="hidden" name="view" value="{{ $viewKey }}">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100" value="{{ $filters['fiscal_year'] ?? '' }}" placeholder="All years"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
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
                    <button class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">Apply Filters</button>
                    <a href="{{ route('reports.workspace.fund-status', ['view' => $viewKey]) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700">Reset</a>
                </div>
            </div>
        </form>
    </section>

    @unless ($fundStatusTemplate)
    <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['TUPAD Allocation', $allocation, 'Total grant allocation in the selected fund scope'],
            ['Accomplishment (Obligated)', $obligated, 'DA obligations plus official ACP payment-stage records'],
            ['Balance (Unobligated)', $unobligated, 'TUPAD allocation less total obligated'],
            ['Total Disbursed', $disbursed, 'DA disbursements plus released ACP checks'],
            ['Undisbursed Obligation', $undisbursed, 'Obligated amount not yet disbursed'],
            ['Cash Balance', $cashBalance, 'TUPAD allocation less total disbursed'],
        ] as [$label, $value, $hint])
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">{{ $label }}</div>
                <div class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">{{ $money($value) }}</div>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $hint }}</p>
            </article>
        @endforeach
    </section>

    <section class="mb-5 grid gap-4 lg:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div><div class="text-xs font-bold text-slate-800">Obligation against Allocation</div><div class="mt-1 text-xs text-slate-500">Operational fund-utilization indicator.</div></div>
                <div class="text-xl font-extrabold text-[#063b86]">{{ number_format($utilization['obligation_rate'], 1) }}%</div>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, max(0, $utilization['obligation_rate'])) }}%"></div></div>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div><div class="text-xs font-bold text-slate-800">Disbursement against Allocation</div><div class="mt-1 text-xs text-slate-500">Operational cash-utilization indicator.</div></div>
                <div class="text-xl font-extrabold text-[#063b86]">{{ number_format($utilization['disbursement_rate'], 1) }}%</div>
            </div>
            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#063b86]" style="width: {{ min(100, max(0, $utilization['disbursement_rate'])) }}%"></div></div>
        </article>
    </section>

    @endunless

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('representatives') || $errors->has('representatives.*'))
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm font-semibold text-rose-800">
            {{ $errors->first('representatives') ?: $errors->first('representatives.*') }}
        </div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">{{ $viewConfig['label'] }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $fundStatusTemplate ? 'Official table structure follows the approved TUPAD fund-status reporting template.' : 'The same validated cohort is used by screen, Print, PDF, Excel, and CSV outputs.' }}
                </p>
            </div>
            @if ($districtRepresentatives->isNotEmpty())
                <button type="button" onclick="document.getElementById('district-representatives-dialog').showModal()"
                    class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Edit Representatives
                </button>
            @endif
        </div>
        @if ($fundStatusTemplate)
            @include('reports.fund-status.partials.template-table-screen', ['report' => $report])
        @else
            @if ($report['warning'])
                <div class="border-b border-amber-200 bg-amber-50 px-5 py-3 text-xs leading-5 text-amber-800">{{ $report['warning'] }}</div>
            @endif
            @if ($report['rows']->isEmpty())
                <div class="px-5 py-12 text-center"><div class="text-sm font-semibold text-slate-700">No fund-status data matched the selected criteria.</div><div class="mt-1 text-xs text-slate-500">Adjust the filters and try again.</div></div>
            @else
                <div class="overflow-x-auto">
                    <table class="tupad-report-table tupad-system-table tupad-report-screen-table tupad-wide-table min-w-[1500px] w-full text-xs">
                        <thead class="bg-slate-50 text-slate-600"><tr>@foreach ($report['columns'] as $column)<th @class(['border-b border-slate-200 px-4 py-3 font-bold', 'text-right' => $column['align'] === 'right', 'text-left' => $column['align'] !== 'right'])>{{ $column['label'] }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report['display_rows'] as $row)
                                <tr class="hover:bg-slate-50/70">@foreach ($report['columns'] as $column)<td @class(['whitespace-nowrap px-4 py-3 text-slate-700', 'text-right tabular-nums' => $column['align'] === 'right', 'text-left' => $column['align'] !== 'right'])>{{ $row[$column['key']] ?? '—' }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </section>

    @if ($districtRepresentatives->isNotEmpty())
        <dialog id="district-representatives-dialog" aria-labelledby="district-representatives-title"
            class="tupad-dialog w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl">
            <form method="POST" action="{{ route('reports.district-representatives.update') }}">
                @csrf
                @method('PUT')
                @foreach ($commonQuery as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 id="district-representatives-title" class="text-base font-bold text-slate-900">Edit District Representatives</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Update the names after each congressional election. Changes apply to the Summary per District report.</p>
                </div>

                <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                    @foreach ($districtRepresentatives->groupBy('province') as $province => $entries)
                        <fieldset class="mb-4 last:mb-0">
                            <legend class="mb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-[#063b86]">{{ $province }}</legend>
                            <div class="space-y-2">
                                @foreach ($entries as $entry)
                                    @php($index = $loop->parent->index * 100 + $loop->index)
                                    <div class="grid grid-cols-[7rem_1fr] items-center gap-3">
                                        <label for="representative-{{ $entry->id }}" class="text-xs font-semibold text-slate-600">{{ $entry->district }}</label>
                                        <input type="hidden" name="representatives[{{ $index }}][id]" value="{{ $entry->id }}">
                                        <input id="representative-{{ $entry->id }}" name="representatives[{{ $index }}][representative]"
                                            value="{{ old("representatives.$index.representative", $entry->representative) }}" maxlength="255"
                                            class="h-9 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3 sm:flex-row sm:justify-end">
                    <button type="button" onclick="this.closest('dialog').close()"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        Save Representatives
                    </button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
