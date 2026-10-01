{{-- Project Information --}}

<div id="overview" data-workspace-panel="overview"
    class="scroll-mt-32 mt-5 grid gap-5 xl:grid-cols-2 {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

    {{-- At-a-glance summary --}}
    @include('projects.partials.overview-summary')

    @if ($project->status !== \App\Enums\ProjectStatus::COMPLETED)
        @php
            $isThroughAcp = $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP;

            if ($isThroughAcp) {
                $completionLiquidationSummary = app(
                    \App\Services\Projects\ProjectAcpLiquidationService::class,
                )->summary($project);

                $completionAcp = app(\App\Services\Projects\AcpWorkflowService::class);
                $completionAcpLegacy = $completionAcp->isLegacy($project);

                $completionChecklist = array_values(array_filter([
                    ['label' => 'ACP Payment', 'complete' => (bool) $project->acpPayment, 'tab' => 'workflow'],
                    [
                        'label' => 'ACP Check Release',
                        'complete' => (bool) $project->acpCheckRelease,
                        'tab' => 'workflow',
                    ],
                    $completionAcpLegacy ? null : ['label' => 'GSIS Enrollment (Insurance)', 'complete' => (bool) $project->insuranceEnrollment, 'tab' => 'workflow'],
                    $completionAcpLegacy ? null : ['label' => 'PPE Delivery', 'complete' => $project->ppeDeliveries->isNotEmpty(), 'tab' => 'workflow'],
                    $completionAcpLegacy ? null : ['label' => 'NAFA (Notice of Availability of Fund)', 'complete' => (bool) $project->nafa, 'tab' => 'workflow'],
                    $completionAcpLegacy ? null : ['label' => 'Notice to Proceed', 'complete' => (bool) $project->noticeToProceed, 'tab' => 'workflow'],
                    $completionAcpLegacy ? null : ['label' => 'Orientation', 'complete' => (bool) $project->orientation, 'tab' => 'workflow'],
                    [
                        'label' => 'Implementation Period (Work Period)',
                        'complete' => (bool) $project->implementation,
                        'tab' => 'workflow',
                    ],
                    $completionAcpLegacy ? null : ['label' => 'Release of Assistance (Payout Date Reached)', 'complete' => $completionAcp->releaseDone($project), 'tab' => 'workflow'],
                    [
                        'label' => 'ACP Liquidation (Fully Liquidated)',
                        'complete' => (bool) ($completionLiquidationSummary['is_fully_liquidated'] ?? false),
                        'tab' => 'financial',
                    ],
                ]));
            } else {
                $completionPaymentSummary = app(\App\Services\Payments\ProjectPaymentService::class)->summary(
                    $project,
                );
                $completionReleaseSummary = app(\App\Services\Payments\ProjectPaymentService::class)->releaseSummary(
                    $project,
                );

                $completionChecklist = [
                    [
                        'label' => 'Insurance Enrollment',
                        'complete' => (bool) $project->insuranceEnrollment,
                        'tab' => 'workflow',
                    ],
                    [
                        'label' => 'PPE Delivery',
                        'complete' => $project->ppeDeliveries->isNotEmpty(),
                        'tab' => 'workflow',
                    ],
                    [
                        'label' => 'Notice to Proceed',
                        'complete' => (bool) $project->noticeToProceed,
                        'tab' => 'workflow',
                    ],
                    ['label' => 'Orientation', 'complete' => (bool) $project->orientation, 'tab' => 'workflow'],
                    [
                        'label' => 'Implementation Period (Work Period)',
                        'complete' => (bool) $project->implementation,
                        'tab' => 'workflow',
                    ],
                    [
                        'label' => 'Post-Documentary Requirements',
                        'complete' => $project->postDocumentsComplete(),
                        'tab' => 'workflow',
                    ],
                    [
                        'label' => 'Obligation Tranches Completed',
                        'complete' => (bool) ($completionPaymentSummary['obligations_completed'] ?? false),
                        'tab' => 'financial',
                    ],
                    [
                        'label' => 'Payment of Wages (Fully Disbursed)',
                        'complete' => (bool) ($completionPaymentSummary['is_fully_paid'] ?? false),
                        'tab' => 'financial',
                    ],
                    [
                        'label' => 'Release of Assistance (Every Tranche)',
                        'complete' => (bool) ($completionReleaseSummary['all_released'] ?? false),
                        'tab' => 'workflow',
                    ],
                    [
                        'label' => 'All Payout Dates Reached',
                        'complete' => (bool) ($completionReleaseSummary['all_release_dates_reached'] ?? false),
                        'tab' => 'workflow',
                    ],
                ];
            }

            $completionRemaining = collect($completionChecklist)
                ->reject(fn(array $item): bool => $item['complete'])
                ->count();
        @endphp

        @php
            $completionTotal = count($completionChecklist);
            $completionDone = $completionTotal - $completionRemaining;
            $completionPercent = $completionTotal > 0 ? (int) round(($completionDone / $completionTotal) * 100) : 0;
        @endphp

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">

            <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $completionRemaining > 0 ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600' }}" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Completion Readiness</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Every requirement must be complete before the project automatically moves to Completed.
                        </p>
                    </div>
                </div>

                <div class="min-w-48 sm:text-right">
                    <div class="text-xs font-semibold {{ $completionRemaining > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                        {{ $completionRemaining > 0 ? "{$completionDone} of {$completionTotal} complete · {$completionRemaining} remaining" : 'All requirements complete' }}
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-full rounded-full {{ $completionRemaining > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ $completionPercent }}%"></div>
                    </div>
                </div>
            </div>

            <ul class="grid gap-px bg-slate-100 sm:grid-cols-2">
                @foreach ($completionChecklist as $item)
                    <li class="flex items-center justify-between gap-3 bg-white px-5 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $item['complete'] ? 'bg-emerald-500 text-white' : 'border-2 border-slate-200 text-transparent' }}"
                                aria-hidden="true">
                                ✓
                            </span>
                            <span class="truncate text-sm {{ $item['complete'] ? 'text-slate-500 line-through decoration-slate-300' : 'font-semibold text-slate-900' }}">
                                {{ $item['label'] }}
                            </span>
                            <span class="sr-only">{{ $item['complete'] ? '(complete)' : '(pending)' }}</span>
                        </div>

                        @unless ($item['complete'])
                            <a href="{{ route('projects.show', ['project' => $project, 'workspace' => $item['tab']]) }}"
                                class="inline-flex h-8 shrink-0 items-center gap-1 rounded-lg bg-blue-50 px-3 text-xs font-semibold text-[#063b86] transition hover:bg-blue-100">
                                Go to step <span aria-hidden="true">→</span>
                            </a>
                        @endunless
                    </li>
                @endforeach
            </ul>

        </section>
    @endif

    @php
        $projectInfoRows = array_filter([
            ['ADL Number', $project->allocation->adl->adl_number, false],
            ['Date Received', $project->date_received->format('F d, Y'), false],
            ['Fund Sponsor', $project->fund_sponsor, false],
            ['Partner', $project->partner, false],
            ['Program', $project->program ?: null, false],
            ['Project Series', $project->project_series ?: '—', false],
            ['TEVS Date Verified', $project->tevs_date_verified?->format('F d, Y') ?? '—', false],
            ['Nature of Work', $project->nature_of_work, true],
            $project->project_series_remarks ? ['Project Series Remarks', $project->project_series_remarks, true] : null,
            $project->tevs_remarks ? ['TEVS Remarks', $project->tevs_remarks, true] : null,
            $project->remarks ? ['Remarks', $project->remarks, true] : null,
        ], fn ($row) => $row !== null && $row[1] !== null);
    @endphp

    <section class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-[#063b86]" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
                </span>
                <h2 class="text-sm font-bold text-slate-900">Project Information</h2>
            </div>
        </div>

        <dl class="grid flex-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
            @foreach ($projectInfoRows as [$infoLabel, $infoValue, $infoWide])
                <div class="{{ $infoWide ? 'sm:col-span-2' : '' }}">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $infoLabel }}</dt>
                    <dd class="mt-1 whitespace-pre-line break-words text-sm font-medium text-slate-800">{{ $infoValue }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($canManageProject && !$projectEditingLocked)
            <details class="group/edit-details border-t border-slate-100 bg-slate-50/60">
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 marker:content-none [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-[#063b86] hover:text-[#063b86]">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                        <span class="group-open/edit-details:hidden">Edit Project Details</span>
                        <span class="hidden group-open/edit-details:inline">Close Editor</span>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform group-open/edit-details:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>

                <form method="POST" action="{{ route('projects.details.update', $project) }}"
                    class="border-t border-slate-200 p-5">
                    @csrf
                    @method('PUT')

                    @if ($projectImplementationStarted)
                        <div
                            class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-800">
                            Implementation has already started, so Duration, Wage Rate, Beneficiaries, and Insurance
                            Rate/Beneficiaries are locked here. Use Replace Beneficiary (Beneficiaries tab) to
                            adjust
                            Insurance Beneficiaries or Total Project Cost instead.
                        </div>
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">Date Received</label>
                            <input type="date" name="date_received" required
                                value="{{ old('date_received', $project->date_received->toDateString()) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            @error('date_received')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">Project Series</label>
                            <input type="text" name="project_series" required maxlength="100"
                                value="{{ old('project_series', $project->project_series) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            @error('project_series')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs font-semibold text-slate-700">Project Title</label>
                            <input type="text" name="project_title" required maxlength="255"
                                value="{{ old('project_title', $project->project_title) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            @error('project_title')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs font-semibold text-slate-700">Nature of Work</label>
                            <textarea name="nature_of_work" required maxlength="3000" rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('nature_of_work', $project->nature_of_work) }}</textarea>
                            @error('nature_of_work')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">Fund Sponsor</label>
                            <input type="text" name="fund_sponsor" required maxlength="255"
                                value="{{ old('fund_sponsor', $project->fund_sponsor) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            @error('fund_sponsor')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">Partner</label>
                            <input type="text" name="partner" required maxlength="255"
                                value="{{ old('partner', $project->partner) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            @error('partner')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Project Series Remarks <span class="font-normal text-slate-400">(Optional)</span>
                            </label>
                            <textarea name="project_series_remarks" maxlength="3000" rows="2"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('project_series_remarks', $project->project_series_remarks) }}</textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">TEVS Date
                                Verified</label>
                            <input type="date" name="tevs_date_verified" required
                                value="{{ old('tevs_date_verified', $project->tevs_date_verified?->toDateString()) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            @error('tevs_date_verified')
                                <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                TEVS Remarks <span class="font-normal text-slate-400">(Optional)</span>
                            </label>
                            <input type="text" name="tevs_remarks" maxlength="3000"
                                value="{{ old('tevs_remarks', $project->tevs_remarks) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Remarks <span class="font-normal text-slate-400">(Optional)</span>
                            </label>
                            <textarea name="remarks" maxlength="3000" rows="2"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->remarks) }}</textarea>
                        </div>

                        @unless ($projectImplementationStarted)
                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">Duration (Number of
                                    Days)</label>
                                <input type="number" name="number_of_days" required min="10" max="90"
                                    value="{{ old('number_of_days', $project->number_of_days) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                @error('number_of_days')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">Wage Rate</label>
                                <input type="number" name="wage_rate" required min="0.01" step="0.01"
                                    data-money-input
                                    value="{{ old('wage_rate', $project->wage_rate) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                @error('wage_rate')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">Total
                                    Beneficiaries</label>
                                <input type="number" name="beneficiaries_total" required min="1"
                                    value="{{ old('beneficiaries_total', $project->beneficiaries_total) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                @error('beneficiaries_total')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">Female
                                    Beneficiaries</label>
                                <input type="number" name="beneficiaries_female" required min="0"
                                    value="{{ old('beneficiaries_female', $project->beneficiaries_female) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                @error('beneficiaries_female')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">Insurance Rate</label>
                                <input type="number" name="insurance_rate" required min="0" step="0.01"
                                    data-money-input
                                    value="{{ old('insurance_rate', $project->insurance_rate) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                @error('insurance_rate')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Insurance Beneficiaries <span class="font-normal text-slate-400">(Optional)</span>
                                </label>
                                <input type="number" name="insurance_beneficiaries" min="0"
                                    value="{{ old('insurance_beneficiaries', $project->insurance_beneficiaries) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Defaults to Total Beneficiaries when left blank.
                                </p>
                                @error('insurance_beneficiaries')
                                    <p class="mt-1 text-[11px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endunless

                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit"
                            class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                            Save Project Details
                        </button>
                    </div>

                </form>
            </details>
        @endif

    </section>

    @php
        $locationInfoRows = [
            ['Location', $project->full_location, true],
            ['District', $project->district ?: 'Not Assigned', false],
            ['Income Class', $project->income_class ?: 'Not yet assigned', false],
            ['Implementation Mode', $project->implementation_mode->label(), false],
            ['Duration', $project->number_of_days.' days — '.$project->term->label(), false],
        ];
    @endphp

    <section class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </span>
            <h2 class="text-sm font-bold text-slate-900">Location &amp; Implementation</h2>
        </div>

        <dl class="grid flex-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
            @foreach ($locationInfoRows as [$locationLabel, $locationValue, $locationWide])
                <div class="{{ $locationWide ? 'sm:col-span-2' : '' }}">
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $locationLabel }}</dt>
                    <dd class="mt-1 break-words text-sm font-medium text-slate-800">{{ $locationValue }}</dd>
                </div>
            @endforeach
        </dl>

    </section>

    {{-- Beneficiary Mapping vs Actual Beneficiary Mapping --}}
    @include('projects.partials.beneficiary-mapping-comparison')

    {{-- Obligations | Release of Assistance, with Disbursements below (after payment is done) --}}
    @include('projects.partials.obligation-release-overview')

</div>
