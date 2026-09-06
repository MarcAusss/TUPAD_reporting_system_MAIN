@extends('layouts.app')

@section('title', $title)

@section('content')
    @php
        $utilization = max(0, min(100, (float) ($totals['utilization'] ?? 0)));
        $targetBeneficiaries = max(0, (int) ($totals['target_beneficiaries'] ?? 0));
        $accomplishedBeneficiaries = max(0, (int) ($totals['beneficiaries'] ?? 0));
        $femaleBeneficiaries = max(0, (int) ($totals['female'] ?? 0));
        $beneficiaryProgress = $targetBeneficiaries > 0
            ? min(100, ($accomplishedBeneficiaries / $targetBeneficiaries) * 100)
            : 0;

        $workflowStages = [
            ['key' => 'under_evaluation', 'label' => 'Under Evaluation'],
            ['key' => 'for_approval', 'label' => 'For Approval'],
            ['key' => 'approved', 'label' => 'Approved'],
            ['key' => 'for_implementation', 'label' => 'With NTP / For Implementation'],
            ['key' => 'ongoing_implementation', 'label' => 'Ongoing Implementation'],
            ['key' => 'post_docs', 'label' => 'Implemented / Post-Docs'],
            ['key' => 'for_payment', 'label' => 'IMSD / Payment'],
        ];

        $workflowMax = collect($workflowStages)
            ->map(fn (array $stage) => (float) ($totals[$stage['key']] ?? 0))
            ->max() ?: 1;
    @endphp

    <div data-regional-fund-summary="true">
        <x-page-header eyebrow="Monitoring" :title="$title"
            description="Regional fund summary generated from current PER ADL data and official project workflow records." />

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,.75fr)]">
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Regional Fund Position</h2>
                        <p class="mt-1 text-xs text-slate-500">Allocation, target, obligation and remaining regional fund position.</p>
                    </div>
                    <span class="inline-flex w-fit items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-blue-800">
                        Current monitoring basis
                    </span>
                </div>

                <div class="grid sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['Allocation Total', $totals['allocation_total'], 'Regional ceiling'],
                        ['Target Grants', $totals['target_grants'], 'Program target'],
                        ['Obligated / Accomplished', $totals['obligated_grants'], 'Recorded obligation'],
                        ['Available Balance', $totals['available_balance'], 'Available fund position'],
                    ] as [$label, $value, $caption])
                        <div class="border-b border-slate-100 px-5 py-5 sm:border-r xl:border-b-0 last:border-r-0">
                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $label }}</div>
                            <div class="mt-2 text-xl font-bold tabular-nums text-slate-950">₱{{ number_format($value, 2) }}</div>
                            <div class="mt-1 text-[11px] text-slate-500">{{ $caption }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-200 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Fund Utilization</div>
                            <div class="mt-1 text-2xl font-bold tabular-nums text-[#063b86]">{{ number_format($totals['utilization'], 2) }}%</div>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            ₱{{ number_format($totals['obligated_grants'], 2) }} of ₱{{ number_format($totals['target_grants'], 2) }}
                        </div>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200" aria-label="Fund utilization">
                        <div class="h-full rounded-full bg-[#1765d8] transition-all"
                            style="width: {{ number_format($utilization, 2, '.', '') }}%"></div>
                    </div>
                </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Coverage Snapshot</h2>
                    <p class="mt-1 text-xs text-slate-500">Current monitoring scope represented in this summary.</p>
                </div>
                <dl class="divide-y divide-slate-100 px-5">
                    <div class="flex items-center justify-between gap-4 py-4">
                        <dt class="text-xs text-slate-500">Monitored ADL Rows</dt>
                        <dd class="text-lg font-bold tabular-nums text-slate-900">{{ number_format($rowCount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-4">
                        <dt class="text-xs text-slate-500">Province Coverage</dt>
                        <dd class="text-lg font-bold tabular-nums text-slate-900">{{ number_format($provinceCount) }}</dd>
                    </div>
                    <div class="py-4">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-xs text-slate-500">Unutilized Funds</dt>
                            <dd class="text-sm font-bold tabular-nums text-slate-900">₱{{ number_format($totals['unutilized'], 2) }}</dd>
                        </div>
                    </div>
                </dl>
                <div class="border-t border-slate-200 bg-slate-50/70 px-5 py-4">
                    <a href="{{ route('fund-monitoring.per-adl-current') }}"
                        class="inline-flex h-9 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-xs font-semibold text-[#17325c] transition hover:bg-slate-50">
                        Open PER ADL Register
                    </a>
                </div>
            </article>
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)]">
            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Cost Composition</h2>
                    <p class="mt-1 text-xs text-slate-500">Current recorded program cost components.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ([
                        ['Wages', $totals['wages']],
                        ['PPE', $totals['ppe']],
                        ['Insurance', $totals['insurance']],
                        ['Remaining', $totals['remaining']],
                    ] as [$label, $value])
                        <div class="flex items-center justify-between gap-5 px-5 py-3.5">
                            <span class="text-xs font-medium text-slate-600">{{ $label }}</span>
                            <span class="text-sm font-bold tabular-nums text-slate-900">₱{{ number_format($value, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Beneficiary Delivery</h2>
                    <p class="mt-1 text-xs text-slate-500">Target and accomplished beneficiary coverage from official project records.</p>
                </div>
                <div class="grid gap-0 sm:grid-cols-3">
                    <div class="border-b border-slate-100 px-5 py-5 sm:border-b-0 sm:border-r">
                        <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Target</div>
                        <div class="mt-2 text-2xl font-bold tabular-nums text-slate-950">{{ number_format($targetBeneficiaries) }}</div>
                    </div>
                    <div class="border-b border-slate-100 px-5 py-5 sm:border-b-0 sm:border-r">
                        <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Accomplished</div>
                        <div class="mt-2 text-2xl font-bold tabular-nums text-[#063b86]">{{ number_format($accomplishedBeneficiaries) }}</div>
                    </div>
                    <div class="px-5 py-5">
                        <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Female</div>
                        <div class="mt-2 text-2xl font-bold tabular-nums text-slate-950">{{ number_format($femaleBeneficiaries) }}</div>
                    </div>
                </div>
                <div class="border-t border-slate-200 bg-slate-50/70 px-5 py-4">
                    <div class="flex items-center justify-between gap-4 text-xs">
                        <span class="font-medium text-slate-600">Accomplishment against target</span>
                        <span class="font-bold tabular-nums text-[#063b86]">{{ number_format($beneficiaryProgress, 2) }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-[#1765d8]"
                            style="width: {{ number_format($beneficiaryProgress, 2, '.', '') }}%"></div>
                    </div>
                </div>
            </article>
        </section>

        <section data-workflow-exposure="true" class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Workflow Exposure</h2>
                    <p class="mt-1 text-xs text-slate-500">Fund exposure across active project workflow stages.</p>
                </div>
                <div class="text-xs text-slate-500">
                    Remaining balance: <span class="font-bold tabular-nums text-slate-800">₱{{ number_format($totals['remaining'], 2) }}</span>
                </div>
            </div>

            <div class="grid gap-x-8 gap-y-4 p-5 lg:grid-cols-2">
                @foreach ($workflowStages as $stage)
                    @php
                        $amount = (float) ($totals[$stage['key']] ?? 0);
                        $width = $workflowMax > 0 ? min(100, ($amount / $workflowMax) * 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-xs font-medium text-slate-600">{{ $stage['label'] }}</span>
                            <span class="text-xs font-bold tabular-nums text-slate-900">₱{{ number_format($amount, 2) }}</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-[#1f5fae]"
                                style="width: {{ number_format($width, 2, '.', '') }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
