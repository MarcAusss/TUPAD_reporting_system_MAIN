@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">

    <div class="min-w-0">

        @if($eyebrow)
            <div class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">
                {{ $eyebrow }}
            </div>
        @endif

        <h1 class="{{ $eyebrow ? 'mt-1' : '' }} text-2xl font-bold tracking-tight text-slate-900">
            {{ $title }}
        </h1>

        @if($description)
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">
                {{ $description }}
            </p>
        @endif

    </div>

    @if(isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif

</div>
