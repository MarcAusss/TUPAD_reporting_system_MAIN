@extends('layouts.app')

@section('title', $queueTitle)

@section('content')
    <x-page-header eyebrow="Through ACP Workflow" :title="$queueTitle" :description="$queueDescription">
        <x-slot:actions>
            <a href="{{ route('projects.index') }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                View All Projects
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4">
        <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-blue-700">Responsible Account</div>
        <div class="mt-1 text-sm font-bold text-blue-950">{{ $queueOwner }}</div>
        <p class="mt-1 text-xs leading-5 text-blue-800">
            This queue contains only Through ACP projects. Direct Administration records are intentionally excluded.
        </p>
    </section>

    <form method="GET" class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row">
            <label class="sr-only" for="acp-workflow-search">Search Through ACP projects</label>
            <input id="acp-workflow-search" name="q" value="{{ request('q') }}"
                placeholder="Search project, code, province, municipality..."
                class="h-10 flex-1 rounded-lg border border-slate-300 px-3 text-sm">
            <button type="submit" class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Search
            </button>
            @if (request()->filled('q'))
                <a href="{{ url()->current() }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
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
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Location</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Approved Cost</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">ACP Progress</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($projects as $project)
                        @php
                            $liquidation = $project->getAttribute('acp_liquidation_summary') ?? [];
                            $money = fn ($value) => '₱'.number_format((float) $value, 2);
                            $progressLabel = match ($queue) {
                                'payment' => $project->acpPayment ? 'Payment recorded' : 'Payment pending',
                                'check-release' => $project->acpCheckRelease ? 'Check released' : 'Check release pending',
                                'implementation' => $project->implementation
                                    ? ($project->status === \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION ? 'Implementation ongoing' : 'Work period recorded')
                                    : 'Work period pending',
                                'liquidation' => number_format((int) ($liquidation['progress_percent'] ?? 0)).'% liquidated',
                                default => '—',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4 align-top">
                                <div class="font-semibold text-slate-900">{{ $project->project_title }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $project->approval?->project_code ?: 'No project code yet' }}
                                    · {{ $project->allocation?->adl?->adl_number ?: 'No ADL' }}
                                </div>
                            </td>
                            <td class="px-5 py-4 align-top text-sm text-slate-600">
                                {{ collect([$project->barangay, $project->municipality, $project->province])->filter()->implode(', ') ?: '—' }}
                            </td>
                            <td class="px-5 py-4 align-top">
                                <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                    {{ $project->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right align-top text-sm font-semibold text-slate-800">
                                {{ $money($project->total_project_cost) }}
                            </td>
                            <td class="px-5 py-4 text-right align-top">
                                <div class="text-xs font-semibold text-slate-700">{{ $progressLabel }}</div>
                                @if ($queue === 'liquidation')
                                    <div class="mt-1 text-[10px] text-slate-500">
                                        ₱{{ number_format(((int) ($liquidation['liquidated_cents'] ?? 0)) / 100, 2) }} of
                                        ₱{{ number_format(((int) ($liquidation['required_cents'] ?? 0)) / 100, 2) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right align-top">
                                <a href="{{ route($actionRoute, $project) }}"
                                    class="inline-flex h-9 items-center rounded-lg bg-[#063b86] px-3 text-xs font-semibold text-white hover:bg-[#052f6b]">
                                    {{ $actionLabel }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">{{ $emptyMessage }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($projects->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">
                {{ $projects->links() }}
            </div>
        @endif
    </section>
@endsection
