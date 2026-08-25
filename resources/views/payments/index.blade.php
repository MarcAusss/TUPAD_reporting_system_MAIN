@extends('layouts.app')

@section('title', 'Payment of Wages Queue')

@section('content')
<x-page-header
    eyebrow="Payment"
    title="Payment of Wages Queue"
    description="Review projects eligible for wage payment or obligation recording and open the project to complete the action."
/>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Payment Processing</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ number_format($projects->total()) }} project record(s) in this queue
            </p>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">ADL</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Partner</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Project Cost</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Payment of Wages</th>
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
                            {{ $project->partner }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            ₱{{ number_format($project->total_project_cost, 2) }}
                        </td>

                        <td class="px-5 py-4">
                            @if($project->obligation)
                                <x-status-badge tone="success">Recorded</x-status-badge>
                            @else
                                <x-status-badge tone="warning">Pending</x-status-badge>
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
                            <a
                                href="{{ route('projects.show', $project) }}"
                                class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-950"
                            >
                                Open Project
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-0">
                            <x-empty-state
                                title="No projects waiting for payment"
                                message="Projects will appear here after post-documentary requirements are completed and the project reaches For Payment."
                            />
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
