@extends('layouts.app')

@php
    $isSingleProjectSummary = $sourceProject !== null;
    $sourceProjectCode = $sourceProject?->approval?->project_code;

    $summaryPageTitle = $isSingleProjectSummary
        ? 'Project Summary'
        : $province->name . ' Province Summary';

    $summaryPageDescription = $isSingleProjectSummary
        ? $province->name . ' Project Summary — only the selected project is shown. Its municipalities and districts are expanded below.'
        : $province->name . ' Project Summary — official province project register with exact municipality / barangay beneficiary allocation.';
@endphp

@section('title', $summaryPageTitle)

@section('content')

<x-page-header
    eyebrow="Project Summary"
    :title="$summaryPageTitle"
    :description="$summaryPageDescription"
>
    <x-slot:actions>
        @if($isSingleProjectSummary)
            <a
                href="{{ route('projects.index') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Back to Projects
            </a>

            <a
                href="{{ route('project-summary.province', $province) }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-[#063b86] bg-white px-4 text-sm font-semibold text-[#063b86] hover:bg-blue-50"
            >
                {{ $province->name }} Province Summary
            </a>
        @else
            <a
                href="{{ route('project-summary.index') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                All Provinces
            </a>
        @endif
    </x-slot:actions>
</x-page-header>

{{-- =========================================================
    TOP: Province Project Register
========================================================== --}}

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">

        <div>
            <h2 class="text-sm font-semibold text-slate-900">
                {{ $isSingleProjectSummary ? 'Selected Project' : 'Project Summary' }}
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                @if($isSingleProjectSummary)
                    Only this project is included in this report.
                    @if($sourceProjectCode)
                        Official Project Code: <b>{{ $sourceProjectCode }}</b>.
                    @endif
                @else
                    Official project data for {{ $province->name }}.
                @endif
            </p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row">

            @unless($isSingleProjectSummary)
                <div class="relative">
                    <input
                        id="projectRegisterSearch"
                        type="search"
                        placeholder="Search project code, proponent or title..."
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-3 pr-9 text-sm sm:w-80"
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
            @endunless

            <button
                type="button"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                onclick="window.print()"
            >
                Print / Export
            </button>

        </div>

    </div>

    <div class="overflow-x-auto">

        <table class="w-full min-w-[1500px] table-fixed text-[10px] xl:min-w-0">

            <colgroup>
                <col class="w-[9%]">
                <col class="w-[10%]">
                <col class="w-[17%]">
                <col class="w-[8%]">
                <col class="w-[11%]">
                <col class="w-[7%]">
                <col class="w-[10%]">
                <col class="w-[8%]">
                <col class="w-[10%]">
                <col class="w-[10%]">
            </colgroup>

            <thead class="bg-[#f6f8fb] text-[#173b70]">

                <tr>
                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold">
                        Project Code
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold">
                        Proponent
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-left font-bold">
                        Project Title
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold">
                        Total Benefs.
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold">
                        Total Amount Assisted
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold">
                        No. of Days
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold leading-4">
                        Wages
                        <br>
                        <span class="font-medium text-slate-500">
                            (415.00/435.00)
                        </span>
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold leading-4">
                        PPEs
                        <br>
                        <span class="font-medium text-slate-500">
                            (350.00)
                        </span>
                    </th>

                    <th class="border-b border-r border-slate-200 px-2 py-3 text-center font-bold leading-4">
                        Micro-Insurance
                        <br>
                        <span class="font-medium text-slate-500">
                            (50.00)
                        </span>
                    </th>

                    <th class="border-b border-slate-200 px-2 py-3 text-center font-bold">
                        Amount Assisted
                    </th>
                </tr>

            </thead>

            <tbody
                id="projectRegisterBody"
                class="divide-y divide-slate-100"
            >

                @forelse($projects as $project)

                    @php
                        $componentAmount =
                            (float) $project->wages_total
                            + (float) $project->ppe_total
                            + (float) $project->insurance_total;
                    @endphp

                    <tr
                        class="js-project-register-row hover:bg-slate-50"
                        data-search="{{ mb_strtolower(
                            collect([
                                $project->approval?->project_code,
                                $project->partner,
                                $project->project_title,
                            ])
                            ->filter()
                            ->implode(' ')
                        ) }}"
                    >

                        <td class="border-r border-slate-100 px-2 py-3 text-center font-semibold text-slate-700">
                            {{ $project->approval?->project_code ?: '—' }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-center text-slate-600">
                            {{ $project->partner ?: '—' }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-left font-semibold text-slate-800">
                            {{ $project->project_title }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-center font-semibold text-slate-700">
                            {{ number_format($project->beneficiaries_total) }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-right font-semibold text-slate-700">
                            ₱{{ number_format((float) $project->total_project_cost, 2) }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-center text-slate-700">
                            {{ number_format($project->number_of_days) }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-right text-slate-700">
                            ₱{{ number_format((float) $project->wages_total, 2) }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-right text-slate-700">
                            ₱{{ number_format((float) $project->ppe_total, 2) }}
                        </td>

                        <td class="border-r border-slate-100 px-2 py-3 text-right text-slate-700">
                            ₱{{ number_format((float) $project->insurance_total, 2) }}
                        </td>

                        <td class="px-2 py-3 text-right font-bold text-[#063b86]">
                            ₱{{ number_format($componentAmount, 2) }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="10"
                            class="px-5 py-12 text-center text-sm text-slate-400"
                        >
                            No official projects are recorded for {{ $province->name }}.
                        </td>
                    </tr>

                @endforelse

            </tbody>

            @if($projects->isNotEmpty())
                <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-bold text-slate-800">

                    <tr>
                        <td
                            colspan="3"
                            class="border-r border-slate-200 px-2 py-3 text-right"
                        >
                            {{ $isSingleProjectSummary ? 'PROJECT TOTAL' : 'PROVINCE TOTAL' }}
                        </td>

                        <td class="border-r border-slate-200 px-2 py-3 text-center">
                            {{ number_format($provinceStats['beneficiaries']) }}
                        </td>

                        <td class="border-r border-slate-200 px-2 py-3 text-right">
                            ₱{{ number_format($provinceStats['amount_assisted'], 2) }}
                        </td>

                        <td class="border-r border-slate-200 px-2 py-3"></td>

                        <td class="border-r border-slate-200 px-2 py-3 text-right">
                            ₱{{ number_format((float) $projects->sum('wages_total'), 2) }}
                        </td>

                        <td class="border-r border-slate-200 px-2 py-3 text-right">
                            ₱{{ number_format((float) $projects->sum('ppe_total'), 2) }}
                        </td>

                        <td class="border-r border-slate-200 px-2 py-3 text-right">
                            ₱{{ number_format((float) $projects->sum('insurance_total'), 2) }}
                        </td>

                        <td class="px-2 py-3 text-right text-[#063b86]">
                            ₱{{ number_format(
                                (float) $projects->sum('wages_total')
                                + (float) $projects->sum('ppe_total')
                                + (float) $projects->sum('insurance_total'),
                                2
                            ) }}
                        </td>
                    </tr>

                </tfoot>
            @endif

        </table>

    </div>

</section>

{{-- =========================================================
    BOTTOM: Province / Municipality Summary
========================================================== --}}

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 bg-[#fbfcfe] px-5 py-4">

        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">

            <div>
                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-[#063b86]">
                    {{ $isSingleProjectSummary ? 'Project Location Summary' : 'Per Province Summary' }}
                </div>

                <h2 class="mt-1 text-lg font-bold text-slate-950">
                    @if($isSingleProjectSummary)
                        {{ $sourceProjectCode ?: $sourceProject->project_title }} — Municipalities and Districts
                    @else
                        {{ $province->name }} — Municipalities with Project Data
                    @endif
                </h2>

                <p class="mt-1 text-xs leading-5 text-slate-500">
                    @if($isSingleProjectSummary)
                        Only locations attached to this one project are shown, including all covered municipalities across different districts.
                    @else
                        Only districts, municipalities/cities and barangays represented by current project locations are shown.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">

                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                        Districts
                    </div>

                    <div class="mt-1 text-lg font-bold text-slate-900">
                        {{ number_format($provinceStats['district_count']) }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                        Municipalities
                    </div>

                    <div class="mt-1 text-lg font-bold text-slate-900">
                        {{ number_format($provinceStats['municipality_count']) }}
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-center">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                        Barangays
                    </div>

                    <div class="mt-1 text-lg font-bold text-slate-900">
                        {{ number_format($provinceStats['barangay_count']) }}
                    </div>
                </div>

                <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-center">
                    <div class="text-[9px] font-bold uppercase tracking-wide text-blue-600">
                        Beneficiaries
                    </div>

                    <div class="mt-1 text-lg font-bold text-[#063b86]">
                        {{ number_format($provinceStats['beneficiaries']) }}
                    </div>
                </div>

            </div>

        </div>

    </div>

    <div class="border-b border-slate-200 px-5 py-3">

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">

            <div class="relative w-full lg:w-[420px]">
                <input
                    id="provinceHierarchySearch"
                    type="search"
                    placeholder="Search district, municipality, barangay or project..."
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

            <div class="flex gap-2">

                <button
                    id="expandProvinceHierarchy"
                    type="button"
                    class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Expand All
                </button>

                <button
                    id="collapseProvinceHierarchy"
                    type="button"
                    class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Collapse All
                </button>

            </div>

        </div>

    </div>

    <div
        id="provinceHierarchy"
        class="divide-y divide-slate-200"
    >

        @forelse($districts as $districtIndex => $district)

            @php
                $districtSearch =
                    mb_strtolower(
                        collect([
                            $district['name'],
                            ...$district['municipalities']
                                ->pluck('name')
                                ->all(),
                            ...$district['municipalities']
                                ->pluck('barangays')
                                ->flatten(1)
                                ->pluck('name')
                                ->all(),
                            ...$district['municipalities']
                                ->pluck('projects')
                                ->flatten(1)
                                ->pluck('project_title')
                                ->all(),
                        ])
                        ->filter()
                        ->implode(' ')
                    );

                $districtTone =
                    [
                        [
                            'badge' => 'bg-emerald-600',
                            'soft' => 'bg-emerald-50 text-emerald-700',
                            'line' => 'border-emerald-300',
                        ],
                        [
                            'badge' => 'bg-blue-600',
                            'soft' => 'bg-blue-50 text-blue-700',
                            'line' => 'border-blue-300',
                        ],
                        [
                            'badge' => 'bg-amber-500',
                            'soft' => 'bg-amber-50 text-amber-700',
                            'line' => 'border-amber-300',
                        ],
                    ][$districtIndex % 3];
            @endphp

            <details
                class="js-province-district"
                data-search="{{ $districtSearch }}"
                open
            >

                <summary class="cursor-pointer list-none bg-[#fbfcfe] px-5 py-3">

                    <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_130px_150px_170px] md:items-center">

                        <div class="flex min-w-0 items-center gap-3">

                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-xs font-bold text-white {{ $districtTone['badge'] }}">
                                {{ $districtIndex + 1 }}
                            </span>

                            <div>
                                <div class="text-sm font-bold text-slate-900">
                                    {{ $district['name'] }}
                                </div>

                                <div class="mt-0.5 text-[10px] text-slate-400">
                                    {{ number_format($district['municipality_count']) }}
                                    municipality/city ·
                                    {{ number_format($district['project_count']) }}
                                    project(s)
                                </div>
                            </div>

                        </div>

                        <div class="text-right">
                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                Beneficiaries
                            </div>

                            <div class="mt-0.5 text-xs font-bold text-slate-700">
                                {{ number_format($district['beneficiaries']) }}
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                Female
                            </div>

                            <div class="mt-0.5 text-xs font-bold text-slate-700">
                                {{ number_format($district['female_beneficiaries']) }}
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                Amount Assisted
                            </div>

                            <div class="mt-0.5 text-xs font-bold text-[#063b86]">
                                ₱{{ number_format($district['amount_assisted'], 2) }}
                            </div>
                        </div>

                    </div>

                </summary>

                <div class="ml-5 border-l {{ $districtTone['line'] }}">

                    @foreach($district['municipalities'] as $municipality)

                        @php
                            $municipalitySearch =
                                mb_strtolower(
                                    collect([
                                        $municipality['name'],
                                        ...$municipality['barangays']
                                            ->pluck('name')
                                            ->all(),
                                        ...$municipality['projects']
                                            ->pluck('project_title')
                                            ->all(),
                                    ])
                                    ->filter()
                                    ->implode(' ')
                                );
                        @endphp

                        <details
                            class="js-province-municipality border-b border-slate-100 last:border-b-0"
                            data-search="{{ $municipalitySearch }}"
                            open
                        >

                            <summary class="cursor-pointer list-none px-5 py-3 hover:bg-slate-50">

                                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_130px_150px_170px] md:items-center">

                                    <div class="flex min-w-0 items-center gap-3">

                                        <span class="-ml-[21px] w-5 border-t {{ $districtTone['line'] }}"></span>

                                        <svg
                                            class="h-4 w-4 shrink-0 text-slate-500"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path d="M3 21h18"></path>
                                            <path d="M6 21V8l6-4 6 4v13"></path>
                                            <path d="M9 21v-5h6v5"></path>
                                        </svg>

                                        <div>
                                            <div class="text-xs font-bold text-slate-800">
                                                {{ $municipality['name'] }}

                                                @if($municipality['is_city'])
                                                    <span class="ml-1 rounded bg-violet-50 px-1.5 py-0.5 text-[8px] font-bold uppercase text-violet-700">
                                                        City
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="mt-0.5 text-[10px] text-slate-400">
                                                {{ number_format($municipality['project_count']) }}
                                                project(s) ·
                                                {{ number_format($municipality['barangay_count']) }}
                                                barangay(s) with project data
                                            </div>
                                        </div>

                                    </div>

                                    <div class="text-right text-xs font-semibold text-slate-700">
                                        {{ number_format($municipality['beneficiaries']) }}
                                    </div>

                                    <div class="text-right text-xs font-semibold text-slate-700">
                                        {{ number_format($municipality['female_beneficiaries']) }}
                                    </div>

                                    <div class="text-right text-xs font-bold text-[#063b86]">
                                        ₱{{ number_format($municipality['amount_assisted'], 2) }}
                                    </div>

                                </div>

                            </summary>

                            <div class="bg-slate-50/50 px-5 pb-4 pt-1">

                                @if($municipality['barangays']->isNotEmpty())

                                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">

                                        <div class="grid grid-cols-[minmax(180px,1fr)_90px_110px_110px] gap-2 border-b border-slate-200 bg-slate-50 px-3 py-2 text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                            <div>Barangay</div>
                                            <div class="text-right">Projects</div>
                                            <div class="text-right">Beneficiaries</div>
                                            <div class="text-right">Female</div>
                                        </div>

                                        @foreach($municipality['barangays'] as $barangay)

                                            <details
                                                class="js-province-barangay border-b border-slate-100 last:border-b-0"
                                                data-search="{{ mb_strtolower(
                                                    collect([
                                                        $barangay['name'],
                                                        ...$barangay['projects']
                                                            ->pluck('project_title')
                                                            ->all(),
                                                    ])
                                                    ->filter()
                                                    ->implode(' ')
                                                ) }}"
                                            >

                                                <summary class="cursor-pointer list-none px-3 py-2.5 hover:bg-slate-50">

                                                    <div class="grid grid-cols-[minmax(180px,1fr)_90px_110px_110px] items-center gap-2">

                                                        <div class="flex min-w-0 items-center gap-2">
                                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-400"></span>

                                                            <span class="truncate text-[10px] font-semibold text-slate-700">
                                                                {{ $barangay['name'] }}
                                                            </span>
                                                        </div>

                                                        <div class="text-right text-[10px] text-slate-500">
                                                            {{ number_format($barangay['project_count']) }}
                                                        </div>

                                                        <div class="text-right text-[10px] font-bold text-slate-700">
                                                            {{ number_format($barangay['beneficiaries']) }}
                                                        </div>

                                                        <div class="text-right text-[10px] font-semibold text-slate-600">
                                                            {{ number_format($barangay['female_beneficiaries']) }}
                                                        </div>

                                                    </div>

                                                </summary>

                                                <div class="border-t border-slate-100 bg-slate-50 px-3 py-2">

                                                    @foreach($barangay['project_entries'] as $projectEntry)

                                                        @php
                                                            $coveredProject = $projectEntry['project'];
                                                        @endphp

                                                        <div class="flex flex-col gap-2 border-b border-slate-100 py-2 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">

                                                            <div>
                                                                <div class="text-[10px] font-semibold text-slate-700">
                                                                    {{ $coveredProject->approval?->project_code ?: '—' }}
                                                                    ·
                                                                    {{ $coveredProject->project_title }}
                                                                </div>

                                                                <div class="mt-0.5 text-[9px] {{ $projectEntry['is_exact'] ? 'font-semibold text-emerald-600' : 'text-amber-600' }}">
                                                                    {{ $projectEntry['is_exact']
                                                                        ? 'Exact barangay beneficiary allocation'
                                                                        : 'Legacy project-level beneficiary coverage' }}
                                                                </div>
                                                            </div>

                                                            <div class="flex gap-3 text-[10px]">
                                                                <span class="font-semibold text-slate-700">
                                                                    {{ number_format($projectEntry['beneficiaries']) }}
                                                                    benef.
                                                                </span>

                                                                <span class="font-semibold text-slate-500">
                                                                    {{ number_format($projectEntry['female_beneficiaries']) }}
                                                                    female
                                                                </span>
                                                            </div>

                                                        </div>

                                                    @endforeach

                                                </div>

                                            </details>

                                        @endforeach

                                    </div>

                                @else

                                    <div class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-3 text-[10px] text-slate-500">
                                        This municipality is represented by project data, but no barangay-level location was recorded.
                                    </div>

                                @endif

                            </div>

                        </details>

                    @endforeach

                </div>

            </details>

        @empty

            <div class="px-5 py-12 text-center">
                <div class="text-sm font-semibold text-slate-700">
                    No municipality project data found
                </div>

                <p class="mt-1 text-xs text-slate-400">
                    Municipalities appear here once an official project targets them.
                </p>
            </div>

        @endforelse

    </div>

    @if($provinceStats['has_legacy_coverage'])
        <div class="border-t border-amber-200 bg-amber-50 px-5 py-3 text-[10px] leading-5 text-amber-800">
            <b>Barangay beneficiary figures are coverage totals only for legacy projects without exact allocation.</b>
            New projects use saved Total and Female beneficiary counts per barangay. Legacy multi-barangay records remain marked as coverage because their historical project total cannot be divided safely without source data.
        </div>
    @else
        <div class="border-t border-emerald-200 bg-emerald-50 px-5 py-3 text-[10px] leading-5 text-emerald-800">
            <b>Exact beneficiary allocation is complete.</b>
            Barangay figures come from the saved project-location allocation, and municipality / district totals are rolled up from those barangay values.
        </div>
    @endif

</section>

@push('scripts')
<script>
    document.addEventListener(
        'DOMContentLoaded',
        function () {
            const registerSearch =
                document.getElementById(
                    'projectRegisterSearch'
                );

            const registerRows =
                document.querySelectorAll(
                    '.js-project-register-row'
                );

            const hierarchy =
                document.getElementById(
                    'provinceHierarchy'
                );

            const hierarchySearch =
                document.getElementById(
                    'provinceHierarchySearch'
                );

            const expandButton =
                document.getElementById(
                    'expandProvinceHierarchy'
                );

            const collapseButton =
                document.getElementById(
                    'collapseProvinceHierarchy'
                );

            registerSearch?.addEventListener(
                'input',
                function () {
                    const query =
                        registerSearch.value
                            .trim()
                            .toLowerCase();

                    registerRows.forEach(
                        function (row) {
                            row.classList.toggle(
                                'hidden',
                                query !== ''
                                && !(
                                    row.dataset.search
                                    ?? ''
                                ).includes(query)
                            );
                        }
                    );
                }
            );

            function allHierarchyDetails() {
                return hierarchy
                    ? Array.from(
                        hierarchy.querySelectorAll(
                            'details'
                        )
                    )
                    : [];
            }

            expandButton?.addEventListener(
                'click',
                function () {
                    allHierarchyDetails()
                        .forEach(
                            function (detail) {
                                detail.open = true;
                            }
                        );
                }
            );

            collapseButton?.addEventListener(
                'click',
                function () {
                    allHierarchyDetails()
                        .forEach(
                            function (detail) {
                                detail.open = false;
                            }
                        );
                }
            );

            hierarchySearch?.addEventListener(
                'input',
                function () {
                    const query =
                        hierarchySearch.value
                            .trim()
                            .toLowerCase();

                    hierarchy
                        ?.querySelectorAll(
                            '.js-province-district'
                        )
                        .forEach(
                            function (district) {
                                const matches =
                                    query === ''
                                    || (
                                        district.dataset.search
                                        ?? ''
                                    ).includes(query);

                                district.classList.toggle(
                                    'hidden',
                                    !matches
                                );

                                if (
                                    matches
                                    && query !== ''
                                ) {
                                    district.open = true;

                                    district
                                        .querySelectorAll(
                                            'details'
                                        )
                                        .forEach(
                                            function (detail) {
                                                const detailMatches =
                                                    (
                                                        detail.dataset.search
                                                        ?? ''
                                                    ).includes(query);

                                                detail.classList.toggle(
                                                    'hidden',
                                                    !detailMatches
                                                );

                                                if (detailMatches) {
                                                    detail.open = true;
                                                }
                                            }
                                        );
                                } else if (query === '') {
                                    district
                                        .querySelectorAll(
                                            'details'
                                        )
                                        .forEach(
                                            function (detail) {
                                                detail.classList.remove(
                                                    'hidden'
                                                );
                                            }
                                        );
                                }
                            }
                        );
                }
            );
        }
    );
</script>
@endpush

@endsection
