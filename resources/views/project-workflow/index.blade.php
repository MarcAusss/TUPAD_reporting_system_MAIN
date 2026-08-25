@extends('layouts.app')

@section('title', $queueTitle)

@section('content')

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

    <div>
        <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
            Project Workflow
        </div>

        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
            {{ $queueTitle }}
        </h1>

        <p class="mt-1 max-w-3xl text-sm text-slate-500">
            {{ $queueDescription }}
        </p>
    </div>

    <a
        href="{{ route('projects.index') }}"
        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
    >
        View All Projects
    </a>

</div>

<section class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">

    <div class="text-xs font-bold uppercase tracking-[0.1em] text-blue-700">
        Responsible Account
    </div>

    <div class="mt-1 text-sm font-semibold text-blue-950">
        {{ $queueOwner }}
    </div>

    <p class="mt-1 text-xs leading-5 text-blue-700">
        This queue is a filtered view of official project records.
        Opening a project takes you to the existing Project Detail workflow.
    </p>

</section>

<form
    method="GET"
    action="{{ route('project-workflow.index', ['queue' => $queue]) }}"
    class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
>

    <div class="flex flex-col gap-3 sm:flex-row">

        <div class="flex-1">

            <label
                for="workflow-search"
                class="sr-only"
            >
                Search workflow projects
            </label>

            <input
                id="workflow-search"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search project, code, province, municipality..."
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >

        </div>

        <button
            type="submit"
            class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800"
        >
            Search
        </button>

        @if(request()->filled('q'))

            <a
                href="{{ route('project-workflow.index', ['queue' => $queue]) }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Clear
            </a>

        @endif

    </div>

</form>

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
                                {{ $project->project_code ?: 'No project code yet' }}
                            </div>

                        </td>

                        <td class="px-5 py-4">

                            <div class="text-sm text-slate-700">
                                {{ $project->allocation->adl->adl_number }}
                            </div>

                            <div class="mt-1 text-xs text-slate-400">
                                {{ $project->partner ?: '—' }}
                            </div>

                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ collect([
                                $project->barangay,
                                $project->municipality,
                                $project->province,
                            ])->filter()->implode(', ') }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-700">
                            {{ number_format($project->beneficiaries_total) }}
                        </td>

                        <td class="px-5 py-4">

                            <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                {{ $project->status->label() }}
                            </span>

                        </td>

                        <td class="px-5 py-4 text-right">

                            <a
                                href="{{ route('projects.show', $project) }}"
                                class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800"
                            >
                                Open Project
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            class="px-5 py-12 text-center"
                        >

                            <div class="text-sm font-semibold text-slate-700">
                                {{ $emptyMessage }}
                            </div>

                            <p class="mt-1 text-xs text-slate-400">
                                Projects will appear here automatically when they reach this workflow stage.
                            </p>

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
