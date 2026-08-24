@extends('layouts.error')

@section('title', 'Session Expired')

@section('content')
<div class="flex min-h-[65vh] items-center justify-center">
    <div class="w-full max-w-xl text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl border border-amber-200 bg-amber-50">
            <svg class="h-7 w-7 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <circle cx="12" cy="12" r="9"></circle>
                <path d="M12 7v5l3 2"></path>
            </svg>
        </div>

        <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900">
            Session Expired
        </h1>

        <p class="mt-2 text-sm leading-6 text-slate-500">
            Your session or form token has expired. Reload the page and submit the form again.
        </p>

        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <button type="button" onclick="window.location.reload()" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                Reload Page
            </button>

            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Sign In
                </a>
            @endauth
        </div>
    </div>
</div>
@endsection
