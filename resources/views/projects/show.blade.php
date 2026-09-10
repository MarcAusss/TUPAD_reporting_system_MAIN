@extends('layouts.app')

@section('title', $project->project_title)

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | Role-aware Back Link
    |--------------------------------------------------------------------------
    */

    if (auth()->user()->isFocal()) {
        if ($project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP) {
            $backUrl = route('projects.index');
            $backLabel = 'Project Registry';
        } else {
            $backUrl = route('payments.index');
            $backLabel = 'Payment Queue';
        }
    } else {
        $backUrl = route('projects.index');
        $backLabel = 'Project Management';
    }

@endphp

<x-page-header
    eyebrow="Official Project"
    :title="$project->project_title"
    description="Review the project profile, current workflow status, and the action required to move the project forward."
>
    <x-slot:actions>
        <a
            href="{{ $backUrl }}"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            ← {{ $backLabel }}
        </a>
    </x-slot:actions>
</x-page-header>

<div
    data-project-workspace
    data-default-tab="{{ $workspace['default_tab'] }}"
>
    <x-project-workspace-header
        :project="$project"
        :workspace="$workspace"
    />

    @include('projects.partials.quick-workflow-action')

{{-- Financial Summary --}}

<div id="financial-summary" data-workspace-panel="financial" class="scroll-mt-32 mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4 {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Wages
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->wages_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            PPE
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->ppe_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Insurance
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->insurance_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Total Project Cost
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->total_project_cost, 2) }}
        </div>

    </div>

</div>

{{-- Project Information --}}

<div id="overview" data-workspace-panel="overview" class="scroll-mt-32 mt-5 grid gap-5 xl:grid-cols-2 {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Project Information
            </h2>

        </div>

        <dl class="divide-y divide-slate-100">

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    ADL Number
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->allocation->adl->adl_number }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Fund Sponsor
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->fund_sponsor }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Partner
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->partner }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Date Received
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->date_received->format('F d, Y') }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Nature of Work
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->nature_of_work }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Project Series
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->project_series ?: '—' }}
                </dd>

            </div>

            @if($project->project_series_remarks)
                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">
                        Project Series Remarks
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->project_series_remarks }}
                    </dd>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    TEVS Date Verified
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->tevs_date_verified?->format('F d, Y') ?? '—' }}
                </dd>

            </div>

            @if($project->tevs_remarks)
                <div class="grid grid-cols-2 gap-4 px-5 py-3">
                    <dt class="text-xs text-slate-500">
                        TEVS Remarks
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->tevs_remarks }}
                    </dd>
                </div>
            @endif

            @if($project->remarks)

                <div class="grid grid-cols-2 gap-4 px-5 py-3">

                    <dt class="text-xs text-slate-500">
                        Remarks
                    </dt>

                    <dd class="text-right text-sm font-medium text-slate-800">
                        {{ $project->remarks }}
                    </dd>

                </div>

            @endif

        </dl>

    </section>

    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Location & Implementation
            </h2>

        </div>

        <dl class="divide-y divide-slate-100">

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Location
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->full_location }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    District
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->district ?: 'Not Assigned' }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Income Class
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->income_class ?: 'Not yet assigned' }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Mode
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->implementation_mode->label() }}
                </dd>

            </div>

            <div class="grid grid-cols-2 gap-4 px-5 py-3">

                <dt class="text-xs text-slate-500">
                    Duration
                </dt>

                <dd class="text-right text-sm font-medium text-slate-800">
                    {{ $project->number_of_days }} days
                    —
                    {{ $project->term->label() }}
                </dd>

            </div>

        </dl>

    </section>

</div>

{{-- Beneficiaries & Wage --}}

<section data-workspace-panel="beneficiaries" class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Beneficiaries & Wage
        </h2>

    </div>

    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">

        <div>

            <div class="text-xs text-slate-500">
                Declared Beneficiaries
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->beneficiaries_total) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Female Beneficiaries
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->beneficiaries_female) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Wage Rate
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                ₱{{ number_format($project->wage_rate, 2) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Insurance Rate
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                ₱{{ number_format($project->insurance_rate, 2) }}
            </div>

        </div>

    </div>

</section>

{{-- Beneficiary Summary --}}

<section data-workspace-panel="beneficiaries" class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Beneficiary Summary</h2>
        <p class="mt-1 text-xs text-slate-500">
            Only aggregate beneficiary counts are recorded. Individual personal records are not encoded; beneficiary residence geography is stored separately as aggregate address allocations.
        </p>
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Beneficiaries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($project->beneficiaries_total) }}</div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Female Beneficiaries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($project->beneficiaries_female) }}</div>
        </div>
    </div>
</section>

{{-- Project Location Coverage --}}

@if($project->projectLocations->isNotEmpty())

    <section data-workspace-panel="overview" class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Project Location Coverage
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                All selected district, municipality/city, and barangay target areas for this project.
            </p>
        </div>

        <div class="grid gap-3 p-5 lg:grid-cols-2">

            @foreach($project->projectLocations as $location)

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">

                    <div class="flex items-center justify-between gap-3">

                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                                {{ $location->district }}
                            </div>

                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                {{ $location->municipality->name }}
                            </div>
                        </div>

                        <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 shadow-sm">
                            {{ $location->barangays->count() }} brgy
                        </span>

                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">

                        @foreach($location->barangays as $barangay)
                            <span class="rounded-md border border-blue-100 bg-blue-50 px-2.5 py-1 text-[11px] font-medium text-blue-800">
                                {{ $barangay->name }}
                            </span>
                        @endforeach

                    </div>

                </div>

            @endforeach

        </div>

    </section>

@endif

{{-- Beneficiary Classification, Intervention Focus & Labor Market --}}

@php
    $phase7SectorRecords = $project->beneficiarySectors->keyBy(
        fn ($sector) => $sector->sector_key->value
    );

    $phase7ReferralTotals = [
        'referred' => $project->laborMarketReferrals->sum('interested_referred_total'),
        'provided' => $project->laborMarketReferrals->sum('provided_intervention_total'),
        'amount' => $project->laborMarketReferrals->sum(
            fn ($referral) => (float) $referral->amount_released
        ),
    ];
@endphp

