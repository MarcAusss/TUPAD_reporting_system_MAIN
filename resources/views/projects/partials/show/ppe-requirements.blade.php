{{-- PPE Requirements --}}

<section id="ppe-requirements" data-workspace-panel="overview"
    class="scroll-mt-32 mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-50 text-orange-600" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">PPE Requirements</h2>
                <p class="mt-0.5 text-xs text-slate-500">Protective equipment planned for the project beneficiaries.</p>
            </div>
        </div>
        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700">
            ₱{{ number_format((float) $project->ppeItems->sum('total_amount'), 2) }} total
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="tupad-system-table min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Type</th>
                    <th class="px-5 py-3 text-left font-semibold">Product</th>
                    <th class="px-5 py-3 text-right font-semibold">Beneficiaries</th>
                    <th class="px-5 py-3 text-right font-semibold">Quantity</th>
                    <th class="px-5 py-3 text-right font-semibold">Unit Amount</th>
                    <th class="px-5 py-3 text-right font-semibold">Total</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($project->ppeItems as $item)
                    <tr class="transition hover:bg-slate-50/70">
                        <td class="px-5 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $item->ppe_type === \App\Enums\PpeType::HAZARDOUS ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $item->ppe_type->label() }}
                            </span>
                        </td>
                        <td class="px-5 py-3 font-medium text-slate-800">{{ $item->product }}</td>
                        <td class="px-5 py-3 text-right text-slate-600">{{ number_format($item->beneficiary_count) }}</td>
                        <td class="px-5 py-3 text-right text-slate-600">
                            {{ $project->term === \App\Enums\ProjectTerm::LONG_TERM ? number_format($item->quantity) : '—' }}
                        </td>
                        <td class="px-5 py-3 text-right text-slate-600">₱{{ number_format($item->unit_amount, 2) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-slate-900">₱{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-0"><x-empty-state size="sm" icon="shield" title="No PPE requirement was recorded." message="PPE items are set when the project is created." /></td>
                    </tr>
                @endforelse
            </tbody>

            @if ($project->ppeItems->isNotEmpty())
                <tfoot class="border-t-2 border-slate-200 bg-slate-50 text-sm font-bold text-slate-900">
                    <tr>
                        <td colspan="5" class="px-5 py-3 text-right">Total PPE</td>
                        <td class="px-5 py-3 text-right">₱{{ number_format((float) $project->ppeItems->sum('total_amount'), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

</section>
