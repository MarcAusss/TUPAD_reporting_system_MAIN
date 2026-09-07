@props([
    'project',
    'workspace',
])

@php
    $projectCode = $project->approval?->project_code;

    $canonicalMunicipalities = $project->projectLocations
        ->pluck('municipality.name')
        ->filter()
        ->unique()
        ->values();

    $canonicalProvinces = $project->projectLocations
        ->pluck('province.name')
        ->filter()
        ->unique()
        ->values();

    $locationLabel = $canonicalMunicipalities->isNotEmpty()
        ? $canonicalMunicipalities->take(2)->implode(', ')
            .($canonicalMunicipalities->count() > 2 ? ' +'.($canonicalMunicipalities->count() - 2).' more' : '')
            .($canonicalProvinces->isNotEmpty() ? ', '.$canonicalProvinces->implode(', ') : '')
        : collect([$project->municipality, $project->province])->filter()->implode(', ');


    $quickWorkflowHeaderAction = (auth()->user()->isAdmin() || auth()->user()->isTc())
        && in_array($project->status, [
            \App\Enums\ProjectStatus::ONGOING_PROFILING,
            \App\Enums\ProjectStatus::TSSD_EVALUATION,
            \App\Enums\ProjectStatus::FOR_COMPLIANCE,
            \App\Enums\ProjectStatus::FOR_APPROVAL,
        ], true);
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :tone="$workspace['status_tone']">
                        {{ $project->status->label() }}
                    </x-status-badge>

                    <span class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        {{ $project->implementation_mode->label() }}
                    </span>

                    @if($projectCode)
                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-bold tracking-wide text-slate-700">
                            {{ $projectCode }}
                        </span>
                    @endif
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <div class="text-xs font-semibold text-slate-500">Location</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">
                            {{ $locationLabel ?: 'Not specified' }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-slate-500">Beneficiaries</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">
                            {{ number_format($project->beneficiaries_total) }} total
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-slate-500">Duration</div>
                        <div class="mt-1 text-sm font-semibold text-slate-900">
                            {{ number_format($project->number_of_days) }} day(s)
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-slate-500">Total Project Cost</div>
                        <div class="mt-1 text-sm font-bold text-slate-950">
                            ₱{{ number_format($project->total_project_cost, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full rounded-xl border border-blue-200 bg-blue-50 p-4 xl:max-w-md">
                <div class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">
                    {{ $workspace['action']['eyebrow'] }}
                </div>

                <h2 class="mt-2 text-base font-bold text-blue-950">
                    {{ $workspace['action']['title'] }}
                </h2>

                <p class="mt-1 text-sm leading-6 text-blue-800">
                    {{ $workspace['action']['description'] }}
                </p>

                @if($quickWorkflowHeaderAction)
                    <button
                        type="button"
                        data-quick-workflow-open
                        class="mt-4 inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b] focus:outline-none focus:ring-2 focus:ring-blue-300"
                    >
                        Continue Workflow
                        <span aria-hidden="true" class="ml-2">→</span>
                    </button>
                @elseif($workspace['action']['label'] && $workspace['action']['href'])
                    @if($workspace['action']['external'])
                        <a
                            href="{{ $workspace['action']['href'] }}"
                            class="mt-4 inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
                        >
                            {{ $workspace['action']['label'] }}
                            <span aria-hidden="true" class="ml-2">→</span>
                        </a>
                    @else
                        <a
                            href="{{ request()->fullUrlWithQuery(['workspace' => $workspace['action']['tab']]).'#'.$workspace['action']['anchor'] }}"
                            data-workspace-open-tab="{{ $workspace['action']['tab'] }}"
                            data-workspace-anchor="{{ $workspace['action']['anchor'] }}"
                            class="mt-4 inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
                        >
                            {{ $workspace['action']['label'] }}
                            <span aria-hidden="true" class="ml-2">→</span>
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-5 sm:px-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">
                    Project Progress
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ $workspace['progress_percent'] }}% through the official workflow
                </div>
            </div>

            <div class="text-xs font-semibold text-slate-600">
                Stage {{ $workspace['current_stage_index'] + 1 }} of {{ count($workspace['stages']) }}
            </div>
        </div>

        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
            <div
                class="h-full rounded-full bg-[#063b86] transition-all"
                style="width: {{ $workspace['progress_percent'] }}%"
            ></div>
        </div>

        <ol class="mt-4 grid min-w-[760px] grid-cols-8 gap-2 overflow-x-auto pb-1" aria-label="Project workflow progress">
            @foreach($workspace['stages'] as $index => $stage)
                <li
                    @class([
                        'rounded-lg border px-3 py-3',
                        'border-emerald-200 bg-emerald-50' => $stage['state'] === 'complete',
                        'border-blue-300 bg-blue-50 ring-1 ring-blue-200' => $stage['state'] === 'current',
                        'border-slate-200 bg-white' => $stage['state'] === 'upcoming',
                    ])
                    @if($stage['state'] === 'current') aria-current="step" @endif
                >
                    <div class="flex items-center gap-2">
                        <span
                            @class([
                                'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                'bg-emerald-700 text-white' => $stage['state'] === 'complete',
                                'bg-[#063b86] text-white' => $stage['state'] === 'current',
                                'bg-slate-100 text-slate-500' => $stage['state'] === 'upcoming',
                            ])
                        >
                            {{ $stage['state'] === 'complete' ? '✓' : $index + 1 }}
                        </span>

                        <span
                            @class([
                                'text-xs font-semibold leading-4',
                                'text-emerald-900' => $stage['state'] === 'complete',
                                'text-blue-950' => $stage['state'] === 'current',
                                'text-slate-500' => $stage['state'] === 'upcoming',
                            ])
                        >
                            {{ $stage['label'] }}
                        </span>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <nav class="overflow-x-auto px-3 py-2" aria-label="Project workspace sections">
        <div class="flex min-w-max items-center gap-1" role="tablist">
            @foreach($workspace['tabs'] as $tab)
                <a
                    href="{{ request()->fullUrlWithQuery(['workspace' => $tab['key']]) }}"
                    role="tab"
                    data-workspace-tab-target="{{ $tab['key'] }}"
                    aria-selected="{{ $workspace['default_tab'] === $tab['key'] ? 'true' : 'false' }}"
                    @class([
                        'rounded-lg px-4 py-2.5 text-sm font-semibold transition',
                        'bg-[#063b86] text-white' => $workspace['default_tab'] === $tab['key'],
                        'text-slate-600 hover:bg-slate-100 hover:text-slate-950' => $workspace['default_tab'] !== $tab['key'],
                    ])
                >
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
</section>
