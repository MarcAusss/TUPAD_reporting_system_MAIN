<section class="mb-5" data-focal-operations-overview>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="grid xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="p-5 lg:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Operational Overview</div>
                        <h2 class="mt-1 text-base font-bold text-slate-900">Action Queue Aging</h2>
                        <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                            Prioritize financial records that have remained in their current action queue the longest.
                        </p>
                    </div>
                    <div class="text-[10px] leading-4 text-slate-400 sm:max-w-[280px] sm:text-right">
                        Attention at {{ $actionQueueData['attention_days'] }}+ days; critical at {{ $actionQueueData['critical_days'] }}+ days.
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 overflow-hidden rounded-lg border border-slate-200 bg-slate-50/60 lg:grid-cols-4">
                    @foreach ([
                        ['Pending Actions', $actionQueueData['total_pending'], 'Waiting for Focal action'],
                        ['Needs Attention', $actionQueueData['aged_pending'], $actionQueueData['attention_days'].'+ days'],
                        ['Critical Aging', $actionQueueData['critical_pending'], $actionQueueData['critical_days'].'+ days'],
                        ['Oldest Pending', $actionQueueData['oldest_days'].'d', 'Longest current wait'],
                    ] as [$label, $value, $hint])
                        <div class="border-slate-200 px-4 py-4 {{ $loop->even ? 'border-l' : '' }} {{ $loop->index >= 2 ? 'border-t' : '' }} {{ !$loop->first ? 'lg:border-l' : '' }} lg:border-t-0">
                            <div class="text-[9px] font-bold uppercase tracking-[0.1em] text-slate-400">{{ $label }}</div>
                            <div class="mt-1.5 text-2xl font-extrabold tracking-tight text-slate-950">
                                {{ is_numeric($value) ? number_format($value) : $value }}
                            </div>
                            <div class="mt-1 text-[10px] text-slate-500">{{ $hint }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="border-t border-slate-200 bg-slate-50/45 p-5 xl:border-l xl:border-t-0" data-focal-fund-position>
                <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Fund Position</div>
                <div class="mt-1 flex items-end justify-between gap-3">
                    <div>
                        <div class="text-xs text-slate-500">Available budget</div>
                        <div class="mt-1 text-xl font-extrabold tracking-tight text-slate-950">₱{{ number_format($remainingBudget, 2) }}</div>
                    </div>
                    <div class="text-right text-[10px] font-semibold text-slate-500">{{ number_format($remainingPercent, 1) }}% remaining</div>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-slate-800" style="width: {{ min(100, max(0, $utilizationPercent)) }}%"></div>
                </div>

                <dl class="mt-4 divide-y divide-slate-200 text-xs">
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-slate-500">Program budget</dt>
                        <dd class="font-semibold text-slate-900">₱{{ number_format($totalBudget, 2) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-slate-500">Allocated</dt>
                        <dd class="font-semibold text-slate-900">₱{{ number_format($totalAllocated, 2) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-slate-500">Utilization</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format($utilizationPercent, 1) }}%</dd>
                    </div>
                </dl>
            </aside>
        </div>
    </div>
</section>
