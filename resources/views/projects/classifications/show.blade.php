@extends('layouts.app')

@section('title', 'Beneficiary Classification — '.$project->project_title)

@section('content')
<x-page-header
    eyebrow="Project Reporting Data"
    title="Beneficiary Classification & Labor Market"
    description="Encode aggregate sector classifications, the project's primary intervention focus, and monthly Active Labor Market referrals."
>
    <x-slot:actions>
        <a
            href="{{ route('projects.show', $project) }}"
            class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            ← Project Detail
        </a>
    </x-slot:actions>
</x-page-header>

<x-page-alerts />

<section class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Project' => $project->project_title,
            'Project Code' => $project->approval?->project_code ?: 'Not yet assigned',
            'ADL Number' => $project->allocation->adl->adl_number,
            'Declared Beneficiaries' => number_format($project->beneficiaries_total).' total / '.number_format($project->beneficiaries_female).' female',
        ] as $label => $value)
            <div class="bg-white px-5 py-4">
                <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                    {{ $label }}
                </div>
                <div class="mt-1 text-sm font-semibold leading-5 text-slate-900">
                    {{ $value }}
                </div>
            </div>
        @endforeach
    </div>
</section>

<div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 p-4">
    <div class="text-sm font-semibold text-blue-950">Aggregate reporting data only</div>
    <p class="mt-1 text-xs leading-5 text-blue-800">
        These classifications do not create individual beneficiary records and do not replace the exact beneficiary allocation stored for each project barangay. Sector categories may overlap, so their totals are not forced to equal the project's declared beneficiary count.
    </p>
</div>

<form
    method="POST"
    action="{{ route('projects.classifications.update', $project) }}"
    class="space-y-5"
>
    @csrf
    @method('PUT')

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Primary Intervention Focus</h2>
            <p class="mt-1 text-xs text-slate-500">
                Select the single primary intervention classification that best represents this project.
            </p>
        </div>

        <div class="p-5">
            <label for="intervention_focus" class="mb-2 block text-xs font-semibold text-slate-700">
                Intervention Focus
            </label>
            <select
                id="intervention_focus"
                name="intervention_focus"
                required
                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm lg:max-w-2xl"
            >
                <option value="">Select primary intervention focus</option>
                @foreach($interventionFocuses as $focus)
                    <option
                        value="{{ $focus->value }}"
                        @selected(old('intervention_focus', $project->intervention_focus?->value) === $focus->value)
                    >
                        {{ $focus->label() }}
                    </option>
                @endforeach
            </select>
            @error('intervention_focus')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    @foreach([
        ['title' => 'Priority / Vulnerable Sectors', 'categories' => $priorityCategories],
        ['title' => 'Occupational / Livelihood Sectors', 'categories' => $occupationalCategories],
    ] as $group)
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ $group['title'] }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Enter zero when the category does not apply. Female count must not exceed the category total.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Sector Category</th>
                            <th class="w-52 px-5 py-3 text-left text-xs font-semibold text-slate-500">Total Beneficiaries</th>
                            <th class="w-52 px-5 py-3 text-left text-xs font-semibold text-slate-500">Female Beneficiaries</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($group['categories'] as $category)
                            @php
                                $sectorRecord = $sectorRecords->get($category->value);
                                $totalField = "sectors.{$category->value}.total";
                                $femaleField = "sectors.{$category->value}.female";
                            @endphp
                            <tr>
                                <td class="px-5 py-3 text-sm font-semibold text-slate-800">
                                    {{ $category->label() }}
                                </td>
                                <td class="px-5 py-3">
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        required
                                        name="sectors[{{ $category->value }}][total]"
                                        value="{{ old($totalField, $sectorRecord?->beneficiaries_total ?? 0) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >
                                    @error($totalField)
                                        <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td class="px-5 py-3">
                                    <input
                                        type="number"
                                        min="0"
                                        step="1"
                                        required
                                        name="sectors[{{ $category->value }}][female]"
                                        value="{{ old($femaleField, $sectorRecord?->beneficiaries_female ?? 0) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >
                                    @error($femaleField)
                                        <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <div class="flex justify-end">
        <button
            type="submit"
            class="inline-flex h-11 items-center rounded-lg bg-[#063b86] px-6 text-sm font-semibold text-white hover:bg-[#052f6b]"
        >
            Save Classification Data
        </button>
    </div>
