{{-- Project Status History --}}

<section id="history" data-workspace-panel="history"
    class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Project Status History
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Historical workflow transitions for this project.
        </p>

    </div>

    <div class="overflow-x-auto">

        <table class="tupad-system-table min-w-full">

            <thead class="bg-slate-50">

                <tr>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Date & Time
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        From
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        To
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Changed By
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Remarks
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse($project
                                ->statusHistory
                                ->sortByDesc('changed_at')
                            as $history)
                    <tr>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                            {{ $history->changed_at->format('M d, Y g:i A') }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $history->from_status?->label() ?? 'Created' }}
                        </td>

                        <td class="px-5 py-4">

                            <span
                                class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                {{ $history->to_status->label() }}
                            </span>

                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $history->changer?->name ?? 'System' }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-500">
                            {{ $history->remarks ?: '—' }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="5" class="p-0"><x-empty-state size="sm" icon="history" title="No status history has been recorded yet." message="Every workflow status change is logged here with who made it and when." /></td>

                    </tr>
                @endforelse

            </tbody>

        </table>

    </div>

</section>