<section id="beneficiary-classification" data-workspace-panel="beneficiaries" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">
                Beneficiary Classification &amp; Labor Market
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Beneficiary addresses drive Beneficiary Mapping, while sector classifications and labor-market records remain separate reporting dimensions.
            </p>
        </div>

        @if(auth()->user()->isAdmin() || auth()->user()->isTc())
            <a
                href="{{ route('projects.classifications.show', $project) }}"
                class="inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]"
            >
                Manage Classification
            </a>
        @endif
    </div>

    @php
        $beneficiaryAddressGroups = $project->beneficiaryAddresses
            ->groupBy('municipality_id')
            ->map(function ($addresses, $municipalityId) {
                return [
                    'municipality_id' => (int) $municipalityId,
                    'barangays' => $addresses->map(fn ($address) => [
                        'barangay_id' => (int) $address->barangay_id,
                        'beneficiaries_total' => (int) $address->beneficiaries_total,
                        'beneficiaries_female' => (int) $address->beneficiaries_female,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $beneficiaryAddressFormData = collect(old(
            'beneficiary_addresses',
            $beneficiaryAddressGroups
        ))->values()->all();
        $beneficiaryAddressAllocatedTotal = (int) $project->beneficiaryAddresses->sum('beneficiaries_total');
        $beneficiaryAddressAllocatedFemale = (int) $project->beneficiaryAddresses->sum('beneficiaries_female');
    @endphp

    <div class="border-b border-slate-200 bg-slate-50/70 p-5">
        <div class="rounded-xl border border-blue-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-blue-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                        Beneficiary Mapping Source
                    </div>
                    <h3 class="mt-1 text-sm font-semibold text-slate-900">
                        Beneficiary Address &amp; Geographic Allocation
                    </h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                        Encode where the project beneficiaries reside. These address allocations are used by Beneficiary Mapping and are kept separate from Project Location Mapping.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:min-w-[270px]">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Declared Total</div>
                        <div class="mt-1 text-sm font-bold text-slate-900">{{ number_format($project->beneficiaries_total) }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Declared Female</div>
                        <div class="mt-1 text-sm font-bold text-slate-900">{{ number_format($project->beneficiaries_female) }}</div>
                    </div>
                </div>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->isTc())
                @if($beneficiaryAddressProvince)
                    <form
                        id="beneficiaryAddressForm"
                        method="POST"
                        action="{{ route('projects.beneficiary-addresses.update', $project) }}"
                        class="p-5"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="province_id" value="{{ $beneficiaryAddressProvince->id }}">

                        @if($errors->has('province_id') || $errors->has('beneficiary_addresses') || $errors->has('beneficiary_addresses.*'))
                            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                                {{ $errors->first('province_id') ?: $errors->first('beneficiary_addresses') ?: $errors->first('beneficiary_addresses.*') }}
                            </div>
                        @endif

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Province</label>
                                    <div class="flex h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800">
                                        {{ $beneficiaryAddressProvince->name }}
                                    </div>
                                    <p class="mt-2 text-[11px] leading-5 text-slate-500">
                                        Province is automatically locked to the project/coordinator assignment. Only municipalities and barangays inside this province can be saved.
                                    </p>
                                </div>

                                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-800">Municipalities / Cities &amp; Barangays</h4>
                                        <p class="mt-1 text-[11px] text-slate-500">Add every beneficiary municipality/city, select its barangays, then allocate Total and Female counts.</p>
                                    </div>
                                    <button
                                        id="addBeneficiaryAddressLocation"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-blue-300 bg-white px-3 text-xs font-semibold text-blue-800 hover:bg-blue-50"
                                    >
                                        + Add Municipality / City
                                    </button>
                                </div>

                                <div id="beneficiaryAddressLocations" class="mt-4 space-y-4"></div>
                            </div>

                            <aside>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 lg:sticky lg:top-24">
                                    <div class="text-xs font-semibold text-slate-800">Address Allocation Status</div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200">
                                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Allocated</div>
                                            <div id="beneficiaryAddressTotal" class="mt-1 text-sm font-bold text-slate-900">0</div>
                                        </div>
                                        <div class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200">
                                            <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Female</div>
                                            <div id="beneficiaryAddressFemale" class="mt-1 text-sm font-bold text-slate-900">0</div>
                                        </div>
                                    </div>

                                    <div id="beneficiaryAddressValidation" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800">
                                        Complete the beneficiary address allocation.
                                    </div>

                                    <button
                                        type="submit"
                                        class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]"
                                    >
                                        Save Beneficiary Addresses
                                    </button>
                                </div>
                            </aside>
                        </div>
                    </form>
                @else
                    <div class="p-5">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                            Beneficiary address encoding is unavailable because this project has no resolvable assigned province.
                        </div>
                    </div>
                @endif
            @else
                <div class="p-5">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                        Beneficiary address allocation is read-only for this account.
                    </div>
                </div>
            @endif

            <details class="border-t border-slate-200" @if($project->beneficiaryAddresses->isEmpty()) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                    <div>
                        <div class="text-xs font-semibold text-slate-900">View All Beneficiary Address Data</div>
                        <div class="mt-1 text-[11px] text-slate-500">
                            {{ number_format($project->beneficiaryAddresses->count()) }} barangay record(s) ·
                            {{ number_format($beneficiaryAddressAllocatedTotal) }} total ·
                            {{ number_format($beneficiaryAddressAllocatedFemale) }} female
                        </div>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand / Collapse</span>
                </summary>

                <div class="overflow-x-auto border-t border-slate-200">
                    <table class="tupad-system-table min-w-[760px] w-full">
                        <thead class="bg-slate-50">
                            <tr class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                <th class="px-4 py-3 text-left">Province</th>
                                <th class="px-4 py-3 text-left">District</th>
                                <th class="px-4 py-3 text-left">Municipality / City</th>
                                <th class="px-4 py-3 text-left">Barangay</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Female</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($project->beneficiaryAddresses as $address)
                                <tr>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ $address->province?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ $address->municipality?->district ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs font-semibold text-slate-800">{{ $address->municipality?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-700">{{ $address->barangay?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-xs font-semibold text-slate-900">{{ number_format($address->beneficiaries_total) }}</td>
                                    <td class="px-4 py-3 text-right text-xs text-slate-600">{{ number_format($address->beneficiaries_female) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-xs text-slate-400">
                                        No beneficiary address allocation has been encoded yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </div>

    @if((auth()->user()->isAdmin() || auth()->user()->isTc()) && $beneficiaryAddressProvince)
        <script>
            (() => {
                const root = document.getElementById('beneficiaryAddressLocations');
                const addButton = document.getElementById('addBeneficiaryAddressLocation');
                const form = document.getElementById('beneficiaryAddressForm');
                const status = document.getElementById('beneficiaryAddressValidation');
                const totalOutput = document.getElementById('beneficiaryAddressTotal');
                const femaleOutput = document.getElementById('beneficiaryAddressFemale');

                if (!root || !addButton || !form || !status) return;

                const initialGroups = @json($beneficiaryAddressFormData);
                const municipalityUrl = @json(route('locations.municipalities', $beneficiaryAddressProvince));
                const declaredTotal = Number(@json((int) $project->beneficiaries_total));
                const declaredFemale = Number(@json((int) $project->beneficiaries_female));
                let municipalityOptions = [];
                let nextIndex = 0;

                const escapeHtml = value => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const fetchJson = async url => {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load geographic reference data.');
                    }

                    return response.json();
                };

                const selectedMunicipalityIds = () => Array.from(
                    root.querySelectorAll('.beneficiary-municipality-select')
                )
                    .map(select => select.value)
                    .filter(Boolean);

                const refreshMunicipalityOptions = () => {
                    const selected = selectedMunicipalityIds();

                    root.querySelectorAll('.beneficiary-municipality-select').forEach(select => {
                        Array.from(select.options).forEach(option => {
                            if (!option.value) return;
                            option.disabled = option.value !== select.value && selected.includes(option.value);
                        });
                    });
                };

                const updateStatus = () => {
                    const totalInputs = Array.from(root.querySelectorAll('.beneficiary-address-total'));
                    const femaleInputs = Array.from(root.querySelectorAll('.beneficiary-address-female'));

                    const allocatedTotal = totalInputs.reduce((sum, input) => sum + Number(input.value || 0), 0);
                    const allocatedFemale = femaleInputs.reduce((sum, input) => sum + Number(input.value || 0), 0);
                    const incomplete = totalInputs.length === 0
                        || totalInputs.some(input => input.value === '')
                        || femaleInputs.some(input => input.value === '');
                    const femaleExceeds = totalInputs.some((input, index) =>
                        Number(femaleInputs[index]?.value || 0) > Number(input.value || 0)
                    );

                    totalOutput.textContent = allocatedTotal.toLocaleString();
                    femaleOutput.textContent = allocatedFemale.toLocaleString();

                    if (femaleExceeds) {
                        status.className = 'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                        status.textContent = 'A barangay Female count cannot exceed that barangay Total count.';
                        return false;
                    }

                    if (incomplete) {
                        status.className = 'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                        status.textContent = 'Select at least one barangay and complete all Total and Female allocations.';
                        return false;
                    }

                    if (allocatedTotal !== declaredTotal || allocatedFemale !== declaredFemale) {
                        status.className = 'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                        status.textContent = `Allocated ${allocatedTotal.toLocaleString()} of ${declaredTotal.toLocaleString()} total and ${allocatedFemale.toLocaleString()} of ${declaredFemale.toLocaleString()} female beneficiaries.`;
                        return false;
                    }

                    status.className = 'mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-[11px] font-semibold leading-5 text-emerald-700';
                    status.textContent = 'Beneficiary address allocation is complete and ready to save.';
                    return true;
                };

                const renderSelectedBarangays = (card, groupIndex) => {
                    const selectedBox = card.querySelector('.beneficiary-selected-barangays');
                    const checked = Array.from(card.querySelectorAll('.beneficiary-barangay-checkbox:checked'));
                    const existing = new Map(
                        Array.from(selectedBox.querySelectorAll('.beneficiary-address-row')).map(row => [
                            row.dataset.barangayId,
                            {
                                total: row.querySelector('.beneficiary-address-total')?.value ?? '',
                                female: row.querySelector('.beneficiary-address-female')?.value ?? '',
                            },
                        ])
                    );

                    selectedBox.innerHTML = '';

                    if (checked.length === 0) {
                        selectedBox.innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>';
                        updateStatus();
                        return;
                    }

                    checked.forEach((checkbox, barangayIndex) => {
                        const barangayId = checkbox.value;
                        const values = existing.get(barangayId) || {
                            total: checkbox.dataset.total ?? '',
                            female: checkbox.dataset.female ?? '',
                        };
                        const row = document.createElement('div');
                        row.className = 'beneficiary-address-row grid gap-3 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_110px_110px]';
                        row.dataset.barangayId = barangayId;
                        row.innerHTML = `
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-slate-800">${escapeHtml(checkbox.dataset.name)}</div>
                                <div class="mt-1 text-[10px] text-slate-400">Beneficiary home address allocation</div>
                                <input type="hidden" name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][barangay_id]" value="${escapeHtml(barangayId)}">
                            </div>
                            <label>
                                <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Total</span>
                                <input type="number" min="0" step="1" required name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][beneficiaries_total]" value="${escapeHtml(values.total)}" class="beneficiary-address-total h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                            </label>
                            <label>
                                <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Female</span>
                                <input type="number" min="0" step="1" required name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][beneficiaries_female]" value="${escapeHtml(values.female)}" class="beneficiary-address-female h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                            </label>
                        `;

                        row.querySelectorAll('input[type="number"]').forEach(input => {
                            input.addEventListener('input', updateStatus);
                        });
                        selectedBox.appendChild(row);
                    });

                    updateStatus();
                };

                const loadBarangays = async (card, groupIndex, municipalityId, initialBarangays = []) => {
                    const optionsBox = card.querySelector('.beneficiary-barangay-options');
                    const selectedMap = new Map(
                        (initialBarangays || []).map(row => [String(row.barangay_id), row])
                    );

                    if (!municipalityId) {
                        optionsBox.innerHTML = '<div class="px-3 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>';
                        card.querySelector('.beneficiary-selected-barangays').innerHTML = '<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>';
                        updateStatus();
                        return;
                    }

                    optionsBox.innerHTML = '<div class="px-3 py-4 text-center text-[11px] text-slate-400">Loading barangays...</div>';

                    try {
                        const barangays = await fetchJson(`/locations/municipalities/${municipalityId}/barangays`);
                        optionsBox.innerHTML = '';

                        barangays.forEach(barangay => {
                            const existing = selectedMap.get(String(barangay.id));
                            const label = document.createElement('label');
                            label.className = 'flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-xs text-slate-700 hover:bg-slate-50';
                            label.innerHTML = `
                                <input type="checkbox" value="${barangay.id}" data-name="${escapeHtml(barangay.name)}" data-total="${escapeHtml(existing?.beneficiaries_total ?? '')}" data-female="${escapeHtml(existing?.beneficiaries_female ?? '')}" class="beneficiary-barangay-checkbox h-4 w-4 rounded border-slate-300 text-blue-700" ${existing ? 'checked' : ''}>
                                <span>${escapeHtml(barangay.name)}</span>
                            `;
                            label.querySelector('input').addEventListener('change', () => renderSelectedBarangays(card, groupIndex));
                            optionsBox.appendChild(label);
                        });

                        renderSelectedBarangays(card, groupIndex);
                    } catch (error) {
                        optionsBox.innerHTML = '<div class="px-3 py-4 text-center text-[11px] font-medium text-red-600">Unable to load barangays. Refresh the page and try again.</div>';
                    }
                };

                const addLocationCard = async initial => {
                    const groupIndex = nextIndex++;
                    const card = document.createElement('div');
                    card.className = 'beneficiary-address-card rounded-xl border border-slate-200 bg-white p-4';
                    card.dataset.index = groupIndex;
                    card.innerHTML = `
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700">Municipality / City</label>
                                <select name="beneficiary_addresses[${groupIndex}][municipality_id]" required class="beneficiary-municipality-select h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-xs">
                                    <option value="">Select municipality / city</option>
                                    ${municipalityOptions.map(item => `<option value="${item.id}">${escapeHtml(item.name)}${item.district ? ` — ${escapeHtml(item.district)}` : ''}</option>`).join('')}
                                </select>
                            </div>
                            <button type="button" class="remove-beneficiary-address-card inline-flex h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-[11px] font-semibold text-red-700 hover:bg-red-50">Remove</button>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-[10px] font-bold uppercase tracking-wide text-slate-500">Select Barangays</div>
                                <div class="beneficiary-barangay-options max-h-52 overflow-y-auto p-2">
                                    <div class="px-3 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-2 text-[10px] font-bold uppercase tracking-wide text-slate-500">Selected Barangay Allocation</div>
                                <div class="beneficiary-selected-barangays space-y-2">
                                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>
                                </div>
                            </div>
                        </div>
                    `;

                    root.appendChild(card);

                    const select = card.querySelector('.beneficiary-municipality-select');
                    if (initial?.municipality_id) {
                        select.value = String(initial.municipality_id);
                    }

                    select.addEventListener('change', async () => {
                        refreshMunicipalityOptions();
                        await loadBarangays(card, groupIndex, select.value, []);
                    });

                    card.querySelector('.remove-beneficiary-address-card').addEventListener('click', () => {
                        card.remove();
                        refreshMunicipalityOptions();
                        updateStatus();
                    });

                    refreshMunicipalityOptions();

                    if (select.value) {
                        await loadBarangays(card, groupIndex, select.value, initial?.barangays || []);
                    } else {
                        updateStatus();
                    }
                };

                const initialize = async () => {
                    try {
                        municipalityOptions = await fetchJson(municipalityUrl);
                    } catch (error) {
                        status.className = 'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                        status.textContent = 'Unable to load municipalities/cities for the assigned province.';
                        addButton.disabled = true;
                        return;
                    }

                    if (Array.isArray(initialGroups) && initialGroups.length > 0) {
                        for (const group of initialGroups) {
                            await addLocationCard(group);
                        }
                    } else {
                        await addLocationCard(null);
                    }

                    updateStatus();
                };

                addButton.addEventListener('click', () => addLocationCard(null));

                form.addEventListener('submit', event => {
                    if (!updateStatus()) {
                        event.preventDefault();
                        status.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });

                initialize();
            })();
        </script>
    @endif

    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        <div class="bg-white px-5 py-4 sm:col-span-2 xl:col-span-1">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Primary Intervention Focus
            </div>
            <div class="mt-1 text-sm font-semibold leading-5 text-slate-900">
                {{ $project->intervention_focus?->label() ?? 'Not yet classified' }}
            </div>
        </div>

        <div class="bg-white px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Referral Records
            </div>
            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->laborMarketReferrals->count()) }}
            </div>
        </div>

        <div class="bg-white px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Referred / Provided
            </div>
            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($phase7ReferralTotals['referred']) }} /
                {{ number_format($phase7ReferralTotals['provided']) }}
            </div>
        </div>

        <div class="bg-white px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">
                Intervention Amount Released
            </div>
            <div class="mt-1 text-lg font-bold text-emerald-700">
                ₱{{ number_format($phase7ReferralTotals['amount'], 2) }}
            </div>
        </div>
    </div>

    <div class="grid gap-5 p-5 xl:grid-cols-2">
        @foreach([
            'Priority / Vulnerable Sectors' => \App\Enums\BeneficiarySectorCategory::priorityVulnerable(),
            'Occupational / Livelihood Sectors' => \App\Enums\BeneficiarySectorCategory::occupationalLivelihood(),
        ] as $groupLabel => $categories)
            <div class="overflow-hidden rounded-lg border border-slate-200">
                <div class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-800">
                    {{ $groupLabel }}
                </div>
                <table class="tupad-system-table w-full">
                    <thead>
                        <tr class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            <th class="px-4 py-2 text-left">Category</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-right">Female</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($categories as $category)
                            @php
                                $phase7Sector = $phase7SectorRecords->get($category->value);
                            @endphp
                            <tr>
                                <td class="px-4 py-2 text-xs font-medium text-slate-700">
                                    {{ $category->label() }}
                                </td>
                                <td class="px-4 py-2 text-right text-xs font-semibold text-slate-900">
                                    {{ number_format($phase7Sector?->beneficiaries_total ?? 0) }}
                                </td>
                                <td class="px-4 py-2 text-right text-xs text-slate-600">
                                    {{ number_format($phase7Sector?->beneficiaries_female ?? 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</section>

{{-- Evaluation & Approval --}}

<section id="evaluation" data-workspace-panel="workflow" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Evaluation & Approval
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Current project status: {{ $project->status->label() }}
        </p>

    </div>

    <div class="p-5">

        {{-- Ongoing Profiling --}}

        @if($project->status === \App\Enums\ProjectStatus::ONGOING_PROFILING)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="text-sm font-semibold text-amber-900">Ongoing Profiling</div>

                <p class="mt-1 text-xs leading-5 text-amber-800">
                    Review the project profile and supporting information. Submit to TSSD Evaluation only when profiling is complete.
                </p>

                @if(auth()->user()->isAdmin() || auth()->user()->isTc())
                    <form
                        method="POST"
                        action="{{ route('projects.evaluation.start', $project) }}"
                        class="mt-4"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
                        >
                            Submit to TSSD Evaluation
                        </button>
                    </form>
                @endif
            </div>
        @endif

        {{-- TSSD Evaluation / Compliance --}}

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::TSSD_EVALUATION,
                    \App\Enums\ProjectStatus::FOR_COMPLIANCE,
                ],
                true
            )
        )

            @if($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE)

                @php
                    $latestEvaluation = $project
                        ->evaluations
                        ->where('result', 'for_compliance')
                        ->sortByDesc('evaluated_at')
                        ->first();

                    $complianceAgingDays =
                        $latestEvaluation?->evaluated_at
                            ? (int) $latestEvaluation
                                ->evaluated_at
                                ->copy()
                                ->startOfDay()
                                ->diffInDays(
                                    now()->startOfDay()
                                )
                            : 0;
                @endphp

                <div class="mb-5 overflow-hidden rounded-xl border border-amber-200 bg-amber-50">

                    <div class="border-b border-amber-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                            <div>
                                <div class="text-sm font-semibold text-amber-900">
                                    Project for Compliance
                                </div>

                                <p class="mt-1 text-xs leading-5 text-amber-700">
                                    Record the Date of Compliance. Saving automatically moves this project to For Approval.
                                </p>
                            </div>

                            <div class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-right">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-amber-600">
                                    Aging
                                </div>

                                <div class="mt-0.5 text-lg font-bold text-amber-900">
                                    {{ number_format($complianceAgingDays) }}
                                    <span class="text-xs font-semibold">
                                        day(s)
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="p-4">

                        @if($latestEvaluation)

                            <div class="grid gap-4 lg:grid-cols-2">

                                <div class="rounded-lg border border-amber-200 bg-white p-4">
                                    <div class="text-xs font-semibold text-amber-800">
                                        Findings
                                    </div>

                                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                                        {{ $latestEvaluation->findings ?: '—' }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-amber-200 bg-white p-4">
                                    <div class="text-xs font-semibold text-amber-800">
                                        Required Documents
                                    </div>

                                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                                        {{ $latestEvaluation->required_documents ?: '—' }}
                                    </p>
                                </div>

                            </div>

                            <div class="mt-4 text-xs text-amber-700">
                                Compliance requested:
                                <span class="font-semibold">
                                    {{ $latestEvaluation->evaluated_at->format('F d, Y') }}
                                </span>
                            </div>

                        @endif

                        <form
                            method="POST"
                            action="{{ route('projects.compliance.store', $project) }}"
                            class="mt-5"
                        >

                            @csrf

                            <div class="grid gap-4 md:grid-cols-2 md:items-end">

                                <div>
                                    <label
                                        for="compliance-date"
                                        class="mb-2 block text-xs font-semibold text-slate-700"
                                    >
                                        Date of Compliance
                                        <span class="text-rose-600">*</span>
                                    </label>

                                    <input
                                        id="compliance-date"
                                        name="compliance_date"
                                        type="date"
                                        required
                                        min="{{ $latestEvaluation?->evaluated_at?->toDateString() }}"
                                        value="{{ old('compliance_date', now()->toDateString()) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                    >

                                    @error('compliance_date')
                                        <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div class="flex md:justify-end">
                                    <button
                                        type="submit"
                                        class="h-10 rounded-lg bg-amber-700 px-5 text-sm font-semibold text-white hover:bg-amber-800"
                                    >
                                        Save Compliance
                                    </button>
                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            @endif

            @if($project->status === \App\Enums\ProjectStatus::TSSD_EVALUATION)

                <form
                    method="POST"
                    action="{{ route('projects.evaluation.store', $project) }}"
                    class="space-y-4"
                >

                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Evaluation Result
                            </label>

                            <select
                                id="evaluation-result"
                                name="result"
                                required
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                            >

                                <option value="">
                                    Select result
                                </option>

                                <option
                                    value="for_compliance"
                                    @selected(old('result') === 'for_compliance')
                                >
                                    For Compliance
                                </option>

                                <option
                                    value="for_approval"
                                    @selected(old('result') === 'for_approval')
                                >
                                    For Approval
                                </option>

                            </select>

                        </div>

                    </div>

                    <div
                        id="for-approval-note"
                        class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3"
                    >
                        <div class="text-xs font-semibold text-emerald-900">
                            Ready for Approval
                        </div>

                        <p class="mt-1 text-xs leading-5 text-emerald-700">
                            Findings and Required Documents are not required when the evaluation result is For Approval.
                        </p>
                    </div>

                    <div
                        id="compliance-fields"
                        class="space-y-4"
                    >
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">

                            <div class="text-xs font-semibold text-amber-900">
                                Compliance Details Required
                            </div>

                            <p class="mt-1 text-xs leading-5 text-amber-700">
                                Both Findings and Required Documents are required when the result is For Compliance.
                            </p>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Findings
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                id="evaluation-findings"
                                name="findings"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="State the findings that require compliance..."
                            >{{ old('findings') }}</textarea>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Required Documents
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                id="evaluation-required-documents"
                                name="required_documents"
                                rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="List the documentary requirements to be complied with..."
                            >{{ old('required_documents') }}</textarea>

                        </div>
                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old('remarks') }}</textarea>

                    </div>

                    <div class="flex justify-end">

                        <button
                            type="submit"
                            class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                        >
                            Save Evaluation
                        </button>

                    </div>

                </form>

            @endif

        @endif

        {{-- For Approval --}}

        @if($project->status === \App\Enums\ProjectStatus::FOR_APPROVAL)

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">

                <div class="text-sm font-semibold text-emerald-800">
                    Project ready for approval
                </div>

                <p class="mt-1 text-xs leading-5 text-emerald-700">
                    Assign the official Project Code during approval. One project receives one Project Code, and that code cannot be reused by another project.
                    Saving approval automatically updates the project status to Approved.
                </p>

            </div>

            <form
                method="POST"
                action="{{ route('projects.approval.store', $project) }}"
                class="mt-5 space-y-4"
            >

                @csrf

                <div class="grid gap-4 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date of Approval
                        </label>

                        <input
                            name="approval_date"
                            type="date"
                            value="{{ old('approval_date', now()->format('Y-m-d')) }}"
                            required
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Official Project Code
                            <span class="text-rose-600">*</span>
                        </label>

                        <input
                            name="project_code"
                            type="text"
                            required
                            autocomplete="off"
                            value="{{ old('project_code') }}"
                            placeholder="Example: TUPAD-ALB-2026-001"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-semibold uppercase tracking-wide"
                        >

                        <p class="mt-1.5 text-[10px] leading-4 text-slate-500">
                            This becomes the single official Project Code for this project after approval.
                        </p>

                        @error('project_code')
                            <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Approval Remarks
                    </label>

                    <textarea
                        name="remarks"
                        rows="3"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    >{{ old('remarks') }}</textarea>

                </div>

                <div class="flex justify-end">

                    <button
                        type="submit"
                        class="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800"
                    >
                        Approve Project
                    </button>

                </div>

            </form>

        @endif

        {{-- Approved --}}

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::COMPLETED,
                ],
                true
            )
            && $project->approval
        )

            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Approved Project
                        </div>

                        <div class="mt-1 text-xl font-bold text-emerald-900">
                            {{ $project->approval->project_code }}
                        </div>

                    </div>

                    <div class="text-left sm:text-right">

                        <div class="text-xs text-emerald-600">
                            Date of Approval
                        </div>

                        <div class="mt-1 text-sm font-semibold text-emerald-900">
                            {{ $project->approval->approval_date->format('F d, Y') }}
                        </div>

                    </div>

                </div>

            </div>

        @endif

    </div>

