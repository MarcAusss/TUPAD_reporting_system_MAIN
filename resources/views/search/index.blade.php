@extends('layouts.app')

@section('title', 'Search')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="text-xs font-semibold uppercase tracking-[.08em] text-slate-400">
                System Search
            </div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#10294f]">
                Search Results
            </h1>
            @if($query)
                <p class="mt-1 text-sm text-slate-500">
                    Results for <span class="font-semibold text-slate-700">“{{ $query }}”</span>
                </p>
            @endif
        </div>

        <form method="GET" action="{{ route('search.index') }}" role="search" class="w-full lg:max-w-xl">
            <label for="results-search" class="sr-only">Search system records</label>
            <div class="flex gap-2">
                <div class="tupad-input flex h-11 min-w-0 flex-1 items-center rounded-lg px-3.5 focus-within:ring-2 focus-within:ring-[#1765d8]/30">
                    <svg class="h-[18px] w-[18px] shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                    <input
                        id="results-search"
                        name="q"
                        type="search"
                        value="{{ $query }}"
                        placeholder="Project, ADL, code, province, municipality..."
                        class="h-full w-full bg-transparent pl-3 text-sm text-[#233f67] outline-none placeholder:text-[#8290a5]"
                    >
                </div>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white transition hover:bg-[#052f6c] focus:outline-none focus:ring-2 focus:ring-[#1765d8] focus:ring-offset-2">
                    Search
                </button>
            </div>
        </form>
    </div>

    @if(mb_strlen($query) < 2)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
            <div class="text-sm font-semibold text-amber-900">Enter at least 2 characters.</div>
            <p class="mt-1 text-sm text-amber-700">
                Search by project title, project code, ADL number, province, municipality, or barangay.
            </p>
        </div>
    @else
        @if($projects->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-bold text-[#10294f]">Projects</h2>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $projects->count() }}</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($projects as $project)
                        <a
                            href="{{ route('projects.show', $project) }}"
                            class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                        >
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">{{ $project->project_title }}</div>
                                <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-xs text-slate-500">
                                    <span>{{ $project->approval?->project_code ?? 'No project code yet' }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $project->municipality ?: 'No municipality' }}, {{ $project->province ?: 'No province' }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $project->status->label() }}</span>
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-blue-700">View Project →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($adls->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-bold text-[#10294f]">ADL Records</h2>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $adls->count() }}</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($adls as $adl)
                        <a href="{{ route('adl.show', $adl) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-slate-900">{{ $adl->adl_number }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    Adjusted Grants: ₱{{ number_format($adl->adjusted_grants, 2) }} · Remaining: ₱{{ number_format($adl->remaining_balance, 2) }}
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-blue-700">View ADL →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($drafts->isNotEmpty())
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-bold text-[#10294f]">My Project Drafts</h2>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $drafts->count() }}</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @foreach($drafts as $draft)
                        <a href="{{ route('project-drafts.show', $draft) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">{{ $draft->project_title }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $draft->municipality ?: 'No municipality' }}, {{ $draft->province ?: 'No province' }} · {{ $draft->status->label() }}
                                </div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-blue-700">View Draft →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($projects->isEmpty() && $adls->isEmpty() && $drafts->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                </div>
                <h2 class="mt-4 text-sm font-semibold text-slate-800">No matching records</h2>
                <p class="mx-auto mt-1 max-w-lg text-sm text-slate-500">
                    Try a project title, project code, ADL number, province, municipality, or barangay.
                </p>
            </div>
        @endif
    @endif
</div>
@endsection
