@props([
    'title' => 'No records found',
    'message' => null,
])

<div class="px-6 py-12 text-center">
    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-slate-50">
        <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M4 6h16"></path>
            <path d="M4 12h16"></path>
            <path d="M4 18h10"></path>
        </svg>
    </div>

    <div class="mt-4 text-sm font-semibold text-slate-700">
        {{ $title }}
    </div>

    @if($message)
        <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-slate-400">
            {{ $message }}
        </p>
    @endif
</div>
