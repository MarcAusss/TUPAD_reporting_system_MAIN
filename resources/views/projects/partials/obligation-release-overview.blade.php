{{--
    Overview: Obligations (left) | Release of Assistance (right), Disbursements below.
    Shown once the Focal has completed the obligation tranches and every tranche is fully disbursed.
--}}
@php
    $orPayments = app(\App\Services\Payments\ProjectPaymentService::class);
    $orSummary = $orPayments->summary($project);
    $orObligations = $project->obligations->sortBy('tranche_number')->values();
    $orShow = $project->implementation_mode !== \App\Enums\ImplementationMode::THROUGH_ACP
        && $orObligations->isNotEmpty()
        && $orSummary['obligations_completed']
        && $orSummary['is_fully_paid'];
@endphp

@if ($orShow)
    @php
        $orMoney = fn (float|int $amount): string => '₱'.number_format((float) $amount, 2);
        $orReleaseController = \App\Http\Controllers\ProjectReleaseOfAssistanceController::class;
        $orReleaseStates = $orObligations->mapWithKeys(fn ($obligation) => [$obligation->id => $orPayments->trancheReleaseState($obligation)]);
        $orReleasedCount = $orReleaseStates->filter(fn ($state) => in_array($state, ['released', 'release_scheduled'], true))->count();

        $orDisbursements = $orObligations
            ->flatMap(fn ($obligation) => $obligation->disbursements->map(fn ($disbursement) => ['tranche' => $obligation->tranche_number, 'row' => $disbursement]))
            ->sortBy(fn ($item) => [$item['row']->date_disbursed?->timestamp ?? 0, $item['tranche']])
            ->values();

        $orStateBadge = [
            'released' => ['Released', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
            'release_scheduled' => ['Payout scheduled', 'bg-sky-50 text-sky-700 ring-sky-600/20'],
            'ready_for_release' => ['Awaiting release', 'bg-amber-50 text-amber-800 ring-amber-600/20'],
            'awaiting_disbursement' => ['Awaiting disbursement', 'bg-slate-100 text-slate-600 ring-slate-500/15'],
        ];

        $orTh = 'px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500';
        $orTd = 'px-3 py-2.5 align-top';
    @endphp

    <section id="obligation-release-overview" class="scroll-mt-32 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">

        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/></svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Payment of Wages &amp; Release of Assistance</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Obligation tranches with their Release of Assistance, and the disbursements recorded for them.</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Obligations completed</span>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Fully disbursed</span>
                <span class="rounded-full px-3 py-1 {{ $orReleasedCount === $orObligations->count() ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                    {{ $orReleasedCount }} of {{ $orObligations->count() }} released
                </span>
            </div>
        </div>

        <div class="grid gap-4 p-5 xl:grid-cols-2">

            {{-- Obligations --}}
            <div class="overflow-hidden rounded-xl border border-slate-200">
                <div class="flex items-center justify-between bg-slate-50 px-4 py-2.5">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-700">Obligations</h3>
                    <span class="text-xs font-bold text-slate-900">{{ $orMoney($orSummary['obligated_cents'] / 100) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" data-no-columns>
                        <thead class="border-b border-slate-100 bg-white">
                            <tr>
                                <th class="{{ $orTh }}">Tranche</th>
                                <th class="{{ $orTh }}">Obligation Date</th>
                                <th class="{{ $orTh }}">Payee</th>
                                <th class="{{ $orTh }} text-right">Beneficiaries</th>
                                <th class="{{ $orTh }} text-right">Wages</th>
                                <th class="{{ $orTh }} text-right">Insurance</th>
                                <th class="{{ $orTh }} text-right">PPE</th>
                                <th class="{{ $orTh }} text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($orObligations as $obligation)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="{{ $orTd }} font-semibold text-slate-900">Tranche {{ $obligation->tranche_number }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-slate-600">{{ $obligation->obligation_date?->format('M d, Y') ?? '—' }}</td>
                                    <td class="{{ $orTd }} text-slate-700">{{ $obligation->payee ?: '—' }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-right text-slate-700">
                                        {{ number_format((int) $obligation->beneficiaries_total) }}
                                        @if ($obligation->beneficiaries_female !== null)
                                            <span class="block text-[10px] text-slate-400">{{ number_format((int) $obligation->beneficiaries_female) }} female</span>
                                        @endif
                                    </td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-right text-slate-700">{{ $orMoney($obligation->wages_amount ?? 0) }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-right text-slate-700">{{ $orMoney($obligation->insurance_amount ?? 0) }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-right text-slate-700">{{ $orMoney($obligation->ppe_amount ?? 0) }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-right font-semibold text-slate-900">{{ $orMoney($orPayments->obligationCents($obligation) / 100) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                            <tr>
                                <td class="{{ $orTd }}" colspan="3">Total ({{ $orObligations->count() }} {{ \Illuminate\Support\Str::plural('tranche', $orObligations->count()) }})</td>
                                <td class="{{ $orTd }} text-right">{{ number_format((int) $orObligations->sum('beneficiaries_total')) }}</td>
                                <td class="{{ $orTd }} whitespace-nowrap text-right">{{ $orMoney($orObligations->sum('wages_amount')) }}</td>
                                <td class="{{ $orTd }} whitespace-nowrap text-right">{{ $orMoney($orObligations->sum('insurance_amount')) }}</td>
                                <td class="{{ $orTd }} whitespace-nowrap text-right">{{ $orMoney($orObligations->sum('ppe_amount')) }}</td>
                                <td class="{{ $orTd }} whitespace-nowrap text-right">{{ $orMoney($orSummary['obligated_cents'] / 100) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Release of Assistance --}}
            <div class="overflow-hidden rounded-xl border border-emerald-200">
                <div class="flex items-center justify-between bg-emerald-50 px-4 py-2.5">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-emerald-800">Release of Assistance</h3>
                    <span class="text-xs font-bold text-emerald-800">{{ $orReleasedCount }} / {{ $orObligations->count() }} released</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" data-no-columns>
                        <thead class="border-b border-slate-100 bg-white">
                            <tr>
                                <th class="{{ $orTh }}">Tranche</th>
                                <th class="{{ $orTh }}">Mode of Payment</th>
                                <th class="{{ $orTh }}">Date of Payout</th>
                                <th class="{{ $orTh }}">Venue</th>
                                <th class="{{ $orTh }}">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($orObligations as $obligation)
                                @php
                                    [$orMode, $orModeOther] = $orReleaseController::splitPayoutMode($obligation->release_mode);
                                    [$orStateLabel, $orStateClasses] = $orStateBadge[$orReleaseStates[$obligation->id]] ?? $orStateBadge['awaiting_disbursement'];
                                @endphp
                                <tr class="hover:bg-slate-50/70">
                                    <td class="{{ $orTd }} font-semibold text-slate-900">Tranche {{ $obligation->tranche_number }}</td>
                                    <td class="{{ $orTd }} text-slate-700">
                                        @if ($obligation->release_mode)
                                            {{ $orMode === $orReleaseController::OTHER_MODE ? 'Others' : $orMode }}
                                            @if ($orModeOther)
                                                <span class="block text-[10px] text-slate-400">{{ $orModeOther }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-slate-600">{{ $obligation->release_date?->format('M d, Y') ?? '—' }}</td>
                                    <td class="{{ $orTd }} text-slate-700">{{ $obligation->release_venue ?: '—' }}</td>
                                    <td class="{{ $orTd }}">
                                        <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $orStateClasses }}">{{ $orStateLabel }}</span>
                                        @if ($obligation->releaser)
                                            <span class="mt-0.5 block text-[10px] text-slate-400">by {{ $obligation->releaser->name }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-emerald-200 bg-emerald-50/60 font-bold text-emerald-900">
                            <tr>
                                <td class="{{ $orTd }}" colspan="4">Released</td>
                                <td class="{{ $orTd }} whitespace-nowrap">{{ $orReleasedCount }} of {{ $orObligations->count() }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Disbursements --}}
        <div class="px-5 pb-5">
            <div class="overflow-hidden rounded-xl border border-sky-200">
                <div class="flex items-center justify-between bg-sky-50 px-4 py-2.5">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-sky-900">Disbursements</h3>
                    <span class="text-xs font-bold text-sky-900">{{ $orMoney($orSummary['disbursed_cents'] / 100) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm" data-no-columns>
                        <thead class="border-b border-slate-100 bg-white">
                            <tr>
                                <th class="{{ $orTh }}">Tranche</th>
                                <th class="{{ $orTh }}">Date Disbursed</th>
                                <th class="{{ $orTh }}">LDAP / Check No.</th>
                                <th class="{{ $orTh }}">Remarks</th>
                                <th class="{{ $orTh }}">Recorded By</th>
                                <th class="{{ $orTh }} text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($orDisbursements as $item)
                                @php $disbursement = $item['row']; @endphp
                                <tr class="hover:bg-slate-50/70">
                                    <td class="{{ $orTd }} font-semibold text-slate-900">Tranche {{ $item['tranche'] }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-slate-600">{{ $disbursement->date_disbursed?->format('M d, Y') ?? '—' }}</td>
                                    <td class="{{ $orTd }} text-slate-700">{{ $disbursement->ldap_check_number ?: '—' }}</td>
                                    <td class="{{ $orTd }} text-slate-600">{{ $disbursement->remarks ?: '—' }}</td>
                                    <td class="{{ $orTd }} text-slate-600">{{ $disbursement->recorder?->name ?? '—' }}</td>
                                    <td class="{{ $orTd }} whitespace-nowrap text-right font-semibold text-slate-900">{{ $orMoney($disbursement->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-0"><x-empty-state size="sm" icon="money" title="No disbursements recorded." /></td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t-2 border-sky-200 bg-sky-50/60 font-bold text-sky-900">
                            <tr>
                                <td class="{{ $orTd }}" colspan="5">Total disbursed ({{ $orDisbursements->count() }} {{ \Illuminate\Support\Str::plural('disbursement', $orDisbursements->count()) }})</td>
                                <td class="{{ $orTd }} whitespace-nowrap text-right">{{ $orMoney($orSummary['disbursed_cents'] / 100) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endif
