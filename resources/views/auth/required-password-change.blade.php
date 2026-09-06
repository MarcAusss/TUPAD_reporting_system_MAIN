<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Change Password | {{ config('app.name', 'TUPAD Reporting System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-5">
                <div class="text-xs font-bold uppercase tracking-[0.12em] text-blue-700">Account Security</div>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">Set a new password</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    This account is using a temporary credential. You must replace it before accessing the TUPAD Reporting System.
                </p>
            </div>

            <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5 p-6">
                @csrf
                @method('PATCH')

                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-950">
                    Signed in as <span class="font-semibold">{{ $user->username }}</span>
                </div>

                <div>
                    <label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Temporary / Current Password <span class="text-red-600">*</span>
                    </label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    @error('current_password')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        New Password <span class="text-red-600">*</span>
                    </label>
                    <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    @error('password')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Confirm New Password <span class="text-red-600">*</span>
                    </label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                    Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.
                </div>

                <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white hover:bg-blue-800">
                    Change Password and Continue
                </button>
            </form>

            <div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm font-semibold text-slate-600 hover:text-slate-900">Sign out</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
