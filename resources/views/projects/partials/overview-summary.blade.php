{{-- Overview: at-a-glance key figures for the project. --}}
@php
    $summaryCost = (float) $project->total_project_cost;
    $summaryParts = [
        ['label' => 'Wages', 'value' => (float) $project->wages_total, 'bar' => 'bg-[#063b86]'],
        ['label' => 'PPE', 'value' => (float) $project->ppe_total, 'bar' => 'bg-sky-500'],
        ['label' => 'Insurance', 'value' => (float) $project->insurance_total, 'bar' => 'bg-emerald-500'],
    ];
    $summaryFemalePercent = (int) $project->beneficiaries_total > 0
        ? round(((int) $project->beneficiaries_female / (int) $project->beneficiaries_total) * 100)
        : 0;
@endphp

<section class="xl:col-span-2" aria-label="Project summary">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-[#063b86]" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </span>
                Beneficiaries
            </div>
            <div class="mt-3 text-2xl font-extrabold text-slate-900">{{ number_format((int) $project->beneficiaries_total) }}</div>
            <div class="mt-1 text-xs text-slate-500">
                {{ number_format((int) $project->beneficiaries_female) }} female · {{ $summaryFemalePercent }}%
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                <div class="h-full rounded-full bg-pink-400" style="width: {{ $summaryFemalePercent }}%"></div>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                </span>
                Total Project Cost
            </div>
            <div class="mt-3 text-2xl font-extrabold text-slate-900">₱{{ number_format($summaryCost, 2) }}</div>
            <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                @foreach ($summaryParts as $part)
                    @if ($summaryCost > 0 && $part['value'] > 0)
                        <div class="h-full {{ $part['bar'] }}" style="width: {{ ($part['value'] / $summaryCost) * 100 }}%"></div>
                    @endif
                @endforeach
            </div>
            <dl class="mt-2 grid grid-cols-3 gap-1 text-[11px]">
                @foreach ($summaryParts as $part)
                    <div>
                        <dt class="flex items-center gap-1 text-slate-500"><span class="h-2 w-2 rounded-full {{ $part['bar'] }}"></span>{{ $part['label'] }}</dt>
                        <dd class="font-semibold text-slate-800">₱{{ number_format($part['value'], 0) }}</dd>
                    </div>
                @endforeach
            </dl>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-700" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </span>
                Duration
            </div>
            <div class="mt-3 text-2xl font-extrabold text-slate-900">{{ number_format((int) $project->number_of_days) }} days</div>
            <div class="mt-1 text-xs text-slate-500">
                {{ $project->term?->label() }} · ₱{{ number_format((float) $project->wage_rate, 2) }} daily wage
            </div>
            @if ($project->implementation)
                <div class="mt-3 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                    {{ $project->implementation->start_date->format('M d') }} – {{ $project->implementation->end_date->format('M d, Y') }}
                </div>
            @endif
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-violet-700" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
                </span>
                Implementation
            </div>
            <div class="mt-3 text-lg font-extrabold leading-7 text-slate-900">{{ $project->implementation_mode->label() }}</div>
            <div class="mt-1 text-xs text-slate-500">ADL {{ $project->allocation->adl->adl_number }}</div>
            <div class="mt-3 inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-[#063b86]">
                {{ $project->status->label() }}
            </div>
        </article>

    </div>
</section>
