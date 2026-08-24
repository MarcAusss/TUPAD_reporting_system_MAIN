<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') | {{ config('app.name', 'TUPAD Reporting System') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tupad-shell min-h-screen text-[#0f2347] antialiased">
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-[#063b86] focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:text-white focus:shadow-lg"
    >
        Skip to main content
    </a>
    @php
        $user = auth()->user();

        $navClass = fn (bool $active) => $active
            ? 'tupad-nav-active'
            : 'tupad-nav-idle';
    @endphp

    <div class="min-h-screen">
        {{-- Sidebar --}}
        <aside
            id="sidebar"
            class="tupad-desktop-sidebar fixed inset-y-0 left-0 z-50 flex w-[252px] -translate-x-full flex-col border-r border-[#dfe6f0] bg-white transition-transform duration-200 lg:translate-x-0"
        >
            <div class="flex h-[98px] shrink-0 items-center border-b border-[#e4eaf2] px-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="grid h-11 w-11 grid-cols-2 gap-[3px] rounded-xl bg-[#063b86] p-2.5 shadow-sm">
                        <span class="rounded-[2px] bg-white"></span>
                        <span class="rounded-[2px] bg-white/75"></span>
                        <span class="rounded-[2px] bg-white/75"></span>
                        <span class="rounded-[2px] bg-white"></span>
                    </div>

                    <div>
                        <div class="text-[22px] font-extrabold leading-none tracking-tight text-[#071d44]">TUPAD</div>
                        <div class="mt-1 text-[13px] font-semibold tracking-tight text-[#17325c]">Reporting System</div>
                    </div>
                </a>

                <button
                    type="button"
                    id="sidebarClose"
                    class="ml-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                    aria-label="Close sidebar"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="tupad-scrollbar flex-1 overflow-y-auto px-3 py-5">
                <nav class="space-y-1.5">
                    <a href="{{ route('dashboard') }}" class="{{ $navClass(request()->routeIs('dashboard')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                        <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    @if($user->isAdmin() || $user->isFocal())
                        <a href="{{ route('adl.index') }}" class="{{ $navClass(request()->routeIs('adl.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="8" r="3"></circle><path d="M5 20a7 7 0 0 1 14 0"></path><path d="M4 4v6"></path><path d="M2 7h4"></path>
                            </svg>
                            <span>ADL</span>
                        </a>
                    @endif

                    @if($user->isAdmin() || $user->isTc())
                        <a href="{{ route('projects.index') }}" class="{{ $navClass(request()->routeIs('projects.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M3 12h18"></path>
                            </svg>
                            <span>Projects</span>
                        </a>

                        <a href="{{ route('project-draft-reviews.index') }}" class="{{ $navClass(request()->routeIs('project-draft-reviews.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                            </svg>
                            <span>GIP Draft Reviews</span>
                        </a>
                    @elseif($user->isGip())
                        <a href="{{ route('project-drafts.index') }}" class="{{ $navClass(request()->routeIs('project-drafts.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M5 3h10l4 4v14H5z"></path><path d="M15 3v5h5"></path><path d="M8 13h8"></path><path d="M8 17h6"></path>
                            </svg>
                            <span>Project Drafts</span>
                        </a>
                    @endif

                    @if($user->isAdmin() || $user->isFocal())
                        <a href="{{ route('payments.index') }}" class="{{ $navClass(request()->routeIs('payments.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18"></path><path d="M7 15h4"></path>
                            </svg>
                            <span>Payment Queue</span>
                        </a>
                    @endif

                    @if($user->isAdmin() || $user->isTc() || $user->isFocal())
                        <a href="{{ route('reports.index') }}" class="{{ $navClass(request()->routeIs('reports.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 19V9"></path><path d="M10 19V5"></path><path d="M16 19v-7"></path><path d="M22 19H2"></path>
                            </svg>
                            <span>Reports</span>
                        </a>
                    @endif

                    @if($user->isAdmin())
                        <a href="{{ route('users.index') }}" class="{{ $navClass(request()->routeIs('users.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-[19px] w-[19px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                            </svg>
                            <span>Users</span>
                        </a>
                    @endif
                </nav>
            </div>

            <div class="p-4">
                <div class="rounded-xl border border-[#dfe6f0] bg-[#f8fbff] p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[.08em] text-[#6f7f98]">Signed in as</div>
                    <div class="mt-2 truncate text-[13px] font-bold text-[#10294f]">{{ $user->name }}</div>
                    <div class="mt-0.5 text-[11px] text-[#73829a]">{{ $user->roleLabel() }}</div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button type="submit" class="flex h-9 w-full items-center justify-center rounded-lg border border-[#ccd7e6] bg-white text-[12px] font-semibold text-[#17325c] transition hover:bg-[#eef4fb]">
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-slate-950/35 lg:hidden"></div>

        {{-- Main shell --}}
        <div class="min-h-screen lg:pl-[252px]">
            <header class="sticky top-0 z-30 flex h-[86px] items-center border-b border-[#dfe6f0] bg-white/95 px-4 backdrop-blur md:px-6 xl:px-8">
                <button type="button" id="sidebarToggle" class="mr-3 flex h-10 w-10 items-center justify-center rounded-lg text-[#17325c] hover:bg-slate-100 lg:hidden" aria-label="Open sidebar">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path></svg>
                </button>

                <div class="flex min-w-0 flex-1 items-center">
                    <form method="GET" action="{{ Route::has('search.index') ? route('search.index') : route('dashboard') }}" role="search" class="hidden w-full max-w-[520px] md:block">
                        <label for="global-search" class="sr-only">Search the TUPAD Reporting System</label>
                        <div class="tupad-input flex h-11 items-center rounded-lg px-3.5 focus-within:ring-2 focus-within:ring-[#1765d8]/30">
                            <svg class="h-[18px] w-[18px] shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m20 20-3.5-3.5"></path>
                            </svg>
                            <input
                                id="global-search"
                                name="q"
                                type="search"
                                value="{{ request()->routeIs('search.index') ? request('q') : '' }}"
                                placeholder="Search project, ADL, location, project code..."
                                autocomplete="off"
                                class="h-full w-full bg-transparent pl-3 text-[12px] text-[#233f67] outline-none placeholder:text-[#8290a5]"
                            >
                        </div>
                    </form>
                </div>

                <div class="ml-4 flex items-center gap-4">
                    <button
                        type="button"
                        disabled
                        title="Notifications are not yet enabled"
                        aria-label="Notifications are not yet enabled"
                        class="relative flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-full text-slate-300"
                    >
                        <svg class="h-[21px] w-[21px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M13.7 21a2 2 0 0 1-3.4 0"></path>
                        </svg>
                    </button>

                    <div class="hidden h-9 w-px bg-[#e0e7f0] sm:block"></div>

                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#e7eef8] text-xs font-extrabold text-[#063b86] ring-1 ring-[#d9e3f0]">
                            {{ $user->initials() }}
                        </div>
                        <div class="hidden min-w-0 md:block">
                            <div class="max-w-[180px] truncate text-[12px] font-bold text-[#10294f]">{{ $user->name }}</div>
                            <div class="mt-0.5 text-[10px] text-[#6f7f98]">{{ $user->roleLabel() }}</div>
                        </div>
                        <svg class="hidden h-4 w-4 text-[#48617f] md:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg>
                    </div>
                </div>
            </header>

            <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1660px] p-4 md:p-5 xl:p-6">
                @yield('content')
            </main>

            <footer class="mx-auto flex w-full max-w-[1660px] flex-col gap-2 px-5 pb-6 pt-1 text-center text-[10px] text-[#8794a8] sm:flex-row sm:justify-between sm:text-left xl:px-6">
                <span>Department of Labor and Employment · TUPAD Reporting System</span>
                <span>{{ now()->format('Y') }} · Internal Government Information System</span>
            </footer>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                sidebar?.classList.remove('-translate-x-full');
                sidebarOverlay?.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
                sidebarClose?.focus();
            }

            function closeSidebar() {
                sidebar?.classList.add('-translate-x-full');
                sidebarOverlay?.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            sidebarToggle?.addEventListener('click', openSidebar);
            sidebarClose?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);

            document.addEventListener('keydown', function (event) {
                if (
                    event.key === 'Escape'
                    && !sidebarOverlay?.classList.contains('hidden')
                ) {
                    closeSidebar();
                    sidebarToggle?.focus();
                }
            });

            window.addEventListener('resize', function () {
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