</section>

{{-- Evaluation History --}}

@if($project->evaluations->isNotEmpty())

    <section id="evaluation-history" data-workspace-panel="workflow" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Evaluation History
            </h2>

        </div>

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Evaluator
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Result
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Findings
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Required Documents
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @foreach($project->evaluations->sortByDesc('evaluated_at') as $evaluation)

                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->evaluated_at->format('M d, Y g:i A') }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $evaluation->evaluator?->name ?? 'System' }}
                            </td>

                            <td class="px-5 py-4">

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $evaluation->result === 'for_approval'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : 'bg-amber-50 text-amber-700' }}"
                                >
                                    {{ $evaluation->result === 'for_approval'
                                        ? 'For Approval'
                                        : 'For Compliance' }}
                                </span>

                                @if($evaluation->result === 'for_compliance')
                                    @php
                                        $evaluationAging =
                                            $evaluation->compliance_date
                                                ? (int) $evaluation
                                                    ->evaluated_at
                                                    ->copy()
                                                    ->startOfDay()
                                                    ->diffInDays(
                                                        $evaluation
                                                            ->compliance_date
                                                            ->copy()
                                                            ->startOfDay()
                                                    )
                                                : null;
                                    @endphp

                                    <div class="mt-2 text-[10px] leading-4 text-slate-500">
                                        @if($evaluation->compliance_date)
                                            Compliance:
                                            <span class="font-semibold text-slate-700">
                                                {{ $evaluation->compliance_date->format('M d, Y') }}
                                            </span>
                                            · Aging:
                                            <span class="font-semibold text-slate-700">
                                                {{ number_format($evaluationAging) }} day(s)
                                            </span>
                                        @else
                                            Compliance pending
                                        @endif
                                    </div>
                                @endif

                            </td>

                            <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->findings ?: '—' }}
                            </td>

                            <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->required_documents ?: '—' }}
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </section>

