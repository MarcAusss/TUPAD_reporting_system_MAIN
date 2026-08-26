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
            $user->isGip() => 'GIP Encoding Workspace',
            $user->isAdmin() => 'Administrator Workspace',
            default => 'TUPAD Workspace',
        };
    @endphp

    <div class="min-h-screen">
        {{-- Sidebar --}}
        <aside id="sidebar"
            class="tupad-desktop-sidebar fixed inset-y-0 left-0 z-50 flex w-63 -translate-x-full flex-col border-r border-[#dfe6f0] bg-white transition-transform duration-200 lg:translate-x-0">
            <div class="tupad-sidebar-header flex h-24.5 w-full shrink-0 items-center border-b border-[#e4eaf2] px-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                    <div class="grid h-11 w-11 grid-cols-2 gap-0.75 rounded-xl bg-[#063b86] p-2.5 shadow-sm">
                        <span class="rounded-xs bg-white"></span>
                        <span class="rounded-xs bg-white/75"></span>
                        <span class="rounded-xs bg-white/75"></span>
                        <span class="rounded-xs bg-white"></span>
                    </div>

                    <div>
                        <div class="text-[22px] font-extrabold leading-none tracking-tight text-[#071d44]">TUPAD</div>
                        <div class="mt-1 text-[13px] font-semibold tracking-tight text-[#17325c]">Reporting System</div>
                    </div>
                </a>

                <button type="button" id="sidebarClose"
                    class="ml-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                    aria-label="Close sidebar">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>

            <div
                class="tupad-sidebar-scroll tupad-scrollbar w-full min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-3 py-5">
                <nav class="w-full min-w-0 space-y-1.5" aria-label="Primary navigation">

                    <div class="tupad-nav-section pt-0!">
                        Main
                    </div>

                    <a href="{{ route('dashboard') }}"
                        class="{{ $navClass(request()->routeIs('dashboard')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                        <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8">
                            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                        </svg>

                        <span>Dashboard</span>
                    </a>

                    {{-- =========================================================
                        FOCAL WORKSPACE
                    ========================================================== --}}
                    @if ($user->isFocal())

                        <div class="tupad-nav-section">
                            Project Management
                        </div>

                        <a href="{{ route('projects.index') }}"
                            class="{{ $navClass(
                                request()->routeIs('projects.index') ||
                                    request()->routeIs('projects.create') ||
                                    request()->routeIs('projects.show'),
                            ) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <rect x="3" y="7" width="18" height="13" rx="2"></rect>
                                <path d="M3 12h18"></path>
                            </svg>

                            <span>Projects</span>
                        </a>

                        <div class="tupad-nav-section">
                            Project Summary
                        </div>

                        <a href="{{ route('project-summary.index') }}"
                            class="{{ $navClass(request()->routeIs('project-summary.*') || request()->routeIs('projects.summary')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M4 19V9"></path>
                                <path d="M10 19V5"></path>
                                <path d="M16 19v-7"></path>
                                <path d="M22 19H2"></path>
                            </svg>

                            <span>Provincial Summary</span>
                        </a>

                        <div class="tupad-nav-section">
                            Fund Management
                        </div>

                        <a href="{{ route('adl.index') }}"
                            class="{{ $navClass(request()->routeIs('adl.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M12 3v18"></path>
                                <path
                                    d="M17 7.5C17 5.57 14.76 4 12 4S7 5.57 7 7.5 9.24 11 12 11s5 1.57 5 3.5S14.76 18 12 18s-5-1.57-5-3.5">
                                </path>
                            </svg>

                            <span>ADL</span>
                        </a>

                        <div class="tupad-nav-section">
                            Monitoring
                        </div>

                        @if (Route::has('fund-monitoring.per-adl-current'))
                            <a href="{{ route('fund-monitoring.per-adl-current') }}"
                                class="{{ $navClass(request()->routeIs('fund-monitoring.per-adl-current')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75 shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path d="M4 19V9"></path>
                                    <path d="M10 19V5"></path>
                                    <path d="M16 19v-7"></path>
                                    <path d="M22 19H2"></path>
                                </svg>
                                <span>PER ADL (Current)</span>
                            </a>
                        @endif

                        @if (Route::has('fund-monitoring.summary'))
                            <a href="{{ route('fund-monitoring.summary') }}"
                                class="{{ $navClass(request()->routeIs('fund-monitoring.summary')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                    <path d="M8 9h8"></path>
                                    <path d="M8 13h5"></path>
                                    <path d="M8 17h7"></path>
                                </svg>
                                <span>Summary</span>
                            </a>
                        @endif

                        @if (Route::has('fund-monitoring.summary-current'))
                            <a href="{{ route('fund-monitoring.summary-current') }}"
                                class="{{ $navClass(request()->routeIs('fund-monitoring.summary-current')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75 shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 7v5l3 2"></path>
                                </svg>
                                <span>Summary (Current)</span>
                            </a>
                        @endif

                        @if (Route::has('fund-monitoring.per-province-current'))
                            <a href="{{ route('fund-monitoring.per-province-current') }}"
                                class="{{ $navClass(request()->routeIs('fund-monitoring.per-province-current')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75 shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path d="M3 21h18"></path>
                                    <path d="M5 21V9l7-5 7 5v12"></path>
                                    <path d="M9 21v-6h6v6"></path>
                                </svg>
                                <span>Per Province (Current)</span>
                            </a>
                        @endif

                        <div class="tupad-nav-section">
                            Payment
                        </div>

                        <a href="{{ route('payments.index') }}"
                            class="{{ $navClass(request()->routeIs('payments.*') || request()->routeIs('projects.payment.*')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75 shrink-0" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                <path d="M3 10h18"></path>
                                <path d="M7 15h4"></path>
                            </svg>
                            <span>Payment of Wages</span>
                        </a>

                        <div class="tupad-nav-section">
                            Reporting
                        </div>

                        <a href="{{ route('reports.index') }}"
                            class="{{ $navClass(request()->routeIs('reports.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M4 19V9"></path>
                                <path d="M10 19V5"></path>
                                <path d="M16 19v-7"></path>
                                <path d="M22 19H2"></path>
                            </svg>
                            <span>Reports</span>
                        </a>

                        {{-- =========================================================
                        TC / ADMIN PROJECT WORKSPACE
                    ========================================================== --}}
                    @elseif($user->isTc() || $user->isAdmin())
                        @if ($user->isAdmin())
                            <div class="tupad-nav-section">
                                Fund Management
                            </div>

                            <a href="{{ route('adl.index') }}"
                                class="{{ $navClass(request()->routeIs('adl.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M12 3v18"></path>
                                    <path
                                        d="M17 7.5C17 5.57 14.76 4 12 4S7 5.57 7 7.5 9.24 11 12 11s5 1.57 5 3.5S14.76 18 12 18s-5-1.57-5-3.5">
                                    </path>
                                </svg>
                                <span>ADL</span>
                            </a>
                        @endif

                        <div class="tupad-nav-section">
                            Project Management
                        </div>

                        <a href="{{ route('projects.index') }}"
                            class="{{ $navClass(
                                request()->routeIs('projects.index') ||
                                    request()->routeIs('projects.create') ||
                                    request()->routeIs('projects.show'),
                            ) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <rect x="3" y="7" width="18" height="13" rx="2"></rect>
                                <path d="M3 12h18"></path>
                            </svg>
                            <span>Projects</span>
                        </a>

                        <a href="{{ route('project-draft-reviews.index') }}"
                            class="{{ $navClass(request()->routeIs('project-draft-reviews.*')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75 shrink-0" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                            </svg>
                            <span>GIP Draft Reviews</span>
                        </a>

                        <div class="tupad-nav-section">
                            Project Summary
                        </div>

                        <a href="{{ route('project-summary.index') }}"
                            class="{{ $navClass(request()->routeIs('project-summary.*') || request()->routeIs('projects.summary')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M4 19V9"></path>
                                <path d="M10 19V5"></path>
                                <path d="M16 19v-7"></path>
                                <path d="M22 19H2"></path>
                            </svg>

                            <span>Provincial Summary</span>
                        </a>

                        <div class="tupad-nav-section">
                            Project Workflow
                        </div>

                        @php
                            $workflowQueue = request()->route('queue');
                        @endphp

                        @foreach ([
        'tssd-evaluation' => ['TSSD Evaluation', 'M9 11l2 2 4-4 M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18'],
        'for-compliance' => ['For Compliance', 'M12 9v4 M12 17h.01 M5 4h14v16H5z'],
        'for-approval' => ['For Approval', 'M7 12l3 3 7-7 M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1'],
        'implementation' => ['Implementation', 'M4 21h16 M6 21V9l6-4 6 4v12 M9 14h6'],
        'post-documents' => ['Post-Documents', 'M5 3h10l4 4v14H5z M15 3v5h5 M8 13h8 M8 17h6'],
        'release-of-assistance' => ['Release of Assistance', 'M3 12h18 M15 6l6 6-6 6 M9 6H4v12h5'],
    ] as $queueKey => [$queueLabel, $queueIcon])
                            <a href="{{ route('project-workflow.index', ['queue' => $queueKey]) }}"
                                class="{{ $navClass(request()->routeIs('project-workflow.index') && $workflowQueue === $queueKey) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75 shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path d="{{ $queueIcon }}"></path>
                                </svg>
                                <span>{{ $queueLabel }}</span>
                            </a>
                        @endforeach

                        @if ($user->isAdmin())
                            <div class="tupad-nav-section">
                                Payment
                            </div>

                            <a href="{{ route('payments.index') }}"
                                class="{{ $navClass(request()->routeIs('payments.*')) }} flex min-h-11 items-center gap-3 rounded-lg px-4 py-2 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                                    <path d="M3 10h18"></path>
                                    <path d="M7 15h4"></path>
                                </svg>
                                <span>Payment of Wages</span>
                            </a>
                        @endif

                        <div class="tupad-nav-section">
                            Reporting
                        </div>

                        <a href="{{ route('reports.index') }}"
                            class="{{ $navClass(request()->routeIs('reports.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M4 19V9"></path>
                                <path d="M10 19V5"></path>
                                <path d="M16 19v-7"></path>
                                <path d="M22 19H2"></path>
                            </svg>
                            <span>Reports</span>
                        </a>

                        @if ($user->isAdmin())
                            <div class="tupad-nav-section">
                                Administration
                            </div>

                            <a href="{{ route('users.index') }}"
                                class="{{ $navClass(request()->routeIs('users.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                                <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                </svg>
                                <span>Users</span>
                            </a>
                        @endif

                        {{-- =========================================================
                        GIP WORKSPACE
                    ========================================================== --}}
                    @elseif($user->isGip())
                        <div class="tupad-nav-section">
                            Project Management
                        </div>

                        <a href="{{ route('project-drafts.index') }}"
                            class="{{ $navClass(request()->routeIs('project-drafts.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
                            <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.8">
                                <path d="M5 3h10l4 4v14H5z"></path>
                                <path d="M15 3v5h5"></path>
                                <path d="M8 13h8"></path>
                                <path d="M8 17h6"></path>
                            </svg>
                            <span>Project Drafts</span>
                        </a>

                    @endif

                </nav>
            </div>

            <div class="tupad-sidebar-footer w-full shrink-0 p-4">
                <div class="w-full min-w-0 rounded-xl border border-[#dfe6f0] bg-[#f8fbff] p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[.08em] text-[#6f7f98]">Signed in as</div>
                    <div class="mt-2 truncate text-[13px] font-bold text-[#10294f]">{{ $user->name }}</div>
                    <div class="mt-0.5 text-[11px] text-[#73829a]">{{ $user->roleLabel() }}</div>

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
        <div class="tupad-main-shell min-h-screen lg:pl-63">
            <header
                class="sticky top-0 z-30 flex h-21.5 items-center border-b border-[#dfe6f0] bg-white/95 px-4 backdrop-blur md:px-6 xl:px-8">
                <button type="button" id="sidebarToggle"
                    class="mr-3 flex h-10 w-10 items-center justify-center rounded-lg text-[#17325c] hover:bg-slate-100 lg:hidden"
                    aria-label="Open sidebar">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16"></path>
                        <path d="M4 12h16"></path>
                        <path d="M4 18h16"></path>
                    </svg>
                </button>

                <div class="flex min-w-0 flex-1 items-center">
                    <form method="GET"
                        action="{{ Route::has('search.index') ? route('search.index') : route('dashboard') }}"
                        role="search" class="hidden w-full max-w-130 md:block">
                        <label for="global-search" class="sr-only">Search the TUPAD Reporting System</label>
                        <div
                            class="tupad-input flex h-11 items-center rounded-lg px-3.5 focus-within:ring-2 focus-within:ring-[#1765d8]/30">
                            <svg class="h-4.5 w-4.5 shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none"
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
                        <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                            Current Workspace
                        </div>
                        <div class="mt-0.5 text-[11px] font-semibold text-[#355378]">
                            {{ $workspaceLabel }}
                        </div>
                    </div>

                    <div class="hidden h-9 w-px bg-[#e0e7f0] sm:block"></div>

                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[#e7eef8] text-xs font-extrabold text-[#063b86] ring-1 ring-[#d9e3f0]">
                            {{ $user->initials() }}
                        </div>
                        <div class="hidden min-w-0 md:block">
                            <div class="max-w-45 truncate text-[12px] font-bold text-[#10294f]">
                                {{ $user->name }}</div>
                            <div class="mt-0.5 text-[10px] text-[#6f7f98]">{{ $user->roleLabel() }}</div>
                        </div>
                        <svg class="hidden h-4 w-4 text-[#48617f] md:block" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
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
                        <svg class="h-4.25 w-4.25 shrink-0 text-[#4b6385]" viewBox="0 0 24 24" fill="none"
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

            <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1660px] p-4 md:p-5 xl:p-6">

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
