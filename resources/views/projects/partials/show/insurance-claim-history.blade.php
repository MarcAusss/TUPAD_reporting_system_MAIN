{{-- Insurance Claim History --}}

@if ($project->insuranceClaims->isNotEmpty())
    <section data-workspace-panel="history"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Insurance Claim History
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Every insurance claim (incident report) recorded for this project.
            </p>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach ($project->insuranceClaims as $claim)
                <details class="group/claim">
                    <summary
                        class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden hover:bg-slate-50/60">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-900">
                                {{ $claim->incident_date->format('F d, Y') }}
                                @if ($claim->incident_time)
                                    &middot; approx. {{ $claim->incident_time->format('g:i A') }}
                                @endif
                                &middot;
                                {{ $claim->beneficiaries->count() }}
                                {{ $claim->beneficiaries->count() === 1 ? 'beneficiary' : 'beneficiaries' }}
                                injured
                            </div>
                            <div class="mt-1 max-w-2xl truncate text-[11px] text-slate-500">
                                {{ $claim->incident_description }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-semibold">
                            <span class="text-slate-400">
                                Reported by {{ $claim->reporter?->name ?? 'System' }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-500">
                                Expand / Collapse
                            </span>
                        </div>
                    </summary>

                    <div class="border-t border-slate-200 bg-slate-50/60 px-5 py-4">

                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-red-700">
                                What Happened
                            </div>
                            <p class="mt-2 whitespace-pre-line text-xs leading-5 text-slate-700">
                                {{ $claim->incident_description }}
                            </p>
                        </div>

                        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3">
                            <div class="text-[10px] font-bold uppercase tracking-wide text-red-700">
                                Injured Beneficiary(ies)
                            </div>
                            <ul class="mt-2 space-y-1.5 text-xs text-slate-700">
                                @foreach ($claim->beneficiaries as $injured)
                                    <li>
                                        <span
                                            class="font-semibold text-slate-900">{{ $injured->full_name }}</span>
                                        <span class="text-slate-500">&mdash; {{ $injured->address }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                    </div>
                </details>
            @endforeach
        </div>

    </section>
@endif
