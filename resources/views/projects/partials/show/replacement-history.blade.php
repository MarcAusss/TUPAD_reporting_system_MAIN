{{-- Beneficiary Replacement History --}}

@if ($project->beneficiaryReplacements->isNotEmpty())
    <section data-workspace-panel="history"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Beneficiary Replacement History
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Every beneficiary replacement recorded for this project, oldest information preserved even after
                the replacement is complete.
            </p>
        </div>

        <div class="divide-y divide-slate-100">
            @foreach ($project->beneficiaryReplacements as $replacement)
                <details class="group/replacement">
                    <summary
                        class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden hover:bg-slate-50/60">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-900">
                                {{ $replacement->performed_at->format('F d, Y g:i A') }}
                                &middot;
                                {{ $replacement->removedMembers()->count() }} replaced,
                                {{ $replacement->addedMembers()->count() }} added
                            </div>
                            <div class="mt-1 max-w-2xl truncate text-[11px] text-slate-500">
                                {{ $replacement->reason }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-semibold">
                            <span class="text-slate-400">
                                By {{ $replacement->performer?->name ?? 'System' }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-500">
                                Expand / Collapse
                            </span>
                        </div>
                    </summary>

                    <div class="border-t border-slate-200 bg-slate-50/60 px-5 py-4">

                        <div class="grid gap-4 md:grid-cols-2">

                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700">
                                    Original Beneficiaries
                                </div>
                                <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                    @forelse($replacement->removedMembers()->with('beneficiary')->get() as $member)
                                        <li>{{ $member->beneficiary?->full_name ?? 'Unknown beneficiary' }}</li>
                                    @empty
                                        <li class="text-slate-400">None recorded.</li>
                                    @endforelse
                                </ul>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                    Replacement Beneficiaries
                                </div>
                                <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                    @forelse($replacement->addedMembers()->with('beneficiary')->get() as $member)
                                        <li>{{ $member->beneficiary?->full_name ?? 'Unknown beneficiary' }}</li>
                                    @empty
                                        <li class="text-slate-400">None recorded.</li>
                                    @endforelse
                                </ul>
                            </div>

                        </div>

                        @if ($replacement->hasAnyDetailChange())
                            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700">
                                    Updated Details
                                </div>
                                <ul class="mt-2 space-y-1 text-xs text-blue-900">
                                    @if ($replacement->insurance_beneficiaries_changed)
                                        <li>
                                            Insurance Beneficiaries: Changed
                                            ({{ number_format($replacement->insurance_beneficiaries_before) }}
                                            &rarr;
                                            {{ number_format($replacement->insurance_beneficiaries_after) }})
                                        </li>
                                    @endif
                                    @if ($replacement->total_project_cost_changed)
                                        <li>
                                            Total Project Cost: Changed
                                            (₱{{ number_format($replacement->total_project_cost_before, 2) }}
                                            &rarr;
                                            ₱{{ number_format($replacement->total_project_cost_after, 2) }})
                                        </li>
                                    @endif
                                    @if ($replacement->beneficiary_address_changed)
                                        <li>Beneficiary Address: Changed</li>
                                    @endif
                                </ul>
                            </div>
                        @else
                            <div class="mt-4 text-[11px] leading-4 text-slate-400">
                                Updated Details: Insurance Beneficiaries &mdash; No Change &middot;
                                Total Project Cost &mdash; No Change &middot;
                                Beneficiary Address &mdash; No Change
                            </div>
                        @endif

                    </div>
                </details>
            @endforeach
        </div>

    </section>
@endif
