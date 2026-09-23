<section class="mb-5" data-tc-operations-overview>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-5 lg:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Operational Overview</div>
                    <h2 class="mt-1 text-base font-bold text-slate-900">Action Queue Aging</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                        Prioritize projects that have remained in their current workflow status the longest.
                    </p>
                </div>
                <div class="text-[10px] leading-4 text-slate-400 sm:max-w-[280px] sm:text-right">
                    Attention at {{ $actionQueueData['attention_days'] }}+ days; critical at {{ $actionQueueData['critical_days'] }}+ days.
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 overflow-hidden rounded-lg border border-slate-200 bg-slate-50/60 lg:grid-cols-4">
                @foreach ([
                    ['Pending Actions', $actionQueueData['total_pending'], 'Waiting in your workflow queues'],
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
    </div>
</section>
