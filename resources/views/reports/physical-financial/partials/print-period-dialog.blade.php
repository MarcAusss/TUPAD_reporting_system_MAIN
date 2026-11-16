{{--
    "What to print" chooser for Accomplishment per Semester / per Quarter / per Month.
    The "all" choice prints the full table (same as the report); a single period prints that period only.
    Expects $exportQuery, $filters, $matrix from the parent view, plus:
      $printParam    'month', 'quarter' or 'semester' (the query parameter each choice sets)
      $printOptions  [number => label]
      $printLabel    report name shown in the subtitle
      $allLabel, $allDescription, $singleHeading
--}}
@php
    $printBase = \Illuminate\Support\Arr::except($exportQuery, ['month', 'quarter', 'semester']);
    $currentPeriod = isset($filters[$printParam]) ? (int) $filters[$printParam] : null;
    $printYear = $exportQuery['fiscal_year'] ?? ($matrix['fiscal_year'] ?? now('Asia/Manila')->year);
@endphp

<dialog id="print-period-dialog"
    class="tupad-dialog rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl"
    {{-- Centering is inline so it does not depend on the compiled Tailwind build. --}}
    style="position: fixed; inset: 0; margin: auto; height: fit-content; max-height: calc(100dvh - 2rem); width: min(34rem, calc(100vw - 2rem)); overflow-y: auto;"
    aria-labelledby="print-period-title">
    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
        <div>
            <h2 id="print-period-title" class="text-base font-bold">What do you want to print?</h2>
            <p class="mt-1 text-xs text-slate-500">{{ $printLabel }} · FY{{ $printYear }}. Opens the print view in a new tab.</p>
        </div>
        <button type="button" data-print-period-close
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
            aria-label="Close">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
        </button>
    </div>

    <div class="space-y-4 px-6 py-5">
        <a href="{{ route('reports.print', $printBase) }}" target="_blank" data-print-period-choice
            @class([
                'flex items-center justify-between rounded-xl border px-4 py-3 transition',
                'border-[#063b86] bg-blue-50 ring-2 ring-blue-100' => $currentPeriod === null,
                'border-slate-300 hover:border-[#063b86] hover:bg-blue-50' => $currentPeriod !== null,
            ])>
            <span>
                <span class="block text-sm font-bold text-[#063b86]">{{ $allLabel }}</span>
                <span class="block text-xs text-slate-500">{{ $allDescription }}</span>
            </span>
            <svg class="h-5 w-5 text-[#063b86]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
        </a>

        <div>
            <div class="mb-2 text-[11px] font-extrabold uppercase tracking-[0.12em] text-slate-500">{{ $singleHeading }}</div>
            <div @class(['grid gap-2', 'grid-cols-3 sm:grid-cols-4' => count($printOptions) > 4, 'grid-cols-2 sm:grid-cols-4' => count($printOptions) <= 4])>
                @foreach ($printOptions as $periodNumber => $periodLabel)
                    <a href="{{ route('reports.print', array_merge($printBase, [$printParam => $periodNumber, 'fiscal_year' => $printYear])) }}"
                        target="_blank" data-print-period-choice
                        @class([
                            'flex h-11 items-center justify-center rounded-lg border text-sm font-semibold transition',
                            'border-[#063b86] bg-[#063b86] text-white' => $currentPeriod === $periodNumber,
                            'border-slate-300 text-slate-700 hover:border-[#063b86] hover:bg-blue-50 hover:text-[#063b86]' => $currentPeriod !== $periodNumber,
                        ])>
                        {{ $periodLabel }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</dialog>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dialog = document.getElementById('print-period-dialog');
            const opener = document.querySelector('[data-print-period-open]');

            if (!dialog || !opener || typeof dialog.showModal !== 'function') {
                return;
            }

            opener.addEventListener('click', function () { dialog.showModal(); });

            dialog.addEventListener('click', function (event) {
                // Close on backdrop click, the close button, or after choosing what to print.
                if (event.target === dialog || event.target.closest('[data-print-period-close]') || event.target.closest('[data-print-period-choice]')) {
                    dialog.close();
                }
            });
        });
    </script>
@endpush
