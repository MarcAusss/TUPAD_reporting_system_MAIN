<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'Dashboard') | {{ config('app.name', 'TUPAD Reporting System') }}
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-slate-800">

    <div class="min-h-screen">

        {{-- <------------------------------------------- Sidebar ------------------------------/> --}}
        <aside id="sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:translate-x-0">

            {{-- <------------------------------------------- Sidebar Header ------------------------------/> --}}
            <div class="flex h-16 items-center border-b border-slate-200 px-4">

                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white">
                    T
                </div>

                <div class="ml-3 min-w-0">

                    <div class="text-sm font-bold tracking-wide text-slate-900">
                        TUPAD
                    </div>

                    <div class="text-xs text-slate-500">
                        Reporting System
                    </div>

                </div>

                <button type="button" id="sidebarClose"
                    class="ml-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                    aria-label="Close sidebar">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>

            </div>

            {{-- <------------------------------------------- Navigation ------------------------------/> --}}
            <div class="flex-1 overflow-y-auto p-3">

                {{-- <------------------------------------------- Main Navigation ------------------------------/> --}}
                <div>

                    <div class="px-3 pb-2 text-[10px] font-semibold tracking-[0.14em] text-slate-400">
                        MAIN
                    </div>

                    <nav class="space-y-1">

                        {{-- Dashboard --}}
                        <a href="{{ route('dashboard') }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                            {{ request()->routeIs('dashboard')
                                ? 'bg-slate-100 text-slate-900'
                                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                                <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                                <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                                <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                            </svg>

                            <span>
                                Dashboard
                            </span>
                        </a>

                        {{-- ADL Management --}}
                        @if (auth()->user()->isAdmin() || auth()->user()->isFocal())
                            <a href="{{ route('adl.index') }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
                                {{ request()->routeIs('adl.*')
                                    ? 'bg-slate-100 text-slate-900'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M4 7h16"></path>
                                    <path d="M4 12h16"></path>
                                    <path d="M4 17h10"></path>
                                </svg>

                                <span>
                                    ADL Management
                                </span>
                            </a>
                        @endif

                        {{-- <------------------------------------------- Project Management ------------------------------/> --}}

                        @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                            <a href="{{ route('projects.index') }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
            {{ request()->routeIs('projects.*')
                ? 'bg-slate-100 text-slate-900'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M8 6h13"></path>
                                    <path d="M8 12h13"></path>
                                    <path d="M8 18h13"></path>
                                    <path d="M3 6h.01"></path>
                                    <path d="M3 12h.01"></path>
                                    <path d="M3 18h.01"></path>
                                </svg>

                                <span>
                                    Project Management
                                </span>
                            </a>

                            <a href="{{ route('project-draft-reviews.index') }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
            {{ request()->routeIs('project-draft-reviews.*')
                ? 'bg-slate-100 text-slate-900'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M9 11l3 3L22 4"></path>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                                </svg>

                                <span>
                                    GIP Draft Reviews
                                </span>
                            </a>
                        @elseif(auth()->user()->isGip())
                            <a href="{{ route('project-drafts.index') }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium
            {{ request()->routeIs('project-drafts.*')
                ? 'bg-slate-100 text-slate-900'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M4 4h16v16H4z"></path>
                                    <path d="M8 9h8"></path>
                                    <path d="M8 13h6"></path>
                                </svg>

                                <span>
                                    Project Drafts
                                </span>
                            </a>
                        @endif

                        {{-- Reports --}}
                        <button type="button" disabled
                            class="flex w-full cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M4 19V9"></path>
                                <path d="M10 19V5"></path>
                                <path d="M16 19v-7"></path>
                                <path d="M22 19H2"></path>
                            </svg>

                            <span>
                                Reports
                            </span>
                        </button>

                    </nav>

                </div>

                {{-- <------------------------------------------- System Navigation ------------------------------/> --}}
                <div class="mt-8">

                    <div class="px-3 pb-2 text-[10px] font-semibold tracking-[0.14em] text-slate-400">
                        SYSTEM
                    </div>

                    <nav class="space-y-1">

                        {{-- User Management --}}
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('users.index') }}"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>

                                <span>
                                    User Management
                                </span>
                            </a>
                        @endif

                        {{-- Settings --}}
                        @if (auth()->user()->isAdmin())
                            <button type="button" disabled
                                class="flex w-full cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-400">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path
                                        d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.2 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H2.4a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.2 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 8.6 4.2a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V2.4a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 .4 1.1 1.7 1.7 0 0 0 1 .6 1.7 1.7 0 0 0 1.88-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.8 8.6a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.1.4 1.7 1.7 0 0 0-1 1Z">
                                    </path>
                                </svg>

                                <span>
                                    Settings
                                </span>
                            </button>
                        @endif

                    </nav>

                </div>

            </div>

            {{-- <------------------------------------------- Sidebar User ------------------------------/> --}}
            <div class="border-t border-slate-200 p-4">

                <div class="flex items-center">

                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-700">
                        {{ auth()->user()->initials() }}
                    </div>

                    <div class="ml-3 min-w-0">

                        <div class="truncate text-xs font-semibold text-slate-800">
                            {{ auth()->user()->name }}
                        </div>

                        <div class="text-[11px] text-slate-500">
                            {{ auth()->user()->roleLabel() }}
                        </div>

                    </div>

                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">

                    @csrf

                    <button type="submit"
                        class="flex w-full items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                        Sign Out
                    </button>

                </form>

            </div>

        </aside>

        {{-- <------------------------------------------- Mobile Overlay ------------------------------/> --}}
        <div id="sidebarOverlay" class="fixed inset-0 z-40 hidden bg-slate-950/30 lg:hidden"></div>

        {{-- <------------------------------------------- Main Application ------------------------------/> --}}
        <div class="min-h-screen lg:pl-64">

            {{-- <------------------------------------------- Topbar ------------------------------/> --}}
            <header
                class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur md:px-6">

                <div class="flex min-w-0 flex-1 items-center">

                    <button type="button" id="sidebarToggle"
                        class="mr-2 flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 lg:hidden"
                        aria-label="Open sidebar">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M4 6h16"></path>
                            <path d="M4 12h16"></path>
                            <path d="M4 18h16"></path>
                        </svg>
                    </button>

                    <div
                        class="hidden w-full max-w-sm items-center rounded-lg border border-slate-200 bg-slate-50 px-3 md:flex">

                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m20 20-3.5-3.5"></path>
                        </svg>

                        <input type="search" placeholder="Search..."
                            class="h-9 w-full bg-transparent pl-2 text-xs text-slate-700 outline-none placeholder:text-slate-400">

                    </div>

                </div>

                <div class="flex items-center gap-2">

                    <button type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                        aria-label="Notifications">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M13.7 21a2 2 0 0 1-3.4 0"></path>
                        </svg>
                    </button>

                    <div class="flex items-center">

                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-700">
                            {{ auth()->user()->initials() }}
                        </div>

                        <div class="ml-2 hidden md:block">

                            <div class="text-xs font-semibold text-slate-800">
                                {{ auth()->user()->name }}
                            </div>

                            <div class="text-[11px] text-slate-500">
                                {{ auth()->user()->roleLabel() }}
                            </div>

                        </div>

                    </div>

                </div>

            </header>

            {{-- <------------------------------------------- Page Content ------------------------------/> --}}
            <main class="mx-auto w-full max-w-[1600px] p-4 md:p-6 lg:p-7">

                @yield('content')

            </main>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarClose = document.getElementById('sidebarClose');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function openSidebar() {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            sidebarToggle?.addEventListener('click', openSidebar);
            sidebarClose?.addEventListener('click', closeSidebar);
            sidebarOverlay?.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    sidebarOverlay.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                }
            });
        });
    </script>

    @stack('scripts')

</body>

</html>
