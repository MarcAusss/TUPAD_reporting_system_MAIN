@extends('layouts.error')
@section('title', 'Access Denied')
@section('content')
<div class="flex min-h-[65vh] items-center justify-center">
    <div class="w-full max-w-xl text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl border border-red-200 bg-red-50">
            <svg class="h-7 w-7 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.7 2.9 16.5A2 2 0 0 0 4.6 19.5h14.8a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"></path></svg>
        </div>
        <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900">Access Denied</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">You do not have permission to access this page or perform this action.</p>
        @if($exception->getMessage())
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">{{ $exception->getMessage() }}</div>
        @endif
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">Return to Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">Sign In</a>
            @endauth
            <button type="button" onclick="history.back()" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">Go Back</button>
        </div>
    </div>
</div>
@endsection
