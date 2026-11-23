@extends('layouts.app')

@section('title', 'GSIS Enrollment Dashboard')

@php
    $peso = fn (int $cents): string => '₱'.number_format($cents / 100, 2);
    $stageTone = [
        'prepared' => 'bg-slate-100 text-slate-700',
        'for_billing' => 'bg-indigo-50 text-indigo-700',
        'for_routing' => 'bg-sky-50 text-sky-700',
        'for_payment' => 'bg-amber-50 text-amber-800',
        'for_or_policy' => 'bg-violet-50 text-violet-700',
        'complete' => 'bg-emerald-50 text-emerald-700',
    ];
@endphp

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        title="Dashboard"
        description="Projects whose insurance was budgeted by the TUPAD Coordinators wait here for GSIS GPAI enrollment. Enroll them in bulk, then track each transmittal until the OR and Policy are received."
    >
        <x-slot:actions>
            @include('gsis.partials.year-filter', ['route' => 'dashboard'])
            <a href="{{ route('gsis.enrollments.create') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                + Enroll Projects
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="GPAI enrollment totals">
        <article class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-amber-700">Awaiting Enrollment</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($eligible->count()) }} <span class="text-sm font-semibold text-slate-500">projects</span></div>
            <p class="mt-1 text-xs text-slate-500">{{ number_format($eligible->sum('insurance_beneficiaries')) }} beneficiaries · ₱{{ number_format($eligiblePremium, 2) }} premium</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Transmittals FY {{ $fiscalYear }}</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($enrollments->count()) }}</div>
            <p class="mt-1 text-xs text-slate-500">{{ number_format($stageCounts['complete'] ?? 0) }} complete · {{ number_format($enrollments->count() - ($stageCounts['complete'] ?? 0)) }} in process</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Beneficiaries Enrolled</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($enrolledBeneficiaries) }}</div>
            <p class="mt-1 text-xs text-slate-500">Total amount {{ $peso((int) $enrolledTotalCents) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Current Cash Advance Balance</div>
            @if ($currentCashAdvance)
                <div class="mt-2 text-2xl font-extrabold {{ $currentCashAdvance->balanceCents() > 0 ? 'text-slate-900' : 'text-red-700' }}">{{ $peso($currentCashAdvance->balanceCents()) }}</div>
                <p class="mt-1 text-xs text-slate-500">{{ $currentCashAdvance->label() }} of {{ $peso($currentCashAdvance->amountCents()) }}</p>
            @else
                <div class="mt-2 text-2xl font-extrabold text-slate-400">—</div>
                <p class="mt-1 text-xs text-slate-500"><a href="{{ route('gsis.cash-advances') }}" class="font-semibold text-[#063b86] hover:underline">Add a Cash Advance</a> to enroll through CA.</p>
            @endif
        </article>
    </section>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Transmittals by Stage</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (\App\Models\GpaiEnrollment::STAGES as $key => $label)
                <a href="{{ route('gsis.status', ['fiscal_year' => $fiscalYear, 'stage' => $key]) }}"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold {{ $stageTone[$key] }} hover:opacity-80">
                    {{ $label }}
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ number_format($stageCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @php
        // Transmittals of the selected fiscal year that are not complete yet, oldest first.
        $inProcess = $enrollments->reject(fn ($enrollment) => $enrollment->isComplete())->values();
    @endphp

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Transmittals In Process</h2>
                <p class="mt-1 text-xs text-slate-500">Pick up where you left off: Continue opens the transmittal at its next GSIS step.</p>
            </div>
            <a href="{{ route('gsis.status', ['fiscal_year' => $fiscalYear]) }}" class="text-xs font-semibold text-[#063b86] hover:underline">Open Yearly Status →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="tupad-system-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="p-3 text-left">GPAI No.</th>
                        <th class="p-3 text-left">Transmitted</th>
                        <th class="p-3 text-left">Status</th>
                        <th class="p-3 text-left">Next Step</th>
                        <th class="p-3 text-right">Beneficiaries</th>
                        <th class="p-3 text-right">Total</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inProcess as $enrollment)
                        @php
                            $continueUrl = route('gsis.enrollments.edit', $enrollment).($enrollment->nextStep() ? '#step-'.$enrollment->nextStep() : '');
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap p-3 font-semibold"><a href="{{ $continueUrl }}" class="text-[#063b86] hover:underline">{{ $enrollment->gpai_number }}</a></td>
                            <td class="whitespace-nowrap p-3 text-slate-600">{{ $enrollment->transmittal_date->format('M j, Y') }}</td>
                            <td class="whitespace-nowrap p-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $stageTone[$enrollment->stage()] }}">{{ $enrollment->stageLabel() }}</span>
                            </td>
                            <td class="p-3 text-slate-700">{{ $enrollment->nextStepLabel() }}</td>
                            <td class="p-3 text-right">{{ number_format($enrollment->beneficiaries()) }}</td>
                            <td class="whitespace-nowrap p-3 text-right">{{ $peso($enrollment->totalCents()) }}</td>
                            <td class="whitespace-nowrap p-3 text-right">
                                <a href="{{ $continueUrl }}"
                                    class="inline-flex items-center rounded-lg bg-[#063b86] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#052f6b]">
                                    Continue →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-sm text-slate-400">No transmittals in process for FY {{ $fiscalYear }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Projects Awaiting GPAI Enrollment</h2>
                <p class="mt-1 text-xs text-slate-500">Projects with an insurance budget, from TSSD Evaluation onward (before approval), that are not enrolled yet.</p>
            </div>
            @if ($eligible->isNotEmpty())
                <a href="{{ route('gsis.enrollments.create') }}" class="text-xs font-semibold text-[#063b86] hover:underline">Select projects to enroll →</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="tupad-system-table min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="p-3 text-left">Project</th>
                        <th class="p-3 text-left">Location</th>
                        <th class="p-3 text-left">ADL No.</th>
                        <th class="p-3 text-right">Insured Beneficiaries</th>
                        <th class="p-3 text-right">Premium</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($eligible->take(15) as $project)
                        <tr>
                            <td class="p-3 font-semibold text-slate-900">
                                {{ $project->project_title }}
                                @if ($project->latestGpaiEnrollmentRequest)
                                    <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">Requested {{ $project->latestGpaiEnrollmentRequest->created_at->diffForHumans() }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-600">{{ collect([$project->municipality, $project->province])->filter()->implode(', ') ?: '—' }}</td>
                            <td class="p-3 text-slate-600">{{ $project->allocation?->adl?->adl_number ?: '—' }}</td>
                            <td class="p-3 text-right">{{ number_format((int) $project->insurance_beneficiaries) }}</td>
                            <td class="p-3 text-right font-semibold">₱{{ number_format((float) $project->insurance_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-sm text-slate-400">No projects are waiting for GPAI enrollment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($eligible->count() > 15)
            <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                Showing 15 of {{ number_format($eligible->count()) }}. <a href="{{ route('gsis.enrollments.create') }}" class="font-semibold text-[#063b86] hover:underline">See all</a>
            </div>
        @endif
    </section>
@endsection
