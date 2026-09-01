@extends('layouts.app')

@section('title', 'TUPAD Geographic Mapping')

@section('content')
    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header eyebrow="Official Reporting" title="TUPAD Geographic Mapping"
            description="Visualize project implementation, exact beneficiary concentration, beneficiary-sector project concentration, and intervention-focus project concentration using the same official Bicol geographic drill-down." />

        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('reports.index', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Detailed Data
            </a>
            <a href="{{ route('reports.print', $exportQuery) }}" target="_blank"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print</a>
            <a href="{{ route('reports.export.pdf', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">PDF</a>
        </div>
    </div>

    {{-- The four mapping families are the primary selector for one authoritative interactive map. --}}
    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Geographic mapping families">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($families as $key => $tab)
                <a href="{{ route('reports.workspace.geographic-mapping', array_merge($commonQuery, ['view' => $key])) }}"
                    aria-current="{{ $familyKey === $key ? 'page' : 'false' }}"
                    class="{{ $familyKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] opacity-70">
                        Map {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <div class="mt-1 text-sm font-bold leading-5">{{ $tab['label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    @if (auth()->user()->isFocal() || auth()->user()->isAdmin() || auth()->user()->isTc())
        <livewire:reports.geographic-distribution-map
            :family="$familyKey"
            :fiscal-year="$filters['fiscal_year'] ?? null"
            :quarter="$filters['quarter'] ?? null"
            :month="$filters['month'] ?? null"
            :status="$filters['status'] ?? null"
            :implementation-mode="$filters['implementation_mode'] ?? null"
            :sector-group="$filters['sector_group'] ?? null"
            :sector="$filters['sector'] ?? null"
            :intervention-focus="$filters['intervention_focus'] ?? null" />
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Mapping Data Register</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Official report rows for the selected {{ strtolower($family['label']) }} and reporting filters.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.export.excel', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('reports.export.csv', $exportQuery) }}"
                    class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500">No mapping rows available.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <th class="whitespace-nowrap px-4 py-3 font-bold uppercase tracking-wide text-slate-600">
                                    {{ $column['label'] }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($displayRows as $displayRow)
                            <tr class="hover:bg-slate-50/70">
                                @foreach ($report['columns'] as $column)
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                        {{ $displayRow[$column['key']] ?? '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
