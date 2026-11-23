{{-- TUPAD Coordinator: compact action queue aging summary, shown above the work queue. --}}
<section class="mb-5" data-tc-aging-summary>
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <div class="flex items-baseline gap-2">
                    <h2 class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Action Queue Aging</h2>
                </div>
                <span class="text-[11px] text-slate-400">
                    Attention {{ $actionQueueData['attention_days'] }}+ days · critical {{ $actionQueueData['critical_days'] }}+ days
                </span>
            </div>

            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([
                    ['Pending', $actionQueueData['total_pending'], 'text-slate-950'],
                    ['Needs Attention', $actionQueueData['aged_pending'], $actionQueueData['aged_pending'] > 0 ? 'text-amber-700' : 'text-slate-950'],
                    ['Critical', $actionQueueData['critical_pending'], $actionQueueData['critical_pending'] > 0 ? 'text-rose-700' : 'text-slate-950'],
                    ['Oldest Pending', $actionQueueData['oldest_days'].'d', 'text-slate-950'],
                ] as [$label, $value, $tone])
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $label }}</div>
                        <div class="text-lg font-extrabold leading-tight {{ $tone }}">
                            {{ is_numeric($value) ? number_format($value) : $value }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
</section>
