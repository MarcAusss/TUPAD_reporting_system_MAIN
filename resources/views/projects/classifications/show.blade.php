@extends('layouts.app')

@section('title', 'Beneficiary Classification — '.$project->project_title)

@section('content')
@php
    $declaredTotal = (int) $project->beneficiaries_total;
    $declaredFemale = (int) $project->beneficiaries_female;
    $currentFocus = old('intervention_focus', $project->intervention_focus?->value);
    $referrals = $project->laborMarketReferrals;

    $sectorGroups = [
        ['key' => 'priority', 'title' => 'Priority / Vulnerable Sectors', 'hint' => 'Persons with disability, senior citizens, youth, indigenous peoples and other vulnerable groups.', 'categories' => $priorityCategories, 'tone' => 'violet'],
        ['key' => 'occupational', 'title' => 'Occupational / Livelihood Sectors', 'hint' => 'The usual source of livelihood of the beneficiaries.', 'categories' => $occupationalCategories, 'tone' => 'sky'],
    ];

    $focusIcons = [
        'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        'M3 21h18M5 21V7l7-4 7 4v14M9 9h1M14 9h1M9 13h1M14 13h1M9 17h1M14 17h1',
        'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20',
        'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
        'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
    ];

    $inputClass = 'h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm transition focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-200';
@endphp

<x-page-header
    eyebrow="Project Reporting Data"
    :breadcrumbs="\App\Support\Breadcrumbs::forProject($project, 'Beneficiary Classification')"
    title="Beneficiary Classification & Labor Market"
    description="Encode aggregate sector classifications, the project's primary intervention focus, and monthly Active Labor Market referrals."
>
    <x-slot:actions>
        <a href="{{ route('projects.show', ['project' => $project, 'workspace' => 'beneficiaries']) }}"
            class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            ← Project Detail
        </a>
    </x-slot:actions>
</x-page-header>

<x-page-alerts />

{{-- Project context --}}
<section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-2">
        <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-400">Project</div>
        <div class="mt-1 text-sm font-bold leading-5 text-slate-900">{{ $project->project_title }}</div>
        <div class="mt-2 flex flex-wrap gap-2 text-[11px] font-semibold">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{{ $project->approval?->project_code ?: 'Project code not yet assigned' }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">ADL {{ $project->allocation->adl->adl_number }}</span>
        </div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-400">Declared Beneficiaries</div>
        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ number_format($declaredTotal) }}</div>
        <div class="text-xs text-slate-500">{{ number_format($declaredFemale) }} female</div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-400">Labor Market Records</div>
        <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ number_format($referrals->count()) }}</div>
        <div class="text-xs text-slate-500">{{ number_format($referrals->sum('interested_referred_total')) }} referred · {{ number_format($referrals->sum('provided_intervention_total')) }} provided</div>
    </div>
</section>

