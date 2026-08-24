@props(['tone' => 'neutral', 'title', 'message' => null])
@php
$classes = match ($tone) {
    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
    'danger' => 'border-red-200 bg-red-50 text-red-800',
    'info' => 'border-blue-200 bg-blue-50 text-blue-800',
    default => 'border-slate-200 bg-slate-50 text-slate-700',
};
@endphp
<div {{ $attributes->merge(['class' => "rounded-lg border p-4 $classes"]) }}>
    <div class="text-sm font-semibold">{{ $title }}</div>
    @if($message)<p class="mt-1 text-xs leading-5 opacity-90">{{ $message }}</p>@endif
    @if(trim($slot) !== '')<div class="mt-4">{{ $slot }}</div>@endif
</div>
