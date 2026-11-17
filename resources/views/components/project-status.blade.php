{{--
    Colour-coded project status pill with an optional "X days in this stage" chip.
    <x-project-status :project="$project" />            pill + aging chip
    <x-project-status :project="$project" :aging="false" />
--}}
@props([
    'project',
    'aging' => true,
])

@php
    $status = $project->status;
    $days = $aging && $status !== \App\Enums\ProjectStatus::COMPLETED ? $project->daysInCurrentStatus() : null;
    $enteredAt = $days !== null ? $project->statusEnteredAt() : null;

    $agingClasses = match (true) {
        $days === null => '',
        $days >= 14 => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        $days >= 7 => 'bg-amber-50 text-amber-800 ring-amber-600/20',
        default => 'bg-slate-50 text-slate-600 ring-slate-500/15',
    };
@endphp

<span {{ $attributes->class('inline-flex flex-wrap items-center gap-1.5') }}>
    <span class="inline-flex max-w-[200px] items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $status->pillClasses() }}">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $status->dotClass() }}" aria-hidden="true"></span>
        <span class="truncate">{{ $status->label() }}</span>
    </span>

    @if($days !== null)
        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-bold ring-1 ring-inset {{ $agingClasses }}"
            title="In this stage since {{ $enteredAt?->format('M d, Y') }}" data-sort-value="{{ $days }}">
            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            {{ $days === 0 ? 'Today' : $days.' '.\Illuminate\Support\Str::plural('day', $days) }}
        </span>
    @endif
</span>
