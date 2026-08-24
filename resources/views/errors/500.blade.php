@extends('layouts.error')
@section('title', 'System Error')
@section('content')
<div class="flex min-h-[65vh] items-center justify-center">
    <div class="w-full max-w-xl text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl border border-red-200 bg-red-50"><span class="text-lg font-bold text-red-700">500</span></div>
        <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900">System Error</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">The system encountered an unexpected error while processing your request.</p>
        <p class="mt-2 text-xs text-slate-400">Try again. If the issue continues, contact the system administrator.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <button type="button" onclick="window.location.reload()" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">Try Again</button>
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">Sign In</a>
            @endauth
        </div>
    </div>
</div>
@endsection
