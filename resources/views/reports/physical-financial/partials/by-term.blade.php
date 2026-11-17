{{--
    Physical & Financial table split by project term (Short-Term / Long-Term).
    Overall view: Target | Accomplishment | Balance, each per term.
    Semester view: Accomplishment is further split into 1st / 2nd Semester.
    Expects $matrix, $viewKey, $number, $money from the parent view.
--}}
@php
    $termKeys = ['short_term', 'long_term'];
    $termLabels = ['short_term' => 'Short-Term', 'long_term' => 'Long-Term'];
    $periods = $viewKey === 'overall' ? [] : $matrix['periods'];
    $hasPeriods = $periods !== [];
    $headerRows = $hasPeriods ? 4 : 3;

    $cell = static fn (?array $values, string $key, callable $format): string =>
        $values === null ? '—' : $format($values[$key] ?? 0);

    // Data columns in display order: [path inside terms.<term>, ...].
    $columns = [];
    foreach ($termKeys as $termKey) {
        $columns[] = $termKey.'.target';
    }
    if ($hasPeriods) {
        foreach ($periods as $period) {
            foreach ($termKeys as $termKey) {
                $columns[] = $termKey.'.periods.'.$period['key'];
            }
        }
    } else {
        foreach ($termKeys as $termKey) {
            $columns[] = $termKey.'.accomplishment';
        }
    }
    foreach ($termKeys as $termKey) {
        $columns[] = $termKey.'.balance';
    }

    $unsplitProvinces = collect($matrix['rows'])
        ->filter(fn (array $row): bool => data_get($row, 'terms.short_term.target') === null)
        ->pluck('province');

    $th = 'border border-slate-700 px-3 py-1.5 text-center font-bold';
@endphp

<div class="overflow-x-auto p-4" data-pf-panel="term" hidden>
    @if ($unsplitProvinces->isNotEmpty())
        <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800">
            <strong>Target not yet split by term:</strong> {{ $unsplitProvinces->implode(', ') }}.
            These provinces have a saved combined target only, so their term target and balance show “—”.
            @if (auth()->user()?->isFocal())
                <a href="{{ route('reports.reformulated-targets.edit', ['fiscal_year' => $matrix['fiscal_year'] ?? now('Asia/Manila')->year]) }}" class="font-semibold underline">Split the target</a>.
            @endif
        </div>
    @endif

    <table class="tupad-report-table tupad-report-screen-table w-full min-w-max border-collapse text-[11px] text-slate-800"
        aria-label="{{ $hasPeriods ? 'Accomplishment per '.($viewKey === 'quarter' ? 'quarter' : 'semester') : 'Overall accomplishment' }} by short-term and long-term projects">
        <thead>
            <tr>
                <th rowspan="{{ $headerRows }}" class="border border-slate-700 px-3 py-2 text-left font-extrabold">Province</th>
                <th colspan="4" @if ($hasPeriods) rowspan="2" @endif class="border border-slate-700 px-3 py-2 text-center font-extrabold">Reformulated Target</th>
                <th colspan="{{ $hasPeriods ? 4 * count($periods) : 4 }}" class="border border-slate-700 px-3 py-2 text-center font-extrabold">Accomplishment</th>
                <th colspan="4" @if ($hasPeriods) rowspan="2" @endif class="border border-slate-700 px-3 py-2 text-center font-extrabold">Balance</th>
            </tr>
            @if ($hasPeriods)
                <tr>
                    @foreach ($periods as $period)
                        <th colspan="4" class="{{ $th }}">{{ $period['label'] }}</th>
                    @endforeach
                </tr>
            @endif
            <tr>
                @foreach (range(1, count($columns) / 2) as $group)
                    @foreach ($termKeys as $termKey)
                        <th colspan="2" class="{{ $th }}">{{ $termLabels[$termKey] }}</th>
                    @endforeach
                @endforeach
            </tr>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ $th }}">Physical</th>
                    <th class="{{ $th }}">Financial</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($matrix['rows'] as $row)
                <tr class="hover:bg-slate-50">
                    <td class="border border-slate-700 px-3 py-2 font-semibold">{{ $row['province'] }}</td>
                    @foreach ($columns as $column)
                        @php($values = data_get($row, 'terms.'.$column))
                        <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $cell($values, 'physical', $number) }}</td>
                        <td class="border border-slate-700 px-3 py-2 text-right tabular-nums">{{ $cell($values, 'financial_cents', $money) }}</td>
                    @endforeach
                </tr>
            @endforeach

            <tr class="tupad-report-total-row font-extrabold text-white">
                <td class="border border-slate-700 px-3 py-2">TOTAL</td>
                @foreach ($columns as $column)
                    @php($values = data_get($matrix['total'], 'terms.'.$column))
                    <td class="border border-slate-700 px-3 py-2 text-right">{{ $cell($values, 'physical', $number) }}</td>
                    <td class="border border-slate-700 px-3 py-2 text-right">{{ $cell($values, 'financial_cents', $money) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