@endif

{{-- Implementation Preparation --}}

@if(
    in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::APPROVED,
            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        ],
        true
    )
    && $project->implementation_mode
        === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION
)

    <section id="implementation" data-workspace-panel="workflow" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <h2 class="text-sm font-semibold text-slate-900">
                        Project Implementation
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Direct Administration workflow: Insurance, PPE, Notice to Proceed, Orientation, and Work Period.
                    </p>

                </div>

                @if($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)

                    <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        Ready for Implementation
                    </span>

                @endif

            </div>

        </div>

        @php
            $preparationItems = [
                'Insurance' => (bool) $project->insuranceEnrollment,
                'PPE Delivery' => (bool) $project->ppeDelivery,
                'Notice to Proceed' => (bool) $project->noticeToProceed,
                'Orientation' => (bool) $project->orientation,
                'Implementation Period' => (bool) $project->implementation,
            ];

            $completedPreparation = collect($preparationItems)
                ->filter()
                ->count();

            $preparationPercent =
                count($preparationItems) > 0
                    ? ($completedPreparation / count($preparationItems)) * 100
                    : 0;
        @endphp

        <div class="border-b border-slate-200 p-5">

            <div class="flex items-center justify-between">

                <span class="text-xs font-semibold text-slate-600">
                    Preparation Completion
                </span>

                <span class="text-xs font-semibold text-slate-800">
                    {{ $completedPreparation }}/{{ count($preparationItems) }}
                </span>

            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">

                <div
                    class="h-full rounded-full bg-slate-800"
                    style="width: {{ $preparationPercent }}%;"
                ></div>

            </div>

            <div class="mt-4 flex flex-wrap gap-2">

                @foreach($preparationItems as $label => $complete)

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $complete
                                ? 'bg-emerald-50 text-emerald-700'
                                : 'bg-slate-100 text-slate-500' }}"
                    >
                        {{ $complete ? '✓' : '•' }}
                        {{ $label }}
                    </span>

                @endforeach

            </div>

        </div>

        @if(
            in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                ],
                true
            )
        )

            <div class="grid gap-5 p-5 xl:grid-cols-2">

                {{-- Combined Implementation Requirements --}}

                <form
                    method="POST"
                    action="{{ route('projects.implementation.requirements', $project) }}"
                    class="xl:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white"
                >
                    @csrf

                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">
                                    Implementation Requirements
                                </h3>

                                <p class="mt-1 text-xs leading-5 text-slate-500">
                                    Record Insurance, PPE, and Notice to Proceed for this Direct Administration project.
                                    Saving all three complete requirements automatically moves the project to For Implementation.
                                </p>
                            </div>

                            <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                                Single Submission
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-5 p-5 xl:grid-cols-3">

                        {{-- Insurance Enrollment --}}

                        <section class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 1
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        Insurance Enrollment
                                    </h4>
                                </div>

                                @if($project->insuranceEnrollment)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                                <div class="text-xs font-semibold text-blue-900">
                                    Approved project values are locked
                                </div>

                                <p class="mt-1 text-xs leading-5 text-blue-700">
                                    Insurance Beneficiaries and Insurance Amount use the approved
                                    project values and cannot be edited here.
                                </p>
                            </div>

                            <div class="mt-4 grid gap-4">

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Enrolled
                                    </label>

                                    <input
                                        name="insurance[date_enrolled]"
                                        type="date"
                                        required
                                        value="{{ old(
                                            'insurance.date_enrolled',
                                            $project->insuranceEnrollment?->date_enrolled?->format('Y-m-d')
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >

                                    @error('insurance.date_enrolled')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Beneficiaries
                                        </label>

                                        <div class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                            <span class="font-semibold text-slate-900">
                                                {{ number_format(
                                                    $project->insurance_beneficiaries
                                                    ?? $project->beneficiaries_total
                                                ) }}
                                            </span>

                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Locked
                                            </span>
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Uses the approved insurance beneficiary count.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Amount
                                        </label>

                                        <div class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                            <span class="font-semibold text-slate-900">
                                                ₱{{ number_format($project->insurance_total, 2) }}
                                            </span>

                                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Locked
                                            </span>
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Uses the approved project insurance amount.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Mode of Payment
                                    </label>

                                    <select
                                        name="insurance[payment_mode]"
                                        required
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm"
                                    >
                                        <option value="">
                                            Select mode
                                        </option>

                                        <option
                                            value="voucher"
                                            @selected(
                                                old(
                                                    'insurance.payment_mode',
                                                    $project->insuranceEnrollment?->payment_mode
                                                ) === 'voucher'
                                            )
                                        >
                                            Voucher
                                        </option>

                                        <option
                                            value="ca"
                                            @selected(
                                                old(
                                                    'insurance.payment_mode',
                                                    $project->insuranceEnrollment?->payment_mode
                                                ) === 'ca'
                                            )
                                        >
                                            CA
                                        </option>
                                    </select>

                                    @error('insurance.payment_mode')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        OR Number
                                    </label>

                                    <input
                                        name="insurance[or_number]"
                                        value="{{ old(
                                            'insurance.or_number',
                                            $project->insuranceEnrollment?->or_number
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Policy Number
                                    </label>

                                    <input
                                        name="insurance[policy_number]"
                                        value="{{ old(
                                            'insurance.policy_number',
                                            $project->insuranceEnrollment?->policy_number
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Remarks
                                    </label>

                                    <textarea
                                        name="insurance[remarks]"
                                        rows="2"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                    >{{ old(
                                        'insurance.remarks',
                                        $project->insuranceEnrollment?->remarks
                                    ) }}</textarea>
                                </div>

                            </div>
                        </section>

                        {{-- PPE Delivery --}}

                        <section class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 2
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        PPE Delivery
                                    </h4>
                                </div>

                                @if($project->ppeDelivery)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date of Delivery Receipt
                                </label>

                                <input
                                    name="ppe[delivery_receipt_date]"
                                    type="date"
                                    required
                                    value="{{ old(
                                        'ppe.delivery_receipt_date',
                                        $project->ppeDelivery?->delivery_receipt_date?->format('Y-m-d')
                                    ) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                >

                                @error('ppe.delivery_receipt_date')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    PPE Provided
                                </label>

                                <textarea
                                    name="ppe[ppe_provided]"
                                    rows="5"
                                    required
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >{{ old(
                                    'ppe.ppe_provided',
                                    $project->ppeDelivery?->ppe_provided
                                ) }}</textarea>

                                @error('ppe.ppe_provided')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea
                                    name="ppe[remarks]"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >{{ old(
                                    'ppe.remarks',
                                    $project->ppeDelivery?->remarks
                                ) }}</textarea>
                            </div>
                        </section>

                        {{-- Notice to Proceed --}}

                        <section class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 3
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        Notice to Proceed
                                    </h4>
                                </div>

                                @if($project->noticeToProceed)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4 grid gap-4">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Issued
                                    </label>

                                    <input
                                        name="ntp[date_issued]"
                                        type="date"
                                        required
                                        value="{{ old(
                                            'ntp.date_issued',
                                            $project->noticeToProceed?->date_issued?->format('Y-m-d')
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >

                                    @error('ntp.date_issued')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Released
                                    </label>

                                    <input
                                        name="ntp[date_released]"
                                        type="date"
                                        required
                                        value="{{ old(
                                            'ntp.date_released',
                                            $project->noticeToProceed?->date_released?->format('Y-m-d')
                                        ) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                                    >

                                    @error('ntp.date_released')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea
                                    name="ntp[remarks]"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >{{ old(
                                    'ntp.remarks',
                                    $project->noticeToProceed?->remarks
                                ) }}</textarea>
                            </div>
                        </section>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-slate-500">
                            Saving updates all three requirements together. Once complete, status automatically becomes For Implementation.
                        </p>

                        <button
                            type="submit"
                            class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]"
                        >
                            Save Implementation Requirements
                        </button>
                    </div>
                </form>

                @if($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)

                    <div class="xl:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                        <div class="text-xs font-semibold text-emerald-900">
                            Pre-Implementation Requirements Complete
                        </div>
                        <p class="mt-1 text-xs leading-5 text-emerald-800">
                            The project is now For Implementation. Record the Orientation and Work Period.
                            Once both are complete, the actual date controls the automatic implementation status.
                        </p>
                    </div>

                {{-- Orientation --}}

                <form
                    method="POST"
                    action="{{ route('projects.implementation.orientation', $project) }}"
                    class="rounded-xl border border-slate-200 p-5"
                >

                    @csrf

                    <h3 class="text-sm font-semibold text-slate-900">
                        Orientation
                    </h3>

                    <div class="mt-4">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date of Orientation
                        </label>

                        <input
                            name="orientation_date"
                            type="date"
                            required
                            value="{{ old(
                                'orientation_date',
                                $project->orientation?->orientation_date?->format('Y-m-d')
                            ) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >

                    </div>

                    <div class="mt-4">
                        <div class="mb-2 text-xs font-semibold text-slate-700">
                            Program Coverage for Monthly Reporting
                        </div>
                        <p class="mb-3 text-[11px] leading-4 text-slate-500">
                            Mark the beneficiary programs actually covered during this orientation. Legacy records may remain unspecified.
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                <input
                                    type="checkbox"
                                    name="alkansssya_conducted"
                                    value="1"
                                    @checked(old('alkansssya_conducted', $project->orientation?->alkansssya_conducted))
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#063b86]"
                                >
                                <span>
                                    <span class="block text-xs font-semibold text-slate-800">AlkanSSSya</span>
                                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Included in the recorded TUPAD beneficiary orientation.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                <input
                                    type="checkbox"
                                    name="yakap_conducted"
                                    value="1"
                                    @checked(old('yakap_conducted', $project->orientation?->yakap_conducted))
                                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#063b86]"
                                >
                                <span>
                                    <span class="block text-xs font-semibold text-slate-800">YAKAP Program for TUPAD Beneficiaries</span>
                                    <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Included in the recorded TUPAD beneficiary orientation.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old(
                            'remarks',
                            $project->orientation?->remarks
                        ) }}</textarea>

                    </div>

                    <button
                        type="submit"
                        class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Save Orientation
                    </button>

                </form>

                {{-- Implementation Period --}}

                <form
                    method="POST"
                    action="{{ route('projects.implementation.period', $project) }}"
                    class="rounded-xl border border-slate-200 p-5 xl:col-span-2"
                >

                    @csrf

                    <h3 class="text-sm font-semibold text-slate-900">
                        Implementation Period
                    </h3>

                    <p class="mt-1 text-xs text-slate-500">
                        Enter the planned implementation Start Date and End Date.
                        The approved {{ $project->number_of_days }}-day duration is shown as reference only.
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Start Date
                            </label>

                            <input
                                id="implementation-start-date"
                                name="start_date"
                                type="date"
                                required
                                value="{{ old(
                                    'start_date',
                                    $project->implementation?->start_date?->format('Y-m-d')
                                ) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                            >

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                End Date
                            </label>

                            <input
                                id="implementation-end-date"
                                name="end_date"
                                type="date"
                                required
                                value="{{ old(
                                    'end_date',
                                    $project->implementation?->end_date?->format('Y-m-d')
                                ) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                            >

                            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                Enter the actual planned End Date. It cannot be earlier than the Start Date.
                            </p>

                        </div>

                    </div>

                    <div class="mt-4">

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="2"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old(
                            'remarks',
                            $project->implementation?->remarks
                        ) }}</textarea>

                    </div>

                    <button
                        type="submit"
                        class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Save Implementation Period
                    </button>

                </form>

                @elseif($project->status === \App\Enums\ProjectStatus::APPROVED)

                    <div class="xl:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                        <div class="text-xs font-semibold text-amber-900">
                            Orientation and Work Period are not open yet
                        </div>
                        <p class="mt-1 text-xs leading-5 text-amber-800">
                            Complete Insurance, PPE, and Notice to Proceed first. When all three are complete,
                            the project automatically moves to For Implementation and scheduling becomes available.
                        </p>
                    </div>

                @endif

            </div>

        @endif

    </section>

