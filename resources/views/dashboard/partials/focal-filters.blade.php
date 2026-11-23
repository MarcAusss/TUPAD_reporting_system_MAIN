{{--
    Focal dashboard filters (App\Services\Dashboards\DashboardFilters).
    Collapsed to a "Filters" button with the applied filters as chips; the button
    shows / hides the filter panel (resources/js/dashboard-filters.js), which also
    loads municipalities for the chosen province and keeps month inside quarter.
--}}
@php
    $filters = $dashboardFilters;
    $options = $dashboardFilterOptions;
    $active = $filters->toArray();
    $count = count($active);

    $select = fn (bool $on): string => 'h-10 w-full rounded-lg border px-3 text-sm shadow-xs outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/20 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400 '
        .($on ? 'border-[#1765d8]/60 bg-[#eaf2ff] font-semibold text-[#063b86]' : 'border-slate-300 bg-white text-slate-800');
    $label = 'mb-1.5 block text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500';

    $chips = array_filter([
        'year' => $filters->year ? 'FY'.$filters->year : null,
        'quarter' => $filters->quarter ? 'Q'.$filters->quarter : null,
        'month' => $filters->month ? $options['months'][$filters->month] : null,
        'province' => $filters->provinceId && ! $filters->provinceLocked ?$options['provinces']->firstWhere('id', $filters->provinceId)?->name : null,
        'municipality' => $filters->municipalityId ? $options['municipalities']->firstWhere('id', $filters->municipalityId)?->name : null,
        'sponsor' => $filters->sponsor ? 'Sponsor: '.$filters->sponsor : null,
        'partner' => $filters->partner ? 'Partner: '.$filters->partner : null,
        'adl' => $filters->adlId ? $options['adls']->firstWhere('id', $filters->adlId)?->adl_number : null,
        'status' => $filters->status ? \App\Enums\ProjectStatus::from($filters->status)->label() : null,
    ]);
@endphp

