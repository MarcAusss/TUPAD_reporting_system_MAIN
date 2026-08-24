@props(['tone' => 'neutral'])
@php
$classes = match ($tone) {
    'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/10',
    'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/10',
    'danger' => 'bg-red-50 text-red-700 ring-red-600/10',
    'info' => 'bg-blue-50 text-blue-700 ring-blue-600/10',
    default => 'bg-slate-100 text-slate-700 ring-slate-600/10',
};
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset $classes"]) }}>{{ $slot }}</span>
