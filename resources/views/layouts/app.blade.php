<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'TUPAD Reporting System') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="tupad-shell min-h-screen text-[#0f2347] antialiased"
    data-notification-feed-url="{{ url('/notifications/feed') }}"
    data-notification-user-id="{{ auth()->id() }}"
    data-notification-poll-ms="10000">
    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-100 focus:rounded-lg focus:bg-[#063b86] focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg">
        Skip to main content
    </a>
    @php
        $user = auth()->user();

        $navClass = fn(bool $active) => $active ? 'tupad-nav-active' : 'tupad-nav-idle';

        $workspaceLabel = match (true) {
            $user->isFocal() => 'Focal Fund Monitoring Workspace',
            $user->isTc() => 'TUPAD Coordinator Workspace',
            $user->isAdmin() => 'Administrator Workspace',
            default => 'TUPAD Workspace',
        };

        $notificationSummary = app(\App\Services\Notifications\NotificationCenterService::class)->build($user);
        $notificationCount = (int) ($notificationSummary['total_count'] ?? 0);
    @endphp

    <div class="min-h-screen">
        {{-- Sidebar --}}
        <aside id="sidebar"
            class="tupad-desktop-sidebar fixed inset-y-0 left-0 z-50 flex w-63 -translate-x-full flex-col border-r border-[#dfe6f0] bg-white transition-transform duration-200 lg:translate-x-0">
            <div class="tupad-sidebar-header flex h-[78px] w-full shrink-0 items-center border-b border-[#e4eaf2] px-5">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="flex h-13 w-11 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white">
                        <img src="{{ asset('images/mainlogo.jpg') }}" alt="TUPAD Reporting System logo" class="h-full w-full object-contain">
                    </div>

                    <div>
                        <div class="text-[22px] font-extrabold leading-none tracking-tight text-[#071d44]">TUPAD</div>
                        <div class="mt-1 text-[13px] font-semibold tracking-tight text-[#17325c]">Reporting System</div>
                    </div>
                </a>

                <button type="button" id="sidebarClose"
                    class="ml-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                    aria-label="Close sidebar" aria-controls="sidebar">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>

            <div
                class="tupad-sidebar-scroll tupad-scrollbar w-full min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-3 py-5">
                <nav class="w-full min-w-0 space-y-1.5" aria-label="Primary navigation">

                    <div class="tupad-nav-section !pt-0">
                        Main
                    </div>

                    <a href="{{ route('dashboard') }}"
                        class="{{ $navClass(request()->routeIs('dashboard')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                        <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        </svg>

                        <span>Dashboard</span>
                    </a>

                    {{-- Role-specific navigation is kept in focused partials so the main shell remains stable. --}}
                    @if ($user->isFocal())
                        @include('layouts.partials.sidebar-focal')
                    @elseif ($user->isTc() || $user->isAdmin())
                        @include('layouts.partials.sidebar-project-operations')
                    @endif

                </nav>
            </div>

            <div class="tupad-sidebar-footer w-full shrink-0 p-4">
                <div class="w-full min-w-0 rounded-xl border border-[#dfe6f0] bg-[#f8fbff] p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[.08em] text-[#6f7f98]">Signed in as</div>
                    <div class="mt-2 truncate text-[13px] font-bold text-[#10294f]">{{ $user->name }}</div>
                    <div class="mt-0.5 text-[11px] text-[#73829a]">{{ $user->roleLabel() }}</div>
                    @if ($user->isTc())
                        <div class="mt-0.5 truncate text-[11px] text-[#73829a]">{{ $user->assignedProvince?->name ?? 'Province not assigned' }}</div>

                        <a href="{{ route('account.show') }}"
                            class="mt-3 flex h-9 w-full items-center justify-center rounded-lg border border-[#ccd7e6] bg-white text-[12px] font-semibold text-[#17325c] transition hover:bg-[#eef4fb] {{ request()->routeIs('account.*') ? 'ring-2 ring-blue-100' : '' }}">
                            My Account
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit"
                            class="flex h-9 w-full items-center justify-center rounded-lg border border-[#ccd7e6] bg-white text-[12px] font-semibold text-[#17325c] transition hover:bg-[#eef4fb]">
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-slate-950/35 lg:hidden"></div>

        {{-- Main shell --}}
        <div class="tupad-main-shell min-h-screen lg:pl-[252px]">
            <header
                class="tupad-topbar sticky top-0 z-30 flex h-[78px] items-center border-b border-[#dfe6f0] bg-white/95 px-4 backdrop-blur md:px-6 xl:px-8">
                <button type="button" id="sidebarToggle"
                    class="mr-3 flex h-10 w-10 items-center justify-center rounded-lg text-[#17325c] hover:bg-slate-100 lg:hidden"
                    aria-label="Open sidebar" aria-controls="sidebar" aria-expanded="false">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16"></path>
                        <path d="M4 12h16"></path>
                        <path d="M4 18h16"></path>
                    </svg>
                </button>

                <div class="flex min-w-0 flex-1 items-center">
                    <form method="GET"
                        action="{{ Route::has('search.index') ? route('search.index') : route('dashboard') }}"
                        role="search" class="hidden w-full max-w-[520px] md:block">
                        <label for="global-search" class="sr-only">Search the TUPAD Reporting System</label>
                        <div
                            class="tupad-input flex h-11 items-center rounded-lg px-3.5 focus-within:ring-2 focus-within:ring-[#1765d8]/30">
                            <svg class="h-[18px] w-[18px] shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-3.5-3.5"></path>
                            </svg>
                            <input id="global-search" name="q" type="search"
                                value="{{ request()->routeIs('search.index') ? request('q') : '' }}"
                                placeholder="Search project, ADL, location, project code..." autocomplete="off"
                                class="h-full w-full bg-transparent pl-3 text-[12px] text-[#233f67] outline-none placeholder:text-[#8290a5]">
                        </div>
                    </form>
                </div>

                <div class="ml-4 flex items-center gap-4">
                    <div class="hidden text-right xl:block">
                        <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-400">
                            Current Workspace
                        </div>
                        <div class="mt-0.5 text-[11px] font-semibold text-[#355378]">
                            {{ $workspaceLabel }}
                        </div>
                    </div>

                    <a href="{{ route('notifications.index') }}"
                        data-notification-bell
                        class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#dfe6f0] bg-white text-[#355378] transition hover:bg-slate-50 {{ request()->routeIs('notifications.*') ? 'ring-2 ring-blue-100' : '' }}"
                        aria-label="Notifications{{ $notificationCount > 0 ? ': '.$notificationCount.' active item(s)' : '' }}">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M10 21h4"></path>
                        </svg>
                        <span data-notification-badge
                            class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-[#b42318] px-1.5 py-0.5 text-[9px] font-extrabold leading-4 text-white ring-2 ring-white {{ $notificationCount > 0 ? '' : 'hidden' }}">
                            {{ $notificationCount > 99 ? '99+' : $notificationCount }}
                        </span>
                        <span data-notification-live-label class="sr-only">
                            {{ $notificationCount > 0 ? $notificationCount.' active notification item(s)' : 'No active notifications' }}
                        </span>
                    </a>

                    <div class="hidden h-9 w-px bg-[#e0e7f0] sm:block"></div>

                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#e7eef8] text-xs font-extrabold text-[#063b86] ring-1 ring-[#d9e3f0]">
                            {{ $user->initials() }}
                        </div>
                        <div class="hidden min-w-0 md:block">
                            <div class="max-w-[180px] truncate text-[12px] font-bold text-[#10294f]">
                                {{ $user->name }}</div>
                            <div class="mt-0.5 text-[10px] text-[#6f7f98]">{{ $user->roleLabel() }}</div>
                        </div>
                        {{-- <svg class="hidden h-4 w-4 text-[#48617f] md:block" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg> --}}
                    </div>
                </div>
            </header>

            <div class="border-b border-[#e6ebf2] bg-white px-4 py-3 md:hidden">
                <form method="GET"
                    action="{{ Route::has('search.index') ? route('search.index') : route('dashboard') }}"
                    role="search">
                    <label for="global-search-mobile" class="sr-only">
                        Search the TUPAD Reporting System
                    </label>

                    <div class="tupad-input flex h-10 items-center rounded-lg px-3">
                        <svg class="h-[17px] w-[17px] shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.5-3.5"></path>
                        </svg>

                        <input id="global-search-mobile" name="q" type="search"
                            value="{{ request()->routeIs('search.index') ? request('q') : '' }}"
                            placeholder="Search project, ADL, location..." autocomplete="off"
                            class="h-full w-full bg-transparent pl-3 text-[12px] outline-none">
                    </div>
                </form>
            </div>

            <main id="main-content" tabindex="-1" class="tupad-main-content mx-auto w-full max-w-[1660px] p-4 md:p-5 xl:p-6">

                {{-- Global success/error feedback --}}
                @if (session('success'))
                    <div class="tupad-feedback tupad-feedback-success mb-5" role="status">
                        <div class="tupad-feedback-icon">✓</div>

                        <div>
                            <div class="tupad-feedback-title">
                                Action completed
                            </div>

                            <div class="tupad-feedback-message">
                                {{ session('success') }}
                            </div>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="tupad-feedback tupad-feedback-error mb-5" role="alert">
                        <div class="tupad-feedback-icon">!</div>

                        <div class="min-w-0">
                            <div class="tupad-feedback-title">
                                Please review the highlighted information
                            </div>

                            <div class="tupad-feedback-message">
                                {{ $errors->first() }}
                            </div>

                            @if ($errors->count() > 1)
                                <div class="mt-1 text-[11px] font-medium opacity-75">
                                    {{ $errors->count() - 1 }} additional validation issue(s) are shown in the form.
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
                @yield('content')
            </main>

            <footer
                class="mx-auto flex w-full max-w-[1660px] flex-col gap-2 px-5 pb-6 pt-1 text-center text-[10px] text-[#8794a8] sm:flex-row sm:justify-between sm:text-left xl:px-6">
                <span>Department of Labor and Employment · TUPAD Reporting System</span>
                <span>{{ now()->format('Y') }} · Internal Government Information System</span>
            </footer>
        </div>
    </div>

    <div data-notification-toast-region
        class="pointer-events-none fixed right-4 top-24 z-[90] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2"
        aria-live="polite" aria-atomic="false"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                sidebar?.classList.remove('-translate-x-full');
                sidebarOverlay?.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                sidebarToggle?.setAttribute('aria-expanded', 'true');
                sidebarClose?.focus();
            }

            function closeSidebar() {
                sidebar?.classList.add('-translate-x-full');
                sidebarOverlay?.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                sidebarToggle?.setAttribute('aria-expanded', 'false');
            }

            sidebarToggle?.addEventListener('click', openSidebar);
            sidebarClose?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', function(event) {
                if (
                    event.key === 'Escape' &&
                    !sidebarOverlay?.classList.contains('hidden')
                ) {
                    closeSidebar();
                    sidebarToggle?.focus();
                }
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebarOverlay?.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                }
            });
        });
    </script>

    @stack('scripts')
</body>

</html>
