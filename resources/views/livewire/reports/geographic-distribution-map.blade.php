@php
    $mapLevel = $mapPayload['map_level'] ?? 'region';
    $isRegionView = $mapLevel === 'region';
    $isProvinceScope = in_array($mapLevel, ['province', 'municipality'], true);
    $isMunicipalityView = $mapLevel === 'municipality';
    $selectedProvince = $mapPayload['selected_province'] ?? null;
    $selectedMunicipality = $mapPayload['selected_municipality'] ?? null;
    $areaRows = $mapPayload['areas'] ?? [];
    $areaLabel = $isMunicipalityView ? 'Barangay' : ($isProvinceScope ? 'Municipality / City' : 'Province');
    $mapHeading = $isProvinceScope
        ? strtoupper(($selectedProvince['name'] ?? 'Selected Province') . ' MAP')
        : 'BICOL REGION MAP';
    $metric = $mapPayload['metric'] ?? ['key' => 'beneficiaries', 'label' => 'Beneficiaries', 'description' => 'Exact geographically allocated beneficiaries'];
    $metricLabel = $metric['label'] ?? 'Beneficiaries';
    $metricKey = $metric['key'] ?? 'beneficiaries';
    $chartHeading = $isMunicipalityView
        ? strtoupper(($selectedMunicipality['name'] ?? 'Selected Municipality') . ' — ' . $metricLabel . ' BY BARANGAY')
        : strtoupper($metricLabel . ' BY ' . ($isProvinceScope ? 'MUNICIPALITY / CITY' : 'PROVINCE'));
    $metricSummary = $mapPayload['metric_summary'] ?? ['display' => '0', 'label' => $metricLabel];
    $hasMetricValues = (bool) data_get($mapPayload, 'empty_state.has_values', false);
    $allocationAvailable = (bool) ($mapPayload['summary']['allocation_available'] ?? false);
    $canViewRegion = (bool) data_get($mapPayload, 'viewer_scope.can_view_region', true);
    $isProvinceLockedViewer = ! $canViewRegion;
    $filtersActive = filled($fiscalYear) || filled($reportingPeriod) || filled($status) || filled($implementationMode);
    $legendColors = ['#dbeafe', '#bfdbfe', '#93c5fd', '#3b82f6', '#063b86'];
@endphp

