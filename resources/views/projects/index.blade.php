@extends('layouts.app')

@section('title', 'Project Management')

@section('content')

    <x-page-header eyebrow="Project Management" title="Official Projects"
        description="Create, review, and continue official TUPAD projects through their required workflow stages.">
        <x-slot:actions>
            @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                <a href="{{ route('projects.create') }}"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    <span class="text-base leading-none">+</span>
                    Add Project
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Project Registry</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($projects->total()) }} project record(s)
                </p>
            </div>
        </div>

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
                                    {{ $project->partner }}
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

                                <span
                                    class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    {{ $project->status->label() }}
                                </span>

                            </td>

                            <td class="px-5 py-4 text-right">

                                @php
                                    $canOpenProject =
                                        !auth()->user()->isFocal() ||
                                        in_array(
                                            $project->status,
                                            [
                                                \App\Enums\ProjectStatus::FOR_PAYMENT,
                                                \App\Enums\ProjectStatus::COMPLETED,
                                            ],
                                            true,
                                        );
                                @endphp

                                <div class="flex justify-end gap-2">

                                    @if ($canOpenProject)
                                        <a href="{{ route('projects.show', $project) }}"
                                            class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-950">
                                            Open Project
                                        </a>
                                    @endif

                                    <a href="{{ route('projects.summary', $project) }}"
                                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                        title="Open province project summary">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path d="M4 19V9"></path>
                                            <path d="M10 19V5"></path>
                                            <path d="M16 19v-7"></path>
                                            <path d="M22 19H2"></path>
                                        </svg>

                                        Summary
                                    </a>

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state title="No official projects yet"
                                    message="Create the first official TUPAD project when an ADL allocation is ready.">
                                    <x-slot:action>
                                        <a href="{{ route('projects.create') }}"
                                            class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                            Add Project
                                        </a>
                                    </x-slot:action>
                                </x-empty-state>
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
