@extends('layouts.app')

@section('title', 'Provincial Project Summary')

@section('content')

<x-page-header
    eyebrow="Project Summary"
    title="Provincial Summary"
    description="Open a province to review its project register and municipalities with current project beneficiary coverage."
/>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-sm font-semibold text-slate-900">
                Bicol Provinces
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Project totals and municipality counts below are derived from current official project records.
            </p>
        </div>

        <div class="relative w-full sm:w-80">
            <input
                id="provinceCardSearch"
                type="search"
                placeholder="Search province..."
                class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm"
            >

            <svg
                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
            >
                <circle cx="11" cy="11" r="7"></circle>
                <path d="m20 20-3.5-3.5"></path>
            </svg>
        </div>

    </div>

    <div
        id="provinceSummaryCards"
        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
    >

        @foreach($provinces as $item)

            <a
                href="{{ route('project-summary.province', $item['province']) }}"
                class="js-province-summary-card group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-blue-300 hover:shadow-md"
                data-search="{{ mb_strtolower($item['province']->name) }}"
            >

                <div class="flex items-start justify-between gap-4">

                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#063b86]">
                            Province
                        </div>

                        <h3 class="mt-1 text-lg font-bold text-slate-950">
                            {{ $item['province']->name }}
                        </h3>
                    </div>

                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-[#063b86] transition group-hover:bg-[#063b86] group-hover:text-white">
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M3 21h18"></path>
                            <path d="M5 21V9l7-5 7 5v12"></path>
                            <path d="M9 21v-6h6v6"></path>
                        </svg>
                    </div>

                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">

                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            Projects
                        </div>

                        <div class="mt-1 text-xl font-bold text-slate-900">
                            {{ number_format($item['project_count']) }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            Municipalities
                        </div>

                        <div class="mt-1 text-xl font-bold text-slate-900">
                            {{ number_format($item['municipality_count']) }}
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            Beneficiaries
                        </div>

                        <div class="mt-1 text-xl font-bold text-[#063b86]">
                            {{ number_format($item['beneficiaries']) }}
                        </div>

                        <div class="mt-0.5 text-[10px] text-slate-400">
                            {{ number_format($item['female_beneficiaries']) }} female
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-50 p-3">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            Amount Assisted
                        </div>

                        <div class="mt-1 text-sm font-bold text-slate-900">
                            ₱{{ number_format($item['amount_assisted'], 2) }}
                        </div>
                    </div>

                </div>

                <div class="mt-4 flex items-center justify-end text-xs font-semibold text-[#063b86]">
                    Open Provincial Summary
                    <span class="ml-1">→</span>
                </div>

            </a>

        @endforeach

    </div>

</section>

@push('scripts')
<script>
    document.addEventListener(
        'DOMContentLoaded',
        function () {
            const search =
                document.getElementById(
                    'provinceCardSearch'
                );

            const cards =
                document.querySelectorAll(
                    '.js-province-summary-card'
                );

            search?.addEventListener(
                'input',
                function () {
                    const query =
                        search.value
                            .trim()
                            .toLowerCase();

                    cards.forEach(
                        function (card) {
                            card.classList.toggle(
                                'hidden',
                                query !== ''
                                && !(
                                    card.dataset.search
                                    ?? ''
                                ).includes(query)
                            );
                        }
                    );
                }
            );
        }
    );
</script>
@endpush

@endsection
