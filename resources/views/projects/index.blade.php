@extends('layouts.app')

@section('title', 'Project Management')

@section('content')

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Project Management
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            Manage official TUPAD project profiles.
        </p>
    </div>

    <a
        href="{{ route('projects.create') }}"
        class="inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
    >
        Add Project
    </a>

</div>

@if(session('success'))

    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ session('success') }}
    </div>

@endif

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="overflow-x-auto">

        <table class="min-w-full">

            <thead class="bg-slate-50">

                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Project
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        ADL / Partner
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Location
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Beneficiaries
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Project Cost
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

                    <tr class="hover:bg-slate-50/60">

                        <td class="px-5 py-4">

                            <div class="text-sm font-semibold text-slate-900">
                                {{ $project->project_title }}
                            </div>

                            <div class="mt-1 text-xs text-slate-400">
                                {{ $project->date_received->format('M d, Y') }}
                            </div>

                        </td>

                        <td class="px-5 py-4">

                            <div class="text-sm text-slate-700">
                                {{ $project->allocation->adl->adl_number }}
                            </div>

                            <div class="mt-1 text-xs text-slate-400">
                                {{ $project->allocation->partner }}
                            </div>

                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $project->barangay }},
                            {{ $project->municipality }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-700">
                            {{ number_format($project->beneficiaries_total) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            ₱{{ number_format($project->total_project_cost, 2) }}
                        </td>

                        <td class="px-5 py-4">

                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                {{ $project->status->label() }}
                            </span>

                        </td>

                        <td class="px-5 py-4 text-right">

                            <a
                                href="{{ route('projects.show', $project) }}"
                                class="text-sm font-semibold text-slate-700 hover:text-slate-950"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="7"
                            class="px-5 py-12 text-center text-sm text-slate-400"
                        >
                            No official projects have been created.
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