{{--
    Distribution chart (Focal and TUPAD Coordinator dashboards), modeled on the
    TUPAD "Resource Mapping" illustrations: one district pie per province.

      province card  → all municipalities of the province (bars)
      district slice → municipalities of that district (bars)
      municipality   → barangays of that municipality (bars, loaded on demand)

    Draws beneficiaries by default. Pass $bdKind = 'projects' with
    $distribution = the project distribution to chart projects the same way.
    $bdCompact = true lays the province cards in one column (half-width use).
--}}
@php
    $bdKind = $bdKind ?? 'beneficiaries';
    $isProjects = $bdKind === 'projects';
    $distribution = $distribution ?? $beneficiaryDistribution ?? ['provinces' => [], 'summary' => ['total' => 0, 'female' => 0, 'municipalities_served' => 0]];
    $bdDataId = $bdDataId ?? 'focalBeneficiaryDistributionData';
    $bdCompact = $bdCompact ?? false;
    $isTcView = ($roleMode ?? null) === 'tc';
    $placeName = $isTcView ? ($assignedProvinceName ?? 'Your Province') : 'Bicol Region';
    $unit = $isProjects ? ['one' => 'project', 'many' => 'projects'] : ['one' => 'beneficiary', 'many' => 'beneficiaries'];
    $secondary = $isProjects ? 'beneficiaries' : 'female';
    // Labels for the chart script.
    $distribution['labels'] = [
        'unit' => $unit['many'],
        'unit_one' => $unit['one'],
        'secondary' => $secondary,
        'axis' => $isProjects ? 'No. of Projects' : 'No. of Beneficiaries',
        'root' => $placeName,
    ];
@endphp

<section class="h-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    data-beneficiary-distribution
    data-bd-data="{{ $bdDataId }}"
    data-bd-kind="{{ $bdKind }}"
    data-barangay-endpoint="{{ $distribution['barangay_endpoint'] ?? '' }}">

    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 {{ $bdCompact ? '' : 'lg:flex-row lg:items-end lg:justify-between' }}">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Resource Mapping</div>
            <h2 class="mt-1 text-base font-bold text-slate-900">{{ $isProjects ? 'Project' : 'Beneficiary' }} Distribution — {{ $placeName }}</h2>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                @if ($isTcView)
                    Click the province to see all of its municipalities, or a district slice to see only that district.
                @else
                    Click a province to see all of its municipalities, or click a district slice to see only that district.
                @endif
                Click a municipality bar to see its barangays.
            </p>
        </div>

        <dl class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-500">
            <div><dt class="inline">{{ ucfirst($unit['many']) }}</dt> <dd class="inline font-bold text-slate-900">{{ number_format($distribution['summary']['total']) }}</dd></div>
            <div><dt class="inline">{{ ucfirst($secondary) }}</dt> <dd class="inline font-bold text-slate-900">{{ number_format($distribution['summary']['female']) }}</dd></div>
            <div><dt class="inline">Municipalities served</dt> <dd class="inline font-bold text-slate-900">{{ number_format($distribution['summary']['municipalities_served']) }}</dd></div>
        </dl>
    </div>

    {{-- Level 1: one pie per province --}}
    <div class="grid gap-4 p-5 {{ $bdCompact ? '' : 'md:grid-cols-2 xl:grid-cols-3' }}" data-bd-overview>
        @foreach ($distribution['provinces'] as $province)
            <article role="button" tabindex="0"
                data-bd-province="{{ $province['id'] }}"
                aria-label="{{ $province['name'] }}: {{ number_format($province['total']) }} {{ $unit['many'] }}. Open municipalities."
                class="group relative flex cursor-pointer flex-col rounded-xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                style="--bd-accent: {{ $province['color'] }}; border-top: 4px solid {{ $province['color'] }};">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase tracking-wide" style="color: {{ $province['color'] }}">{{ $province['name'] }}</h3>
                        <p class="text-xs font-semibold text-slate-500">
                            {{ number_format($province['total']) }} {{ $province['total'] === 1 ? $unit['one'] : $unit['many'] }}
                        </p>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400 transition group-hover:text-slate-700">Municipalities →</span>
                </div>

                <div class="relative mx-auto mt-3 h-44 w-44" style="width: 11rem; height: 11rem;">
                    <canvas data-bd-pie="{{ $province['id'] }}" role="img"
                        aria-label="{{ $province['name'] }} {{ $unit['many'] }} by district"></canvas>
                    @if ($province['total'] === 0)
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-center text-[11px] font-semibold text-slate-400">
                            No {{ $unit['many'] }}<br>mapped yet
                        </div>
                    @endif
                </div>

                <ul class="mt-3 space-y-1">
                    @foreach ($province['districts'] as $district)
                        <li>
                            <button type="button" data-bd-district="{{ $province['id'] }}" data-bd-district-name="{{ $district['name'] }}"
                                class="flex w-full items-center justify-between gap-3 rounded-md px-2 py-1 text-left text-xs hover:bg-slate-50">
                                <span class="flex items-center gap-2 text-slate-700">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $district['color'] }}"></span>
                                    {{ $district['name'] }}
                                </span>
                                <span class="font-semibold tabular-nums text-slate-900">
                                    {{ number_format($district['total']) }}
                                    <span class="ml-1 font-normal text-slate-400">
                                        {{ $province['total'] > 0 ? number_format($district['total'] / $province['total'] * 100, 1) : '0.0' }}%
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </div>

    {{-- Levels 2–3: bar chart drill-down --}}
    <div class="hidden" data-bd-detail>
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <nav class="flex flex-wrap items-center gap-1 text-xs font-semibold text-slate-500" aria-label="Drill-down path" data-bd-breadcrumb></nav>
                <h3 class="mt-1 text-sm font-extrabold uppercase tracking-wide" data-bd-title></h3>
                <p class="text-xs font-semibold text-slate-500" data-bd-subtitle></p>
            </div>
            <button type="button" data-bd-back
                class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                ← Back
            </button>
        </div>

        <div class="flex flex-wrap gap-x-4 gap-y-1 px-5 pt-3 text-xs text-slate-600" data-bd-legend></div>
        <p class="px-5 pt-1 text-[11px] text-slate-400" data-bd-hint></p>

        <div class="overflow-x-auto px-5 pb-5 pt-2">
            <div class="relative" data-bd-chart-wrap style="height: 380px;">
                <canvas data-bd-bar role="img" aria-label="{{ ucfirst($unit['many']) }} bar chart"></canvas>
            </div>
        </div>

        <div class="hidden px-5 pb-6 text-center text-xs text-slate-500" data-bd-loading>Loading barangays…</div>
        <div class="hidden px-5 pb-6 text-center text-xs text-rose-600" data-bd-error>Could not load barangays. Please try again.</div>
    </div>

    <p class="border-t border-slate-100 px-5 py-2.5 text-[11px] text-slate-500">
        @if ($isProjects)
            Every project in the workflow is counted, from TSSD Evaluation to Completed. A project implemented in several
            municipalities or barangays counts once in each.
        @else
            Counts are the Actual Beneficiary Mapping (beneficiary address allocations less beneficiaries not included in the
            payment) across all official projects. Projects without an encoded Beneficiary Mapping are not counted here.
        @endif
    </p>
</section>

<script id="{{ $bdDataId }}" type="application/json">@json($distribution)</script>
