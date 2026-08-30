@extends('layouts.app')

@section('title', 'TUPAD Geographic Mapping')

@section('content')
    @php
        $levelLabels = [
            'province' => 'Province',
            'district' => 'District',
            'municipality' => 'Municipality',
            'barangay' => 'Barangay',
        ];
        $activeSectorGroup = $filters['sector_group'] ?? \App\Enums\BeneficiarySectorCategory::GROUP_PRIORITY_VULNERABLE;
        $mapTitle = $family['label'] . ($level ? ' by ' . ($levelLabels[$level] ?? ucfirst($level)) : '');
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header eyebrow="Official Reporting" title="TUPAD Geographic Mapping"
            description="Visualize project implementation, exact beneficiary concentration, beneficiary sectors, and intervention focus without inventing missing geographic allocations or GIS coordinates." />

        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('reports.index', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Detailed Data
            </a>
            <a href="{{ route('reports.print', $exportQuery) }}" target="_blank"
                class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print</a>
            <a href="{{ route('reports.export.pdf', $exportQuery) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">PDF</a>
        </div>
    </div>

    @if (($familyKey === 'projects') && ($level === 'province') && (auth()->user()->isFocal() || auth()->user()->isAdmin() || auth()->user()->isTc()))
        <livewire:reports.geographic-distribution-map
            :fiscal-year="$filters['fiscal_year'] ?? null"
            :quarter="$filters['quarter'] ?? null"
            :month="$filters['month'] ?? null"
            :status="$filters['status'] ?? null"
            :implementation-mode="$filters['implementation_mode'] ?? null" />
    @endif

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Geographic mapping families">
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($families as $key => $tab)
                <a href="{{ route('reports.workspace.geographic-mapping', array_merge($commonQuery, ['view' => $key])) }}"
                    class="{{ $familyKey === $key ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900' }} rounded-lg border px-4 py-3 transition">
                    <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] opacity-70">Map {{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="mt-1 text-sm font-bold leading-5">{{ $tab['label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <details class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
            <div>
                <div class="text-sm font-bold text-slate-900">Mapping Coverage Directory</div>
                <div class="mt-1 text-xs text-slate-500">Reference hierarchy for all four mapping families. Open to review every supported level/category.</div>
            </div>
            <span class="text-xs font-semibold text-[#063b86]">Show hierarchy</span>
        </summary>
        <div class="grid gap-4 border-t border-slate-100 p-5 lg:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Project Mapping</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-700">
                    @foreach (['Province', 'District', 'Municipality'] as $item)<span class="rounded-md bg-white px-2.5 py-1.5 ring-1 ring-slate-200">{{ $item }}</span>@endforeach
                </div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Beneficiary Mapping</div>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-slate-700">
                    @foreach (['Province', 'District', 'Municipality', 'Barangay'] as $item)<span class="rounded-md bg-white px-2.5 py-1.5 ring-1 ring-slate-200">{{ $item }}</span>@endforeach
                </div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Sector Mapping</div>
                <div class="mt-3 space-y-3">
                    @foreach ($sectorGroups as $group)
                        <div>
                            <div class="text-xs font-bold text-slate-700">{{ $group['label'] }}</div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($group['categories'] as $sector)<span class="rounded-md bg-white px-2 py-1 text-[10px] font-semibold text-slate-600 ring-1 ring-slate-200">{{ $sector->label() }}</span>@endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-extrabold uppercase tracking-wide text-slate-500">Intervention-Focus Mapping</div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($interventionFocuses as $focus)<span class="rounded-md bg-white px-2 py-1 text-[10px] font-semibold leading-4 text-slate-600 ring-1 ring-slate-200">{{ $focus->label() }}</span>@endforeach
                </div>
            </article>
        </div>
    </details>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">{{ $family['label'] }}</div>
            <p class="mt-1 max-w-4xl text-sm leading-6 text-slate-500">{{ $family['description'] }}</p>
        </div>

        @if ($family['levels'] !== [])
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="mb-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Mapping Level</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($family['levels'] as $availableLevel)
                        <a href="{{ route('reports.workspace.geographic-mapping', array_merge($commonQuery, ['view' => $familyKey, 'level' => $availableLevel])) }}"
                            class="{{ $level === $availableLevel ? 'border-[#063b86] bg-[#063b86] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }} inline-flex h-9 items-center rounded-lg border px-3 text-xs font-semibold">
                            {{ $levelLabels[$availableLevel] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @elseif ($familyKey === 'sectors')
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="mb-2 text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Sector Family</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($sectorGroups as $groupKey => $group)
                        <a href="{{ route('reports.workspace.geographic-mapping', array_merge($commonQuery, ['view' => 'sectors', 'sector_group' => $groupKey])) }}"
                            class="{{ $activeSectorGroup === $groupKey ? 'border-[#063b86] bg-[#063b86] text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }} inline-flex h-9 items-center rounded-lg border px-3 text-xs font-semibold">
                            {{ $group['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="GET" action="{{ route('reports.workspace.geographic-mapping') }}" class="p-5">
            <input type="hidden" name="view" value="{{ $familyKey }}">
            @if ($level)<input type="hidden" name="level" value="{{ $level }}">@endif
            @if ($familyKey === 'sectors')<input type="hidden" name="sector_group" value="{{ $activeSectorGroup }}">@endif

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div>
                    <label for="fiscal_year" class="mb-2 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year" name="fiscal_year" type="number" min="2000" max="2100"
                        value="{{ $filters['fiscal_year'] ?? '' }}" placeholder="All years"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                </div>
                <div>
                    <label for="implementation_mode" class="mb-2 block text-xs font-semibold text-slate-700">Implementation Mode</label>
                    <select id="implementation_mode" name="implementation_mode" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All modes</option>
                        @foreach ($options['implementation_modes'] as $mode)
                            <option value="{{ $mode->value }}" @selected(($filters['implementation_mode'] ?? null) === $mode->value)>{{ $mode->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="mb-2 block text-xs font-semibold text-slate-700">Project Status</label>
                    <select id="status" name="status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        <option value="">All statuses</option>
                        @foreach ($options['statuses'] as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="province_id" class="mb-2 block text-xs font-semibold text-slate-700">Province</label>
                    <select id="province_id" name="province_id"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm disabled:bg-slate-100 disabled:text-slate-600" @disabled($provinceLocked)>
                        @unless ($provinceLocked)<option value="">All provinces</option>@endunless
                        @foreach ($options['provinces'] as $province)
                            <option value="{{ $province->id }}" @selected((string) ($filters['province_id'] ?? '') === (string) $province->id)>{{ $province->name }}</option>
                        @endforeach
                    </select>
                    @if ($provinceLocked)<input type="hidden" name="province_id" value="{{ $filters['province_id'] ?? '' }}">@endif
                </div>

                @if ($familyKey === 'sectors')
                    <div>
                        <label for="sector" class="mb-2 block text-xs font-semibold text-slate-700">Sector Category</label>
                        <select id="sector" name="sector" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All in selected family</option>
                            @foreach ($sectorGroups[$activeSectorGroup]['categories'] as $sector)
                                <option value="{{ $sector->value }}" @selected(($filters['sector'] ?? null) === $sector->value)>{{ $sector->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($familyKey === 'interventions')
                    <div>
                        <label for="intervention_focus" class="mb-2 block text-xs font-semibold text-slate-700">Intervention Focus</label>
                        <select id="intervention_focus" name="intervention_focus" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">All focus categories</option>
                            @foreach ($interventionFocuses as $focus)
                                <option value="{{ $focus->value }}" @selected(($filters['intervention_focus'] ?? null) === $focus->value)>{{ $focus->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="flex items-end gap-2">
                    <button class="inline-flex h-10 flex-1 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">Apply</button>
                    <a href="{{ route('reports.workspace.geographic-mapping', ['view' => $familyKey] + ($familyKey === 'sectors' ? ['sector_group' => $activeSectorGroup] : [])) }}"
                        class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summary as $card)
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-500">{{ $card['label'] }}</div>
                <div class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">{{ is_int($card['value']) ? number_format($card['value']) : $card['value'] }}</div>
                <p class="mt-2 text-[11px] leading-5 text-slate-500">{{ $card['hint'] }}</p>
            </article>
        @endforeach
    </section>

    @if ($familyKey === 'projects' && in_array($level, ['district', 'municipality'], true))
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
            <strong>Multi-location integrity:</strong> project counts may appear in more than one district or municipality when a project spans multiple locations. The visualization does not divide or infer project money across those locations.
        </div>
    @elseif ($familyKey === 'beneficiaries')
        <div class="mb-5 rounded-xl border border-blue-100 bg-blue-50 px-5 py-4 text-sm leading-6 text-blue-900">
            <strong>Exact beneficiary mapping:</strong> only beneficiary counts encoded on exact project-location/barangay allocations are mapped. Legacy records without an exact allocation remain flagged instead of being guessed.
        </div>
    @elseif ($familyKey === 'sectors')
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900">
            <strong>Overlapping classifications:</strong> one beneficiary may be represented in more than one sector category. Sector counts must not be summed and presented as a unique beneficiary total.
        </div>
    @endif

    <section class="mb-5 grid gap-5 2xl:grid-cols-[minmax(0,1fr)_330px]">
        <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">{{ $mapTitle }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Relative intensity is calculated only against the largest value visible in this selected report scope.</p>
                </div>
                <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Data-driven intensity map</div>
            </div>

            @if ($visualRows->isEmpty())
                <div class="px-5 py-14 text-center">
                    <div class="text-sm font-semibold text-slate-700">No mapping data matched the selected criteria.</div>
                    <div class="mt-1 text-xs text-slate-500">Adjust the reporting filters and try again.</div>
                </div>
            @else
                <div class="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($visualRows as $row)
                        <article class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-bold text-slate-900">{{ $row['label'] ?? $row['sector_label'] ?? $row['intervention_focus_label'] ?? 'Unspecified' }}</div>
                                    @if ($familyKey === 'sectors')
                                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $row['sector_group_label'] ?? '' }}</div>
                                    @elseif ($familyKey === 'beneficiaries' && !($row['has_complete_exact_allocation'] ?? false))
                                        <div class="mt-1 text-[10px] font-bold uppercase tracking-wide text-amber-700">Includes legacy unallocated records</div>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-lg font-extrabold text-[#063b86]">{{ number_format((int) $row['map_metric']) }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $row['map_metric_label'] }}</div>
                                </div>
                            </div>

                            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-200">
                                <div class="h-full rounded-full bg-[#063b86]" style="width: {{ max($row['map_metric'] > 0 ? 3 : 0, min(100, $row['map_intensity'])) }}%"></div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-slate-500">
                                @if (array_key_exists('project_count', $row))
                                    <div><span class="font-bold text-slate-700">{{ number_format((int) $row['project_count']) }}</span> project(s)</div>
                                @endif
                                @if (array_key_exists('beneficiaries_total', $row))
                                    <div><span class="font-bold text-slate-700">{{ number_format((int) $row['beneficiaries_total']) }}</span> beneficiaries</div>
                                @endif
                                @if (array_key_exists('beneficiaries_female', $row))
                                    <div><span class="font-bold text-slate-700">{{ number_format((int) $row['beneficiaries_female']) }}</span> female</div>
                                @endif
                                @if (array_key_exists('legacy_unallocated_project_count', $row))
                                    <div><span class="font-bold text-slate-700">{{ number_format((int) $row['legacy_unallocated_project_count']) }}</span> unallocated</div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </article>

        <aside class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Map Reading Guide</div>
            <h2 class="mt-1 text-sm font-bold text-slate-900">Relative concentration</h2>
            <p class="mt-2 text-xs leading-5 text-slate-500">A longer navy bar means a higher value relative to the largest visible area/category. It is not a percentage of the regional population.</p>

            <div class="mt-5 space-y-3">
                @foreach ([100 => 'Highest visible concentration', 65 => 'Higher concentration', 35 => 'Moderate concentration', 10 => 'Lower concentration'] as $width => $label)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-[10px] text-slate-500"><span>{{ $label }}</span><span>{{ $width }}% scale</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#063b86]" style="width: {{ $width }}%"></div></div>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 border-t border-slate-100 pt-4 text-xs leading-5 text-slate-500">
                This module maps encoded reporting data by official area/category. It does not fabricate latitude/longitude, boundary polygons, or missing beneficiary allocations.
            </div>
        </aside>
    </section>

    @if ($familyKey === 'sectors')
        <section class="mb-5 grid gap-5 xl:grid-cols-2">
            @foreach ($sectorGroups as $groupKey => $group)
                <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="text-sm font-bold text-slate-900">{{ $group['label'] }}</h2>
                    </div>
                    <div class="grid gap-2 p-4 sm:grid-cols-2">
                        @foreach ($group['categories'] as $sector)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700">{{ $sector->label() }}</div>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </section>
    @elseif ($familyKey === 'interventions')
        <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Intervention-Focus Categories</h2>
            </div>
            <div class="grid gap-2 p-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($interventionFocuses as $focus)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-xs font-semibold leading-5 text-slate-700">{{ $focus->label() }}</div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Mapping Data Register</h2>
                <p class="mt-1 text-xs text-slate-500">The table below is the same source cohort used by the intensity visualization.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.export.excel', $exportQuery) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Excel</a>
                <a href="{{ route('reports.export.csv', $exportQuery) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">CSV</a>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-slate-500">No mapping rows available.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach ($report['columns'] as $column)
                                <th class="whitespace-nowrap px-4 py-3 font-bold uppercase tracking-wide text-slate-600">{{ $column['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($displayRows as $displayRow)
                            <tr class="hover:bg-slate-50/70">
                                @foreach ($report['columns'] as $column)
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $displayRow[$column['key']] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
