{{-- =====================================================
Fund trend and utilization
====================================================== --}}
<div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">

    <section class="tupad-card overflow-hidden">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">

            <div>
                <h2 class="text-sm font-semibold text-slate-900">
                    Project Cost Trend
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Cumulative official project cost by month for FY {{ $currentYear }}.
                </p>
            </div>

            <div
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-bold text-slate-500">
                FY {{ $currentYear }}
            </div>

        </div>

        <div class="px-4 pb-4 pt-3 sm:px-5">

            <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-60 w-full"
                preserveAspectRatio="none" aria-label="Cumulative project cost trend">
                <defs>
                    <linearGradient id="trendFill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="#1765d8" stop-opacity="0.18"></stop>

                        <stop offset="100%" stop-color="#1765d8" stop-opacity="0.01"></stop>
                    </linearGradient>
                </defs>

                @foreach ([16, 58, 100, 142, 184] as $gridY)
                    <line x1="26" y1="{{ $gridY }}" x2="700" y2="{{ $gridY }}"
                        stroke="#e6edf6" stroke-width="1"></line>
                @endforeach

                <polygon points="{{ $areaPoints }}" fill="url(#trendFill)"></polygon>

                <polyline points="{{ $polyline }}" fill="none" stroke="#1765d8" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round"></polyline>

                @foreach ($points as $point)
                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#1765d8"
                        stroke="#ffffff" stroke-width="2"></circle>
                @endforeach
            </svg>

            <div class="grid grid-cols-12 px-2 text-center text-[9px] font-medium text-slate-400">
                @foreach ($months as $month)
                    <span>{{ $month }}</span>
                @endforeach
            </div>

        </div>

    </section>

    <aside class="tupad-card p-5">

        <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
            Available Budget
        </div>

        <div class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900">
            ₱{{ number_format($remainingBudget, 2) }}
        </div>

        <div class="mt-1 text-xs text-slate-500">
            Remaining balance
        </div>

        <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-slate-100">

            <div class="h-full rounded-full bg-slate-800" style="width: {{ $remainingPercent }}%"></div>

        </div>

        <div class="mt-2 flex items-center justify-between text-[10px] font-semibold text-slate-500">
            <span>
                {{ number_format($remainingPercent, 1) }}% remaining
            </span>

            <span>
                {{ number_format($utilizationPercent, 1) }}% utilized
            </span>
        </div>

        <dl class="mt-5 space-y-3 border-t border-slate-100 pt-4 text-xs">

            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500">Allocated</dt>
                <dd class="font-semibold text-slate-900">
                    ₱{{ number_format($totalAllocated, 2) }}
                </dd>
            </div>

            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500">Total Budget</dt>
                <dd class="font-semibold text-slate-900">
                    ₱{{ number_format($totalBudget, 2) }}
                </dd>
            </div>

        </dl>

        @if ($user->isFocal() || $user->isAdmin())
            <a href="{{ route('adl.index') }}"
                class="mt-5 flex h-10 items-center justify-center rounded-lg bg-slate-900 text-xs font-semibold text-white hover:bg-slate-800">
                View Budget Details
            </a>
        @else
            <div
                class="mt-5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-center text-[11px] text-slate-500">
                Fund maintenance is handled by the Focal account.
            </div>
        @endif

    </aside>

</div>
