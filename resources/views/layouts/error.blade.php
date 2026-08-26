<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'System Message') | {{ config('app.name', 'TUPAD Reporting System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between px-5 sm:px-6 lg:px-8">
            <div>
                <div class="text-sm font-bold tracking-tight text-slate-900">TUPAD Reporting System</div>
                <div class="mt-0.5 text-[11px] text-slate-400">Department of Labor and Employment</div>
            </div>
            @auth
                <a href="{{ route('dashboard') }}"
                    class="text-xs font-semibold text-slate-500 hover:text-slate-900">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900">Sign In</a>
            @endauth
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-5 py-8 sm:px-6 lg:px-8">
        @yield('content')
    </main>
</body>

</html>