<div class="mb-5" data-mapping-phase="2" x-data
    x-on:tupad-map-select-province.window="$wire.selectProvince($event.detail.provinceId)"
    x-on:tupad-map-select-municipality.window="$wire.selectMunicipality($event.detail.municipalityId)">
    <section class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div wire:loading.delay.longer wire:target="fiscalYear,reportingPeriod,status,implementationMode,mapMetric,clearFilters,selectProvince,selectMunicipality,showProvince,showRegion"
            class="absolute inset-0 z-[900] flex items-start justify-center bg-white/55 pt-24 backdrop-blur-[1px]">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-[#17325c] shadow-sm">
                Updating geographic data…
            </div>
        </div>

        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#1765d8]">Interactive Geographic Mapping</div>
                <h2 class="mt-1 text-lg font-extrabold tracking-tight text-[#0d2449]">TUPAD Distribution Map</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    @if ($isMunicipalityView)
                        {{ $selectedProvince['name'] }} → {{ $selectedMunicipality['name'] }}. The municipality remains selected while the graph and map labels show its barangay breakdown.
                    @elseif ($isProvinceScope)
                        @if ($isProvinceLockedViewer)
                            Assigned province: {{ $selectedProvince['name'] }} → Municipality / City. Your geographic scope is locked to this province.
                        @else
                            {{ $selectedProvince['name'] }} → Municipality / City. Municipality labels are visible; click an area for its barangay breakdown.
                        @endif
                    @else
                        Bicol Region → Province. Hover a province for current TUPAD statistics, then click it to view its municipalities and cities.
                    @endif
                </p>
            </div>

            <a href="{{ route('reports.export.pdf', $exportQuery) }}"
                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#063b86] px-4 text-xs font-bold text-white hover:bg-[#052f6d]">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 3v12"></path><path d="m7 10 5 5 5-5"></path><path d="M5 21h14"></path>
                </svg>
                Export Report
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-5 py-3 text-[11px] font-semibold text-slate-500" aria-label="Geographic breadcrumb">
            @foreach ($mapPayload['breadcrumb'] as $crumb)
                @if (! $loop->first)
                    <svg class="h-3 w-3 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>
                @endif

                @if (($crumb['level'] ?? null) === 'region' && ! $isRegionView && $canViewRegion)
                    <button type="button" wire:click="showRegion" class="font-bold text-[#063b86] hover:underline">{{ $crumb['label'] }}</button>
                @elseif (($crumb['level'] ?? null) === 'province' && $isMunicipalityView)
                    <button type="button" wire:click="showProvince" class="font-bold text-[#063b86] hover:underline">{{ $crumb['label'] }}</button>
                @else
                    <span class="{{ $loop->last ? 'font-extrabold text-slate-800' : '' }}">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </div>

        <div class="border-b border-slate-200 bg-[#f8fafc] p-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Fiscal Year</span>
                    <input type="number" min="2000" max="2100" placeholder="All fiscal years"
                        wire:model.blur="fiscalYear"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Period</span>
                    <select wire:model.live="reportingPeriod" @disabled(! filled($fiscalYear))
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                        <option value="">Whole fiscal year</option>
                        @foreach ($reportingPeriods as $periodValue => $periodLabel)
                            <option value="{{ $periodValue }}">{{ $periodLabel }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Map By</span>
                    <select wire:model.live="mapMetric"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-extrabold text-[#063b86]">
                        @foreach (($mapPayload['metric_options'] ?? []) as $option)
                            <option value="{{ $option['key'] }}" @disabled(! $option['available'])>
                                {{ $option['label'] }}{{ ! $option['available'] ? ' — unavailable here' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div>
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Province</span>
                    <div class="flex h-10 w-full items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                        {{ $isProvinceScope ? ($selectedProvince['name'] ?? 'Selected Province') : 'All Bicol Provinces' }}

                        @if ($isProvinceLockedViewer)
                            <span class="ml-1 text-[9px] font-extrabold uppercase tracking-wide text-[#1765d8]">Assigned</span>
                        @endif
                    </div>
                </div>

                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Status</span>
                    <select wire:model.live="status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $projectStatus)
                            <option value="{{ $projectStatus->value }}">{{ $projectStatus->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1.5 block text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-500">Implementation</span>
                    <select wire:model.live="implementationMode" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700">
                        <option value="">All modes</option>
                        @foreach ($implementationModes as $mode)
                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[10px] leading-4 text-slate-500">
                    {{ $metric['description'] }}.
                    @if (! $isRegionView)
                        Allocation is intentionally unavailable below province geography.
                    @endif
                </p>
                @if ($filtersActive)
                    <button type="button" wire:click="clearFilters"
                        class="inline-flex h-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[10px] font-bold text-slate-600 hover:bg-slate-50">
                        Clear report filters
                    </button>
                @endif
            </div>
        </div>

        @error('fiscalYear')<div class="border-b border-red-100 bg-red-50 px-5 py-2 text-xs font-semibold text-red-700">{{ $message }}</div>@enderror
        @error('reportingPeriod')<div class="border-b border-red-100 bg-red-50 px-5 py-2 text-xs font-semibold text-red-700">{{ $message }}</div>@enderror
        @error('mapMetric')<div class="border-b border-amber-100 bg-amber-50 px-5 py-2 text-xs font-semibold text-amber-800">{{ $message }}</div>@enderror
        @error('status')<div class="border-b border-red-100 bg-red-50 px-5 py-2 text-xs font-semibold text-red-700">{{ $message }}</div>@enderror
        @error('implementationMode')<div class="border-b border-red-100 bg-red-50 px-5 py-2 text-xs font-semibold text-red-700">{{ $message }}</div>@enderror

        @if ($mapPayload['metric_notice'] ?? null)
            <div class="border-b border-blue-100 bg-blue-50 px-5 py-2 text-xs font-semibold text-[#063b86]">
                {{ $mapPayload['metric_notice'] }}
            </div>
        @endif

        <div class="grid gap-0 xl:grid-cols-[minmax(0,1.45fr)_minmax(390px,0.85fr)]">
            <article class="border-b border-slate-200 xl:border-b-0 xl:border-r">
                <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">{{ $mapHeading }}</h3>
                        <p class="mt-1 text-[11px] text-slate-500">
                            @if ($isMunicipalityView)
                                {{ $selectedMunicipality['name'] }} is persistently highlighted. Municipality labels remain visible and barangay labels are loaded only for the selected municipality.
                            @elseif ($isProvinceScope)
                                Hover a municipality/city for the selected {{ strtolower($metricLabel) }} metric and project-status counts. Click it to show barangay data; financial allocation is not divided across municipality polygons.
                            @else
                                Hover a province for its TUPAD summary. Choropleth intensity follows {{ strtolower($metricLabel) }}; click a province to drill down.
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isMunicipalityView)
                            <button type="button" wire:click="showProvince"
                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 text-[10px] font-bold text-slate-700 hover:bg-slate-50">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"></path></svg>
                                Back to Municipalities
                            </button>
                        @elseif ($isProvinceScope && $canViewRegion)
                            <button type="button" wire:click="showRegion"
                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 text-[10px] font-bold text-slate-700 hover:bg-slate-50">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"></path></svg>
                                Back to Region
                            </button>
                        @elseif ($isProvinceScope && $isProvinceLockedViewer)
                            <span class="inline-flex h-8 items-center rounded-lg border border-blue-100 bg-blue-50 px-2.5 text-[10px] font-extrabold uppercase tracking-wide text-[#063b86]">
                                Assigned Province Scope
                            </span>
                        @endif

                        <div class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1 text-[10px] font-bold">
                            @if ($isRegionView)
                                <span class="rounded-md bg-[#063b86] px-2.5 py-1.5 text-white">Region View</span>
                                <span class="px-2.5 py-1.5 text-slate-400">Province View</span>
                                <span class="px-2.5 py-1.5 text-slate-400">Barangay Data</span>
                            @elseif ($isMunicipalityView)
                                @if ($canViewRegion)
                                    <button type="button" wire:click="showRegion" class="rounded-md px-2.5 py-1.5 text-[#063b86] hover:bg-white">Region View</button>
                                @endif
                                <button type="button" wire:click="showProvince" class="rounded-md px-2.5 py-1.5 text-[#063b86] hover:bg-white">Province View</button>
                                <span class="rounded-md bg-[#063b86] px-2.5 py-1.5 text-white">Barangay Data</span>
                            @else
                                @if ($canViewRegion)
                                    <button type="button" wire:click="showRegion" class="rounded-md px-2.5 py-1.5 text-[#063b86] hover:bg-white">Region View</button>
                                @endif
                                <span class="rounded-md bg-[#063b86] px-2.5 py-1.5 text-white">Province View</span>
                                <span class="px-2.5 py-1.5 text-slate-400">Barangay Data</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if (! $mapPayload['boundary']['ready'])
                    <div class="mx-5 mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                        {{ $isProvinceScope ? 'Municipality/city' : 'Province' }} GeoJSON has not been published for this view. Run
                        <code class="font-bold">php artisan tupad:mapping-sync-boundaries</code>, then refresh this page.
                    </div>
                @endif

                @if ($isMunicipalityView && ! ($mapPayload['label_boundary']['ready'] ?? false))
                    <div class="mx-5 mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                        Barangay data is available, but geographic barangay labels have not been generated yet. Run
                        <code class="font-bold">php artisan tupad:mapping-sync-barangay-labels</code>, then refresh this page.
                    </div>
                @endif

                @if (! $hasMetricValues)
                    <div class="mx-5 mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                        {{ data_get($mapPayload, 'empty_state.message', 'No geographic values matched the current filters.') }}
                        Administrative boundaries remain available for navigation.
                    </div>
                @endif

                <div class="relative">
                    <div
                        data-tupad-region-map
                        data-geojson-url="{{ $mapPayload['boundary']['url'] }}"
                        class="relative h-[480px] min-h-[420px] bg-[#f8fbff] sm:h-[520px]"
                        wire:ignore>
                        <div data-map-canvas class="h-full w-full" aria-label="Interactive Bicol administrative map"></div>
                        <div data-map-error class="pointer-events-none absolute inset-x-5 top-5 z-[500] hidden rounded-lg border border-red-200 bg-white/95 px-4 py-3 text-xs font-semibold text-red-700 shadow-sm"></div>
                        <button type="button" data-map-home
                            class="absolute bottom-4 right-4 z-[600] inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-[10px] font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 9-8 9 8"></path><path d="M5 10v10h14V10"></path></svg>
                            Fit {{ $isProvinceScope ? 'Province' : 'Region' }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap items-start gap-2 text-[9px] font-semibold text-slate-500">
                        @foreach (($mapPayload['legend'] ?? []) as $index => $band)
                            <span class="inline-flex flex-col gap-1">
                                <span class="h-3 w-12 rounded-sm border border-slate-200" style="background: {{ $legendColors[$index] ?? '#063b86' }}"></span>
                                <span class="max-w-20 tabular-nums text-slate-400">{{ $band['label'] }}</span>
                            </span>
                        @endforeach
                    </div>
                    <div class="max-w-md text-[10px] leading-4 text-slate-400 lg:text-right">
                        @if ($isMunicipalityView)
                            Municipality polygon color uses province-wide {{ strtolower($metricLabel) }} while the chart ranks barangays in {{ $selectedMunicipality['name'] }}.
                        @else
                            Color intensity = {{ strtolower($metricLabel) }}. Darker areas have higher values within the current map scope.
                        @endif
                    </div>
                </div>
            </article>

            <article class="min-w-0 bg-white">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-extrabold text-slate-900">{{ $chartHeading }}</h3>
                    <p class="mt-1 text-[11px] text-slate-500">
                        {{ $isMunicipalityView ? 'Selected-municipality barangays ranked by ' . strtolower($metricLabel) . '.' : 'Same filtered geographic dataset as the choropleth, ranked by ' . strtolower($metricLabel) . '.' }}
                    </p>
                </div>

                <div class="p-5">
                    <div class="h-[360px]" wire:ignore>
                        <canvas data-map-chart aria-label="Horizontal {{ strtolower($metricLabel) }} ranking chart"></canvas>
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <div class="flex items-center justify-between gap-3 text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-400">
                            <span>{{ $areaLabel }} ranking</span>
                            <span class="text-right">{{ $metricSummary['display'] }} {{ strtolower($metricSummary['label']) }}</span>
                        </div>
                        <div class="mt-3 space-y-2.5">
                            @if ($hasMetricValues)
                                @foreach (array_slice($areaRows, 0, 12) as $area)
                                    <div class="grid grid-cols-[24px_minmax(0,1fr)_auto] items-center gap-2 text-[11px]">
                                        <span class="font-extrabold text-slate-400">{{ $loop->iteration }}</span>
                                        <span class="truncate font-semibold text-slate-700">{{ $area['name'] }}</span>
                                        <span class="font-extrabold tabular-nums text-[#0d2449]">{{ $area['value_display'] ?? number_format((int) ($area['value'] ?? 0)) }}</span>
                                    </div>
                                @endforeach
                            @else
                                <div class="rounded-lg bg-slate-50 px-3 py-4 text-center text-[11px] leading-5 text-slate-500">
                                    {{ data_get($mapPayload, 'empty_state.message', 'No mapped values matched the current filters.') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div class="grid border-t border-slate-200 sm:grid-cols-2 xl:grid-cols-5">
            @php
                $projectHint = $isMunicipalityView
                    ? 'Unique selected-municipality projects'
                    : ($isProvinceScope ? 'Unique selected-province projects' : 'Unique regional projects');
                $allocationValue = $allocationAvailable
                    ? '₱' . number_format(((int) ($mapPayload['summary']['allocation_cents'] ?? 0)) / 100, 2)
                    : 'Not split';
                $allocationHint = $allocationAvailable
                    ? ($isProvinceScope ? 'Selected-province cohort; not split by municipality' : 'Existing fund-status semantics')
                    : 'No authoritative municipality/barangay financial split';
                $cards = [
                    ['label' => 'Mapped Beneficiaries', 'value' => number_format((int) $mapPayload['summary']['beneficiaries']), 'hint' => 'Exact geographic allocation'],
                    ['label' => 'Total Projects', 'value' => number_format((int) $mapPayload['summary']['projects']), 'hint' => $projectHint],
                    ['label' => 'Ongoing Projects', 'value' => number_format((int) $mapPayload['summary']['ongoing_projects']), 'hint' => 'Ongoing implementation'],
                    ['label' => 'Completed Projects', 'value' => number_format((int) $mapPayload['summary']['completed_projects']), 'hint' => 'Completed status'],
                    ['label' => 'Total Allocation', 'value' => $allocationValue, 'hint' => $allocationHint],
                ];
            @endphp
            @foreach ($cards as $card)
                <article class="border-b border-slate-100 px-5 py-4 last:border-b-0 sm:border-r xl:border-b-0">
                    <div class="text-[9px] font-extrabold uppercase tracking-[0.12em] text-slate-400">{{ $card['label'] }}</div>
                    <div class="mt-1 text-xl font-extrabold tracking-tight text-[#0d2449]">{{ $card['value'] }}</div>
                    <div class="mt-1 text-[10px] text-slate-400">{{ $card['hint'] }}</div>
                </article>
            @endforeach
        </div>

        <div class="flex flex-col gap-2 border-t border-slate-200 bg-[#f8fafc] px-5 py-3 text-[10px] text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $mapPayload['metric_note'] ?? $mapPayload['data_note'] }}</span>
            @if ((int) $mapPayload['summary']['areas_needing_review'] > 0)
                <span class="font-bold text-amber-700">{{ $mapPayload['summary']['areas_needing_review'] }} {{ strtolower($areaLabel) }} area(s) include legacy unallocated records.</span>
            @else
                <span class="font-bold text-emerald-700">Exact-allocation coverage complete for visible {{ strtolower($areaLabel) }} rows.</span>
            @endif
        </div>
    </section>

    <script type="application/json" data-tupad-map-payload>@json($mapPayload)</script>
</div>
