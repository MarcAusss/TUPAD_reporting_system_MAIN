{{-- GSIS Enrollment Officer: Dashboard (shared link above), Yearly Status, CA (Balance Calculation), Yearly Summary, Reports. --}}
<div class="tupad-nav-section">GSIS GPAI Enrollment</div>

<a href="{{ route('gsis.status') }}"
    class="{{ $navClass(request()->routeIs('gsis.status') || request()->routeIs('gsis.enrollments.*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="3" y="4" width="18" height="17" rx="2"></rect>
        <path d="M3 9h18"></path><path d="M8 2v4"></path><path d="M16 2v4"></path>
        <path d="m9 15 2 2 4-4"></path>
    </svg>
    <span>Yearly Status</span>
</a>

<a href="{{ route('gsis.cash-advances') }}"
    class="{{ $navClass(request()->routeIs('gsis.cash-advances*')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <rect x="2" y="6" width="20" height="12" rx="2"></rect>
        <circle cx="12" cy="12" r="2.5"></circle>
        <path d="M6 10v4"></path><path d="M18 10v4"></path>
    </svg>
    <span>CA (Balance Calculation)</span>
</a>

<a href="{{ route('gsis.summary') }}"
    class="{{ $navClass(request()->routeIs('gsis.summary')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M4 19V9"></path><path d="M10 19V5"></path><path d="M16 19v-7"></path><path d="M22 19H2"></path>
    </svg>
    <span>Yearly Summary</span>
</a>

<a href="{{ route('gsis.report') }}"
    class="{{ $navClass(request()->routeIs('gsis.report')) }} flex h-11 items-center gap-3 rounded-lg px-4 text-[13px] font-semibold transition">
    <svg class="h-4.75 w-4.75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
        <path d="M14 3v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>
    </svg>
    <span>Reports</span>
</a>
