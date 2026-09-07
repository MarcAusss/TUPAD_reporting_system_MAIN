<section id="focalGeographicAnalytics"
    class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    data-dashboard-geographic-analytics
    data-endpoint="{{ route('dashboard.geographic-analytics') }}">
    <div class="border-b border-slate-200 px-5 py-4">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Geographic Report Analytics</div>
                <h2 class="mt-1 text-base font-bold text-slate-900">Bicol Geographic Performance</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                    Select a report family, then select a bar to drill from Bicol provinces to municipalities/cities and barangays.
                </p>
            </div>

            <a href="{{ route('reports.workspace.geographic-mapping') }}"
                class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                Open Geographic Report
            </a>
        </div>

        <div class="mt-4 flex flex-wrap gap-2" role="group" aria-label="Geographic report family">
            @foreach ($geographicAnalytics['controls']['families'] as $family)
                <button type="button"
                    data-geo-family="{{ $family['key'] }}"
                    class="inline-flex min-h-9 items-center rounded-lg border px-3 py-2 text-xs font-semibold transition {{ $family['key'] === $geographicAnalytics['family'] ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">
                    {{ $family['label'] }}
                </button>
            @endforeach
        </div>

        <div class="mt-3 hidden grid-cols-1 gap-3 md:grid-cols-2" data-sector-controls>
            <div>
                <label for="dashboard-sector-group" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Sector family</label>
                <select id="dashboard-sector-group" data-sector-group
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-700">
                    @foreach ($geographicAnalytics['controls']['sector_groups'] as $group)
                        <option value="{{ $group['key'] }}">{{ $group['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="dashboard-sector" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Sector category</label>
                <select id="dashboard-sector" data-sector
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-700">
                    <option value="">All categories in selected family</option>
                </select>
            </div>
        </div>

        <div class="mt-3 hidden" data-intervention-controls>
            <label for="dashboard-intervention-focus" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Intervention focus</label>
            <select id="dashboard-intervention-focus" data-intervention-focus
                class="h-10 w-full max-w-xl rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-700">
                <option value="">All intervention focuses</option>
                @foreach ($geographicAnalytics['controls']['interventions'] as $focus)
                    <option value="{{ $focus['key'] }}">{{ $focus['label'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="px-5 py-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600" data-geo-breadcrumb></div>
                <h3 class="mt-1 text-sm font-bold text-slate-900" data-geo-title></h3>
                <p class="mt-1 text-xs text-slate-500" data-geo-description></p>
            </div>

            <button type="button" data-geo-back
                class="hidden h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                ← Back one level
            </button>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-y border-slate-100 py-3 text-xs text-slate-500">
            <span><strong class="text-slate-800" data-geo-summary-projects>0</strong> total projects</span>
            <span data-project-acp-summary><strong class="text-slate-800" data-geo-summary-acp>0</strong> Through ACP</span>
            <span data-beneficiary-summary><strong class="text-slate-800" data-geo-summary-total>0</strong> beneficiaries</span>
            <span data-female-summary><strong class="text-slate-800" data-geo-summary-female>0</strong> female</span>
            <span><strong class="text-slate-800" data-geo-summary-areas>0</strong> geographic rows</span>
        </div>

        <div class="mt-4 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50/40 p-3" data-geo-chart-scroll style="max-height: 620px;">
            <div data-geo-chart-wrap style="height: 360px; min-width: 620px;">
                <canvas data-geo-chart aria-label="Geographic dashboard bar chart" role="img"></canvas>
            </div>
        </div>

        <div class="mt-4 hidden rounded-lg border border-slate-200 bg-slate-50 px-4 py-5 text-center text-xs text-slate-500" data-geo-empty>
            No matching geographic data is available for this selection.
        </div>

        <div class="mt-3 flex items-start gap-2 rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2.5 text-[11px] leading-5 text-blue-900">
            <span aria-hidden="true">ⓘ</span>
            <span data-geo-note></span>
        </div>
    </div>
</section>

<script id="focalGeographicAnalyticsInitialData" type="application/json">@json($geographicAnalytics)</script>
