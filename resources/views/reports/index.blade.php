@extends('layouts.app')

@section('title', 'Reports')

@section('content')

    <x-page-header
        eyebrow="Reporting"
        title="Reports"
        description="Filter, review, export, and print official TUPAD project records."
    />

    {{-- Filters --}}

    <form method="GET" action="{{ route('reports.index') }}"
        class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="mb-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Report Filters
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Narrow the report before exporting or printing.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">

            <div>

                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Province
                </label>

                <select name="province" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                    <option value="">
                        All Provinces
                    </option>

                    @foreach ($provinces as $province)
                        <option value="{{ $province }}" @selected(request('province') === $province)>
                            {{ $province }}
                        </option>
                    @endforeach

                </select>

            </div>

            <div>

                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Municipality
                </label>

                <select name="municipality" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                    <option value="">
                        All Municipalities
                    </option>

                    @foreach ($municipalities as $municipality)
                        <option value="{{ $municipality }}" @selected(request('municipality') === $municipality)>
                            {{ $municipality }}
                        </option>
                    @endforeach

                </select>

            </div>

            <div>

                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Status
                </label>

                <select name="status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                    <option value="">
                        All Statuses
                    </option>

                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach

                </select>

            </div>

            <div>

                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Date From
                </label>

                <input name="date_from" type="date" value="{{ request('date_from') }}"
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

            <div>

                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Date To
                </label>

                <input name="date_to" type="date" value="{{ request('date_to') }}"
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>
            <div>

                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Barangay
                </label>

                <input name="barangay" value="{{ request('barangay') }}" placeholder="All Barangays"
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

            </div>

        </div>

        <div class="mt-4 flex flex-wrap justify-end gap-2">

            <a href="{{ route('reports.index') }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                Reset
            </a>

            <a href="{{ route('reports.export.csv', request()->query()) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Export CSV
            </a>

            <a href="{{ route('reports.print', request()->query()) }}" target="_blank"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Print Report
            </a>

            <button type="submit"
                class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Apply Filters
            </button>

        </div>

    </form>

    {{-- Summary --}}

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        @foreach ([
            [
                'label' => 'Projects',
                'value' => number_format($summary['projects']),
            ],
            [
                'label' => 'Beneficiaries',
                'value' => number_format($summary['beneficiaries']),
            ],
            [
                'label' => 'Female Beneficiaries',
                'value' => number_format($summary['female_beneficiaries']),
            ],
            [
                'label' => 'Wages',
                'value' => '₱' . number_format($summary['wages'], 2),
            ],
            [
                'label' => 'PPE',
                'value' => '₱' . number_format($summary['ppe'], 2),
            ],
            [
                'label' => 'Insurance',
                'value' => '₱' . number_format($summary['insurance'], 2),
            ],
            [
                'label' => 'Project Cost',
                'value' => '₱' . number_format($summary['project_cost'], 2),
            ],
            [
                'label' => 'Completed',
                'value' => number_format($summary['completed']),
            ],
        ] as $item)
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

                <div class="text-xs font-semibold text-slate-500">
                    {{ $item['label'] }}
                </div>

                <div class="mt-3 text-xl font-bold text-slate-900">
                    {{ $item['value'] }}
                </div>

            </article>
        @endforeach

    </div>

    {{-- Table --}}

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Report Results</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Export and print actions use the currently applied filters.
                </p>
            </div>

            <div class="text-[11px] font-semibold text-slate-400">
                {{ number_format($projects->total()) }} project record(s)
            </div>
        </div>

        <div class="tupad-data-scroll overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Project
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Location
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            ADL / Partner
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Beneficiaries
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Cost
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Registered
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Status
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Action
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($projects as $project)
                        <tr>

                            <td class="px-5 py-4">

                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $project->project_title }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">

                                    @if ($project->approval)
                                        {{ $project->approval->project_code }}
                                    @else
                                        No Project Code
                                    @endif

                                </div>

                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $project->full_location }}
                            </td>

                            <td class="px-5 py-4">

                                <div class="text-sm text-slate-700">
                                    {{ $project->allocation->adl->adl_number }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $project->allocation->partner }}
                                </div>

                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-700">
                                {{ number_format($project->beneficiaries_total) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                ₱{{ number_format($project->total_project_cost, 2) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-700">

                                <span
                                    class="{{ $project->beneficiaries_count === (int) $project->beneficiaries_total
                                        ? 'text-emerald-700'
                                        : 'text-amber-700' }}">
                                    {{ number_format($project->beneficiaries_count) }}
                                </span>

                            </td>

                            <td class="px-5 py-4">

                                <span
                                    class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    {{ $project->status->label() }}
                                </span>

                            </td>

                            <td class="px-5 py-4 text-right">

                                <a href="{{ route('projects.show', $project) }}"
                                    class="text-sm font-semibold text-slate-700 hover:text-slate-950">
                                    View
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="8" class="px-5 py-12 text-center text-sm text-slate-400">
                                No projects match the selected filters.
                            </td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Geographic Summary
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Aggregated official project totals by province and municipality.
            </p>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Province
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Municipality
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Projects
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Beneficiaries
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Female
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Project Cost
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($geographicSummary as $row)
                        <tr>

                            <td class="px-5 py-4 text-sm font-medium text-slate-700">
                                {{ $row['province'] }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $row['municipality'] }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-700">
                                {{ number_format($row['projects']) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-700">
                                {{ number_format($row['beneficiaries']) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-700">
                                {{ number_format($row['female']) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                ₱{{ number_format($row['project_cost'], 2) }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">
                                No geographic report data available.
                            </td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <div class="mt-5">
        {{ $projects->links() }}
    </div>

@endsection