@elseif(
    in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::APPROVED,
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
            \App\Enums\ProjectStatus::FOR_LIQUIDATION,
            \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true
    )
    && $project->implementation_mode
        === \App\Enums\ImplementationMode::THROUGH_ACP
)

    <section id="implementation" data-workspace-panel="workflow" class="scroll-mt-32 mt-5 rounded-xl border border-violet-200 bg-violet-50 p-5 {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
        <div class="text-sm font-semibold text-violet-950">
            Through ACP Workflow
        </div>
        <p class="mt-1 text-xs leading-5 text-violet-800">
            Through ACP uses its own payment, check-release, implementation, and liquidation workflow. Direct Administration Insurance, PPE, Notice to Proceed, Post-Documentary Requirements, and Payment of Wages forms apply only to Direct Administration projects.
        </p>

        @if(
            (auth()->user()->isAdmin() || auth()->user()->isFocal())
            && in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                ],
                true
            )
        )
            <div class="mt-4">
                <a
                    href="{{ route('acp-payments.show', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
                >
                    Open Through ACP Payment & Check Release
                </a>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            @if(
                (auth()->user()->isAdmin() || auth()->user()->isTc())
                && in_array(
                    $project->status,
                    [
                        \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                        \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                        \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                        \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                        \App\Enums\ProjectStatus::COMPLETED,
                    ],
                    true
                )
            )
                <a
                    href="{{ route('acp-implementation.show', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg border border-violet-300 bg-white px-4 text-sm font-semibold text-violet-800 hover:bg-violet-100"
                >
                    Open ACP Implementation
                </a>
            @endif

            @if(
                (auth()->user()->isAdmin() || auth()->user()->isFocal())
                && in_array(
                    $project->status,
                    [
                        \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                        \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                        \App\Enums\ProjectStatus::COMPLETED,
                    ],
                    true
                )
            )
                <a
                    href="{{ route('acp-liquidations.show', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
                >
                    Open ACP Liquidation
                </a>
            @endif
        </div>
    </section>

