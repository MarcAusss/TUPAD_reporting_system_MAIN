{{--
    Beneficiary Mapping vs Actual Beneficiary Mapping, side by side.
    Actual Beneficiary Mapping = Beneficiary Mapping − TC deductions per barangay.
--}}
@php
    $mappingService = app(\App\Services\Projects\ProjectBeneficiaryDeductionService::class);
    $mappingRows = $mappingService->rows($project);
    $mappingFromLocations = $mappingService->source($project) === \App\Services\Projects\ProjectBeneficiaryDeductionService::SOURCE_PROJECT_LOCATIONS;
    $mappingNeedsDeduction = $mappingService->needsDeduction($project);
    $mappingRecorded = $mappingService->isRecorded($project);
    $mappingShortfall = $mappingService->shortfall($project);

    $mappingTotals = [
        'mapped_total' => array_sum(array_column($mappingRows, 'mapped_total')),
        'mapped_female' => array_sum(array_column($mappingRows, 'mapped_female')),
        'actual_total' => array_sum(array_column($mappingRows, 'actual_total')),
        'actual_female' => array_sum(array_column($mappingRows, 'actual_female')),
        'deducted_total' => array_sum(array_column($mappingRows, 'deducted_total')),
    ];

    [$mappingStatusLabel, $mappingStatusTone, $mappingStatusNote] = match (true) {
        $mappingRows === [] => ['No mapping yet', 'bg-slate-100 text-slate-600', 'Encode the Beneficiary Mapping Source (Beneficiaries tab) to see the barangay breakdown.'],
        $mappingNeedsDeduction && $mappingRecorded => ['Deductions recorded', 'bg-emerald-50 text-emerald-700', 'The Actual Beneficiary Mapping excludes the beneficiaries not included in the completed obligations.'],
        $mappingNeedsDeduction => ['Deductions pending', 'bg-amber-50 text-amber-700', sprintf('%d beneficiar%s were not included in the obligations. The TUPAD Coordinator still needs to record which barangays they came from.', $mappingShortfall['total'] ?? 0, ($mappingShortfall['total'] ?? 0) === 1 ? 'y' : 'ies')],
        $project->obligations_completed_at !== null => ['No deductions needed', 'bg-emerald-50 text-emerald-700', 'The completed obligations cover every beneficiary, so both mappings are the same.'],
        default => ['Final after obligations', 'bg-blue-50 text-[#063b86]', 'The Actual Beneficiary Mapping is finalized once the Focal completes the obligation tranches. Until then it matches the Beneficiary Mapping.'],
    };
@endphp

<section id="beneficiary-mapping-comparison" class="scroll-mt-32 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">

    <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-700" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0 0 21 18.382V7.618a1 1 0 0 0-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            </span>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Beneficiary Mapping vs Actual Beneficiary Mapping</h2>
                <p class="mt-0.5 text-xs text-slate-500">Actual = Beneficiary Mapping − beneficiaries not included, per barangay.</p>
            </div>
        </div>
        <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold {{ $mappingStatusTone }}">{{ $mappingStatusLabel }}</span>
    </div>

    <p class="border-b border-slate-100 bg-slate-50/70 px-5 py-2.5 text-xs text-slate-600">
        {{ $mappingStatusNote }}
        @if ($mappingFromLocations && $mappingRows !== [])
            Barangays are taken from the project's location allocations.
        @endif
    </p>

    @if ($mappingRows === [])
        <x-empty-state icon="users" title="No barangay allocations recorded." action-label="Encode Beneficiary Mapping" action-target="beneficiaryAddressForm" action-tab="beneficiaries" message="Encode the Beneficiary Mapping Source on the Beneficiaries tab to compare the mappings per barangay." />
    @else
        <div class="grid gap-4 p-5 lg:grid-cols-2">
            @foreach ([
                ['title' => 'Beneficiary Mapping', 'hint' => 'As encoded for the project', 'total' => 'mapped_total', 'female' => 'mapped_female', 'accent' => 'border-slate-200', 'head' => 'bg-slate-50 text-slate-600'],
                ['title' => 'Actual Beneficiary Mapping', 'hint' => 'After deducting beneficiaries not included', 'total' => 'actual_total', 'female' => 'actual_female', 'accent' => 'border-emerald-200', 'head' => 'bg-emerald-50 text-emerald-800'],
            ] as $table)
                @php $isActualTable = $table['total'] === 'actual_total'; @endphp
                <div class="overflow-hidden rounded-xl border {{ $table['accent'] }}">
                    <div class="flex items-center justify-between gap-2 px-4 py-3 {{ $table['head'] }}">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-wide">{{ $table['title'] }}</div>
                            <div class="text-[11px] opacity-80">{{ $table['hint'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-extrabold">{{ number_format($mappingTotals[$table['total']]) }}</div>
                            <div class="text-[11px] opacity-80">beneficiaries</div>
                        </div>
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 bg-white text-[11px] uppercase tracking-wide text-slate-400 shadow-[0_1px_0_#f1f5f9]">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Barangay</th>
                                    <th class="px-4 py-2 text-right font-semibold">Total</th>
                                    <th class="px-4 py-2 text-right font-semibold">Female</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($mappingRows as $row)
                                    @php $rowDeducted = $isActualTable && $row['deducted_total'] > 0; @endphp
                                    <tr class="{{ $rowDeducted ? 'bg-amber-50/60' : '' }}">
                                        <td class="px-4 py-2.5">
                                            <div class="font-medium text-slate-800">{{ $row['label'] }}</div>
                                            @if ($rowDeducted)
                                                <div class="mt-0.5 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                                    −{{ number_format($row['deducted_total']) }} not included
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-semibold {{ $rowDeducted ? 'text-amber-800' : 'text-slate-800' }}">
                                            {{ number_format($row[$table['total']]) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-slate-600">
                                            {{ $mappingFromLocations ? '—' : number_format($row[$table['female']]) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                                <tr>
                                    <td class="px-4 py-2.5">Total ({{ count($mappingRows) }} barangay{{ count($mappingRows) === 1 ? '' : 's' }})</td>
                                    <td class="px-4 py-2.5 text-right">{{ number_format($mappingTotals[$table['total']]) }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ $mappingFromLocations ? '—' : number_format($mappingTotals[$table['female']]) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($mappingTotals['deducted_total'] > 0)
            <div class="border-t border-slate-100 px-5 py-3 text-xs text-slate-600">
                <span class="font-semibold text-slate-800">{{ number_format($mappingTotals['mapped_total']) }}</span> mapped
                − <span class="font-semibold text-amber-700">{{ number_format($mappingTotals['deducted_total']) }}</span> not included
                = <span class="font-semibold text-emerald-700">{{ number_format($mappingTotals['actual_total']) }}</span> actual beneficiaries
            </div>
        @endif
    @endif
</section>
