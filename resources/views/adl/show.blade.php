@extends('layouts.app')
@section('title', $adl->adl_number)
@section('content')
<x-page-header
    eyebrow="Fund Management"
    :breadcrumbs="[['label' => 'ADL Management', 'url' => route('adl.index')], ['label' => 'ADL '.$adl->adl_number]]"
    :title="$adl->adl_number"
    description="Review the ADL fund position, allocation records, re-alignments, and current PER ADL monitoring breakdown."
>
    <x-slot:actions>
        <a
            href="{{ route('adl.index') }}"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            ← ADL Management
        </a>

        @if(Route::has('fund-monitoring.per-adl-current'))
            <a
                href="{{ route('fund-monitoring.per-adl-current', ['adl_id' => $adl->id]) }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 text-sm font-semibold text-blue-800 hover:bg-blue-100"
            >
                Open PER ADL
            </a>
        @endif

        <a
            href="{{ route('adl.edit', $adl) }}"
            class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]"
        >
            Edit ADL
        </a>
    </x-slot:actions>
</x-page-header>

<div class="mb-5 flex flex-wrap gap-2">
    <a href="#fund-position" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
        Fund Position
    </a>
    <a href="#per-adl-breakdown" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
        PER ADL Breakdown
    </a>
    <a href="#fund-actions" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
        Fund Actions
    </a>
    <a href="#allocation-records" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
        Allocation Records
    </a>
</div>

<div id="fund-position" class="scroll-mt-28 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Original Grants', $adl->grants],
        ['Re-alignment', $adl->total_realignment],
        ['Adjusted Grants', $adl->adjusted_grants],
        ['Remaining Grant Balance', $adl->remaining_balance],
    ] as [$label,$value])
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase text-slate-400">{{ $label }}</div><div class="mt-3 text-xl font-bold text-slate-900">₱{{ number_format($value,2) }}</div></div>
    @endforeach
</div>

<section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">ADL Reference Information</h2></div>
    <div class="grid gap-x-8 gap-y-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Date Received' => $adl->date_received?->format('M d, Y') ?: '—',
            'Batch' => $adl->batch ?: '—', 'Tranche' => $adl->tranche ?: '—',
            'Sponsor / Reference' => $adl->sponsor_reference ?: '—',
            'NFA' => collect([$adl->nfa_date?->format('M d, Y'), $adl->nfa_number])->filter()->implode(' / ') ?: '—',
            'NTA' => collect([$adl->nta_date?->format('M d, Y'), $adl->nta_number])->filter()->implode(' / ') ?: '—',
            'Admin Cost' => '₱'.number_format($adl->admin_cost,2), 'Total ADL' => '₱'.number_format($adl->total,2),
        ] as $label=>$value)
        <div><div class="text-xs text-slate-500">{{ $label }}</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ $value }}</div></div>
        @endforeach
    </div>
</section>

<section id="per-adl-breakdown" class="scroll-mt-28 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">PER ADL (Current) Summary</h2><p class="mt-1 text-xs text-slate-500">Calculated from allocations, official projects, obligations and project statuses.</p></div></div>
    <div class="border-b border-slate-100 bg-slate-50 px-5 py-2 text-[11px] font-medium text-slate-500">
        Scroll horizontally to review the complete official fund breakdown.
    </div>
    <div class="tupad-data-scroll overflow-x-auto"><table class="tupad-system-table tupad-wide-table min-w-[2200px] w-full text-xs">
        <thead>
            <tr class="tupad-column-groups">
                <th colspan="5">Fund &amp; Geography</th>
                <th colspan="9">Allocation &amp; Targets</th>
                <th colspan="6">Project Cost &amp; Beneficiaries</th>
            </tr>
            <tr class="tupad-column-labels">
                <th class="p-3 text-left">Fund Sponsor</th><th class="p-3 text-left">Partner</th><th class="p-3 text-left">Province</th><th class="p-3 text-left">District</th><th class="p-3 text-left">Municipality</th><th class="p-3 text-right">Allocation Grants</th><th class="p-3 text-right">Admin Cost</th><th class="p-3 text-right">Allocation Total</th><th class="p-3 text-right">Re-alignment</th><th class="p-3 text-left">MAF</th><th class="p-3 text-right">Target Grants</th><th class="p-3 text-right">Target Ben.</th><th class="p-3 text-right">Obligated</th><th class="p-3 text-right">%</th><th class="p-3 text-right">Wages</th><th class="p-3 text-right">PPE</th><th class="p-3 text-right">Insurance</th><th class="p-3 text-right">Ben.</th><th class="p-3 text-right">Female</th><th class="p-3 text-right">Unutilized</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">@forelse($perAdlRows as $row)<tr class="hover:bg-slate-50"><td class="p-3">{{ $row['fund_sponsor'] }}</td><td class="p-3">{{ $row['partner'] }}</td><td class="p-3">{{ $row['province'] ?: '—' }}</td><td class="p-3">{{ $row['district'] ?: '—' }}</td><td class="p-3">{{ $row['municipality'] ?: '—' }}</td>@foreach(['allocation_grants','allocation_admin_cost','allocation_total','realignment_grants'] as $k)<td class="p-3 text-right">₱{{ number_format($row[$k],2) }}</td>@endforeach<td class="p-3">{{ $row['maf'] ?: '—' }}</td><td class="p-3 text-right">₱{{ number_format($row['target_grants'],2) }}</td><td class="p-3 text-right">{{ number_format($row['target_beneficiaries']) }}</td><td class="p-3 text-right">₱{{ number_format($row['obligated_grants'],2) }}</td><td class="p-3 text-right">{{ number_format($row['utilization'],2) }}%</td>@foreach(['wages','ppe','insurance'] as $k)<td class="p-3 text-right">₱{{ number_format($row[$k],2) }}</td>@endforeach<td class="p-3 text-right">{{ number_format($row['beneficiaries']) }}</td><td class="p-3 text-right">{{ number_format($row['female']) }}</td><td class="p-3 text-right">₱{{ number_format($row['unutilized'],2) }}</td></tr>@empty<tr><td colspan="20" class="p-8 text-center text-slate-400">No allocations yet.</td></tr>@endforelse</tbody>
    </table></div>
