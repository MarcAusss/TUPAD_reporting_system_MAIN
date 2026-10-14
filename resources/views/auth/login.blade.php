<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        Login | {{ config('app.name', 'TUPAD Reporting System') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

    {{-- TUPAD Reporting System favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon.png') }}">
</head>

<body class="min-h-screen bg-linear-to-br from-[#eef4fb] via-[#e7eef8] to-[#dfe6f0] text-[#10294f] antialiased">

    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">

        <div class="grid w-full max-w-5xl grid-cols-1 gap-3 rounded-[28px] bg-white/80 p-3 shadow-2xl shadow-[#10294f]/10 ring-1 ring-white backdrop-blur md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">

            {{-- <------------------------------------------- Login Form ------------------------------/> --}}

            <section class="flex flex-col px-5 py-6 sm:px-8 sm:py-8">

                {{-- System Identity --}}
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white p-0.5 shadow-sm ring-1 ring-[#dfe6f0]">
                        <img src="{{ asset('images/mainlogo.jpg') }}" alt="TUPAD Reporting System logo" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <div class="text-sm font-extrabold tracking-tight text-[#063b86]">TUPAD</div>
                        <div class="text-[11px] font-semibold text-[#6f7f98]">Reporting System</div>
                    </div>
                </div>

                <div class="flex flex-1 flex-col justify-center py-10">

                    <h1 class="text-2xl font-bold tracking-tight text-[#10294f] sm:text-[28px]">
                        Welcome back
                    </h1>

                    <p class="mt-1.5 text-sm text-[#6f7f98]">
                        Sign in with your authorized system account.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('login.store') }}"
                        class="mt-8 space-y-4"
                    >

                        @csrf

                        {{-- <------------------------------------------- Username ------------------------------/> --}}

                        <div>

                            <label
                                for="username"
                                class="mb-1.5 block text-xs font-semibold text-[#355378]"
                            >
                                Username
                            </label>

                            <input
                                id="username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                autocomplete="username"
                                autofocus
                                required
                                class="block h-12 w-full rounded-xl border border-[#ccd7e6] bg-[#f8fbff] px-4 text-sm text-[#10294f] outline-none transition placeholder:text-[#8794a8] focus:border-[#1765d8] focus:bg-white focus:ring-4 focus:ring-[#1765d8]/15"
                                placeholder="Enter username"
                            >

                            @error('username')

                                <p class="mt-1.5 text-xs font-medium text-[#b42318]">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>

                        {{-- <------------------------------------------- Password ------------------------------/> --}}

                        <div>

                            <label
                                for="password"
                                class="mb-1.5 block text-xs font-semibold text-[#355378]"
                            >
                                Password
                            </label>

                            <div class="relative">

                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                    class="block h-12 w-full rounded-xl border border-[#ccd7e6] bg-[#f8fbff] pl-4 pr-12 text-sm text-[#10294f] outline-none transition placeholder:text-[#8794a8] focus:border-[#1765d8] focus:bg-white focus:ring-4 focus:ring-[#1765d8]/15"
                                    placeholder="Enter password"
                                >

                                <button
                                    type="button"
                                    data-toggle-password="password"
                                    aria-label="Show password"
                                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-[#8794a8] transition hover:text-[#063b86]"
                                >
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>

                            </div>

                            @error('password')

                                <p class="mt-1.5 text-xs font-medium text-[#b42318]">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>

                        {{-- <------------------------------------------- Remember Me ------------------------------/> --}}

                        <label class="flex w-fit cursor-pointer items-center gap-2 pt-1">

                            <input
                                type="checkbox"
                                name="remember"
                                value="1"
                                class="h-4 w-4 rounded border-[#ccd7e6] text-[#063b86] focus:ring-[#1765d8]"
                            >

                            <span class="text-xs text-[#48617f]">
                                Remember me
                            </span>

                        </label>

                        {{-- <------------------------------------------- Submit ------------------------------/> --}}

                        <button
                            type="submit"
                            class="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#063b86] px-4 text-sm font-semibold text-white shadow-lg shadow-[#063b86]/25 transition hover:bg-[#0b3170] focus:outline-none focus:ring-4 focus:ring-[#1765d8]/30"
                        >
                            Sign in
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>

                    </form>

                </div>

                <p class="text-[11px] leading-5 text-[#8794a8]">
                    Authorized personnel only.
                </p>

            </section>

            {{-- <------------------------------------------- Brand Panel ------------------------------/> --}}

            @include('auth.partials.brand-panel')

        </div>

    </main>

    @include('auth.partials.password-toggle-script')

</body>

</html>