<section class="mb-5" data-dashboard-filters aria-labelledby="dashboard-filters-title">
    <h2 id="dashboard-filters-title" class="sr-only">Dashboard filters</h2>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" data-filter-toggle aria-expanded="false" aria-controls="dashboard-filter-panel"
            class="group inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 shadow-xs transition hover:border-[#1765d8]/50 hover:text-[#063b86] focus:outline-none focus:ring-2 focus:ring-[#1765d8]/25 aria-expanded:border-[#1765d8]/60 aria-expanded:bg-[#eaf2ff] aria-expanded:text-[#063b86]">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4-2v-4Z"/></svg>
            Filters
            @if ($count > 0)
                <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#063b86] px-1.5 text-[11px] font-bold text-white">{{ $count }}</span>
            @endif
            <svg class="h-3.5 w-3.5 transition-transform group-aria-expanded:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        @if ($chips === [])
            <span class="text-xs text-slate-500">Showing all records</span>
        @else
            @foreach ($chips as $key => $text)
                @php($without = \Illuminate\Support\Arr::except($active, $key === 'province' ? ['province', 'municipality'] : [$key]))
                <a href="{{ route('dashboard', $without) }}"
                    class="group inline-flex h-7 items-center gap-1 rounded-full border border-blue-200 bg-[#eaf2ff] pl-2.5 pr-1.5 text-xs font-semibold text-[#063b86] hover:border-blue-300 hover:bg-blue-100"
                    aria-label="Remove filter: {{ $text }}">
                    {{ $text }}
                    <svg class="h-3.5 w-3.5 opacity-60 group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </a>
            @endforeach
            <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-slate-500 underline-offset-2 hover:text-[#063b86] hover:underline">Clear all</a>
        @endif
    </div>

    <div id="dashboard-filter-panel" data-filter-panel hidden class="tupad-panel mt-2">
        <form method="GET" action="{{ route('dashboard') }}" data-dashboard-filter-form
            data-municipality-url="{{ url('/locations/provinces/__ID__/municipalities') }}">

            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div>
                    <label for="filter-year" class="{{ $label }}">Year</label>
                    <select id="filter-year" name="year" class="{{ $select((bool) $filters->year) }}">
                        <option value="">All years</option>
                        @foreach ($options['years'] as $year)
                            <option value="{{ $year }}" @selected($filters->year === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-quarter" class="{{ $label }}">Quarter</label>
                    <select id="filter-quarter" name="quarter" class="{{ $select((bool) $filters->quarter) }}" data-filter-quarter>
                        <option value="">All quarters</option>
                        @foreach ($options['quarters'] as $value => $text)
                            <option value="{{ $value }}" @selected($filters->quarter === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-month" class="{{ $label }}">Month</label>
                    <select id="filter-month" name="month" class="{{ $select((bool) $filters->month) }}" data-filter-month>
                        <option value="">All months</option>
                        @foreach ($options['months'] as $value => $text)
                            <option value="{{ $value }}" data-quarter="{{ (int) ceil($value / 3) }}" @selected($filters->month === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    @if ($filters->provinceLocked)
                        {{-- TUPAD Coordinator: always their assigned province. --}}
                        <span class="{{ $label }}">Province</span>
                        <div class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-700" title="Your assigned province">
                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                            <span class="truncate">{{ $options['provinces']->firstWhere('id', $filters->provinceId)?->name ?? 'No assigned province' }}</span>
                        </div>
                    @else
                        <label for="filter-province" class="{{ $label }}">Province</label>
                        <select id="filter-province" name="province" class="{{ $select((bool) $filters->provinceId) }}" data-filter-province>
                            <option value="">All provinces</option>
                            @foreach ($options['provinces'] as $province)
                                <option value="{{ $province->id }}" @selected($filters->provinceId === $province->id)>{{ $province->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div>
                    <label for="filter-municipality" class="{{ $label }}">Municipality / City</label>
                    <select id="filter-municipality" name="municipality" class="{{ $select((bool) $filters->municipalityId) }}" data-filter-municipality
                        @disabled(! $filters->provinceId)>
                        <option value="">{{ $filters->provinceId ? 'All municipalities' : 'Select a province first' }}</option>
                        @foreach ($options['municipalities'] as $municipality)
                            <option value="{{ $municipality->id }}" @selected($filters->municipalityId === $municipality->id)>{{ $municipality->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-sponsor" class="{{ $label }}">Fund Sponsor</label>
                    <select id="filter-sponsor" name="sponsor" class="{{ $select((bool) $filters->sponsor) }}">
                        <option value="">All sponsors</option>
                        @foreach ($options['sponsors'] as $sponsor)
                            <option value="{{ $sponsor }}" @selected($filters->sponsor === $sponsor)>{{ $sponsor }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-partner" class="{{ $label }}">Partner</label>
                    <select id="filter-partner" name="partner" class="{{ $select((bool) $filters->partner) }}">
                        <option value="">All partners</option>
                        @foreach ($options['partners'] as $partner)
                            <option value="{{ $partner }}" @selected($filters->partner === $partner)>{{ $partner }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-adl" class="{{ $label }}">ADL</label>
                    <select id="filter-adl" name="adl" class="{{ $select((bool) $filters->adlId) }}">
                        <option value="">All ADLs</option>
                        @foreach ($options['adls'] as $adl)
                            <option value="{{ $adl->id }}" @selected($filters->adlId === $adl->id)>{{ $adl->adl_number }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-status" class="{{ $label }}">Status</label>
                    <select id="filter-status" name="status" class="{{ $select((bool) $filters->status) }}">
                        <option value="">All statuses</option>
                        @foreach ($options['statuses'] as $status)
                            <option value="{{ $status->value }}" @selected($filters->status === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" data-allow-repeat-submit="true"
                        class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d] focus:outline-none focus:ring-2 focus:ring-blue-300">
                        Apply
                    </button>
                    @if ($active !== [])
                        <a href="{{ route('dashboard') }}"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>
                    @endif
                </div>
            </div>

            <p class="border-t border-slate-100 px-5 py-2.5 text-[11px] text-slate-500">
                Quarter, month, municipality and status apply to project figures only. Fund Position uses year, ADL, province, sponsor and partner.
            </p>
        </form>
    </div>
</section>
