@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

    <div>

        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            Dashboard
        </h1>

        <p class="mt-1 text-sm text-slate-500">

            @if($dashboardMode === 'gip')
                Overview of your project draft encoding activity.
            @else
                Overview of TUPAD program activities and project monitoring.
            @endif

        </p>

    </div>

    <div class="w-fit rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600">
        {{ now()->format('F d, Y') }}
    </div>

</div>

@if($dashboardMode === 'gip')

    {{-- GIP Dashboard --}}

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        @foreach([
            [
                'label' => 'Total Drafts',
                'value' => $totalDrafts,
            ],
            [
                'label' => 'Pending TC Review',
                'value' => $pendingDrafts,
            ],
            [
                'label' => 'Returned for Correction',
                'value' => $returnedDrafts,
            ],
            [
                'label' => 'Confirmed',
                'value' => $confirmedDrafts,
            ],
        ] as $card)

            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                <div class="text-xs font-semibold text-slate-500">
                    {{ $card['label'] }}
                </div>

                <div class="mt-4 text-2xl font-bold text-slate-900">
                    {{ number_format($card['value']) }}
                </div>

            </article>

        @endforeach

    </div>

@else

    {{-- Official Dashboard --}}

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold text-slate-500">
                Total ADLs
            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                {{ number_format($totalAdls) }}
            </div>

            <p class="mt-1 text-xs text-slate-400">
                Active ADL funding records.
            </p>

        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold text-slate-500">
                Official Projects
            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                {{ number_format($totalProjects) }}
            </div>

            <p class="mt-1 text-xs text-slate-400">
                GIP drafts are excluded.
            </p>

        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold text-slate-500">
                Adjusted Grants
            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                ₱{{ number_format($totalAdjustedGrants, 2) }}
            </div>

            <p class="mt-1 text-xs text-slate-400">
                Original grants including re-alignments.
            </p>

        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="text-xs font-semibold text-slate-500">
                Ongoing Implementation
            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                {{ number_format($ongoingProjects) }}
            </div>

            <p class="mt-1 text-xs text-slate-400">
                {{ number_format($completedProjects) }}
                project(s) completed.
            </p>

        </article>

    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-[1.35fr_0.8fr]">

        {{-- Status Overview --}}

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Project Status Overview
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Official projects grouped by current status.
                </p>

            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2">

                @foreach(\App\Enums\ProjectStatus::cases() as $status)

                    @php
                        $count =
                            $statusCounts[$status->value]
                            ?? 0;
                    @endphp

                    <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">

                        <span class="text-xs font-medium text-slate-600">
                            {{ $status->label() }}
                        </span>

                        <span class="text-sm font-bold text-slate-900">
                            {{ number_format($count) }}
                        </span>

                    </div>

                @endforeach

            </div>

        </section>

        {{-- Fund Utilization --}}

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Fund Utilization
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    ADL allocation utilization.
                </p>

            </div>

            <div class="p-5">

                <div class="flex items-center justify-between text-xs text-slate-500">

                    <span>
                        Adjusted Grants
                    </span>

                    <strong class="text-sm text-slate-900">
                        ₱{{ number_format($totalAdjustedGrants, 2) }}
                    </strong>

                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">

                    <div
                        class="h-full rounded-full bg-slate-800"
                        style="width: {{ min(100, $utilizationPercent) }}%;"
                    ></div>

                </div>

                <div class="mt-2 text-right text-xs font-semibold text-slate-500">
                    {{ number_format($utilizationPercent, 1) }}%
                </div>

                <div class="mt-5 divide-y divide-slate-100">

                    <div class="flex items-center justify-between py-3 text-xs text-slate-500">

                        <span>
                            Allocated
                        </span>

                        <strong class="text-slate-800">
                            ₱{{ number_format($totalAllocated, 2) }}
                        </strong>

                    </div>

                    <div class="flex items-center justify-between py-3 text-xs text-slate-500">

                        <span>
                            Remaining
                        </span>

                        <strong class="text-slate-800">
                            ₱{{ number_format($remainingGrants, 2) }}
                        </strong>

                    </div>

                </div>

            </div>

        </section>

    </div>

@endif

{{-- Recent Activity --}}

<section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Recent Activity
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Recent recorded actions in the system.
        </p>

    </div>

    <div class="overflow-x-auto">

        <table class="min-w-full">

            <thead class="bg-slate-50">

                <tr>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Date & Time
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        User
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Module
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Activity
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse($recentActivity as $activity)

                    <tr>

                        <td class="px-5 py-4 text-xs text-slate-500">
                            {{ $activity->performed_at->format('M d, Y g:i A') }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-700">
                            {{ $activity->user?->name ?? 'System' }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $activity->module }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ ucfirst($activity->action) }}
                            {{ $activity->record_label }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="4"
                            class="px-5 py-10 text-center text-sm text-slate-400"
                        >
                            No audit activity has been recorded yet.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>

@endsection