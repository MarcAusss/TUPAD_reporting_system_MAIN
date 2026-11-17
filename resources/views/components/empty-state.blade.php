@props([
    'title' => 'No records found',
    'message' => null,
    'icon' => 'list',
    'size' => 'md',
    // Optional "add the first record" button. With actionTarget/actionClick the
    // button only appears when that form/button exists on the page (tupad-ui.js).
    'actionLabel' => null,
    'actionHref' => null,
    'actionTarget' => null,
    'actionTab' => null,
    'actionClick' => null,
])

@php
    $compact = $size === 'sm';

    $iconPaths = [
        'list' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/>',
        'document' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 3"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    ];
@endphp

<div {{ $attributes->class(['tupad-empty-state text-center', 'px-4 py-6' => $compact, 'px-6 py-12' => ! $compact]) }}>

    <div @class([
        'mx-auto flex items-center justify-center border border-slate-200 bg-slate-50 text-slate-400',
        'h-9 w-9 rounded-lg' => $compact,
        'h-11 w-11 rounded-xl' => ! $compact,
    ])>
        <svg class="{{ $compact ? 'h-4 w-4' : 'h-5 w-5' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            {!! $iconPaths[$icon] ?? $iconPaths['list'] !!}
        </svg>
    </div>

    <div class="{{ $compact ? 'mt-2.5' : 'mt-4' }} text-sm font-semibold text-slate-700">
        {{ $title }}
    </div>

    @if($message)
        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-slate-500">
            {{ $message }}
        </p>
    @endif

    @if($actionLabel && ($actionHref || $actionTarget || $actionClick))
        <div class="{{ $compact ? 'mt-3' : 'mt-4' }} flex justify-center">
            <a href="{{ $actionHref ?? ($actionTarget ? '#'.$actionTarget : '#') }}"
                @unless($actionHref) hidden data-empty-action @endunless
                @if($actionTarget) data-empty-action-target="{{ $actionTarget }}" @endif
                @if($actionClick) data-empty-action-click="{{ $actionClick }}" @endif
                @if($actionTab && $actionTarget) data-workspace-open-tab="{{ $actionTab }}" data-workspace-anchor="{{ $actionTarget }}" @endif
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b] focus:outline-none focus:ring-2 focus:ring-blue-300">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                {{ $actionLabel }}
            </a>
        </div>
    @endif

    @if(isset($action))
        <div class="{{ $compact ? 'mt-3' : 'mt-4' }} flex justify-center">
            {{ $action }}
        </div>
    @endif

</div>
