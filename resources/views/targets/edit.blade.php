@extends('layouts.app')

@section('title', 'Edit Target')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a
                href="{{ route('targets.index') }}"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-[#063b86]"
            >
                <span aria-hidden="true">←</span>
                Targets
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-[#10294f]">
                Edit NGA Target
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                {{ $target->nga }}
            </p>
        </div>
    </div>

    <x-page-alerts />

    <form method="POST" action="{{ route('targets.update', $target) }}" class="space-y-5">
        @csrf
        @method('PUT')

        @include('targets._form-fields')

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <a
                href="{{ route('targets.index') }}"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white transition hover:bg-[#052f6a] focus:outline-none focus:ring-2 focus:ring-[#1765d8]/30"
            >
                Save Changes
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('targets.destroy', $target) }}" class="border-t border-slate-200 pt-5"
        onsubmit="return confirm('Remove this NGA target? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button
            type="submit"
            class="inline-flex h-11 items-center justify-center rounded-lg border border-red-200 bg-white px-5 text-sm font-semibold text-red-700 transition hover:bg-red-50"
        >
            Remove Target
        </button>
    </form>
</div>
@endsection
