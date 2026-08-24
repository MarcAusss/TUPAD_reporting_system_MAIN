@extends('layouts.app')

@section('title', 'Edit ADL')

@section('content')
<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <a
            href="{{ route('adl.show', $adl) }}"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-[#063b86]"
        >
            <span aria-hidden="true">←</span>
            ADL Detail
        </a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight text-[#10294f]">
            Edit {{ $adl->adl_number }}
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            Update only the core ADL information defined in the approved workflow document.
        </p>
    </div>

    <x-page-alerts />

    <form method="POST" action="{{ route('adl.update', $adl) }}" class="space-y-5">
        @csrf
        @method('PUT')

        @include('adl._form-fields', ['adl' => $adl])

        <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
            <a
                href="{{ route('adl.show', $adl) }}"
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
</div>
@endsection
