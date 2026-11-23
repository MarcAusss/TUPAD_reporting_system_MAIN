{{-- Beneficiary Summary --}}

@php
    // Actual counts come from the completed payment (obligation) tranches;
    // the approved (Create Project) counts are kept and shown beside them.
    $summaryActualCounts = $project->actualBeneficiaryCounts();
    $summaryApprovedTotal = (int) $project->beneficiaries_total;
    $summaryApprovedFemale = (int) $project->beneficiaries_female;
    $summaryTranchesInProgress = $summaryActualCounts === null
        && $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION
        && $project->obligations->isNotEmpty();

    $summaryCards = [
        ['Total Beneficiaries', $summaryApprovedTotal, $summaryActualCounts[0] ?? null],
        ['Female Beneficiaries', $summaryApprovedFemale, $summaryActualCounts[1] ?? null],
    ];
@endphp

<section data-workspace-panel="beneficiaries"
    class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Beneficiary Summary</h2>
            <p class="mt-1 text-xs text-slate-500">
                Only aggregate beneficiary counts are recorded. Individual personal records are not encoded; beneficiary
                residence geography is stored separately as aggregate address allocations.
            </p>
        </div>

        @if ($summaryActualCounts !== null)
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-emerald-700">
                Updated from Payment
            </span>
        @endif
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
        @foreach ($summaryCards as [$label, $approved, $actual])
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4" data-beneficiary-summary-card>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</div>

                @if ($actual === null)
                    <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($approved) }}</div>
                @else
                    <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Actual (Payment)</div>
                            <div class="text-2xl font-bold text-slate-900">{{ number_format($actual) }}</div>
                        </div>

                        <div class="text-right">
                            <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Approved (Create Project)</div>
                            <div class="text-sm font-semibold text-slate-500 {{ $actual !== $approved ? 'line-through decoration-slate-300' : '' }}">
                                {{ number_format($approved) }}
                            </div>
                        </div>
                    </div>

                    @if ($actual !== $approved)
                        <div class="mt-2 text-[11px] font-semibold {{ $actual < $approved ? 'text-amber-700' : 'text-blue-700' }}">
                            {{ ($actual < $approved ? '−' : '+').number_format(abs($approved - $actual)).' '.($actual < $approved ? 'not included in the payment' : 'more than approved') }}
                        </div>
                    @else
                        <div class="mt-2 text-[11px] text-slate-400">Same as approved</div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    @if ($summaryActualCounts !== null)
        <p class="border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500">
            Actual counts are the totals of the {{ number_format($project->obligations->count()) }} completed payment
            {{ \Illuminate\Support\Str::plural('tranche', $project->obligations->count()) }}. The approved counts from Create Project are kept for reference.
        </p>
    @elseif ($summaryTranchesInProgress)
        <p class="border-t border-slate-100 px-5 py-3 text-[11px] text-slate-500">
            Payment tranches so far cover {{ number_format((int) $project->obligations->sum('beneficiaries_total')) }} beneficiaries
            ({{ number_format((int) $project->obligations->sum('beneficiaries_female')) }} female).
            These counts update to the payment figures once the Focal completes the obligations.
        </p>
    @endif
</section>