</section>

<div id="fund-actions" class="scroll-mt-28 mt-5 grid gap-5 xl:grid-cols-2">
<section class="rounded-xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Add Allocation</h2></div><form method="POST" action="{{ route('adl.allocations.store',$adl) }}" class="grid gap-4 p-5 md:grid-cols-2">@csrf
    <div class="md:col-span-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-800">
        <b>Focal reference ownership.</b>
        Encode the official Fund Sponsor and Partner for this ADL breakdown.
        These values become reusable choices in the TUPAD Coordinator's Project Create form.
    </div>

    @foreach ([
        ['fund_sponsor', 'Fund Sponsor', $fundSponsorOptions, 'e.g. DOLE Regional Office V', 'Added to the TC Sponsor dropdown.'],
        ['partner', 'Partner', $partnerOptions, 'e.g. LGU Legazpi City', 'Added to the TC Partner dropdown.'],
    ] as [$field, $fieldLabel, $fieldOptions, $fieldPlaceholder, $fieldHelp])
        @php
            $oldValue = trim((string) old($field));
            $oldIsListed = $oldValue !== '' && $fieldOptions->contains($oldValue);
            $oldIsOther = $oldValue !== '' && ! $oldIsListed;
        @endphp
        <div data-reference-field>
            <label for="{{ $field }}_choice" class="mb-1 block text-xs font-semibold">
                {{ $fieldLabel }}
            </label>

            <select
                id="{{ $field }}_choice"
                data-reference-select
                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >
                <option value="">Select {{ strtolower($fieldLabel) }}</option>
                @foreach ($fieldOptions as $option)
                    <option value="{{ $option }}" @selected($oldIsListed && $oldValue === $option)>{{ $option }}</option>
                @endforeach
                <option value="__other__" @selected($oldIsOther)>Other — type a new {{ strtolower($fieldLabel) }}</option>
            </select>

            <input
                id="{{ $field }}"
                name="{{ $field }}"
                data-reference-value
                value="{{ $oldValue }}"
                maxlength="255"
                placeholder="{{ $fieldPlaceholder }}"
                aria-label="New {{ strtolower($fieldLabel) }}"
                class="{{ $oldIsOther ? '' : 'hidden' }} mt-2 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >

            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                {{ $fieldHelp }}
                @if ($fieldOptions->isEmpty())
                    No {{ strtolower($fieldLabel) }} has been encoded yet — choose Other to add the first one.
                @endif
            </p>
        </div>
    @endforeach

    <div class="md:col-span-2 grid gap-4 sm:grid-cols-[14rem_1fr]">
        <div>
            <label for="lceType" class="mb-1 block text-xs font-semibold">
                LCE Type
            </label>
            <select
                id="lceType"
                name="lce_type"
                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
            >
                <option value="">Select LCE type</option>
                <option value="mayor" @selected(old('lce_type') === 'mayor')>Mayor (City / Municipality)</option>
                <option value="governor" @selected(old('lce_type') === 'governor')>Governor (Province)</option>
                <option value="mayor_governor" @selected(old('lce_type') === 'mayor_governor')>Mayor and Governor</option>
                <option value="other" @selected(old('lce_type') === 'other')>Party-list / Other</option>
            </select>
        </div>

        <div>
            <label for="local_chief_executive_partylist" class="mb-1 block text-xs font-semibold">
                Local Chief Executive / Party-list
            </label>
            <input
                id="local_chief_executive_partylist"
                name="local_chief_executive_partylist"
                value="{{ old('local_chief_executive_partylist') }}"
                maxlength="255"
                list="lceSuggestions"
                autocomplete="off"
                placeholder="Type a Mayor / Governor name, or choose the LCE type"
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >
            {{-- Type-to-search suggestions: every encoded Mayor and Governor (filled by the script below). --}}
            <datalist id="lceSuggestions"></datalist>
            <p id="lceHint" class="mt-1 text-[11px] leading-4 text-slate-500" aria-live="polite">
                Type a name to search, or choose Mayor or Governor to fill it from
                @if (auth()->user()?->isFocal())
                    <a href="{{ route('locations.local-chief-executives.edit') }}" class="font-semibold text-[#063b86] hover:underline">Mayors &amp; Governors</a>.
                @else
                    Mayors &amp; Governors (maintained by Focal).
                @endif
            </p>
        </div>
    </div>

    <div class="md:col-span-2 border-t border-slate-100 pt-2">
        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">
            Allocation Scope
        </div>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Province is required. District and City / Municipality are optional and should only be filled when the allocation is restricted to that area.
        </p>
    </div>

    <div>
        <label for="allocationProvince" class="mb-1 block text-xs font-semibold">
            Province <span class="text-red-600">*</span>
        </label>
        <select
            id="allocationProvince"
            name="province"
            required
            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
        >
            <option value="">Select province</option>
            @foreach ($provinces as $province)
                <option value="{{ $province->name }}" @selected(old('province') === $province->name)>
                    {{ $province->name }}
                </option>
            @endforeach
        </select>
        @error('province')
            <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="allocationDistrict" class="mb-1 block text-xs font-semibold">
            District <span class="font-normal text-slate-400">(optional)</span>
        </label>
        <input
            id="allocationDistrict"
            name="district"
            value="{{ old('district') }}"
            maxlength="100"
            autocomplete="off"
            placeholder="Leave blank for province-wide allocation"
            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
        >
    </div>

    <div>
        <label for="allocationMunicipality" class="mb-1 block text-xs font-semibold">
            City / Municipality <span class="font-normal text-slate-400">(optional)</span>
        </label>
        <select
            id="allocationMunicipality"
            name="municipality"
            class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
        >
            <option value="">Not municipality-specific</option>
            @foreach ($municipalities as $municipality)
                @php
                    $municipalityProvince = $provinces->firstWhere('id', $municipality->province_id)?->name;
                @endphp
                <option
                    value="{{ $municipality->name }}"
                    data-province="{{ $municipalityProvince }}"
                    @selected(old('municipality') === $municipality->name && old('province') === $municipalityProvince)
                >
                    {{ $municipality->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-xs font-semibold">
            Allocation Scope
        </label>
        <div
            id="allocationScopeDisplay"
            class="flex min-h-10 w-full items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
            aria-live="polite"
        >
            Province required
        </div>
        <p class="mt-1 text-[11px] leading-4 text-slate-500">
            Generated automatically. No separate Location Display entry is required.
        </p>
    </div>

    <div>
        <label for="grant_amount" class="mb-1 block text-xs font-semibold">
            Grant Allocation <span class="text-red-600">*</span>
        </label>
        <input
            id="grant_amount"
            type="number"
            step="0.01"
            min="0.01"
            name="grant_amount"
            data-money-input
            value="{{ old('grant_amount') }}"
            required
            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
        >
    </div>

    <div>
        <label for="admin_cost_amount" class="mb-1 block text-xs font-semibold">
            Admin Cost Allocation
        </label>
        <input
            id="admin_cost_amount"
            type="number"
            step="0.01"
            min="0"
            name="admin_cost_amount"
            data-money-input
            value="{{ old('admin_cost_amount', 0) }}"
            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
        >
    </div>
    <div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold">Remarks</label><textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></div><div class="md:col-span-2 text-right"><button class="h-10 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white">Save Allocation</button></div>
</form></section>
<section class="rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">
            Add Realignment / MAF
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Choose whether funds are being transferred out of TUPAD or added back to TUPAD.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('adl.realignments.store', $adl) }}"
        class="grid gap-4 p-5 md:grid-cols-2"
        id="adlRealignmentForm"
    >
        @csrf

        <div class="md:col-span-2">
            <label class="mb-2 block text-xs font-semibold text-slate-700">
                Realignment Type
                <span class="text-red-600">*</span>
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="cursor-pointer rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:bg-blue-50/40">
                    <div class="flex items-start gap-3">
                        <input
                            type="radio"
                            name="direction"
                            value="tupad_to_gip"
                            class="mt-1 h-4 w-4 border-slate-300 text-[#063b86] focus:ring-[#063b86]"
                            required
                            @checked(old('direction') === 'tupad_to_gip')
                        >

                        <div>
                            <div class="text-sm font-semibold text-slate-900">
                                TUPAD to GIP
                            </div>

                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                Deduct the realignment amount from the current TUPAD ADL fund.
                            </p>
                        </div>
                    </div>
                </label>

                <label class="cursor-pointer rounded-lg border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:bg-blue-50/40">
                    <div class="flex items-start gap-3">
                        <input
                            type="radio"
                            name="direction"
                            value="gip_to_tupad"
                            class="mt-1 h-4 w-4 border-slate-300 text-[#063b86] focus:ring-[#063b86]"
                            required
                            @checked(old('direction') === 'gip_to_tupad')
                        >

                        <div>
                            <div class="text-sm font-semibold text-slate-900">
                                GIP to TUPAD
                            </div>

                            <p class="mt-1 text-xs leading-5 text-slate-500">
                                Add the realignment amount to the current TUPAD ADL fund.
                            </p>
                        </div>
                    </div>
                </label>
            </div>

            @error('direction')
                <p class="mt-2 text-xs font-medium text-red-600">
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label
                for="realignmentAmount"
                class="mb-1 block text-xs font-semibold"
            >
                Realignment Amount
                <span class="text-red-600">*</span>
            </label>

            <input
                id="realignmentAmount"
                type="number"
                step="0.01"
                min="0.01"
                name="amount"
                data-money-input
                value="{{ old('amount') }}"
                required
                placeholder="0.00"
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >

            @error('amount')
                <p class="mt-1 text-xs font-medium text-red-600">
                    {{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label
                for="realignmentDate"
                class="mb-1 block text-xs font-semibold"
            >
                Realignment Date
                <span class="text-red-600">*</span>
            </label>

            <input
                id="realignmentDate"
                type="date"
                name="realignment_date"
                value="{{ old('realignment_date') }}"
                required
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >
        </div>

        <div class="md:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Current TUPAD ADL Amount
                    </div>

                    <div class="mt-1 text-base font-bold text-slate-900">
                        ₱{{ number_format($adl->adjusted_grants, 2) }}
                    </div>
                </div>

                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Effect
                    </div>

                    <div
                        id="realignmentEffectPreview"
                        class="mt-1 text-sm font-semibold text-slate-700"
                    >
                        Select a realignment type.
                    </div>
                </div>

                <div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        Adjusted TUPAD Amount
                    </div>

                    <div
                        id="adjustedTupadPreview"
                        class="mt-1 text-base font-bold text-[#063b86]"
                    >
                        ₱{{ number_format($adl->adjusted_grants, 2) }}
                    </div>
                </div>
            </div>

            <p class="mt-3 text-[11px] leading-5 text-slate-500">
                Original ADL Grants remain part of the audit record. Realignments change the effective Adjusted TUPAD Amount used for fund availability.
            </p>
        </div>

        <div>
            <label
                for="mafDate"
                class="mb-1 block text-xs font-semibold"
            >
                MAF Date
            </label>

            <input
                id="mafDate"
                type="date"
                name="maf_date"
                value="{{ old('maf_date') }}"
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >
        </div>

        <div>
            <label
                for="mafNumber"
                class="mb-1 block text-xs font-semibold"
            >
                MAF Number
            </label>

            <input
                id="mafNumber"
                name="maf_number"
                value="{{ old('maf_number') }}"
                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
            >
        </div>

        <input
            type="hidden"
            name="reference_number"
            value="{{ old('reference_number') }}"
        >

        <div class="md:col-span-2">
            <label
                for="realignmentReason"
                class="mb-1 block text-xs font-semibold"
            >
                Reason / Remarks
            </label>

            <textarea
                id="realignmentReason"
                name="reason"
                rows="2"
                class="w-full rounded-lg border border-slate-300 p-3 text-sm"
            >{{ old('reason') }}</textarea>
        </div>

        <div class="md:col-span-2 text-right">
            <button
                type="submit"
                class="h-10 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
            >
                Save Realignment
            </button>
        </div>
    </form>
</section>
</div>


<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">
            Realignment History
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            TUPAD to GIP entries reduce the effective TUPAD fund. GIP to TUPAD entries increase it.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="tupad-system-table min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="whitespace-nowrap p-3 text-left">
                        Date
                    </th>

                    <th class="whitespace-nowrap p-3 text-left">
                        Type
                    </th>

                    <th class="whitespace-nowrap p-3 text-right">
                        Amount
                    </th>

                    <th class="whitespace-nowrap p-3 text-left">
                        MAF
                    </th>

                    <th class="whitespace-nowrap p-3 text-left">
                        Remarks
                    </th>

                    <th class="whitespace-nowrap p-3 text-left">
                        Recorded By
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse(
                    $adl->realignments
                        ->sortByDesc('realignment_date')
                    as $realignment
                )
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap p-3 text-slate-700">
                            {{ $realignment->realignment_date?->format('M d, Y') ?: '—' }}
                        </td>

                        <td class="whitespace-nowrap p-3">
                            @if($realignment->direction === 'tupad_to_gip')
                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                    TUPAD to GIP
                                </span>
                            @else
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    GIP to TUPAD
                                </span>
                            @endif
                        </td>

                        <td
                            class="whitespace-nowrap p-3 text-right font-semibold {{ (float) $realignment->amount < 0 ? 'text-red-700' : 'text-emerald-700' }}"
                        >
                            {{ (float) $realignment->amount < 0 ? '−' : '+' }}
                            ₱{{ number_format(abs((float) $realignment->amount), 2) }}
                        </td>

                        <td class="whitespace-nowrap p-3 text-slate-700">
                            {{ $realignment->maf_number ?: $realignment->reference_number ?: '—' }}
                        </td>

                        <td class="max-w-xs p-3 text-slate-600">
                            {{ $realignment->reason ?: '—' }}
                        </td>

                        <td class="whitespace-nowrap p-3 text-slate-600">
                            {{ $realignment->creator?->name ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td
                            colspan="6"
                            class="p-8 text-center text-sm text-slate-400"
                        >
                            No realignment records yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section id="allocation-records" class="scroll-mt-28 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold">Allocation Records</h2>
        <p class="mt-1 text-xs text-slate-500">
            Sponsor and Partner are maintained by Focal at allocation level and are available to TC as reusable Project Create choices.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="tupad-system-table min-w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="p-3 text-left">Fund Sponsor</th>
                    <th class="p-3 text-left">Partner</th>
                    <th class="p-3 text-left">Location</th>
                    <th class="p-3 text-right">Grants</th>
                    <th class="p-3 text-right">Admin</th>
                    <th class="p-3 text-right">Total</th>
                    <th class="p-3 text-right"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody class="divide-y">
                @foreach($adl->allocations as $a)
                    @php
                        $allocationSponsors =
                            filled($a->fund_sponsor)
                                ? $a->fund_sponsor
                                : $a->projects
                                    ->pluck('fund_sponsor')
                                    ->filter()
                                    ->unique()
                                    ->implode(', ');

                        $allocationPartners =
                            filled($a->partner)
                                ? $a->partner
                                : $a->projects
                                    ->pluck('partner')
                                    ->filter()
                                    ->unique()
                                    ->implode(', ');
                    @endphp

                    <tr>
                        <td class="p-3">{{ $allocationSponsors ?: '—' }}</td>
                        <td class="p-3">{{ $allocationPartners ?: '—' }}</td>
                        <td class="p-3">{{ collect([$a->province,$a->district,$a->municipality])->filter()->implode(' / ') ?: $a->location }}</td>
                        <td class="p-3 text-right">₱{{ number_format($a->grant_amount ?? $a->amount,2) }}</td>
                        <td class="p-3 text-right">₱{{ number_format($a->admin_cost_amount ?? 0,2) }}</td>
                        <td class="p-3 text-right font-semibold">₱{{ number_format($a->total_amount ?? $a->amount,2) }}</td>
                        <td class="p-3 text-right">
                            @php
                                $editLimits = \App\Http\Controllers\AdlAllocationController::limits($adl, $a);
                                $editPayload = [
                                    'id' => $a->id,
                                    'url' => route('adl.allocations.update', [$adl, $a]),
                                    'label' => $a->location ?: $a->province,
                                    'locked' => $a->projects->isNotEmpty(),
                                    'min' => $editLimits['min'],
                                    'max' => $editLimits['max'],
                                    'values' => [
                                        'fund_sponsor' => $a->fund_sponsor,
                                        'partner' => $a->partner,
                                        'local_chief_executive_partylist' => $a->local_chief_executive_partylist,
                                        'province' => $a->province,
                                        'district' => $a->district,
                                        'municipality' => $a->municipality,
                                        'grant_amount' => number_format((float) ($a->grant_amount ?? $a->amount), 2, '.', ''),
                                        'admin_cost_amount' => number_format((float) ($a->admin_cost_amount ?? 0), 2, '.', ''),
                                        'remarks' => $a->remarks,
                                    ],
                                ];
                            @endphp
                            <button type="button" data-allocation-edit="{{ json_encode($editPayload) }}"
                                class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:border-[#1765d8]/50 hover:text-[#063b86] focus:outline-none focus:ring-2 focus:ring-[#1765d8]/25"
                                aria-label="Edit allocation {{ $a->location ?: $a->province }}">Edit</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

{{-- Edit Allocation modal: one shared form, filled from the row's Edit button (script below). --}}
@php
    $editErrors = $errors->getBag('allocationEdit');
    $editInput = 'h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/20 disabled:bg-slate-50 disabled:text-slate-500';
    $editLabel = 'mb-1 block text-xs font-semibold text-slate-700';
    // After a failed save, reopen the modal for the same allocation with what was typed.
    $reopenAllocation = $editErrors->any() ? (int) old('allocation_id') : null;
@endphp
<dialog id="allocationEditDialog" aria-labelledby="allocationEditTitle"
    class="tupad-dialog w-[min(44rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl"
    @if ($reopenAllocation) data-reopen="{{ $reopenAllocation }}" data-reopen-values="{{ json_encode(old('edit', [])) }}" @endif>
    <form method="POST" action="" data-allocation-edit-form class="flex max-h-[90vh] flex-col">
        @csrf
        @method('PUT')
        <input type="hidden" name="allocation_id" value="">

        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 pb-4 pt-5">
            <div class="min-w-0">
                <h2 id="allocationEditTitle" class="text-lg font-bold text-[#0f2347]" tabindex="-1">Edit Allocation</h2>
                <p class="text-xs text-slate-500" data-edit-subtitle></p>
            </div>
            <button type="button" data-edit-close class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="space-y-5 overflow-y-auto px-6 py-5">
            @if ($editErrors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700" role="alert">
                    @foreach ($editErrors->all() as $message)<p>{{ $message }}</p>@endforeach
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="edit_fund_sponsor" class="{{ $editLabel }}">Fund Sponsor</label>
                    <input id="edit_fund_sponsor" name="edit[fund_sponsor]" data-edit-field="fund_sponsor" list="editSponsorOptions" maxlength="255" autocomplete="off" class="{{ $editInput }}">
                    <datalist id="editSponsorOptions">@foreach ($fundSponsorOptions as $option)<option value="{{ $option }}">@endforeach</datalist>
                </div>
                <div>
                    <label for="edit_partner" class="{{ $editLabel }}">Partner</label>
                    <input id="edit_partner" name="edit[partner]" data-edit-field="partner" list="editPartnerOptions" maxlength="255" autocomplete="off" class="{{ $editInput }}">
                    <datalist id="editPartnerOptions">@foreach ($partnerOptions as $option)<option value="{{ $option }}">@endforeach</datalist>
                </div>
                <div class="md:col-span-2">
                    <label for="edit_lce" class="{{ $editLabel }}">Local Chief Executive / Party-list</label>
                    <input id="edit_lce" name="edit[local_chief_executive_partylist]" data-edit-field="local_chief_executive_partylist" maxlength="255" class="{{ $editInput }}">
                </div>
            </div>

            <fieldset class="border-t border-slate-100 pt-4">
                <legend class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Allocation Scope</legend>
                <p class="mt-2 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900" data-edit-locked-note>
                    Projects already use this allocation, so its province, district and municipality can no longer be changed.
                </p>
                <div class="mt-3 grid gap-4 md:grid-cols-3">
                    <div>
                        <label for="edit_province" class="{{ $editLabel }}">Province <span class="text-red-600">*</span></label>
                        <select id="edit_province" name="edit[province]" data-edit-field="province" data-edit-scope class="{{ $editInput }}">
                            <option value="">Select province</option>
                            @foreach ($provinces as $province)
                                <option value="{{ $province->name }}">{{ $province->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="edit_district" class="{{ $editLabel }}">District <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="edit_district" name="edit[district]" data-edit-field="district" data-edit-scope maxlength="100" placeholder="Province-wide" class="{{ $editInput }}">
                    </div>
                    <div>
                        <label for="edit_municipality" class="{{ $editLabel }}">City / Municipality <span class="font-normal text-slate-400">(optional)</span></label>
                        <select id="edit_municipality" name="edit[municipality]" data-edit-field="municipality" data-edit-scope class="{{ $editInput }}">
                            <option value="">Not municipality-specific</option>
                            @foreach ($municipalities as $municipality)
                                <option value="{{ $municipality->name }}" data-province="{{ $provinces->firstWhere('id', $municipality->province_id)?->name }}">{{ $municipality->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset class="border-t border-slate-100 pt-4">
                <legend class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Amounts</legend>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="edit_grant_amount" class="{{ $editLabel }}">Grant Allocation <span class="text-red-600">*</span></label>
                        <input id="edit_grant_amount" name="edit[grant_amount]" data-edit-field="grant_amount" type="number" step="0.01" min="0.01" required
                            aria-describedby="edit_grant_hint" class="{{ $editInput }}">
                        <p id="edit_grant_hint" class="mt-1 text-[11px] leading-4 text-slate-500" data-edit-grant-hint></p>
                    </div>
                    <div>
                        <label for="edit_admin_cost_amount" class="{{ $editLabel }}">Admin Cost Allocation</label>
                        <input id="edit_admin_cost_amount" name="edit[admin_cost_amount]" data-edit-field="admin_cost_amount" type="number" step="0.01" min="0" class="{{ $editInput }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="edit_remarks" class="{{ $editLabel }}">Remarks</label>
                        <textarea id="edit_remarks" name="edit[remarks]" data-edit-field="remarks" rows="2" maxlength="2000" class="w-full rounded-lg border border-slate-300 p-3 text-sm outline-none focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/20"></textarea>
                    </div>
                </div>
            </fieldset>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-3.5">
            <button type="button" data-edit-close class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d] focus:outline-none focus:ring-2 focus:ring-blue-300">Save Allocation</button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
    (function () {
        const dialog = document.getElementById('allocationEditDialog');
        if (!dialog || typeof dialog.showModal !== 'function') return;

        const form = dialog.querySelector('[data-allocation-edit-form]');
        const province = form.querySelector('#edit_province');
        const municipality = form.querySelector('#edit_municipality');
        const peso = (value) => '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        let opener = null;
        let dirty = false;

        const syncMunicipalities = () => {
            Array.from(municipality.options).forEach((option) => {
                if (!option.value) return;
                const outside = option.dataset.province !== province.value;
                option.hidden = outside;
                option.disabled = outside;
            });
            if (municipality.selectedOptions[0]?.disabled) municipality.value = '';
        };
        province.addEventListener('change', syncMunicipalities);

        const open = (data, values, trigger) => {
            opener = trigger;
            form.action = data.url;
            form.elements['allocation_id'].value = data.id;
            dialog.querySelector('[data-edit-subtitle]').textContent = data.label || '';
            form.querySelectorAll('[data-edit-field]').forEach((field) => {
                const value = values[field.dataset.editField];
                field.value = value === null || value === undefined ? '' : value;
            });
            syncMunicipalities();
            municipality.value = values.municipality || '';

            // Scope is locked once projects use the allocation (disabled fields are not sent).
            form.querySelectorAll('[data-edit-scope]').forEach((field) => { field.disabled = data.locked; });
            province.required = !data.locked;
            dialog.querySelector('[data-edit-locked-note]').classList.toggle('hidden', !data.locked);

            const grant = form.elements['edit[grant_amount]'];
            grant.max = data.max;
            dialog.querySelector('[data-edit-grant-hint]').textContent =
                `Allowed: ${peso(data.min)} (used by its projects) to ${peso(data.max)} (what the ADL can still give).`;

            dirty = false;
            dialog.showModal();
            dialog.querySelector('#allocationEditTitle').focus();
        };

        const close = async () => {
            if (dirty && window.TupadConfirm) {
                const discard = await window.TupadConfirm({
                    title: 'Close without saving?',
                    message: 'Your changes to this allocation have not been saved.',
                    confirmText: 'Close',
                    cancelText: 'Keep editing',
                });
                if (!discard) return;
            }
            dialog.close();
        };

        form.addEventListener('input', () => { dirty = true; });
        form.addEventListener('submit', () => { dirty = false; });
        dialog.addEventListener('cancel', (event) => { event.preventDefault(); close(); });
        dialog.addEventListener('close', () => opener?.focus());
        dialog.querySelectorAll('[data-edit-close]').forEach((button) => button.addEventListener('click', close));

        const buttons = Array.from(document.querySelectorAll('[data-allocation-edit]'));
        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const data = JSON.parse(button.dataset.allocationEdit);
                open(data, data.values, button);
            });
        });

        // Reopen after a failed save, keeping what was typed.
        if (dialog.dataset.reopen) {
            const button = buttons.find((item) => String(JSON.parse(item.dataset.allocationEdit).id) === dialog.dataset.reopen);
            if (button) {
                const data = JSON.parse(button.dataset.allocationEdit);
                open(data, { ...data.values, ...JSON.parse(dialog.dataset.reopenValues || '{}') }, button);
            }
        }
    })();
</script>
@endpush
@endsection


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const allocationProvince =
            document.getElementById('allocationProvince');

        const allocationDistrict =
            document.getElementById('allocationDistrict');

        const allocationMunicipality =
            document.getElementById('allocationMunicipality');

        const allocationScopeDisplay =
            document.getElementById('allocationScopeDisplay');

        function updateAllocationScope() {
            if (!allocationScopeDisplay) {
                return;
            }

            const province =
                allocationProvince?.value.trim() ?? '';

            const district =
                allocationDistrict?.value.trim() ?? '';

            const municipality =
                allocationMunicipality?.value.trim() ?? '';

            const parts = [
                municipality,
                district,
                province,
            ].filter(Boolean);

            allocationScopeDisplay.textContent =
                parts.length > 0
                    ? parts.join(', ')
                    : 'Province required';
        }

        [
            allocationProvince,
            allocationDistrict,
            allocationMunicipality,
        ].forEach(function (input) {
            input?.addEventListener(
                'input',
                updateAllocationScope
            );

            input?.addEventListener(
                'change',
                updateAllocationScope
            );
        });

        updateAllocationScope();

        /*
        |--------------------------------------------------------------------------
        | Fund Sponsor / Partner Dropdowns
        |--------------------------------------------------------------------------
        |
        | The dropdown offers values already encoded on ADL allocations; "Other"
        | reveals a text box for a new one. The text box is the submitted field.
        |--------------------------------------------------------------------------
        */

        document.querySelectorAll('[data-reference-field]').forEach(function (field) {
            const select = field.querySelector('[data-reference-select]');
            const value = field.querySelector('[data-reference-value]');

            function sync(focusOther) {
                const isOther = select.value === '__other__';

                value.classList.toggle('hidden', !isOther);

                if (isOther) {
                    if (focusOther) {
                        value.value = '';
                        value.focus();
                    }
                } else {
                    value.value = select.value;
                }
            }

            select.addEventListener('change', function () {
                sync(true);
            });

            sync(false);
        });

        /*
        |--------------------------------------------------------------------------
        | Municipality Choices + Local Chief Executive
        |--------------------------------------------------------------------------
        |
        | City / Municipality lists only the selected province's LGUs. Choosing
        | Mayor and/or Governor as the LCE type fills the LCE from the names the
        | Focal maintains in Mayors & Governors; the box stays editable.
        |--------------------------------------------------------------------------
        */

        @php
            $lceGovernors = $provinces->mapWithKeys(fn ($province) => [$province->name => $province->governor]);
            $provinceNames = $provinces->pluck('name', 'id');
            $lceMayors = $municipalities->map(fn ($municipality) => [
                'province' => $provinceNames[$municipality->province_id] ?? null,
                'name' => $municipality->name,
                'mayor' => $municipality->mayor,
            ])->values();
            $lceEditorUrl = auth()->user()?->isFocal() ? route('locations.local-chief-executives.edit') : null;
        @endphp
        const governors = @json($lceGovernors);
        const mayors = @json($lceMayors);
        const lceEditorUrl = @json($lceEditorUrl);

        const lceType = document.getElementById('lceType');
        const lceInput = document.getElementById('local_chief_executive_partylist');
        const lceHint = document.getElementById('lceHint');
        const lceHintDefault = lceHint?.innerHTML ?? '';

        function filterMunicipalities() {
            if (!allocationMunicipality) {
                return;
            }

            const province = allocationProvince?.value ?? '';

            allocationMunicipality.querySelectorAll('option[data-province]').forEach(function (option) {
                const visible = province !== '' && option.dataset.province === province;
                option.hidden = !visible;
                option.disabled = !visible;
            });

            const selected = allocationMunicipality.selectedOptions[0];

            if (selected?.disabled) {
                allocationMunicipality.value = '';
            }
        }

        function setLceHint(message, isWarning) {
            if (!lceHint) {
                return;
            }

            lceHint.className = isWarning
                ? 'mt-1 text-[11px] leading-4 font-medium text-amber-700'
                : 'mt-1 text-[11px] leading-4 text-slate-500';
            lceHint.innerHTML = message;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function fillLce() {
            if (!lceType || !lceInput) {
                return;
            }

            const type = lceType.value;
            const province = allocationProvince?.value ?? '';
            const municipality = allocationMunicipality?.value ?? '';

            if (type === '' || type === 'other') {
                lceInput.readOnly = false;
                setLceHint(type === 'other' ? 'Type the party-list or other LCE name.' : lceHintDefault, false);
                return;
            }

            const parts = [];
            const missing = [];
            const editorLink = lceEditorUrl
                ? `<a href="${lceEditorUrl}" class="font-semibold text-[#063b86] hover:underline">Mayors &amp; Governors</a>`
                : 'Mayors &amp; Governors (Focal)';

            if (type === 'mayor' || type === 'mayor_governor') {
                if (municipality === '') {
                    missing.push('Select a City / Municipality to fill the Mayor.');
                } else {
                    const entry = mayors.find((row) => row.province === province && row.name === municipality);

                    if (entry?.mayor) {
                        parts.push(`Mayor ${entry.mayor}`);
                    } else {
                        missing.push(`No Mayor is encoded for ${escapeHtml(municipality)} yet — add it in ${editorLink} or type it here.`);
                    }
                }
            }

            if (type === 'governor' || type === 'mayor_governor') {
                if (province === '') {
                    missing.push('Select a Province to fill the Governor.');
                } else if (governors[province]) {
                    parts.push(`Governor ${governors[province]}`);
                } else {
                    missing.push(`No Governor is encoded for ${escapeHtml(province)} yet — add it in ${editorLink} or type it here.`);
                }
            }

            lceInput.value = parts.join(' / ');

            if (missing.length > 0) {
                setLceHint(missing.join(' '), true);
            } else {
                setLceHint('Filled from Mayors &amp; Governors. You can still edit it.', false);
            }
        }

        allocationProvince?.addEventListener('change', function () {
            filterMunicipalities();
            updateAllocationScope();
            fillLce();
        });

        allocationMunicipality?.addEventListener('change', fillLce);
        lceType?.addEventListener('change', fillLce);

        /*
        | Type-to-search: suggest every encoded Mayor and Governor (the selected
        | province's first). Picking one sets the LCE type, and the Province
        | when it is still empty; the City / Municipality scope is left alone.
        */
        const lceSuggestions = document.getElementById('lceSuggestions');
        const lceChoices = [
            ...Object.entries(governors)
                .filter(([, governor]) => governor)
                .map(([province, governor]) => ({ value: `Governor ${governor}`, label: `Governor of ${province}`, type: 'governor', province })),
            ...mayors
                .filter((row) => row.mayor)
                .map((row) => ({ value: `Mayor ${row.mayor}`, label: `Mayor of ${row.name}, ${row.province}`, type: 'mayor', province: row.province })),
        ];

        function renderLceSuggestions() {
            if (!lceSuggestions) {
                return;
            }

            const province = allocationProvince?.value ?? '';
            const ordered = province === ''
                ? lceChoices
                : [...lceChoices.filter((choice) => choice.province === province), ...lceChoices.filter((choice) => choice.province !== province)];

            lceSuggestions.replaceChildren(...ordered.map((choice) => {
                const option = document.createElement('option');
                option.value = choice.value;
                option.label = choice.label;
                return option;
            }));
        }

        lceInput?.addEventListener('input', function () {
            const choice = lceChoices.find((item) => item.value === lceInput.value);

            if (!choice) {
                return;
            }

            if (lceType && lceType.value !== 'mayor_governor') {
                lceType.value = choice.type;
            }

            if (allocationProvince && allocationProvince.value === '' && choice.province) {
                allocationProvince.value = choice.province;
                filterMunicipalities();
                updateAllocationScope();
                renderLceSuggestions();
            }

            setLceHint(`${escapeHtml(choice.label)}. You can still edit it.`, false);
        });

        allocationProvince?.addEventListener('change', renderLceSuggestions);
        renderLceSuggestions();
        filterMunicipalities();

        // Keep a value restored after a validation error; only fill when empty.
        if (lceInput && lceInput.value.trim() === '') {
            fillLce();
        }

        const amountInput =
            document.getElementById('realignmentAmount');

        const directionInputs =
            document.querySelectorAll(
                'input[name="direction"]'
            );

        const effectPreview =
            document.getElementById(
                'realignmentEffectPreview'
            );

        const adjustedPreview =
            document.getElementById(
                'adjustedTupadPreview'
            );

        const currentTupadAmount =
            Number(
                @json(
                    (float) $adl->adjusted_grants
                )
            );

        const peso = new Intl.NumberFormat(
            'en-PH',
            {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }
        );

        function selectedDirection() {
            return document.querySelector(
                'input[name="direction"]:checked'
            )?.value ?? null;
        }

        function updateRealignmentPreview() {
            if (
                !amountInput
                || !effectPreview
                || !adjustedPreview
            ) {
                return;
            }

            const direction =
                selectedDirection();

            const amount =
                Math.max(
                    0,
                    Number(
                        window.TupadMoney.unformat(amountInput.value) || 0
                    )
                );

            let adjusted =
                currentTupadAmount;

            if (
                direction === 'tupad_to_gip'
            ) {
                adjusted =
                    currentTupadAmount
                    - amount;

                effectPreview.textContent =
                    amount > 0
                        ? 'TUPAD fund decreases by '
                            + peso.format(amount)
                        : 'TUPAD fund will decrease.';
            } else if (
                direction === 'gip_to_tupad'
            ) {
                adjusted =
                    currentTupadAmount
                    + amount;

                effectPreview.textContent =
                    amount > 0
                        ? 'TUPAD fund increases by '
                            + peso.format(amount)
                        : 'TUPAD fund will increase.';
            } else {
                effectPreview.textContent =
                    'Select a realignment type.';
            }

            adjustedPreview.textContent =
                peso.format(adjusted);
        }

        amountInput?.addEventListener(
            'input',
            updateRealignmentPreview
        );

        directionInputs.forEach(
            function (input) {
                input.addEventListener(
                    'change',
                    updateRealignmentPreview
                );
            }
        );

        updateRealignmentPreview();
    });
</script>
@endpush
