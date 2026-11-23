@extends('layouts.app')

@section('title', 'Search')

{{-- Search results for the GSIS Enrollment Officer (App\Services\Gsis\GsisSearchService). --}}
@section('content')
@php
    $projects = $results['projects'];
    $enrollments = $results['enrollments'];
    $cashAdvances = $results['cash_advances'];
    $empty = $projects === [] && $enrollments === [] && $cashAdvances === [];
@endphp

<div class="tupad-search-page space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[.08em] text-slate-500">GPAI Enrollment Search</div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#10294f]">Search Results</h1>
            @if ($query)
                <p class="mt-1 text-sm text-slate-500">Results for <span class="font-semibold text-slate-700">“{{ $query }}”</span></p>
            @endif
        </div>

        <form method="GET" action="{{ route('search.index') }}" role="search" class="w-full lg:max-w-xl">
            <label for="results-search" class="sr-only">Search enrollment records</label>
            <div class="flex gap-2">
                <div class="tupad-input flex h-11 min-w-0 flex-1 items-center rounded-lg px-3.5 focus-within:ring-2 focus-within:ring-[#1765d8]/30">
                    <svg class="h-4.5 w-4.5 shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                    <input id="results-search" name="q" type="search" value="{{ $query }}"
                        placeholder="Project, GPAI number, ADL, project code, municipality..."
                        class="h-full w-full bg-transparent pl-3 text-sm text-[#233f67] outline-none placeholder:text-[#8290a5]">
                </div>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white transition hover:bg-[#052f6c] focus:outline-none focus:ring-2 focus:ring-[#1765d8] focus:ring-offset-2">Search</button>
            </div>
        </form>
    </div>

    @if (mb_strlen($query) < 2)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <div class="text-sm font-semibold text-amber-900">Enter at least 2 characters.</div>
            <p class="mt-1 text-sm text-amber-700">Search by project title, project code, GPAI number, ADL number, province, or municipality.</p>
        </div>
    @elseif ($empty)
        <div class="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <div class="text-sm font-semibold text-slate-700">No matches for “{{ $query }}”</div>
            <p class="mt-1 text-sm text-slate-500">Only projects that are enrolled or awaiting enrollment, GPAI transmittals and cash advances are searched.</p>
        </div>
    @else
        @foreach ([
            ['Projects', $projects, 'project'],
            ['GPAI Transmittals', $enrollments, 'enrollment'],
            ['Cash Advances', $cashAdvances, 'cash'],
        ] as [$title, $rows, $kind])
            @continue($rows === [])
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-bold text-[#10294f]">{{ $title }}</h2>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ count($rows) }}</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($rows as $row)
                        <a href="{{ $row['url'] }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">{{ $row['title'] ?? $row['number'] ?? $row['label'] }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                                    @if ($kind === 'project')
                                        @foreach (array_filter([$row['code'], $row['adl'] ? 'ADL '.$row['adl'] : null, $row['location']]) as $part)
                                            @unless ($loop->first)<span aria-hidden="true">·</span>@endunless
                                            <span>{{ $part }}</span>
                                        @endforeach
                                        <span class="rounded-full px-2 py-0.5 font-semibold ring-1 ring-inset {{ $row['status_classes'] }}">{{ $row['status'] }}</span>
                                    @else
                                        <span>{{ $row['meta'] }}</span>
                                        @isset($row['stage'])
                                            <span class="rounded-full bg-blue-50 px-2 py-0.5 font-semibold text-[#063b86] ring-1 ring-inset ring-blue-200">{{ $row['stage'] }}</span>
                                        @endisset
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-blue-700">
                                {{ match (true) {
                                    $kind === 'cash' => 'View Cash Advances →',
                                    $kind === 'enrollment' => 'Open Transmittal →',
                                    str_starts_with($row['status'], 'Enrolled') => 'Open Transmittal →',
                                    default => 'Enroll Project →',
                                } }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection
