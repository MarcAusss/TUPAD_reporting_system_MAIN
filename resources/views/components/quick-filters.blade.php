{{--
    Quick filter chips for the registry and queue pages. Each chip toggles one
    query parameter (?aging=14, ?period=this_month, ?mine=1) and keeps the rest
    of the current filters. Server side: Project::scopeApplyQuickFilters().
    <x-quick-filters />                 chips (outside the filter form)
    <x-quick-filters :inputs="true" />  hidden inputs (inside the filter form)
--}}
@props([
    'inputs' => false,
    'mine' => true,
])

@php
    $user = auth()->user();
    $quickParams = ['aging' => '14', 'period' => 'this_month', 'mine' => '1'];
    $activeQuick = collect($quickParams)->filter(fn ($value, $key) => (string) request()->query($key) === $value);

    $chips = [
        ['key' => 'aging', 'label' => 'Aging 14+ days', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        ['key' => 'period', 'label' => 'Received this month', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
    ];

    if ($mine && ($user->isTc() || $user->isAdmin())) {
        $chips[] = ['key' => 'mine', 'label' => 'Created by me', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'];
    }

    $assignedProvinceName = $user->isTc() ? \App\Models\Province::query()->whereKey($user->assigned_province_id)->value('name') : null;
@endphp

@if ($inputs)
    @foreach ($activeQuick as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
@else
    <div {{ $attributes->class('mb-4 flex flex-wrap items-center gap-2') }} data-quick-filters>
        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Quick filters</span>

        @if ($assignedProvinceName)
            <span class="inline-flex h-8 items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-[#063b86]"
                title="TUPAD Coordinators always see their assigned province">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                My province: {{ $assignedProvinceName }}
            </span>
        @endif

        @foreach ($chips as $chip)
            @php $isActive = $activeQuick->has($chip['key']); @endphp
            <a href="{{ request()->fullUrlWithQuery([$chip['key'] => $isActive ? null : $quickParams[$chip['key']], 'page' => null]) }}"
                aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                @class([
                    'inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition',
                    'border-[#063b86] bg-[#063b86] text-white hover:bg-[#052f6b]' => $isActive,
                    'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50' => ! $isActive,
                ])>
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">{!! $chip['icon'] !!}</svg>
                {{ $chip['label'] }}
                @if ($isActive)
                    <span aria-hidden="true">×</span>
                @endif
            </a>
        @endforeach

        @if (count(request()->except('page')) > 0)
            <a href="{{ url()->current() }}" data-clear-filters
                class="inline-flex h-8 items-center px-2 text-xs font-semibold text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline">
                Clear all filters
            </a>
        @endif
    </div>
@endif
