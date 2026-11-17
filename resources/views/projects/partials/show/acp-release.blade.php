{{-- Through ACP Release of Assistance (after the work period ends, before liquidation) --}}

@if (
    $implementationIsAcp &&
        ! $acpWorkflowService->isLegacy($project) &&
        in_array(
            $project->status,
            [
                \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                \App\Enums\ProjectStatus::COMPLETED,
            ],
            true))
    @php
        $acpPayout = $project->payout;
        $acpReleaseOpen = $acpWorkflowService->releaseOpen($project);
        $canRecordAcpRelease = $acpReleaseOpen && ! $acpPayout && (auth()->user()->isTc() || auth()->user()->isAdmin());
        $acpReleaseBag = $errors->getBag(\App\Http\Controllers\ProjectReleaseOfAssistanceController::ACP_ERROR_BAG);
        $releaseController = \App\Http\Controllers\ProjectReleaseOfAssistanceController::class;
        $acpSelectedMode = old('payout_mode');
    @endphp

    <section id="acp-release-of-assistance" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Release of Assistance</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        TC/Admin records the mode of payment, date of payout, and venue once the work period has
                        ended. Liquidation opens when the payout date is reached.
                    </p>
                </div>

                @if ($acpPayout)
                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $acpWorkflowService->releaseDone($project) ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-blue-200 bg-blue-50 text-blue-700' }}">
                        {{ $acpWorkflowService->releaseDone($project) ? 'Released' : 'Waiting for payout date ('.$acpPayout->payout_date->format('M d, Y').')' }}
                    </span>
                @elseif ($acpReleaseOpen)
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">
                        Awaiting Release of Assistance
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                        Opens after the work period ends{{ $project->implementation ? ' ('.$project->implementation->end_date->format('M d, Y').')' : '' }}
                    </span>
                @endif
            </div>
        </div>

        @if ($acpPayout)
            <dl class="grid gap-px bg-slate-200 sm:grid-cols-3">
                @foreach ([
                    'Mode of Payment' => $acpPayout->payout_mode,
                    'Date of Payout' => $acpPayout->payout_date->format('F d, Y'),
                    'Venue' => $acpPayout->venue,
                ] as $acpReleaseLabel => $acpReleaseValue)
                    <div class="bg-white px-5 py-4">
                        <dt class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $acpReleaseLabel }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900">{{ $acpReleaseValue }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="px-5 py-3 text-[11px] text-slate-500">
                @if ($acpPayout->remarks)
                    <p class="mb-1 text-xs text-slate-600">{{ $acpPayout->remarks }}</p>
                @endif
                Recorded{{ $acpPayout->recorder ? ' by '.$acpPayout->recorder->name : '' }}.
                <a href="{{ route('projects.show', ['project' => $project, 'workspace' => 'overview']) }}#section-acp-release-{{ $acpPayout->id }}"
                    class="font-semibold text-[#063b86] hover:underline">Correct this release in the Overview →</a>
            </div>
        @elseif ($canRecordAcpRelease)
            <form method="POST" action="{{ route('projects.acp-release-of-assistance.store', $project) }}" class="p-5" data-release-form data-confirm-title="Record the Release of Assistance?" data-confirm="Check the mode of payment, date of payout, and venue. The project moves to For Liquidation once the payout date is reached." data-confirm-button="Record Release">
                @csrf

                @if ($acpReleaseBag->any())
                    <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                        <ul class="list-disc pl-4">
                            @foreach ($acpReleaseBag->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="acp-payout-mode">Mode of Payment</label>
                        <select id="acp-payout-mode" name="payout_mode" required data-payout-mode
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">Select mode of payment</option>
                            @foreach ($releaseController::PAYOUT_MODES as $payoutMode)
                                <option value="{{ $payoutMode }}" @selected($acpSelectedMode === $payoutMode)>
                                    {{ $payoutMode === $releaseController::OTHER_MODE ? 'Others, specify' : $payoutMode }}
                                </option>
                            @endforeach
                        </select>
                        <div data-payout-mode-other class="mt-2 {{ $acpSelectedMode === $releaseController::OTHER_MODE ? '' : 'hidden' }}">
                            <input name="payout_mode_other" maxlength="92" placeholder="Specify mode of payment"
                                value="{{ old('payout_mode_other') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="acp-payout-date">Date of Payout</label>
                        <input id="acp-payout-date" name="payout_date" type="date" required value="{{ old('payout_date') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="acp-payout-venue">Venue</label>
                        <input id="acp-payout-venue" name="venue" required maxlength="255" value="{{ old('venue') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    </div>

                    <div class="md:col-span-3">
                        <label class="mb-2 block text-xs font-semibold text-slate-700" for="acp-payout-remarks">
                            Remarks <span class="font-normal text-slate-400">(optional)</span>
                        </label>
                        <input id="acp-payout-remarks" name="remarks" maxlength="3000" value="{{ old('remarks') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        Save Release of Assistance
                    </button>
                </div>
            </form>

            <script>
                (() => {
                    const form = document.querySelector('#acp-release-of-assistance [data-release-form]');
                    const select = form?.querySelector('[data-payout-mode]');
                    const other = form?.querySelector('[data-payout-mode-other]');
                    const input = other?.querySelector('input');

                    select?.addEventListener('change', () => {
                        const isOther = select.value === @js($releaseController::OTHER_MODE);
                        other.classList.toggle('hidden', !isOther);
                        input.required = isOther;
                    });
                })();
            </script>
        @else
            <p class="px-5 py-6 text-center text-xs text-slate-500">
                @if ($acpReleaseOpen)
                    Waiting for the TUPAD Coordinator to record the Release of Assistance.
                @else
                    The Release of Assistance opens after the work period ends.
                @endif
            </p>
        @endif
    </section>
@endif
