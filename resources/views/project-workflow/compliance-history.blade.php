@extends('layouts.app')

@section('title', 'Compliance History')

@section('content')

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

        <div>
            <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
                Project Workflow
            </div>

            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                Compliance History
            </h1>

            <p class="mt-1 max-w-3xl text-sm text-slate-500">
                Every project that ever received a TSSD "For Compliance" finding, whether it is still pending or has
                already complied and moved on. Projects do not drop out of this record once they are resolved.
            </p>
        </div>

        <a href="{{ route('project-workflow.index', ['queue' => 'for-compliance']) }}"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            View Live "For Compliance" Queue
        </a>

    </div>

    <section class="mb-5 grid gap-4 sm:grid-cols-2">

        <div class="rounded-xl border border-amber-300 bg-amber-50/70 px-4 py-3">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[12px] font-bold uppercase tracking-wide text-amber-700">
                        Still Pending
                    </div>
                    <p class="mt-1 text-[11px] leading-4 text-slate-600">
                        Not yet complied. Aging counts up from the TSSD finding.
                    </p>
                </div>
                <div class="text-3xl font-bold leading-none text-slate-950">
                    {{ number_format($pendingCount) }}
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-emerald-300 bg-emerald-50/70 px-4 py-3">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-[12px] font-bold uppercase tracking-wide text-emerald-700">
                        Complied
                    </div>
                    <p class="mt-1 text-[11px] leading-4 text-slate-600">
                        Aging is fixed at how long it took to comply.
                    </p>
                </div>
                <div class="text-3xl font-bold leading-none text-slate-950">
                    {{ number_format($compliedCount) }}
                </div>
            </div>
        </div>

    </section>

    <form method="GET" action="{{ route('project-workflow.compliance-history') }}"
        class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

        <div class="flex flex-col gap-3 sm:flex-row">

            <div class="flex-1">
                <label for="compliance-history-search" class="sr-only">
                    Search compliance history
                </label>

                <input id="compliance-history-search" name="q" value="{{ request('q') }}"
                    placeholder="Search project, code, province, municipality..."
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>

            <select name="status"
                class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm sm:w-52">
                <option value="" @selected($status === '')>All statuses</option>
                <option value="pending" @selected($status === 'pending')>Still pending</option>
                <option value="complied" @selected($status === 'complied')>Complied</option>
            </select>

            <button type="submit"
                class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Search
            </button>

            @if (request()->filled('q') || request()->filled('status'))
                <a href="{{ route('project-workflow.compliance-history') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Clear
                </a>
            @endif

        </div>

    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">ADL / Partner</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">TSSD Finding</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Complied</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Compliance Remarks</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Aging</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Current Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($evaluations as $evaluation)
                        @php
                            $project = $evaluation->project;
                            $complied = $evaluation->isComplied();
                            $agingDays = $evaluation->agingDays();

                            /*
                            |--------------------------------------------------------------------------
                            | Aging Urgency
                            |--------------------------------------------------------------------------
                            |
                            | Still-pending findings are highlighted the longer they stay unresolved,
                            | so the account responsible can act ASAP on the oldest ones first.
                            |
                            */

                            $agingClass = match (true) {
                                $complied => 'text-emerald-700',
                                $agingDays >= 15 => 'text-red-700',
                                $agingDays >= 7 => 'text-amber-700',
                                default => 'text-slate-700',
                            };
                        @endphp

                        <tr class="hover:bg-slate-50/60">

                            <td class="px-5 py-4">
                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $project?->project_title ?? 'Project no longer available' }}
                                </div>
                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $project?->approval?->project_code ?: 'No project code yet' }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="text-sm text-slate-700">
                                    {{ $project?->allocation?->adl?->adl_number ?? '—' }}
                                </div>
                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $project?->partner ?: '—' }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="text-sm text-slate-700">
                                    {{ $evaluation->evaluated_at->format('M d, Y') }}
                                </div>
                                <div class="mt-1 text-xs text-slate-400">
                                    By {{ $evaluation->evaluator?->name ?? '—' }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                @if ($complied)
                                    <span
                                        class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                        {{ $evaluation->compliance_date?->format('M d, Y') ?? '—' }}
                                    </span>
                                    <div class="mt-1 text-xs text-slate-400">
                                        By {{ $evaluation->complier?->name ?? '—' }}
                                    </div>
                                @else
                                    <span
                                        class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                        Not yet complied
                                    </span>
                                @endif
                            </td>

                            <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->compliance_remarks ?: '—' }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <div class="text-sm font-bold {{ $agingClass }}">
                                    {{ number_format($agingDays) }} day(s)
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400">
                                    {{ $complied ? 'To comply' : 'Since TSSD finding' }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                @if ($project)
                                    <span
                                        class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                        {{ $project->status->label() }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="px-5 py-4 text-right">
                                @if ($project)
                                    <a href="{{ route('projects.show', $project) }}"
                                        class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                                        Open Project
                                    </a>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <div class="text-sm font-semibold text-slate-700">
                                    No compliance history found.
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    Projects appear here automatically the moment a TSSD evaluation results in "For
                                    Compliance."
                                </p>
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <div class="mt-5">
        {{ $evaluations->links() }}
    </div>

@endsection
