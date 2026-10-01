{{-- Release of Assistance (per tranche) --}}

@if (
    $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION &&
        in_array($project->status, [\App\Enums\ProjectStatus::FOR_PAYMENT, \App\Enums\ProjectStatus::COMPLETED], true) &&
        ($project->obligations->isNotEmpty() || $project->payout))
    @php
        $releaseController = \App\Http\Controllers\ProjectReleaseOfAssistanceController::class;
        $releasePaymentService = app(\App\Services\Payments\ProjectPaymentService::class);
        $releaseSummary = $releasePaymentService->releaseSummary($project);
        $releasePaymentSummary = $releasePaymentService->summary($project);
        $canRecordRelease =
            $project->status === \App\Enums\ProjectStatus::FOR_PAYMENT &&
            (auth()->user()->isTc() || auth()->user()->isAdmin());
        $releasedCount = $releaseSummary['released'] + $releaseSummary['release_scheduled'];
        $releaseProgress = $releaseSummary['tranche_count'] > 0
            ? (int) floor(($releasedCount * 100) / $releaseSummary['tranche_count'])
            : 0;
        $releaseStateMeta = [
            'awaiting_disbursement' => ['Waiting for Focal disbursement', 'border-slate-200 bg-slate-50 text-slate-600'],
            'ready_for_release' => ['Ready for Release of Assistance', 'border-amber-200 bg-amber-50 text-amber-800'],
            'release_scheduled' => ['Released · payout date pending', 'border-blue-200 bg-blue-50 text-blue-800'],
            'released' => ['Released', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
        ];
        $otherPayoutMode = $releaseController::OTHER_MODE;
    @endphp

    <section id="release-of-assistance" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Release of Assistance Progress</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Each tranche follows: Focal obligation → Focal disbursement → TC Release of Assistance.
                        The project completes once the Focal completes the tranches, every tranche is
                        disbursed and released, and every payout date is reached.
                    </p>
                </div>

                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-bold
                    {{ $project->status === \App\Enums\ProjectStatus::COMPLETED
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                    {{ $releasedCount }} of {{ $releaseSummary['tranche_count'] }} tranche(s) released
                </span>
            </div>

            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                <div class="h-full rounded-full bg-[#063b86] transition-all" style="width: {{ $releaseProgress }}%"></div>
            </div>

            <div class="mt-3 grid gap-2 text-[11px] sm:grid-cols-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-slate-600">
                    <span class="font-bold">{{ $releaseSummary['awaiting_disbursement'] }}</span> waiting for disbursement
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                    <span class="font-bold">{{ $releaseSummary['ready_for_release'] }}</span> ready for release
                </div>
                <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-blue-800">
                    <span class="font-bold">{{ $releaseSummary['release_scheduled'] }}</span> payout date pending
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">
                    <span class="font-bold">{{ $releaseSummary['released'] }}</span> released
                </div>
            </div>

            @if ($project->status === \App\Enums\ProjectStatus::FOR_PAYMENT && ! $releasePaymentSummary['obligations_completed'])
                <p class="mt-3 text-[11px] text-slate-500">
                    The Focal has not yet completed the obligation tranches; more tranches may still be added.
                </p>
            @endif
        </div>

        @if ($project->payout && $project->obligations->every(fn ($obligation) => ! $obligation->isReleased()))
            <div class="border-b border-slate-200 bg-amber-50 px-5 py-3 text-xs text-amber-900">
                Earlier project-level release on record: {{ $project->payout->payout_mode }},
                {{ $project->payout->payout_date->format('F d, Y') }}, {{ $project->payout->venue }}.
            </div>
        @endif

        <ol class="divide-y divide-slate-100">
            @foreach ($project->obligations as $obligation)
                @php
                    $trancheState = $releasePaymentService->trancheReleaseState($obligation);
                    $trancheObligated = $releasePaymentService->obligationCents($obligation);
                    $trancheDisbursed = $releasePaymentService->disbursedForObligationCents($obligation);
                    $trancheFullyDisbursed = $trancheState !== 'awaiting_disbursement';
                    $trancheReleased = $obligation->isReleased();
                    $errorBag = $errors->getBag($releaseController::errorBag($obligation));
                    $usesOld = (int) old('release_obligation_id') === (int) $obligation->id;
                    [$storedMode, $storedModeOther] = $releaseController::splitPayoutMode($obligation->release_mode);
                    $selectedMode = $usesOld ? old('payout_mode') : $storedMode;
                    $steps = [
                        ['Obligated', true, $obligation->obligation_date->format('M d, Y')],
                        ['Disbursed', $trancheFullyDisbursed, '₱'.number_format($trancheDisbursed / 100, 2).' of ₱'.number_format($trancheObligated / 100, 2)],
                        ['Release of Assistance', $trancheReleased, $trancheReleased ? $obligation->release_date->format('M d, Y') : 'Pending'],
                        ['Payout Date Reached', $trancheState === 'released', $trancheState === 'release_scheduled' ? 'On '.$obligation->release_date->format('M d, Y') : ($trancheState === 'released' ? 'Done' : 'Pending')],
                    ];
                @endphp

                <li id="release-tranche-{{ $obligation->tranche_number }}" class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-widest text-blue-700">
                                Tranche {{ $obligation->tranche_number }}
                            </div>
                            <div class="mt-0.5 text-sm font-bold text-slate-900">
                                ₱{{ number_format($trancheObligated / 100, 2) }}
                                <span class="font-normal text-slate-500">· {{ number_format($obligation->beneficiaries_total) }} beneficiaries · {{ $obligation->payee }}</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $releaseStateMeta[$trancheState][1] }}">
                            {{ $releaseStateMeta[$trancheState][0] }}
                        </span>
                    </div>

                    <ol class="mt-3 grid gap-2 sm:grid-cols-4" aria-label="Tranche {{ $obligation->tranche_number }} progress">
                        @foreach ($steps as [$stepLabel, $stepDone, $stepNote])
                            <li class="flex items-start gap-2 rounded-lg border px-3 py-2 {{ $stepDone ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }}">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold {{ $stepDone ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500' }}">
                                    {{ $stepDone ? '✓' : $loop->iteration }}
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[11px] font-semibold {{ $stepDone ? 'text-emerald-900' : 'text-slate-700' }}">{{ $stepLabel }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ $stepNote }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>

                    @if ($trancheReleased)
                        <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-4">
                            @foreach ([
                                'Mode of Payment' => $obligation->release_mode,
                                'Date of Payout' => $obligation->release_date->format('F d, Y'),
                                'Venue' => $obligation->release_venue,
                                'Recorded By' => ($obligation->releaser?->name ?? '—').($obligation->released_at ? ' · '.$obligation->released_at->format('M d, Y h:i A') : ''),
                            ] as $releaseLabel => $releaseValue)
                                <div class="bg-white px-3 py-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $releaseLabel }}</dt>
                                    <dd class="mt-0.5 text-xs font-semibold text-slate-800">{{ $releaseValue }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($obligation->release_remarks)
                            <p class="mt-2 text-xs text-slate-600">{{ $obligation->release_remarks }}</p>
                        @endif
                        <a href="{{ route('projects.show', ['project' => $project, 'workspace' => 'overview']) }}#section-release-{{ $obligation->id }}"
                            class="mt-2 inline-flex text-[11px] font-semibold text-[#063b86] hover:underline">
                            Correct this release in the Overview →
                        </a>
                    @elseif (! $trancheFullyDisbursed)
                        <p class="mt-3 text-xs text-slate-500">
                            Waiting for the Focal to fully disburse this tranche before the Release of Assistance can be recorded.
                        </p>
                    @elseif (! $canRecordRelease)
                        <p class="mt-3 text-xs text-slate-500">
                            Waiting for the TUPAD Coordinator to record the Release of Assistance.
                        </p>
                    @endif

                    @if ($canRecordRelease && $trancheFullyDisbursed && ! $trancheReleased)
                        <details class="mt-3 rounded-lg border border-slate-200 bg-slate-50" @if (! $trancheReleased || $errorBag->any()) open @endif>
                            <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-[#063b86]">
                                {{ $trancheReleased ? 'Edit Release of Assistance' : 'Record Release of Assistance' }}
                            </summary>

                            <form method="POST"
                                action="{{ route('projects.release-of-assistance.store', [$project, $obligation]) }}"
                                class="border-t border-slate-200 p-4" data-release-form
                                data-confirm-title="Record the Release of Assistance for Tranche {{ $obligation->tranche_number }}?" data-confirm="Check the mode of payment, date of payout, and venue. Once recorded, changes need an approved edit request." data-confirm-button="Record Release">
                                @csrf
                                <input type="hidden" name="release_obligation_id" value="{{ $obligation->id }}">

                                <div class="grid gap-4 md:grid-cols-3">
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="payout_mode_{{ $obligation->id }}">Mode of Payment</label>
                                        <select id="payout_mode_{{ $obligation->id }}" name="payout_mode" required data-payout-mode
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                            <option value="">Select mode of payment</option>
                                            @foreach ($releaseController::PAYOUT_MODES as $payoutMode)
                                                <option value="{{ $payoutMode }}" @selected($selectedMode === $payoutMode)>
                                                    {{ $payoutMode === $otherPayoutMode ? 'Others, specify' : $payoutMode }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errorBag->has('payout_mode'))
                                            <p class="mt-1 text-xs text-rose-600">{{ $errorBag->first('payout_mode') }}</p>
                                        @endif

                                        <div data-payout-mode-other class="mt-2 {{ $selectedMode === $otherPayoutMode ? '' : 'hidden' }}">
                                            <input name="payout_mode_other" maxlength="92" placeholder="Specify mode of payment"
                                                value="{{ $usesOld ? old('payout_mode_other') : $storedModeOther }}"
                                                @required($selectedMode === $otherPayoutMode)
                                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                            @if ($errorBag->has('payout_mode_other'))
                                                <p class="mt-1 text-xs text-rose-600">{{ $errorBag->first('payout_mode_other') }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="payout_date_{{ $obligation->id }}">Date of Payout</label>
                                        <input id="payout_date_{{ $obligation->id }}" name="payout_date" type="date" required
                                            value="{{ $usesOld ? old('payout_date') : $obligation->release_date?->toDateString() }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        @if ($errorBag->has('payout_date'))
                                            <p class="mt-1 text-xs text-rose-600">{{ $errorBag->first('payout_date') }}</p>
                                        @endif
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="payout_venue_{{ $obligation->id }}">Venue</label>
                                        <input id="payout_venue_{{ $obligation->id }}" name="venue" required maxlength="255"
                                            value="{{ $usesOld ? old('venue') : $obligation->release_venue }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        @if ($errorBag->has('venue'))
                                            <p class="mt-1 text-xs text-rose-600">{{ $errorBag->first('venue') }}</p>
                                        @endif
                                    </div>

                                    <div class="md:col-span-3">
                                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="payout_remarks_{{ $obligation->id }}">
                                            Remarks <span class="font-normal text-slate-400">(optional)</span>
                                        </label>
                                        <input id="payout_remarks_{{ $obligation->id }}" name="remarks" maxlength="3000"
                                            value="{{ $usesOld ? old('remarks') : $obligation->release_remarks }}"
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    </div>
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <button type="submit"
                                        class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                        {{ $trancheReleased ? 'Update' : 'Save' }} Release of Assistance
                                    </button>
                                </div>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ol>

        <script>
            document.querySelectorAll('[data-release-form]').forEach((form) => {
                const select = form.querySelector('[data-payout-mode]');
                const field = form.querySelector('[data-payout-mode-other]');
                const input = field?.querySelector('input');

                select?.addEventListener('change', () => {
                    const isOther = select.value === @js($otherPayoutMode);
                    field.classList.toggle('hidden', !isOther);
                    input.required = isOther;
                    if (isOther) input.focus();
                });
            });
        </script>
    </section>
@endif
