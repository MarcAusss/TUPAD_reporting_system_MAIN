<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Change Password | {{ config('app.name', 'TUPAD Reporting System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- TUPAD Reporting System favicon --}}
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon.png') }}">
</head>
<body class="min-h-screen bg-linear-to-br from-[#eef4fb] via-[#e7eef8] to-[#dfe6f0] text-[#10294f] antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6">
        <div class="grid w-full max-w-5xl grid-cols-1 gap-3 rounded-[28px] bg-white/80 p-3 shadow-2xl shadow-[#10294f]/10 ring-1 ring-white backdrop-blur md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">

            {{-- <------------------------------------------- Brand Panel ------------------------------/> --}}

            @include('auth.partials.brand-panel', [
                'eyebrow' => 'Account Security',
                'headline' => 'Secure your account before you continue.',
                'caption' => 'Replace your temporary credential with a private password only you know.',
            ])

            {{-- <------------------------------------------- Change Password Form ------------------------------/> --}}

            <section class="flex flex-col px-5 py-6 sm:px-8 sm:py-8">

                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white p-0.5 shadow-sm ring-1 ring-[#dfe6f0]">
                            <img src="{{ asset('images/mainlogo.jpg') }}" alt="TUPAD Reporting System logo" class="h-full w-full object-contain">
                        </div>
                        <div>
                            <div class="text-sm font-extrabold tracking-tight text-[#063b86]">TUPAD</div>
                            <div class="text-[11px] font-semibold text-[#6f7f98]">Reporting System</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#48617f] transition hover:bg-[#eef4fb] hover:text-[#063b86]">Sign out</button>
                    </form>
                </div>

                <div class="flex flex-1 flex-col justify-center py-8">

                    <h1 class="text-2xl font-bold tracking-tight text-[#10294f] sm:text-[28px]">Set a new password</h1>
                    <p class="mt-1.5 text-sm leading-6 text-[#6f7f98]">
                        This account is using a temporary credential. You must replace it before accessing the TUPAD Reporting System.
                    </p>

                    <div class="mt-5 flex items-center gap-2.5 rounded-xl bg-[#eaf2ff] px-3.5 py-2.5 text-sm text-[#17325c]">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-xs font-extrabold text-[#063b86] ring-1 ring-[#d9e3f0]">
                            {{ strtoupper(substr($user->username, 0, 1)) }}
                        </span>
                        <span>Signed in as <span class="font-semibold">{{ $user->username }}</span></span>
                    </div>

                    <form method="POST" action="{{ route('password.change.update') }}" class="mt-6 space-y-4">
                        @csrf
                        @method('PATCH')

                        @foreach ([
                            ['id' => 'current_password', 'label' => 'Temporary / Current Password', 'autocomplete' => 'current-password', 'minlength' => null],
                            ['id' => 'password', 'label' => 'New Password', 'autocomplete' => 'new-password', 'minlength' => 8],
                            ['id' => 'password_confirmation', 'label' => 'Confirm New Password', 'autocomplete' => 'new-password', 'minlength' => 8],
                        ] as $field)
                            <div>
                                <label for="{{ $field['id'] }}" class="mb-1.5 block text-xs font-semibold text-[#355378]">
                                    {{ $field['label'] }} <span class="text-[#b42318]">*</span>
                                </label>
                                <div class="relative">
                                    <input id="{{ $field['id'] }}" name="{{ $field['id'] }}" type="password" required
                                        @if ($field['minlength']) minlength="{{ $field['minlength'] }}" @endif
                                        autocomplete="{{ $field['autocomplete'] }}"
                                        class="block h-12 w-full rounded-xl border border-[#ccd7e6] bg-[#f8fbff] pl-4 pr-12 text-sm text-[#10294f] outline-none transition focus:border-[#1765d8] focus:bg-white focus:ring-4 focus:ring-[#1765d8]/15">
                                    <button type="button" data-toggle-password="{{ $field['id'] }}" aria-label="Show password"
                                        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-[#8794a8] transition hover:text-[#063b86]">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                @error($field['id'])
                                    <p class="mt-1.5 text-xs font-medium text-[#b42318]">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach

                        <p class="flex items-center gap-2 text-xs text-[#6f7f98]">
                            <svg class="h-4 w-4 text-[#1765d8]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4m0 4h.01"/></svg>
                            Use at least 8 characters.
                        </p>

                        <button type="submit" class="mt-2 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#063b86] px-5 text-sm font-semibold text-white shadow-lg shadow-[#063b86]/25 transition hover:bg-[#0b3170] focus:outline-none focus:ring-4 focus:ring-[#1765d8]/30">
                            Change Password and Continue
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </button>
                    </form>

                </div>

            </section>

        </div>
    </main>

    @include('auth.partials.password-toggle-script')
</body>
</html>
