@extends('layouts.app')

@section('title', $section['label'])

@section('content')
    <x-page-header eyebrow="Official Reporting" :title="$section['label']"
        :description="$section['description']" />

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Report categories">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($sections as $key => $reportSection)
                <a href="{{ route($reportSection['route']) }}"
                    class="{{ $activeKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.15em] opacity-70">
                        {{ $reportSection['number'] }}
                    </div>
                    <div class="mt-1 text-sm font-bold leading-5">{{ $reportSection['short_label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">
                    Report Workspace {{ $section['number'] }}
                </div>
                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-slate-900">{{ $section['label'] }}</h2>
                <p class="mt-1 max-w-4xl text-sm leading-6 text-slate-500">
                    Select the report view you need. Existing Phase 8/9 data views remain available while dedicated screen and official print layouts are implemented in {{ $section['phase'] }}.
                </p>
            </div>
            <a href="{{ route('reports.index') }}"
                class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Detailed Report Generator
            </a>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-2">
            @foreach ($section['items'] as $item)
                <article class="flex min-h-52 flex-col rounded-xl border border-slate-200 bg-slate-50/40 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">{{ $item['label'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $item['description'] }}</p>
                        </div>

                        @if ($item['status'] === 'available')
                            <span class="shrink-0 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-700">
                                Current Data
                            </span>
                        @else
                            <span class="shrink-0 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                                {{ $section['phase'] }}
                            </span>
                        @endif
                    </div>

                    @if (!empty($item['children']))
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($item['children'] as $child)
                                <span class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600">
                                    {{ $child }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if (!empty($item['groups']))
                        <div class="mt-4 space-y-3">
                            @foreach ($item['groups'] as $groupLabel => $groupItems)
                                <details class="rounded-lg border border-slate-200 bg-white">
                                    <summary class="cursor-pointer px-3 py-2.5 text-xs font-bold text-slate-700">
                                        {{ $groupLabel }}
                                    </summary>
                                    <div class="flex flex-wrap gap-1.5 border-t border-slate-100 p-3">
                                        @foreach ($groupItems as $groupItem)
                                            <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">
                                                {{ $groupItem }}
                                            </span>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-auto pt-5">
                        @if (!empty($item['links']))
                            <div class="flex flex-wrap gap-2">
                                @foreach ($item['links'] as $link)
                                    <a href="{{ route('reports.index', $link['query']) }}"
                                        class="inline-flex h-9 items-center rounded-lg border border-blue-200 bg-white px-3 text-xs font-semibold text-[#063b86] hover:bg-blue-50">
                                        {{ $link['label'] }} Data
                                    </a>
                                @endforeach
                            </div>
                        @elseif (!empty($item['query']))
                            <a href="{{ route('reports.index', $item['query']) }}"
                                class="inline-flex h-9 items-center rounded-lg bg-[#063b86] px-3.5 text-xs font-semibold text-white hover:bg-[#052f6d]">
                                Open Current Data View
                            </a>
                        @else
                            <div class="text-xs font-semibold text-slate-500">
                                Navigation structure ready · report-specific data and print implementation follows in {{ $section['phase'] }}.
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm leading-6 text-blue-900">
        <strong>Phase 14A scope:</strong> this revision reorganizes report navigation and workspace UX only. Existing report calculations, province security, and Print/PDF/Excel/CSV generation remain unchanged. Official print layouts will be implemented in their dedicated report phases.
    </div>
@endsection
