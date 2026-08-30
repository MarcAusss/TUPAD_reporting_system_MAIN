@php
    $reportsOpen = request()->routeIs('reports.*');
    $reportWorkspaceRoutes = [
        'reports.workspace.physical-financial' => 'Physical & Financial',
        'reports.workspace.fund-status' => 'Fund Status',
        'reports.workspace.monthly' => 'Monthly Reports',
        'reports.workspace.quarterly' => 'Quarterly Reports',
        'reports.workspace.geographic-mapping' => 'Geographic Mapping',
    ];
@endphp

<details class="group/report-nav" @if ($reportsOpen) open @endif>
    <summary
        class="{{ $navClass($reportsOpen) }} flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition marker:content-none [&::-webkit-details-marker]:hidden"
        aria-label="Reports navigation">
        <svg class="h-[19px] w-[19px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8">
            <path d="M4 19V9"></path>
            <path d="M10 19V5"></path>
            <path d="M16 19v-7"></path>
            <path d="M22 19H2"></path>
        </svg>
        <span class="min-w-0 flex-1">Reports</span>
        <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-open/report-nav:rotate-180"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="m6 9 6 6 6-6"></path>
        </svg>
    </summary>

    <div class="ml-5 mt-1 space-y-1 border-l border-slate-200 pl-3">
        @foreach ($reportWorkspaceRoutes as $reportRoute => $reportLabel)
            <a href="{{ route($reportRoute) }}"
                class="{{ request()->routeIs($reportRoute) ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} flex min-h-9 items-center rounded-lg border px-3 py-2 text-[12px] font-semibold leading-4 transition">
                <span>{{ $reportLabel }}</span>
            </a>
        @endforeach
    </div>
</details>
