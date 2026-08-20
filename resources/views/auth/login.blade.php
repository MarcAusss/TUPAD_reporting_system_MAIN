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

</head>

<body class="min-h-screen bg-slate-100">

    <main class="flex min-h-screen items-center justify-center px-4 py-8">

        <div class="w-full max-w-md">

            {{-- <------------------------------------------- System Identity ------------------------------/> --}}

            <div class="mb-6 text-center">

                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-slate-900 text-xl font-bold text-white shadow-sm">
                    T
                </div>

                <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900">
                    TUPAD Reporting System
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Department of Labor and Employment
                </p>

            </div>

            {{-- <------------------------------------------- Login Card ------------------------------/> --}}

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-6 py-5">

                    <h2 class="text-base font-semibold text-slate-900">
                        Sign in
                    </h2>

                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Enter your authorized system account credentials.
                    </p>

                </div>

                <form
                    method="POST"
                    action="{{ route('login.store') }}"
                    class="space-y-5 p-6"
                >

                    @csrf

                    {{-- <------------------------------------------- Username ------------------------------/> --}}

                    <div>

                        <label
                            for="username"
                            class="mb-2 block text-xs font-semibold text-slate-700"
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
                            class="block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            placeholder="Enter username"
                        >

                        @error('username')

                            <p class="mt-2 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>

                    {{-- <------------------------------------------- Password ------------------------------/> --}}

                    <div>

                        <label
                            for="password"
                            class="mb-2 block text-xs font-semibold text-slate-700"
                        >
                            Password
                        </label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="block h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-slate-500 focus:ring-2 focus:ring-slate-200"
                            placeholder="Enter password"
                        >

                        @error('password')

                            <p class="mt-2 text-xs font-medium text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>

                    {{-- <------------------------------------------- Remember Me ------------------------------/> --}}

                    <label class="flex cursor-pointer items-center gap-2">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                        >

                        <span class="text-xs text-slate-600">
                            Remember me
                        </span>

                    </label>

                    {{-- <------------------------------------------- Submit ------------------------------/> --}}

                    <button
                        type="submit"
                        class="flex h-11 w-full items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                    >
                        Sign in
                    </button>

                </form>

            </section>

            <p class="mt-5 text-center text-[11px] leading-5 text-slate-400">
                Authorized personnel only. System activities may be recorded for audit purposes.
            </p>

        </div>

    </main>

</body>

</html>