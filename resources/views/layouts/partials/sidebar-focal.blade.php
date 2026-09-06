@php
    $fundMonitoringOpen = request()->routeIs('fund-monitoring.*');
    $acpOpen = request()->routeIs('acp-workflow.*') || request()->routeIs('acp-payments.*') || request()->routeIs('acp-liquidations.*');
@endphp

<div class="tupad-nav-section">Projects</div>

<a href="{{ route('projects.index') }}"
    class="{{ $navClass(request()->routeIs('projects.index') || request()->routeIs('projects.show')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        <rect x="3" y="7" width="18" height="13" rx="2"></rect>
        <path d="M3 12h18"></path>
    </svg>
    <span>Project Registry</span>
</a>

<a href="{{ route('project-summary.index') }}"
    class="{{ $navClass(request()->routeIs('project-summary.*') || request()->routeIs('projects.summary')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M4 19V9"></path><path d="M10 19V5"></path><path d="M16 19v-7"></path><path d="M22 19H2"></path>
    </svg>
    <span>Provincial Summary</span>
</a>

<div class="tupad-nav-section">Funds & Payments</div>

<a href="{{ route('adl.index') }}"
    class="{{ $navClass(request()->routeIs('adl.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M12 3v18"></path><path d="M17 7.5C17 5.57 14.76 4 12 4S7 5.57 7 7.5 9.24 11 12 11s5 1.57 5 3.5S14.76 18 12 18s-5-1.57-5-3.5"></path>
    </svg>
    <span>ADL Management</span>
</a>

<details class="group/fund-monitoring" @if ($fundMonitoringOpen) open @endif>
    <summary class="{{ $navClass($fundMonitoringOpen) }} flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition marker:content-none [&::-webkit-details-marker]:hidden">
        <svg class="h-[19px] w-[19px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M4 19V9"></path><path d="M10 19V5"></path><path d="M16 19v-7"></path><path d="M22 19H2"></path>
        </svg>
        <span class="min-w-0 flex-1">Fund Monitoring</span>
        <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-open/fund-monitoring:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg>
    </summary>
    <div class="ml-5 mt-1 space-y-1 border-l border-slate-200 pl-3">
        @if (Route::has('fund-monitoring.per-adl-current'))
            <a href="{{ route('fund-monitoring.per-adl-current') }}" class="{{ request()->routeIs('fund-monitoring.per-adl-current') ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} flex min-h-9 items-center rounded-lg border px-3 py-2 text-[12px] font-semibold">PER ADL (Current)</a>
        @endif
        @if (Route::has('fund-monitoring.summary-current'))
            <a href="{{ route('fund-monitoring.summary-current') }}" class="{{ request()->routeIs('fund-monitoring.summary-current') ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} flex min-h-9 items-center rounded-lg border px-3 py-2 text-[12px] font-semibold">Regional Summary</a>
        @endif
        @if (Route::has('fund-monitoring.per-province-current'))
            <a href="{{ route('fund-monitoring.per-province-current') }}" class="{{ request()->routeIs('fund-monitoring.per-province-current') ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} flex min-h-9 items-center rounded-lg border px-3 py-2 text-[12px] font-semibold">Per Province</a>
        @endif
    </div>
</details>

<a href="{{ route('payments.index') }}"
    class="{{ $navClass(request()->routeIs('payments.*') || request()->routeIs('projects.payment.*')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
    <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18"></path><path d="M7 15h4"></path></svg>
    <span>Payment of Wages</span>
</a>

<details class="group/acp-nav" @if ($acpOpen) open @endif>
    <summary class="{{ $navClass($acpOpen) }} flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition marker:content-none [&::-webkit-details-marker]:hidden">
        <svg class="h-[19px] w-[19px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v12H4z"></path><path d="M8 10h8 M8 14h5"></path></svg>
        <span class="min-w-0 flex-1">Through ACP</span>
        <svg class="h-4 w-4 shrink-0 transition-transform duration-200 group-open/acp-nav:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg>
    </summary>
    <div class="ml-5 mt-1 space-y-1 border-l border-slate-200 pl-3">
        @foreach ([
            'acp-workflow.payment' => 'ACP Payment',
            'acp-workflow.check-release' => 'Check Release',
            'acp-workflow.liquidation' => 'Liquidation',
        ] as $acpRoute => $acpLabel)
            @php
                $active = request()->routeIs($acpRoute)
                    || (request()->routeIs('acp-payments.*') && in_array($acpRoute, ['acp-workflow.payment', 'acp-workflow.check-release'], true))
                    || (request()->routeIs('acp-liquidations.*') && $acpRoute === 'acp-workflow.liquidation');
            @endphp
            <a href="{{ route($acpRoute) }}" class="{{ $active ? 'border-blue-200 bg-blue-50 text-[#063b86]' : 'border-transparent text-slate-600 hover:bg-slate-50 hover:text-slate-900' }} flex min-h-9 items-center rounded-lg border px-3 py-2 text-[12px] font-semibold">{{ $acpLabel }}</a>
        @endforeach
    </div>
</details>

<div class="tupad-nav-section">Reporting</div>

<a href="{{ route('executive-dashboard.index') }}" class="{{ $navClass(request()->routeIs('executive-dashboard.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M7 15V11"></path><path d="M12 15V7"></path><path d="M17 15v-5"></path></svg>
    <span>Executive Dashboard</span>
</a>

@include('layouts.partials.report-navigation')

<div class="tupad-nav-section">Administration</div>

<a href="{{ route('users.index') }}" class="{{ $navClass(request()->routeIs('users.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path></svg>
    <span>User Accounts</span>
</a>
