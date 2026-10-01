{{-- Project Location Coverage --}}

@if ($project->projectLocations->isNotEmpty())

    <section data-workspace-panel="overview"
        class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Project Location Coverage</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Selected district, municipality/city, and barangay target areas.
                    </p>
                </div>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                {{ $project->projectLocations->count() }} {{ \Illuminate\Support\Str::plural('location', $project->projectLocations->count()) }}
                · {{ $project->projectLocations->sum(fn ($location) => $location->barangays->count()) }} barangays
            </span>
        </div>

        <div class="grid gap-3 p-5 lg:grid-cols-2">

            @foreach ($project->projectLocations as $location)
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-widest text-teal-700">
                                {{ $location->district }}
                            </div>
                            <div class="mt-0.5 text-sm font-semibold text-slate-900">
                                {{ $location->municipality->name }}
                            </div>
                        </div>

                        <span class="rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-700">
                            {{ $location->barangays->count() }} brgy
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($location->barangays as $barangay)
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                                {{ $barangay->name }}
                                @if ($barangay->pivot?->beneficiaries_total !== null)
                                    <span class="rounded bg-white px-1.5 text-[10px] font-bold text-teal-700 ring-1 ring-teal-100">
                                        {{ number_format((int) $barangay->pivot->beneficiaries_total) }}
                                    </span>
                                @endif
                            </span>
                        @endforeach
                    </div>

                </div>
            @endforeach

        </div>

    </section>

@endif