@endif

{{-- Authoritative Project Workflow Guide --}}
<section id="final-workflow" data-workspace-panel="workflow" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
    <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
        Authoritative {{ $project->implementation_mode->label() }} Workflow
    </div>

    @php
        $workflowStatuses = app(\App\Services\Projects\ProjectWorkflowDefinition::class)
            ->happyPathFor($project->implementation_mode);
    @endphp

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach($workflowStatuses as $workflowStatus)
            <span
                class="inline-flex items-center rounded-full border px-3 py-1.5 text-[11px] font-semibold {{
                    $project->status === $workflowStatus
                        ? 'border-blue-300 bg-blue-50 text-blue-800'
                        : 'border-slate-200 bg-slate-50 text-slate-600'
                }}"
            >
                {{ $loop->iteration }}. {{ $workflowStatus->label() }}
            </span>
        @endforeach
    </div>

    <p class="mt-3 text-[11px] leading-5 text-slate-500">
        For Compliance remains an optional TSSD evaluation branch before For Approval when deficiencies require corrective submission.
    </p>
</section>

{{-- Post-Documentary Requirements --}}

@if(
    $project->implementation_mode
        === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION
    && in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true
    )
)

    <section id="post-documents" data-workspace-panel="workflow" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Submission of Post-Documentary Requirements
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        TC/Admin records the complete post-implementation documentary submission.
                        Financial processing begins only after the required submission is recorded.
                    </p>
                </div>

                @if($project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS)
                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700">
                        Auto-update → For Payment
                    </span>
                @endif
            </div>
        </div>

        @if(
            $project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS
            && (
                auth()->user()->isAdmin()
                || auth()->user()->isTc()
            )
        )

            <form
                method="POST"
                action="{{ route('projects.post-documents.store', $project) }}"
                enctype="multipart/form-data"
                class="border-b border-slate-200 p-5"
            >
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Received
                        </label>

                        <input
                            type="date"
                            name="date_received"
                            required
                            value="{{ old('date_received', now()->format('Y-m-d')) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Forwarded to IMSD
                        </label>

                        <input
                            type="date"
                            name="date_forwarded_to_imsd"
                            required
                            value="{{ old('date_forwarded_to_imsd') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                        >
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Attachments Received
                        </label>

                        <input
                            type="file"
                            name="attachments[]"
                            multiple
                            required
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                        >

                        <p class="mt-1 text-[11px] text-slate-400">
                            Select one or more files. Maximum 10 MB per attachment.
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea
                            name="remarks"
                            rows="3"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        type="submit"
                        class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Save Post-Documentary Requirements
                    </button>
                </div>
            </form>

        @endif

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date Received
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Document
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Forwarded to IMSD
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Attachment
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($project->postDocuments as $document)

                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->date_received->format('M d, Y') }}
                            </td>

                            <td class="px-5 py-4 text-sm font-medium text-slate-800">
                                {{ $document->document_type }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->date_forwarded_to_imsd?->format('M d, Y')
                                    ?? 'Not yet forwarded' }}
                            </td>

                            <td class="px-5 py-4">

                                @if($document->attachment_path)

                                    <a
                                        href="{{ route(
                                            'projects.post-documents.download',
                                            [
                                                'project' => $project,
                                                'projectPostDocument' => $document,
                                            ]
                                        ) }}"
                                        class="text-sm font-semibold text-blue-700 hover:underline"
                                    >
                                        Download File
                                    </a>

                                @else

                                    <span class="text-sm text-slate-400">
                                        None
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-sm text-slate-400"
                            >
                                No post-documentary requirements recorded.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endif