</form>

<section class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Active Labor Market Program Referrals</h2>
        <p class="mt-1 text-xs text-slate-500">
            Save one aggregate record per month and program. Submitting the same month and program again updates that record with a complete audit trail.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('projects.labor-market-referrals.store', $project) }}"
        class="border-b border-slate-200 bg-slate-50 p-5"
    >
        @csrf

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label class="mb-2 block text-xs font-semibold text-slate-700">Month</label>
                <input
                    type="month"
                    name="reporting_month"
                    required
                    value="{{ old('reporting_month', now()->format('Y-m')) }}"
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                >
                @error('reporting_month')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-1 xl:col-span-3">
                <label class="mb-2 block text-xs font-semibold text-slate-700">Program</label>
                <select
                    name="program"
                    required
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                >
                    <option value="">Select program</option>
                    @foreach($laborMarketPrograms as $program)
                        <option value="{{ $program->value }}" @selected(old('program') === $program->value)>
                            {{ $program->label() }}
                        </option>
                    @endforeach
                </select>
                @error('program')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            @foreach([
                ['name' => 'interested_referred_total', 'label' => 'Interested Beneficiaries Referred'],
                ['name' => 'interested_referred_female', 'label' => 'Females Referred'],
                ['name' => 'provided_intervention_total', 'label' => 'Provided with Intervention'],
                ['name' => 'provided_intervention_female', 'label' => 'Females Provided with Intervention'],
            ] as $field)
                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">{{ $field['label'] }}</label>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        name="{{ $field['name'] }}"
                        required
                        value="{{ old($field['name'], 0) }}"
                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                    >
                    @error($field['name'])
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div>
                <label class="mb-2 block text-xs font-semibold text-slate-700">Amount Released (Php)</label>
                <input
                    type="number"
                    min="0"
                    step="0.01"
                    name="amount_released"
                    required
                    value="{{ old('amount_released', '0.00') }}"
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                >
                @error('amount_released')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-xs font-semibold text-slate-700">
                    Skills Training / Livelihood Assistance / Employment Services Availed
                </label>
                <textarea
                    name="services_availed"
                    rows="3"
                    maxlength="5000"
                    required
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                    placeholder="Describe the assistance or services availed..."
                >{{ old('services_availed') }}</textarea>
                @error('services_availed')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button
                type="submit"
                class="inline-flex h-10 items-center rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800"
            >
                Save Monthly Referral
            </button>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1280px]">
            <thead class="bg-white">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Month / Program</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Referred</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Female Referred</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Provided</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Female Provided</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Amount Released</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Services Availed</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Audit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($project->laborMarketReferrals as $referral)
                    <tr class="align-top">
                        <td class="px-5 py-4">
                            <div class="text-sm font-semibold text-slate-900">
                                {{ $referral->reporting_month->format('F Y') }}
                            </div>
                            <div class="mt-1 text-xs text-blue-700">
                                {{ $referral->program->label() }}
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800">
                            {{ number_format($referral->interested_referred_total) }}
                        </td>
                        <td class="px-5 py-4 text-right text-sm text-slate-700">
                            {{ number_format($referral->interested_referred_female) }}
                        </td>
                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800">
                            {{ number_format($referral->provided_intervention_total) }}
                        </td>
                        <td class="px-5 py-4 text-right text-sm text-slate-700">
                            {{ number_format($referral->provided_intervention_female) }}
                        </td>
                        <td class="px-5 py-4 text-right text-sm font-semibold text-emerald-700">
                            ₱{{ number_format($referral->amount_released, 2) }}
                        </td>
                        <td class="max-w-sm whitespace-pre-line px-5 py-4 text-xs leading-5 text-slate-600">
                            {{ $referral->services_availed }}
                        </td>
                        <td class="px-5 py-4 text-xs leading-5 text-slate-500">
                            <div>Recorded by {{ $referral->recorder?->name ?? 'Deleted user' }}</div>
                            <div>Updated by {{ $referral->updater?->name ?? 'Deleted user' }}</div>
                            <div>{{ $referral->updated_at->format('M d, Y g:i A') }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-10 text-center text-sm text-slate-400">
                            No monthly Active Labor Market referral records have been encoded.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
