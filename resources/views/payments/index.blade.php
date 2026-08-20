@extends('layouts.app')

@section('title', 'Payment Queue')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Payment Queue</h1>
    <p class="mt-1 text-sm text-slate-500">
        Projects ready for payment processing and completed payout records.
    </p>
</div>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">ADL</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Partner</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Project Cost</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Payment</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
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
                                {{ $project->barangay }}, {{ $project->municipality }}
                            </div>
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $project->allocation->adl->adl_number }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $project->allocation->partner }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            ₱{{ number_format($project->total_project_cost, 2) }}
                        </td>

                        <td class="px-5 py-4">
                            @if($project->obligation)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    Recorded
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    Pending
                                </span>
                            @endif
                        </td>

                        <td class="px-5 py-4">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                {{ $project->status === \App\Enums\ProjectStatus::COMPLETED
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-blue-50 text-blue-700' }}">
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
                        <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-400">
                            No projects are currently waiting for payment.
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