{{-- Payment of Wages --}}

@if(
    $project->implementation_mode
        === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION
    && in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true
    )
)

    <section id="payment" data-workspace-panel="financial" class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Payment of Wages
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Wage obligations and disbursements are managed by the Focal/Admin account.
            </p>
        </div>

        <div class="p-5">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="text-sm font-semibold text-blue-950">
                    Obligation and Disbursement Processing
                </div>
                <p class="mt-1 text-xs leading-5 text-blue-700">
                    Official project references, totals, payment tranches, and their corresponding disbursements are consolidated in the Payment of Wages interface.
                </p>

                @if(auth()->user()->isAdmin() || auth()->user()->isFocal())
                    <a
                        href="{{ route('payments.show', $project) }}"
                        class="mt-3 inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]"
                    >
                        Manage Payment of Wages
                    </a>
                @else
                    <p class="mt-3 text-xs font-semibold text-blue-800">
                        Waiting for Focal/Admin payment action.
                    </p>
                @endif
            </div>
        </div>
    </section>

@endif
{{-- PPE Requirements --}}

<section id="ppe-requirements" data-workspace-panel="overview" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            PPE Requirements
        </h2>

    </div>

    <div class="overflow-x-auto">

        <table class="tupad-system-table min-w-full">

            <thead class="bg-slate-50">

                <tr>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Type
                    </th>

                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                        Product
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Beneficiaries
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Unit Amount
                    </th>

                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                        Total
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-slate-100">

                @forelse($project->ppeItems as $item)

                    <tr>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $item->ppe_type->label() }}
                        </td>

                        <td class="px-5 py-4 text-sm font-medium text-slate-800">
                            {{ $item->product }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-600">
                            {{ number_format($item->beneficiary_count) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-600">
                            ₱{{ number_format($item->unit_amount, 2) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            ₱{{ number_format($item->total_amount, 2) }}
                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="px-5 py-10 text-center text-sm text-slate-400"
                        >
                            No PPE requirement was recorded.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>

{{-- Project Status History --}}

<section id="history" data-workspace-panel="history" class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'history' ? 'hidden' : '' }}">

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

                @forelse(
                    $project
                        ->statusHistory
                        ->sortByDesc('changed_at')
                    as $history
                )

                    <tr>

                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                            {{ $history->changed_at->format('M d, Y g:i A') }}
                        </td>

                        <td class="px-5 py-4 text-sm text-slate-600">
                            {{ $history->from_status?->label() ?? 'Created' }}
                        </td>

                        <td class="px-5 py-4">

                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
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

                        <td
                            colspan="5"
                            class="px-5 py-10 text-center text-sm text-slate-400"
                        >
                            No status history has been recorded yet.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</section>



</div>

@endsection
