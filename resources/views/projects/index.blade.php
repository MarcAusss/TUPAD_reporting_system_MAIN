@extends('layouts.app')

@section('title', 'Project Management')

@section('content')
    @php
        $hasFilters = filled($filters['q'])
            || filled($filters['status'])
            || filled($filters['implementation_mode'])
            || filled($filters['province_id'])
            || filled($filters['municipality_id'])
            || filled($filters['fiscal_year']);

        $selectedProvince = $filters['province_id']
            ? $provinces->firstWhere('id', $filters['province_id'])
            : null;
        $selectedMunicipality = $filters['municipality_id']
            ? $municipalities->firstWhere('id', $filters['municipality_id'])
            : null;
        $selectedStatus = $filters['status']
            ? collect($statuses)->first(fn ($status) => $status->value === $filters['status'])
            : null;
        $selectedMode = $filters['implementation_mode']
            ? collect($implementationModes)->first(fn ($mode) => $mode->value === $filters['implementation_mode'])
            : null;
    @endphp

    <x-page-header eyebrow="Project Management" title="Official Projects"
        description="Search, filter, review, and continue official TUPAD projects through their required workflow stages.">
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

    <section class="tupad-filter-panel mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Registry Filters</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Find projects by title, project code, ADL number, partner, location, workflow stage, or implementation mode.
                    </p>
                </div>

                @if ($assignedProvince)
                    <div class="inline-flex w-fit items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 21s6-5.1 6-11a6 6 0 1 0-12 0c0 5.9 6 11 6 11Z"></path>
                            <circle cx="12" cy="10" r="2"></circle>
                        </svg>
                        Assigned Province: {{ $assignedProvince->name }}
                    </div>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('projects.index') }}" class="p-5">
            <div class="grid gap-4 xl:grid-cols-12">
                <div class="xl:col-span-4">
                    <label for="project-registry-search" class="mb-1.5 block text-xs font-semibold text-slate-700">
                        Search
                    </label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.2-3.2"></path>
                        </svg>
                        <input id="project-registry-search" name="q" value="{{ $filters['q'] }}" type="search"
                            maxlength="100" placeholder="Project, code, ADL, partner, location..."
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    </div>
                </div>

                <div class="xl:col-span-2">
                    <label for="project-status-filter" class="mb-1.5 block text-xs font-semibold text-slate-700">Status</label>
                    <select id="project-status-filter" name="status"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="xl:col-span-2">
                    <label for="project-mode-filter" class="mb-1.5 block text-xs font-semibold text-slate-700">Implementation</label>
                    <select id="project-mode-filter" name="implementation_mode"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All modes</option>
                        @foreach ($implementationModes as $mode)
                            <option value="{{ $mode->value }}" @selected($filters['implementation_mode'] === $mode->value)>
                                {{ $mode->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="xl:col-span-2">
                    <label for="project-year-filter" class="mb-1.5 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <select id="project-year-filter" name="fiscal_year"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All years</option>
                        @foreach ($fiscalYears as $year)
                            <option value="{{ $year }}" @selected((int) $filters['fiscal_year'] === (int) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="xl:col-span-2">
                    <label for="project-sort-filter" class="mb-1.5 block text-xs font-semibold text-slate-700">Sort</label>
                    <select id="project-sort-filter" name="sort"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="newest" @selected($filters['sort'] === 'newest')>Newest received</option>
                        <option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest received</option>
                        <option value="title_asc" @selected($filters['sort'] === 'title_asc')>Project title A–Z</option>
                        <option value="title_desc" @selected($filters['sort'] === 'title_desc')>Project title Z–A</option>
                        <option value="beneficiaries_desc" @selected($filters['sort'] === 'beneficiaries_desc')>Most beneficiaries</option>
                        <option value="beneficiaries_asc" @selected($filters['sort'] === 'beneficiaries_asc')>Fewest beneficiaries</option>
                        <option value="cost_desc" @selected($filters['sort'] === 'cost_desc')>Highest project cost</option>
                        <option value="cost_asc" @selected($filters['sort'] === 'cost_asc')>Lowest project cost</option>
                        <option value="updated_desc" @selected($filters['sort'] === 'updated_desc')>Recently updated</option>
                    </select>
                </div>

                @if (! $assignedProvince)
                    <div class="xl:col-span-3">
                        <label for="project-province-filter" class="mb-1.5 block text-xs font-semibold text-slate-700">Province</label>
                        <select id="project-province-filter" name="province_id"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                            <option value="">All Bicol provinces</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->id }}" @selected((int) $filters['province_id'] === (int) $province->id)>
                                    {{ $province->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="{{ $assignedProvince ? 'xl:col-span-6' : 'xl:col-span-5' }}">
                    <label for="project-municipality-filter" class="mb-1.5 block text-xs font-semibold text-slate-700">Municipality / City</label>
                    <select id="project-municipality-filter" name="municipality_id"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All municipalities / cities</option>
                        @foreach ($municipalities as $municipality)
                            <option value="{{ $municipality->id }}" @selected((int) $filters['municipality_id'] === (int) $municipality->id)>
                                {{ $municipality->name }}{{ $assignedProvince ? '' : ' — '.$municipality->province?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2 {{ $assignedProvince ? 'xl:col-span-6' : 'xl:col-span-4' }}">
                    <button type="submit"
                        class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">
                        Apply Filters
                    </button>
                    <a href="{{ route('projects.index') }}"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        @if ($hasFilters)
            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3">
                <span class="mr-1 text-[11px] font-bold uppercase tracking-wide text-slate-500">Active filters</span>

                @if (filled($filters['q']))
                    <a href="{{ route('projects.index', request()->except(['q', 'page'])) }}"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400">
                        Search: “{{ $filters['q'] }}” <span aria-hidden="true">×</span>
                    </a>
                @endif

                @if ($selectedStatus)
                    <a href="{{ route('projects.index', request()->except(['status', 'page'])) }}"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400">
                        {{ $selectedStatus->label() }} <span aria-hidden="true">×</span>
                    </a>
                @endif

                @if ($selectedMode)
                    <a href="{{ route('projects.index', request()->except(['implementation_mode', 'page'])) }}"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400">
                        {{ $selectedMode->label() }} <span aria-hidden="true">×</span>
                    </a>
                @endif

                @if ($selectedProvince && ! $assignedProvince)
                    <a href="{{ route('projects.index', request()->except(['province_id', 'municipality_id', 'page'])) }}"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400">
                        {{ $selectedProvince->name }} <span aria-hidden="true">×</span>
                    </a>
                @endif

                @if ($selectedMunicipality)
                    <a href="{{ route('projects.index', request()->except(['municipality_id', 'page'])) }}"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400">
                        {{ $selectedMunicipality->name }} <span aria-hidden="true">×</span>
                    </a>
                @endif

                @if (filled($filters['fiscal_year']))
                    <a href="{{ route('projects.index', request()->except(['fiscal_year', 'page'])) }}"
                        class="inline-flex items-center gap-1 rounded-full border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:border-slate-400">
                        FY {{ $filters['fiscal_year'] }} <span aria-hidden="true">×</span>
                    </a>
                @endif
            </div>
        @endif
    </section>

    <section class="tupad-table-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Project Registry</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($projects->total()) }} matching project record(s)
                    @if ($hasFilters)
                        after filters
                    @endif
                </p>
            </div>

            <div class="text-xs text-slate-500">
                @if ($projects->total() > 0)
                    Showing {{ number_format($projects->firstItem()) }}–{{ number_format($projects->lastItem()) }}
                    of {{ number_format($projects->total()) }}
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">ADL / Partner</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Location</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Mode</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Beneficiaries</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Project Cost</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($projects as $project)
                        @php
                            $canOpenProject = true;

                            if (auth()->user()->isFocal()) {
                                $allowedStatuses = $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP
                                    ? [
                                        \App\Enums\ProjectStatus::FOR_PAYMENT,
                                        \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                                        \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                                        \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                                        \App\Enums\ProjectStatus::COMPLETED,
                                    ]
                                    : [
                                        \App\Enums\ProjectStatus::FOR_PAYMENT,
                                        \App\Enums\ProjectStatus::COMPLETED,
                                    ];

                                $canOpenProject = in_array($project->status, $allowedStatuses, true);
                            }
                        @endphp

                        <tr class="align-top hover:bg-slate-50/60">
                            <td class="px-5 py-4">
                                <div class="max-w-[260px] text-sm font-semibold text-slate-900">
                                    {{ $project->project_title }}
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-400">
                                    <span>{{ $project->date_received->format('M d, Y') }}</span>
                                    @if ($project->approval?->project_code)
                                        <span class="text-slate-300">•</span>
                                        <span class="font-medium text-slate-500">{{ $project->approval->project_code }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="text-sm font-medium text-slate-700">
                                    {{ $project->allocation?->adl?->adl_number ?? '—' }}
                                </div>
                                <div class="mt-1 max-w-[220px] text-xs text-slate-500">
                                    {{ $project->partner ?: 'No partner recorded' }}
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <div class="max-w-[260px] text-sm text-slate-700">
                                    {{ $project->full_location ?: 'Location not available' }}
                                </div>
                                @if ($project->projectLocations->count() > 1)
                                    <div class="mt-1 text-[11px] font-medium text-blue-700">
                                        {{ $project->projectLocations->count() }} municipality locations
                                    </div>
                                @endif
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                    {{ $project->implementation_mode->label() }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right text-sm tabular-nums text-slate-700">
                                {{ number_format($project->beneficiaries_total) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold tabular-nums text-slate-900">
                                ₱{{ number_format((float) $project->total_project_cost, 2) }}
                            </td>

                            <td class="px-5 py-4">
                                <span class="inline-flex max-w-[180px] rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    {{ $project->status->label() }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right">
                                <div class="flex min-w-[190px] justify-end gap-2">
                                    @if ($canOpenProject)
                                        <a href="{{ route('projects.show', $project) }}"
                                            class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-950">
                                            Open Project
                                        </a>
                                    @endif

                                    <a href="{{ route('projects.summary', $project) }}"
                                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                                        title="Open province project summary">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
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
                            <td colspan="8" class="p-0">
                                @if ($hasFilters)
                                    <x-empty-state title="No projects match these filters"
                                        message="Adjust the search or filters, or reset the registry to view all projects in your authorized scope.">
                                        <x-slot:action>
                                            <a href="{{ route('projects.index') }}"
                                                class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                                Reset Filters
                                            </a>
                                        </x-slot:action>
                                    </x-empty-state>
                                @else
                                    @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                                        <x-empty-state title="No official projects yet"
                                            message="Create the first official TUPAD project when an ADL allocation is ready.">
                                            <x-slot:action>
                                                <a href="{{ route('projects.create') }}"
                                                    class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                                    Add Project
                                                </a>
                                            </x-slot:action>
                                        </x-empty-state>
                                    @else
                                        <x-empty-state title="No official projects yet"
                                            message="No official TUPAD projects are currently available in the registry." />
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($projects->hasPages())
        <div class="mt-5">
            {{ $projects->links() }}
        </div>
    @endif
@endsection
