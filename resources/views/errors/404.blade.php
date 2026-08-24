@extends('layouts.error')

@section('title', 'Page Not Found')

@section('content')
<div class="flex min-h-[65vh] items-center justify-center">
    <div class="w-full max-w-xl text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
            <span class="text-lg font-bold text-slate-700">404</span>
        </div>

        <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900">
            Page Not Found
        </h1>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            The page or record you requested could not be found. It may have been moved, removed, or the address may be incorrect.
        </p>

        <div class="mt-6 flex flex-wrap justify-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Return to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                    Sign In
                </a>
            @endauth

            <button type="button" onclick="history.back()" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                Go Back
            </button>
        </div>
    </div>
</div>
@endsection