{{-- ============================================================
     Classification (intervention focus + sectors)
============================================================ --}}
<form method="POST" action="{{ route('projects.classifications.update', $project) }}" data-classification-form data-warn-unsaved
    class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
    @csrf
    @method('PUT')

    <div class="min-w-0 space-y-5">

        {{-- Primary intervention focus --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-[#063b86]" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>
                </span>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Primary Intervention Focus <span class="text-rose-600">*</span></h2>
                    <p class="mt-0.5 text-xs text-slate-500">Select the single primary intervention classification that best represents this project.</p>
                </div>
            </div>

            <fieldset class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
                <legend class="sr-only">Intervention Focus</legend>
                @foreach($interventionFocuses as $focus)
                    <label class="group relative flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition hover:border-blue-300 hover:bg-blue-50/40 has-[:checked]:border-[#063b86] has-[:checked]:bg-blue-50 has-[:checked]:ring-2 has-[:checked]:ring-blue-200">
                        <input type="radio" name="intervention_focus" value="{{ $focus->value }}" required
                            @checked($currentFocus === $focus->value)
                            class="peer sr-only">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition group-has-[:checked]:bg-[#063b86] group-has-[:checked]:text-white" aria-hidden="true">
                            <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="{{ $focusIcons[$loop->index % count($focusIcons)] }}"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold leading-5 text-slate-800">{{ $focus->label() }}</span>
                        </span>
                        <span class="hidden h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#063b86] text-white group-has-[:checked]:flex" aria-hidden="true">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 4 4L19 6"/></svg>
                        </span>
                    </label>
                @endforeach
            </fieldset>
            @error('intervention_focus')
                <p class="px-5 pb-4 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        </section>

        {{-- Sector groups --}}
        @foreach($sectorGroups as $group)
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" data-sector-group="{{ $group['key'] }}">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span @class([
                            'flex h-9 w-9 items-center justify-center rounded-xl',
                            'bg-violet-50 text-violet-700' => $group['tone'] === 'violet',
                            'bg-sky-50 text-sky-700' => $group['tone'] === 'sky',
                        ]) aria-hidden="true">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">{{ $group['title'] }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $group['hint'] }} Enter 0 when a category does not apply.</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-700"><span data-group-total>0</span> total</span>
                        <span class="rounded-full bg-pink-50 px-3 py-1 text-pink-700"><span data-group-female>0</span> female</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm" data-no-enhance>
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-2.5 text-left font-semibold">Sector Category</th>
                                <th class="w-40 px-3 py-2.5 text-left font-semibold">Total Beneficiaries</th>
                                <th class="w-40 px-3 py-2.5 text-left font-semibold">Female Beneficiaries</th>
                                <th class="w-28 px-5 py-2.5 text-right font-semibold">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($group['categories'] as $category)
                                @php
                                    $sectorRecord = $sectorRecords->get($category->value);
                                    $totalField = "sectors.{$category->value}.total";
                                    $femaleField = "sectors.{$category->value}.female";
                                @endphp
                                <tr class="transition hover:bg-slate-50/70" data-sector-row>
                                    <td class="px-5 py-2.5 font-medium text-slate-800">{{ $category->label() }}</td>
                                    <td class="px-3 py-2.5">
                                        <input type="number" min="0" step="1" required inputmode="numeric"
                                            aria-label="{{ $category->label() }} total beneficiaries"
                                            name="sectors[{{ $category->value }}][total]"
                                            value="{{ old($totalField, $sectorRecord?->beneficiaries_total ?? 0) }}"
                                            data-sector-total
                                            class="{{ $inputClass }} text-right tabular-nums">
                                        @error($totalField)
                                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <input type="number" min="0" step="1" required inputmode="numeric"
                                            aria-label="{{ $category->label() }} female beneficiaries"
                                            name="sectors[{{ $category->value }}][female]"
                                            value="{{ old($femaleField, $sectorRecord?->beneficiaries_female ?? 0) }}"
                                            data-sector-female
                                            class="{{ $inputClass }} text-right tabular-nums">
                                        <p class="mt-1 hidden text-[11px] font-medium text-rose-600" data-sector-warning>Female cannot exceed the total.</p>
                                        @error($femaleField)
                                            <p class="mt-1 text-[11px] text-rose-600">{{ $message }}</p>
                                        @enderror
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <div class="ml-auto h-1.5 w-16 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                            <div class="h-full rounded-full {{ $group['tone'] === 'violet' ? 'bg-violet-500' : 'bg-sky-500' }}" style="width: 0%" data-sector-bar></div>
                                        </div>
                                        <div class="mt-1 text-[10px] font-semibold text-slate-400" data-sector-share>0%</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    {{-- Sticky summary + save --}}
    <aside class="xl:sticky xl:top-24 xl:self-start">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-bold text-slate-900">Classification Summary</h2>
                <p class="mt-0.5 text-xs text-slate-500">Updates as you type.</p>
            </div>
            <dl class="space-y-3 px-5 py-4 text-sm">
                <div>
                    <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Intervention Focus</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900" data-focus-summary>
                        {{ collect($interventionFocuses)->first(fn ($focus) => $focus->value === $currentFocus)?->label() ?? 'Not selected' }}
                    </dd>
                </div>
                @foreach($sectorGroups as $group)
                    <div class="flex items-center justify-between gap-3" data-summary-group="{{ $group['key'] }}">
                        <dt class="text-xs text-slate-600">{{ $group['title'] }}</dt>
                        <dd class="text-right text-xs font-bold text-slate-900"><span data-summary-total>0</span> <span class="font-medium text-slate-400">/ <span data-summary-female>0</span> F</span></dd>
                    </div>
                @endforeach
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                    <dt class="text-xs text-slate-600">Declared beneficiaries</dt>
                    <dd class="text-xs font-bold text-slate-900">{{ number_format($declaredTotal) }} <span class="font-medium text-slate-400">/ {{ number_format($declaredFemale) }} F</span></dd>
                </div>
            </dl>
            <div class="hidden border-t border-rose-100 bg-rose-50 px-5 py-3 text-xs font-medium text-rose-700" data-summary-invalid>
                Some categories have more female beneficiaries than their total.
            </div>
            <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-3 text-[11px] leading-5 text-slate-500">
                Aggregate reporting data only. Sector categories may overlap, so their totals do not have to equal the declared beneficiaries, and no individual beneficiary records are created.
            </div>
            <div class="border-t border-slate-100 p-4">
                <button type="submit" data-processing-text="Saving…"
                    class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-[#063b86] px-6 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Save Classification Data
                </button>
            </div>
        </div>
    </aside>
</form>

{{-- ============================================================
     Active Labor Market referrals
============================================================ --}}
<section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700" aria-hidden="true">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        </span>
        <div>
            <h2 class="text-sm font-bold text-slate-900">Active Labor Market Program Referrals</h2>
            <p class="mt-0.5 text-xs text-slate-500">Save one aggregate record per month and program. Saving the same month and program again updates that record, with a full audit trail.</p>
        </div>
    </div>

    <form id="labor-market-referral-form" method="POST" action="{{ route('projects.labor-market-referrals.store', $project) }}"
        data-warn-unsaved class="border-b border-slate-100 bg-slate-50/60 p-5">
        @csrf

        <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)]">
            <div>
                <label for="reporting_month" class="mb-2 block text-xs font-semibold text-slate-700">Month</label>
                <input id="reporting_month" type="month" name="reporting_month" required
                    value="{{ old('reporting_month', now()->format('Y-m')) }}" class="{{ $inputClass }}">
                @error('reporting_month')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <fieldset>
                <legend class="mb-2 block text-xs font-semibold text-slate-700">Program <span class="text-rose-600">*</span></legend>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach($laborMarketPrograms as $program)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:border-emerald-300 has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-900 has-[:checked]:ring-2 has-[:checked]:ring-emerald-100">
                            <input type="radio" name="program" value="{{ $program->value }}" required
                                @checked(old('program') === $program->value)
                                class="h-4 w-4 accent-emerald-600">
                            {{ $program->label() }}
                        </label>
                    @endforeach
                </div>
                @error('program')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </fieldset>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach([
                ['name' => 'interested_referred_total', 'label' => 'Interested Beneficiaries Referred'],
                ['name' => 'interested_referred_female', 'label' => 'Females Referred'],
                ['name' => 'provided_intervention_total', 'label' => 'Provided with Intervention'],
                ['name' => 'provided_intervention_female', 'label' => 'Females Provided with Intervention'],
            ] as $field)
                <div>
                    <label for="{{ $field['name'] }}" class="mb-2 block min-h-8 text-xs font-semibold leading-4 text-slate-700">{{ $field['label'] }}</label>
                    <input id="{{ $field['name'] }}" type="number" min="0" step="1" inputmode="numeric" name="{{ $field['name'] }}" required
                        value="{{ old($field['name'], 0) }}" class="{{ $inputClass }} text-right tabular-nums">
                    @error($field['name'])
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div>
                <label for="amount_released" class="mb-2 block min-h-8 text-xs font-semibold leading-4 text-slate-700">Amount Released (Php)</label>
                <input id="amount_released" type="number" min="0" step="0.01" name="amount_released" data-money-input required
                    value="{{ old('amount_released', '0.00') }}" class="{{ $inputClass }} text-right tabular-nums">
                @error('amount_released')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-4">
            <label for="services_availed" class="mb-2 block text-xs font-semibold text-slate-700">
                Skills Training / Livelihood Assistance / Employment Services Availed
            </label>
            <textarea id="services_availed" name="services_availed" rows="3" maxlength="5000" required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-200"
                placeholder="Describe the assistance or services availed...">{{ old('services_availed') }}</textarea>
            @error('services_availed')
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[11px] text-slate-500">Females cannot exceed their totals, and provided cannot exceed referred.</p>
            <button type="submit" data-processing-text="Saving…"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800">
                Save Monthly Referral
            </button>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1080px] text-sm" data-no-columns>
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Month / Program</th>
                    <th class="px-4 py-3 text-right font-semibold">Referred</th>
                    <th class="px-4 py-3 text-right font-semibold">Female Referred</th>
                    <th class="px-4 py-3 text-right font-semibold">Provided</th>
                    <th class="px-4 py-3 text-right font-semibold">Female Provided</th>
                    <th class="px-4 py-3 text-right font-semibold">Amount Released</th>
                    <th class="px-4 py-3 text-left font-semibold">Services Availed</th>
                    <th class="px-5 py-3 text-left font-semibold" data-no-sort>Audit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($referrals as $referral)
                    <tr class="align-top transition hover:bg-slate-50/70">
                        <td class="px-5 py-3.5" data-sort-value="{{ $referral->reporting_month->format('Y-m') }}">
                            <div class="font-semibold text-slate-900">{{ $referral->reporting_month->format('F Y') }}</div>
                            <span class="mt-1 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ $referral->program->label() }}</span>
                        </td>
                        <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-800">{{ number_format($referral->interested_referred_total) }}</td>
                        <td class="px-4 py-3.5 text-right tabular-nums text-slate-600">{{ number_format($referral->interested_referred_female) }}</td>
                        <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-800">{{ number_format($referral->provided_intervention_total) }}</td>
                        <td class="px-4 py-3.5 text-right tabular-nums text-slate-600">{{ number_format($referral->provided_intervention_female) }}</td>
                        <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-emerald-700">₱{{ number_format($referral->amount_released, 2) }}</td>
                        <td class="max-w-sm whitespace-pre-line px-4 py-3.5 text-xs leading-5 text-slate-600">{{ $referral->services_availed }}</td>
                        <td class="px-5 py-3.5 text-[11px] leading-5 text-slate-500">
                            <div>Recorded by <span class="font-semibold text-slate-700">{{ $referral->recorder?->name ?? 'Deleted user' }}</span></div>
                            <div>Updated by <span class="font-semibold text-slate-700">{{ $referral->updater?->name ?? 'Deleted user' }}</span></div>
                            <div>{{ $referral->updated_at->format('M d, Y g:i A') }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-0"><x-empty-state size="sm" icon="users" title="No monthly Active Labor Market referral records have been encoded." action-label="Encode Referral" action-target="labor-market-referral-form" message="Encode the monthly referrals to track beneficiaries referred to the active labor market." /></td>
                    </tr>
                @endforelse
            </tbody>
            @if ($referrals->isNotEmpty())
                <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold text-slate-900">
                    <tr>
                        <td class="px-5 py-3">Total ({{ $referrals->count() }} {{ \Illuminate\Support\Str::plural('record', $referrals->count()) }})</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($referrals->sum('interested_referred_total')) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($referrals->sum('interested_referred_female')) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($referrals->sum('provided_intervention_total')) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($referrals->sum('provided_intervention_female')) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-emerald-700">₱{{ number_format((float) $referrals->sum('amount_released'), 2) }}</td>
                        <td class="px-4 py-3" colspan="2"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('[data-classification-form]');
        if (!form) return;

        const number = (input) => Math.max(0, parseInt(input?.value || '0', 10) || 0);
        const format = (value) => value.toLocaleString('en-PH');

        const refresh = () => {
            let anyInvalid = false;

            form.querySelectorAll('[data-sector-group]').forEach((group) => {
                const rows = Array.from(group.querySelectorAll('[data-sector-row]'));
                const totals = rows.map((row) => number(row.querySelector('[data-sector-total]')));
                const groupTotal = totals.reduce((sum, value) => sum + value, 0);
                let groupFemale = 0;

                rows.forEach((row, index) => {
                    const total = totals[index];
                    const femaleInput = row.querySelector('[data-sector-female]');
                    const female = number(femaleInput);
                    const invalid = female > total;
                    groupFemale += female;
                    anyInvalid ||= invalid;

                    femaleInput.classList.toggle('tupad-field-invalid', invalid);
                    row.querySelector('[data-sector-warning]').classList.toggle('hidden', !invalid);
                    row.classList.toggle('opacity-60', total === 0 && female === 0);

                    const share = groupTotal > 0 ? Math.round((total / groupTotal) * 100) : 0;
                    row.querySelector('[data-sector-bar]').style.width = `${share}%`;
                    row.querySelector('[data-sector-share]').textContent = `${share}%`;
                });

                group.querySelector('[data-group-total]').textContent = format(groupTotal);
                group.querySelector('[data-group-female]').textContent = format(groupFemale);

                const summary = form.querySelector(`[data-summary-group="${group.dataset.sectorGroup}"]`);
                if (summary) {
                    summary.querySelector('[data-summary-total]').textContent = format(groupTotal);
                    summary.querySelector('[data-summary-female]').textContent = format(groupFemale);
                }
            });

            form.querySelector('[data-summary-invalid]')?.classList.toggle('hidden', !anyInvalid);

            const focus = form.querySelector('input[name="intervention_focus"]:checked');
            const focusSummary = form.querySelector('[data-focus-summary]');
            if (focusSummary) {
                focusSummary.textContent = focus ? focus.closest('label').textContent.trim() : 'Not selected';
            }
        };

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        refresh();

        // Block saving while a female count is above its total. Capture phase so
        // it runs before the global submit guard switches the button to "Saving…".
        document.addEventListener('submit', (event) => {
            if (event.target !== form) return;
            if (!form.querySelector('[data-summary-invalid]')?.classList.contains('hidden')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                form.querySelector('.tupad-field-invalid')?.focus();
            }
        }, true);
    });
</script>
@endpush
