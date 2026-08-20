@extends('layouts.app')

@section('title', 'Project Drafts')

@section('content')

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Project Drafts
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Encode project information for TUPAD Coordinator review.
            </p>
        </div>

        <a href="{{ route('project-drafts.create') }}"
            class="inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
            New Project Draft
        </a>

    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4">

        <div class="text-sm font-semibold text-blue-800">
            Draft records are not official projects.
        </div>

        <p class="mt-1 text-xs leading-5 text-blue-700">
            These records do not affect official project totals, allocation utilization, reports, or fund balances until
            confirmed by your assigned TUPAD Coordinator.
        </p>

    </div>

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
                            Assigned TC
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Draft Cost
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

                    @forelse($drafts as $draft)
                        <tr>

                            <td class="px-5 py-4">

                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $draft->project_title }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    Updated {{ $draft->updated_at->diffForHumans() }}
                                </div>

                            </td>

                            <td class="px-5 py-4">

                                <div class="text-sm text-slate-700">
                                    {{ $draft->allocation->adl->adl_number }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $draft->allocation->partner }}
                                </div>

                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $draft->assignedTc->name }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                ₱{{ number_format($draft->total_project_cost, 2) }}
                            </td>

                            <td class="px-5 py-4">

                                @php
                                    $statusClasses = match ($draft->status->value) {
                                        'draft' => 'bg-slate-100 text-slate-700',

                                        'pending_tc_review' => 'bg-amber-50 text-amber-700',

                                        'returned_for_correction' => 'bg-red-50 text-red-700',

                                        'confirmed' => 'bg-emerald-50 text-emerald-700',

                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                    {{ $draft->status->label() }}
                                </span>

                            </td>

                            <td class="px-5 py-4 text-right">

                                <a href="{{ route('project-drafts.show', $draft) }}"
                                    class="text-sm font-semibold text-slate-700 hover:text-slate-950">
                                    View
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">
                                No project drafts have been created.
                            </td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <div class="mt-5">
        {{ $drafts->links() }}
    </div>

@endsection
