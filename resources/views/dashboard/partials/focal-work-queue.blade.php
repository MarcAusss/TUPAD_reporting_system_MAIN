<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-focal-work-queue>
    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Financial Operations</div>
            <h2 class="mt-1 text-sm font-bold text-slate-900">Focal Work Queue</h2>
            <p class="mt-1 text-xs text-slate-500">Fund monitoring and financial actions that require Focal attention.</p>
        </div>
        <div class="text-[10px] text-slate-400">Open only the queue that currently requires action.</div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-slate-50/80">
                <tr>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Financial action</th>
                    <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-slate-500">Purpose</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Pending</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Aged</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Oldest</th>
                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach (['payment', 'acp_payment', 'acp_check_release', 'acp_liquidation'] as $queueKey)
                    @php($queue = $actionQueueData['queues'][$queueKey] ?? null)
                    @if ($queue)
                        <tr data-dashboard-queue="{{ $queueKey }}" class="hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full {{ $queue['critical_count'] > 0 ? 'bg-rose-500' : ($queue['aged_count'] > 0 ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                    <span class="text-xs font-bold text-slate-900">{{ $queue['label'] }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $queue['description'] }}</td>
                            <td class="px-5 py-3 text-right text-sm font-extrabold text-slate-950">{{ number_format($queue['count']) }}</td>
                            <td class="px-5 py-3 text-right text-xs font-semibold {{ $queue['aged_count'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ number_format($queue['aged_count']) }}</td>
                            <td class="px-5 py-3 text-right text-xs font-semibold {{ $queue['critical_count'] > 0 ? 'text-rose-700' : 'text-slate-500' }}">{{ $queue['oldest_days'] }}d</td>
                            <td class="px-5 py-3 text-right"><a href="{{ $queue['url'] }}" class="text-xs font-semibold text-blue-700 hover:text-blue-900">Open →</a></td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</section>
