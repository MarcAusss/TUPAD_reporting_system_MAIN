{{-- Fiscal year picker shared by the GSIS pages. Expects $fiscalYear, $fiscalYears and $route. --}}
<form method="GET" action="{{ route($route) }}" class="flex items-end gap-2">
    @foreach (($keep ?? []) as $name => $value)
        @if (filled($value))
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <div>
        <label for="fiscal_year" class="mb-1 block text-xs font-semibold text-slate-600">Fiscal Year</label>
        <select id="fiscal_year" name="fiscal_year" onchange="this.form.submit()"
            class="h-10 w-32 rounded-lg border border-slate-300 bg-white px-3 text-sm">
            @foreach ($fiscalYears as $year)
                <option value="{{ $year }}" @selected($year === $fiscalYear)>FY {{ $year }}</option>
            @endforeach
        </select>
    </div>
    <noscript>
        <button class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Show</button>
    </noscript>
</form>
