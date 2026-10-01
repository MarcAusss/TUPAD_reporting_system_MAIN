{{-- Beneficiary Classification, Intervention Focus & Labor Market --}}

@php
    $phase7SectorRecords = $project->beneficiarySectors->keyBy(fn($sector) => $sector->sector_key->value);

    $phase7ReferralTotals = [
        'referred' => $project->laborMarketReferrals->sum('interested_referred_total'),
        'provided' => $project->laborMarketReferrals->sum('provided_intervention_total'),
        'amount' => $project->laborMarketReferrals->sum(fn($referral) => (float) $referral->amount_released),
    ];
@endphp

<section id="beneficiary-classification" data-workspace-panel="beneficiaries"
    class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">
                Beneficiary Classification &amp; Labor Market
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Beneficiary addresses drive Beneficiary Mapping, while sector classifications and labor-market
                records remain separate reporting dimensions.
            </p>
        </div>

        @if (auth()->user()->isAdmin() || auth()->user()->isTc())
            <a href="{{ route('projects.classifications.show', $project) }}"
                class="inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">
                Manage Classification
            </a>
        @endif
    </div>

    @php
        $ppeProfilesByBarangay = $project->barangayPpeProfiles->keyBy('barangay_id');
        $ppeItemCountsByBarangay = $project->barangayPpeItemCounts->groupBy('barangay_id');

        $beneficiaryAddressGroups = $project->beneficiaryAddresses
            ->groupBy('municipality_id')
            ->map(function ($addresses, $municipalityId) use ($ppeProfilesByBarangay, $ppeItemCountsByBarangay) {
                return [
                    'municipality_id' => (int) $municipalityId,
                    'barangays' => $addresses
                        ->map(function ($address) use ($ppeProfilesByBarangay, $ppeItemCountsByBarangay) {
                            $profile = $ppeProfilesByBarangay->get($address->barangay_id);
                            $counts = $ppeItemCountsByBarangay->get($address->barangay_id, collect());

                            return [
                                'barangay_id' => (int) $address->barangay_id,
                                'beneficiaries_total' => (int) $address->beneficiaries_total,
                                'beneficiaries_female' => (int) $address->beneficiaries_female,
                                'hazardous_workers' => $profile?->hazardous_workers,
                                'complete_set_workers' => $profile?->complete_set_workers,
                                'ppe_items' => $counts->pluck('recipients', 'project_ppe_item_id')->all(),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $beneficiaryAddressFormData = collect(old('beneficiary_addresses', $beneficiaryAddressGroups))
            ->values()
            ->all();
        $beneficiaryAddressAllocatedTotal = (int) $project->beneficiaryAddresses->sum('beneficiaries_total');
        $beneficiaryAddressAllocatedFemale = (int) $project->beneficiaryAddresses->sum('beneficiaries_female');

        $ppeDistributionItems = $project->ppeItems->map(fn ($item) => [
            'id' => $item->id,
            'product' => $item->product,
            'type' => $item->ppe_type->value,
            'type_label' => $item->ppe_type->label(),
            'beneficiary_count' => (int) $item->beneficiary_count,
            'given_to_everyone' => (int) $item->beneficiary_count === (int) $project->beneficiaries_total,
            'unit_amount' => (float) $item->unit_amount,
        ])->values()->all();

        $ppeDistributionHasHazardous = $project->ppeItems->contains(
            fn ($item) => $item->ppe_type === \App\Enums\PpeType::HAZARDOUS
        );
        $ppeDistributionHazardousCount = $project->ppeItems
            ->where('ppe_type', \App\Enums\PpeType::HAZARDOUS)
            ->count();
    @endphp

    <div class="border-b border-slate-200 bg-slate-50/70 p-5">
        <div class="rounded-xl border border-blue-200 bg-white shadow-sm">
            <div
                class="flex flex-col gap-3 border-b border-blue-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                        Beneficiary Mapping Source
                    </div>
                    <h3 class="mt-1 text-sm font-semibold text-slate-900">
                        Beneficiary Address &amp; Geographic Allocation
                    </h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                        Encode where the project beneficiaries reside. These address allocations are used by
                        Beneficiary Mapping and are kept separate from Project Location Mapping.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:min-w-67.5">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Declared Total
                        </div>
                        <div class="mt-1 text-sm font-bold text-slate-900">
                            {{ number_format($project->beneficiaries_total) }}</div>
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5">
                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Declared Female
                        </div>
                        <div class="mt-1 text-sm font-bold text-slate-900">
                            {{ number_format($project->beneficiaries_female) }}</div>
                    </div>
                </div>
            </div>

            @if (auth()->user()->isAdmin() || auth()->user()->isTc())
                @if ($beneficiaryAddressProvince)
                    <form id="beneficiaryAddressForm" method="POST"
                        action="{{ route('projects.beneficiary-addresses.update', $project) }}" class="p-5">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="province_id" value="{{ $beneficiaryAddressProvince->id }}">

                        @if ($errors->has('province_id') || $errors->has('beneficiary_addresses') || $errors->has('beneficiary_addresses.*'))
                            <div
                                class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                                {{ $errors->first('province_id') ?: $errors->first('beneficiary_addresses') ?: $errors->first('beneficiary_addresses.*') }}
                            </div>
                        @endif

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                            <div>
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">Province</label>
                                    <div
                                        class="flex h-11 items-center rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-800">
                                        {{ $beneficiaryAddressProvince->name }}
                                    </div>
                                    <p class="mt-2 text-[11px] leading-5 text-slate-500">
                                        Province is automatically locked to the project/coordinator assignment. Only
                                        municipalities and barangays inside this province can be saved.
                                    </p>
                                </div>

                                <div
                                    class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h4 class="text-xs font-semibold text-slate-800">Municipalities / Cities
                                            &amp; Barangays</h4>
                                        <p class="mt-1 text-[11px] text-slate-500">Add every beneficiary
                                            municipality/city, select its barangays, then allocate Total and Female
                                            counts.</p>
                                    </div>
                                    <button id="addBeneficiaryAddressLocation" type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-blue-300 bg-white px-3 text-xs font-semibold text-blue-800 hover:bg-blue-50">
                                        + Add Municipality / City
                                    </button>
                                </div>

                                <div id="beneficiaryAddressLocations" class="mt-4 space-y-4"></div>
                            </div>

                            <aside>
                                <div
                                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 lg:sticky lg:top-24">
                                    <div class="text-xs font-semibold text-slate-800">Address Allocation Status
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <div class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200">
                                            <div
                                                class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                                Allocated</div>
                                            <div id="beneficiaryAddressTotal"
                                                class="mt-1 text-sm font-bold text-slate-900">0</div>
                                        </div>
                                        <div class="rounded-lg bg-white px-3 py-2.5 ring-1 ring-slate-200">
                                            <div
                                                class="text-[9px] font-bold uppercase tracking-wide text-slate-400">
                                                Female</div>
                                            <div id="beneficiaryAddressFemale"
                                                class="mt-1 text-sm font-bold text-slate-900">0</div>
                                        </div>
                                    </div>

                                    <div id="beneficiaryAddressValidation"
                                        class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800">
                                        Complete the beneficiary address allocation.
                                    </div>

                                    @if (!empty($ppeDistributionItems))
                                        <div id="beneficiaryAddressPpeProgress" class="mt-3 space-y-1.5"></div>
                                    @endif

                                    <button type="submit"
                                        class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">
                                        Save Beneficiary Addresses
                                    </button>
                                </div>

                                <div class="mt-4 rounded-lg border border-slate-200 bg-white">
                                    <div class="border-b border-slate-200 px-3 py-2.5">
                                        <div class="text-xs font-semibold text-slate-800">Saved Beneficiary Addresses</div>
                                        <div class="mt-0.5 text-[10px] text-slate-500">
                                            Click an entry to jump to it above and edit its Total / Female.
                                        </div>
                                    </div>

                                    <div id="beneficiaryAddressSavedList" class="max-h-80 space-y-1.5 overflow-y-auto p-2">
                                        @forelse ($project->beneficiaryAddresses as $address)
                                            @php
                                                $bcbProfile = $ppeProfilesByBarangay->get($address->barangay_id);
                                            @endphp
                                            <button
                                                type="button"
                                                data-jump-barangay-id="{{ $address->barangay_id }}"
                                                class="beneficiary-address-jump flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left transition hover:bg-blue-50"
                                            >
                                                <span class="min-w-0">
                                                    <span class="block truncate text-xs font-semibold text-slate-800">
                                                        {{ $address->barangay?->name ?? '—' }}
                                                    </span>
                                                    <span class="block truncate text-[10px] text-slate-400">
                                                        {{ $address->municipality?->name ?? '—' }}
                                                    </span>
                                                    @if ($bcbProfile)
                                                        <span class="mt-0.5 inline-flex rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800">
                                                            {{ number_format($bcbProfile->hazardous_workers) }} hazardous
                                                        </span>
                                                    @endif
                                                </span>
                                                <span class="shrink-0 text-right text-[10px] font-semibold text-slate-500">
                                                    {{ number_format($address->beneficiaries_total) }} total
                                                    <br>
                                                    {{ number_format($address->beneficiaries_female) }} female
                                                </span>
                                            </button>
                                        @empty
                                            <div class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">
                                                No beneficiary address allocation has been saved yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <div id="beneficiaryAddressEditModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
                                    <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-xl bg-white shadow-xl">
                                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                                            <div>
                                                <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Same Barangay</div>
                                                <div id="beneficiaryAddressEditModalName" class="mt-0.5 text-sm font-semibold text-slate-900">&mdash;</div>
                                            </div>
                                            <button type="button" id="beneficiaryAddressEditModalClose" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">&times;</button>
                                        </div>

                                        <div class="overflow-y-auto px-5 py-4">
                                            <div id="beneficiaryAddressEditModalWarning" class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-5 text-amber-800"></div>

                                            <div id="beneficiaryAddressEditModalFields">
                                                <div class="grid grid-cols-2 gap-3">
                                                    <label>
                                                        <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Total</span>
                                                        <input type="number" min="0" step="1" id="beneficiaryAddressEditModalTotal" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                                                    </label>
                                                    <label>
                                                        <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Female</span>
                                                        <input type="number" min="0" step="1" id="beneficiaryAddressEditModalFemale" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-xs">
                                                    </label>
                                                </div>

                                                <div id="beneficiaryAddressEditModalPpe" class="mt-4"></div>

                                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">PPE summary</div>
                                                        <div id="beneficiaryAddressEditModalSummary" class="mt-2 space-y-1 text-[11px] text-slate-600">&mdash;</div>
                                                    </div>
                                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                        <div class="text-[9px] font-bold uppercase tracking-wide text-slate-400">Price of overall</div>
                                                        <div id="beneficiaryAddressEditModalOverall" class="mt-2 text-lg font-bold text-slate-900">&#8369;0.00</div>
                                                        <div class="mt-0.5 text-[10px] text-slate-400">Total price of all PPE items for this barangay</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-5 py-4">
                                            <p class="text-[10px] leading-4 text-slate-400">
                                                Saving here updates the fields above. Click "Save Beneficiary Addresses" to persist the change.
                                            </p>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <button type="button" id="beneficiaryAddressEditModalCancel" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 px-4 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                                                <button type="button" id="beneficiaryAddressEditModalSave" class="inline-flex h-9 items-center justify-center rounded-lg bg-[#063b86] px-4 text-[11px] font-semibold text-white hover:bg-[#052f6b]">Save Changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </form>
                @else
                    <div class="p-5">
                        <div
                            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800">
                            Beneficiary address encoding is unavailable because this project has no resolvable
                            assigned province.
                        </div>
                    </div>
                @endif
            @else
                <div class="p-5">
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                        Beneficiary address allocation is read-only for this account.
                    </div>
                </div>
            @endif

            @unless (auth()->user()->isAdmin() || auth()->user()->isTc())
                {{-- Admin/TC already see this same data as the clickable, editable list in the sidebar above. --}}
                <details class="border-t border-slate-200" @if ($project->beneficiaryAddresses->isEmpty()) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                        <div>
                            <div class="text-xs font-semibold text-slate-900">View All Beneficiary Address Data</div>
                            <div class="mt-1 text-[11px] text-slate-500">
                                {{ number_format($project->beneficiaryAddresses->count()) }} barangay record(s) ·
                                {{ number_format($beneficiaryAddressAllocatedTotal) }} total ·
                                {{ number_format($beneficiaryAddressAllocatedFemale) }} female
                            </div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand
                            / Collapse</span>
                    </summary>

                    <div class="overflow-x-auto border-t border-slate-200">
                        <table class="tupad-system-table min-w-190 w-full">
                            <thead class="bg-slate-50">
                                <tr class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                    <th class="px-4 py-3 text-left">Province</th>
                                    <th class="px-4 py-3 text-left">District</th>
                                    <th class="px-4 py-3 text-left">Municipality / City</th>
                                    <th class="px-4 py-3 text-left">Barangay</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                    <th class="px-4 py-3 text-right">Female</th>
                                    <th class="px-4 py-3 text-right">Hazardous</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($project->beneficiaryAddresses as $address)
                                    <tr>
                                        <td class="px-4 py-3 text-xs text-slate-600">
                                            {{ $address->province?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-600">
                                            {{ $address->municipality?->district ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs font-semibold text-slate-800">
                                            {{ $address->municipality?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-700">
                                            {{ $address->barangay?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right text-xs font-semibold text-slate-900">
                                            {{ number_format($address->beneficiaries_total) }}</td>
                                        <td class="px-4 py-3 text-right text-xs text-slate-600">
                                            {{ number_format($address->beneficiaries_female) }}</td>
                                        <td class="px-4 py-3 text-right text-xs text-slate-600">
                                            {{ $ppeProfilesByBarangay->get($address->barangay_id)?->hazardous_workers !== null ? number_format($ppeProfilesByBarangay->get($address->barangay_id)->hazardous_workers) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-0"><x-empty-state size="sm" icon="users" title="No beneficiary address allocation has been encoded yet." action-label="Encode Beneficiary Mapping" action-target="beneficiaryAddressForm" action-tab="beneficiaries" message="Encode the Beneficiary Mapping Source to see where the beneficiaries come from." /></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            @endunless
        </div>
    </div>

    @include('projects.partials.barangay-cost-breakdown')

    @if ((auth()->user()->isAdmin() || auth()->user()->isTc()) && $beneficiaryAddressProvince)
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
                const ppeItems = @json($ppeDistributionItems);
                const ppeHasHazardous = @json($ppeDistributionHasHazardous);
                const ppeHazardousCount = Number(@json($ppeDistributionHazardousCount));
                // Every PPE item is explicitly declared per barangay by the
                // coordinator — none are auto-assumed as "given to everyone".
                const ppeDistributable = ppeItems;
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
                        headers: {
                            'Accept': 'application/json'
                        },
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
                            option.disabled = option.value !== select.value && selected.includes(option
                                .value);
                        });
                    });
                };

                const ppeProgressBox = document.getElementById('beneficiaryAddressPpeProgress');

                const maybeDefaultHazardousWorkers = row => {
                    const hazardousInput = row.querySelector('.beneficiary-hazardous-workers');
                    if (!hazardousInput || hazardousInput.value !== '') return;

                    const maxRecipients = ppeItems
                        .filter(item => item.type === 'hazardous')
                        .reduce((max, item) => {
                            const recipients = Number(row.querySelector(`.beneficiary-ppe-item-input[data-ppe-item-id="${item.id}"]`)?.value || 0);

                            return Math.max(max, recipients);
                        }, 0);

                    hazardousInput.value = maxRecipients;
                };

                const updatePpeProgress = () => {
                    if (ppeItems.length === 0) return true;

                    const rows = Array.from(root.querySelectorAll('.beneficiary-address-row'));
                    let valid = true;
                    const lines = [];

                    ppeDistributable.forEach(item => {
                        const sum = rows.reduce((total, row) => {
                            const input = row.querySelector(`.beneficiary-ppe-item-input[data-ppe-item-id="${item.id}"]`);
                            return total + Number(input?.value || 0);
                        }, 0);

                        const ok = sum === item.beneficiary_count;
                        if (!ok) valid = false;

                        lines.push(`
                            <div class="flex items-center justify-between rounded-md px-2.5 py-1.5 text-[11px] ${ok ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}">
                                <span class="truncate">${escapeHtml(item.product)}</span>
                                <span class="shrink-0 font-semibold">${sum} of ${item.beneficiary_count} distributed</span>
                            </div>
                        `);
                    });

                    rows.forEach(row => {
                        const hazardousInput = row.querySelector('.beneficiary-hazardous-workers');
                        const completeSetInput = row.querySelector('.beneficiary-complete-set-workers');

                        hazardousInput?.classList.remove('border-red-400');
                        completeSetInput?.classList.remove('border-red-400');

                        if (!ppeHasHazardous) return;

                        const barangayTotal = Number(row.querySelector('.beneficiary-address-total')?.value || 0);

                        const hazardousRecipients = ppeItems
                            .filter(item => item.type === 'hazardous')
                            .map(item => Number(row.querySelector(`.beneficiary-ppe-item-input[data-ppe-item-id="${item.id}"]`)?.value || 0));

                        const maxRecipients = hazardousRecipients.length ? Math.max(...hazardousRecipients) : 0;
                        const sumRecipients = hazardousRecipients.reduce((a, b) => a + b, 0);
                        const minRecipients = hazardousRecipients.length ? Math.min(...hazardousRecipients) : 0;
                        const ceiling = Math.min(sumRecipients, barangayTotal);
                        const hazardousValue = Number(hazardousInput?.value || 0);

                        if (hazardousInput && hazardousInput.value !== '' && (hazardousValue < maxRecipients || hazardousValue > ceiling)) {
                            valid = false;
                            hazardousInput.classList.add('border-red-400');
                        }

                        if (completeSetInput && completeSetInput.value !== '') {
                            const completeSetValue = Number(completeSetInput.value);

                            if (completeSetValue > minRecipients || completeSetValue > hazardousValue) {
                                valid = false;
                                completeSetInput.classList.add('border-red-400');
                            }
                        }
                    });

                    if (ppeProgressBox) {
                        ppeProgressBox.innerHTML = lines.join('');
                    }

                    return valid;
                };

                const updateStatus = () => {
                    const ppeValid = updatePpeProgress();
                    const totalInputs = Array.from(root.querySelectorAll('.beneficiary-address-total'));
                    const femaleInputs = Array.from(root.querySelectorAll('.beneficiary-address-female'));

                    const allocatedTotal = totalInputs.reduce((sum, input) => sum + Number(input.value || 0), 0);
                    const allocatedFemale = femaleInputs.reduce((sum, input) => sum + Number(input.value || 0), 0);
                    const incomplete = totalInputs.length === 0 ||
                        totalInputs.some(input => input.value === '') ||
                        femaleInputs.some(input => input.value === '');
                    const femaleExceeds = totalInputs.some((input, index) =>
                        Number(femaleInputs[index]?.value || 0) > Number(input.value || 0)
                    );

                    totalOutput.textContent = allocatedTotal.toLocaleString();
                    femaleOutput.textContent = allocatedFemale.toLocaleString();
                    totalOutput.classList.toggle('text-red-600', allocatedTotal > declaredTotal);
                    femaleOutput.classList.toggle('text-red-600', allocatedFemale > declaredFemale);

                    if (femaleExceeds) {
                        status.className =
                            'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                        status.textContent = 'A barangay Female count cannot exceed that barangay Total count.';
                        return false;
                    }

                    if (incomplete) {
                        status.className =
                            'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                        status.textContent =
                            'Select at least one barangay and complete all Total and Female allocations.';
                        return false;
                    }

                    const exceedsTotal = allocatedTotal > declaredTotal;
                    const exceedsFemale = allocatedFemale > declaredFemale;

                    if (exceedsTotal || exceedsFemale) {
                        status.className =
                            'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
                        status.textContent = exceedsTotal ?
                            `Allocated total of ${allocatedTotal.toLocaleString()} exceeds the project's declared Total Beneficiaries of ${declaredTotal.toLocaleString()} by ${(allocatedTotal - declaredTotal).toLocaleString()}.` :
                            `Allocated female count of ${allocatedFemale.toLocaleString()} exceeds the project's declared Female Beneficiaries of ${declaredFemale.toLocaleString()} by ${(allocatedFemale - declaredFemale).toLocaleString()}.`;
                        return false;
                    }

                    if (allocatedTotal !== declaredTotal || allocatedFemale !== declaredFemale) {
                        status.className =
                            'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                        status.textContent =
                            `Allocated ${allocatedTotal.toLocaleString()} of ${declaredTotal.toLocaleString()} total and ${allocatedFemale.toLocaleString()} of ${declaredFemale.toLocaleString()} female beneficiaries.`;
                        return false;
                    }

                    if (!ppeValid) {
                        status.className =
                            'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                        status.textContent =
                            'Complete the PPE distribution below: every item must be fully distributed, and hazardous/complete-set counts must fit within range.';
                        return false;
                    }

                    status.className =
                        'mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-[11px] font-semibold leading-5 text-emerald-700';
                    status.textContent = 'Beneficiary address allocation is complete and ready to save.';
                    return true;
                };

                const ppeRowMarkup = (groupIndex, barangayIndex, values) => {
                    if (ppeItems.length === 0) return '';

                    const items = values.ppe_items || {};

                    const hazardousField = ppeHasHazardous
                        ? `
                        <label>
                            <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Hazardous workers</span>
                            <input type="number" min="0" step="1" required name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][hazardous_workers]" value="${escapeHtml(values.hazardous_workers ?? '')}" class="beneficiary-hazardous-workers h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                        </label>`
                        : `<input type="hidden" name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][hazardous_workers]" value="0">`;

                    const completeSetField = ppeHazardousCount >= 2
                        ? `
                        <label>
                            <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Complete set <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                            <input type="number" min="0" step="1" name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][complete_set_workers]" value="${escapeHtml(values.complete_set_workers ?? '')}" class="beneficiary-complete-set-workers h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                        </label>`
                        : '';

                    const itemFields = ppeDistributable.map(item => `
                        <div class="rounded-lg border border-slate-200 bg-white p-2">
                            <div class="mb-1.5 flex items-start justify-between gap-1">
                                <span class="truncate text-[9px] font-bold uppercase tracking-wide text-slate-500" title="${escapeHtml(item.product)} (${escapeHtml(item.type_label)})">${escapeHtml(item.product)}</span>
                                <span class="shrink-0 text-[9px] font-bold text-emerald-700">₱${Number(item.unit_amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                            <input type="number" min="0" step="1" required data-ppe-item-id="${item.id}" name="beneficiary_addresses[${groupIndex}][barangays][${barangayIndex}][ppe_items][${item.id}]" value="${escapeHtml(items[item.id] ?? '')}" class="beneficiary-ppe-item-input h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                        </div>`).join('');

                    return `
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <div class="mb-2 text-[9px] font-bold uppercase tracking-wide text-slate-400">PPE distribution for this barangay</div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        ${hazardousField}
                        ${completeSetField}
                        ${itemFields}
                    </div>
                </div>`;
                };

                // Unnamed mirror of ppeRowMarkup for the edit modal: same box layout,
                // but without `name` attributes so it never gets submitted alongside
                // the real row inputs it writes back into on Save.
                const modalPpeMarkup = (values) => {
                    if (ppeItems.length === 0) return '';

                    const items = values.ppe_items || {};

                    const hazardousField = ppeHasHazardous ? `
                        <label>
                            <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Hazardous workers</span>
                            <input type="number" min="0" step="1" value="${escapeHtml(values.hazardous_workers ?? '')}" class="modal-hazardous-workers h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                        </label>` : '';

                    const completeSetField = ppeHazardousCount >= 2 ? `
                        <label>
                            <span class="mb-1 block text-[9px] font-bold uppercase tracking-wide text-slate-400">Complete set <span class="font-normal normal-case text-slate-400">(optional)</span></span>
                            <input type="number" min="0" step="1" value="${escapeHtml(values.complete_set_workers ?? '')}" class="modal-complete-set-workers h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                        </label>` : '';

                    const itemBoxes = ppeDistributable.map(item => `
                        <div class="rounded-lg border border-slate-200 bg-white p-2">
                            <div class="mb-1.5 flex items-start justify-between gap-1">
                                <span class="truncate text-[9px] font-bold uppercase tracking-wide text-slate-500" title="${escapeHtml(item.product)} (${escapeHtml(item.type_label)})">${escapeHtml(item.product)}</span>
                                <span class="shrink-0 text-[9px] font-bold text-emerald-700">₱${Number(item.unit_amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                            <input type="number" min="0" step="1" data-modal-ppe-item-id="${item.id}" value="${escapeHtml(items[item.id] ?? '')}" class="modal-ppe-item-input h-9 w-full rounded-md border border-slate-300 px-2 text-xs">
                        </div>`).join('');

                    return `
                <div class="mb-2 text-[9px] font-bold uppercase tracking-wide text-slate-400">PPE distribution for this barangay</div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    ${hazardousField}
                    ${completeSetField}
                    ${itemBoxes}
                </div>`;
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
                                hazardous_workers: row.querySelector('.beneficiary-hazardous-workers')?.value ?? '',
                                complete_set_workers: row.querySelector('.beneficiary-complete-set-workers')?.value ?? '',
                                ppe_items: Object.fromEntries(
                                    Array.from(row.querySelectorAll('.beneficiary-ppe-item-input'))
                                        .map(input => [input.dataset.ppeItemId, input.value])
                                ),
                            },
                        ])
                    );

                    selectedBox.innerHTML = '';

                    if (checked.length === 0) {
                        selectedBox.innerHTML =
                            '<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>';
                        updateStatus();
                        return;
                    }

                    checked.forEach((checkbox, barangayIndex) => {
                        const barangayId = checkbox.value;
                        let ppeItemsData = {};
                        try {
                            ppeItemsData = JSON.parse(checkbox.dataset.ppeItems || '{}');
                        } catch (error) {
                            ppeItemsData = {};
                        }

                        const values = existing.get(barangayId) || {
                            total: checkbox.dataset.total ?? '',
                            female: checkbox.dataset.female ?? '',
                            hazardous_workers: checkbox.dataset.hazardous ?? '',
                            complete_set_workers: checkbox.dataset.completeSet ?? '',
                            ppe_items: ppeItemsData,
                        };

                        // Single-barangay convenience: every distributable item's
                        // recipients can only be its full beneficiary_count, so
                        // pre-fill it instead of making the coordinator retype it.
                        if (checked.length === 1 && ppeDistributable.length > 0) {
                            ppeDistributable.forEach(item => {
                                if (values.ppe_items[item.id] === undefined || values.ppe_items[item.id] === '') {
                                    values.ppe_items[item.id] = item.beneficiary_count;
                                }
                            });
                        }

                        const row = document.createElement('div');
                        row.className =
                            'beneficiary-address-row rounded-lg border border-slate-200 bg-white p-3';
                        row.dataset.barangayId = barangayId;
                        row.innerHTML = `
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_110px_110px]">
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
                    </div>
                    ${ppeRowMarkup(groupIndex, barangayIndex, values)}
                `;

                        row.querySelectorAll('input[type="number"]').forEach(input => {
                            input.addEventListener('input', () => {
                                maybeDefaultHazardousWorkers(row);
                                updateStatus();
                            });
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
                        optionsBox.innerHTML =
                            '<div class="px-3 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>';
                        card.querySelector('.beneficiary-selected-barangays').innerHTML =
                            '<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-center text-[11px] text-slate-400">No barangay selected.</div>';
                        updateStatus();
                        return;
                    }

                    optionsBox.innerHTML =
                        '<div class="px-3 py-4 text-center text-[11px] text-slate-400">Loading barangays...</div>';

                    try {
                        const barangays = await fetchJson(`/locations/municipalities/${municipalityId}/barangays`);
                        optionsBox.innerHTML = '';

                        barangays.forEach(barangay => {
                            const existing = selectedMap.get(String(barangay.id));
                            const label = document.createElement('label');
                            label.className =
                                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 text-xs text-slate-700 hover:bg-slate-50';
                            label.innerHTML = `
                        <input type="checkbox" value="${barangay.id}" data-name="${escapeHtml(barangay.name)}" data-total="${escapeHtml(existing?.beneficiaries_total ?? '')}" data-female="${escapeHtml(existing?.beneficiaries_female ?? '')}" data-hazardous="${escapeHtml(existing?.hazardous_workers ?? '')}" data-complete-set="${escapeHtml(existing?.complete_set_workers ?? '')}" data-ppe-items="${escapeHtml(JSON.stringify(existing?.ppe_items ?? {}))}" class="beneficiary-barangay-checkbox h-4 w-4 rounded border-slate-300 text-blue-700" ${existing ? 'checked' : ''}>
                        <span>${escapeHtml(barangay.name)}</span>
                    `;
                            label.querySelector('input').addEventListener('change', () =>
                                renderSelectedBarangays(card, groupIndex));
                            optionsBox.appendChild(label);
                        });

                        renderSelectedBarangays(card, groupIndex);
                    } catch (error) {
                        optionsBox.innerHTML =
                            '<div class="px-3 py-4 text-center text-[11px] font-medium text-red-600">Unable to load barangays. Refresh the page and try again.</div>';
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
                        status.className =
                            'mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-[11px] font-medium leading-5 text-red-700';
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

                const savedList = document.getElementById('beneficiaryAddressSavedList');

                const editModal = document.getElementById('beneficiaryAddressEditModal');
                // Re-parent to <body> so position:fixed always covers the full
                // viewport — an ancestor card/tab wrapper further up this page
                // establishes its own containing block and otherwise clips the
                // backdrop to that wrapper instead of the whole screen.
                if (editModal) document.body.appendChild(editModal);
                const editModalName = document.getElementById('beneficiaryAddressEditModalName');
                const editModalWarning = document.getElementById('beneficiaryAddressEditModalWarning');
                const editModalFields = document.getElementById('beneficiaryAddressEditModalFields');
                const editModalTotal = document.getElementById('beneficiaryAddressEditModalTotal');
                const editModalFemale = document.getElementById('beneficiaryAddressEditModalFemale');
                const editModalPpe = document.getElementById('beneficiaryAddressEditModalPpe');
                const editModalSummary = document.getElementById('beneficiaryAddressEditModalSummary');
                const editModalOverall = document.getElementById('beneficiaryAddressEditModalOverall');
                const editModalSave = document.getElementById('beneficiaryAddressEditModalSave');
                let editModalRow = null;

                const formatModalMoney = value => '₱' + Number(value || 0).toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

                const recomputeModalSummary = () => {
                    if (!editModalSummary || !editModalOverall) return;

                    let overall = 0;
                    const lines = [];

                    editModalPpe.querySelectorAll('.modal-ppe-item-input').forEach(input => {
                        const item = ppeDistributable.find(candidate => String(candidate.id) === input.dataset.modalPpeItemId);
                        if (!item) return;

                        const qty = Number(input.value) || 0;
                        const amount = qty * Number(item.unit_amount || 0);
                        overall += amount;

                        if (qty > 0) {
                            lines.push(`${escapeHtml(item.product)} &times; ${qty} = ${formatModalMoney(amount)}`);
                        }
                    });

                    editModalSummary.innerHTML = lines.length ?
                        lines.map(line => `<div>${line}</div>`).join('') :
                        'No PPE recipients entered yet.';
                    editModalOverall.textContent = formatModalMoney(overall);
                };

                const closeEditModal = () => {
                    editModal?.classList.add('hidden');
                    editModal?.classList.remove('flex');
                    editModalRow = null;
                };

                const openEditModal = (name, row) => {
                    editModalRow = row;
                    editModalName.textContent = name;

                    if (!row) {
                        editModalWarning.textContent =
                            'That barangay is not currently selected above. Re-select it in the form to edit its allocation.';
                        editModalWarning.classList.remove('hidden');
                        editModalFields.classList.add('hidden');
                        editModalSave.classList.add('hidden');
                    } else {
                        editModalWarning.classList.add('hidden');
                        editModalFields.classList.remove('hidden');
                        editModalSave.classList.remove('hidden');

                        editModalTotal.value = row.querySelector('.beneficiary-address-total')?.value ?? '';
                        editModalFemale.value = row.querySelector('.beneficiary-address-female')?.value ?? '';

                        editModalPpe.innerHTML = modalPpeMarkup({
                            hazardous_workers: row.querySelector('.beneficiary-hazardous-workers')?.value ?? '',
                            complete_set_workers: row.querySelector('.beneficiary-complete-set-workers')?.value ?? '',
                            ppe_items: Object.fromEntries(
                                Array.from(row.querySelectorAll('.beneficiary-ppe-item-input'))
                                .map(input => [input.dataset.ppeItemId, input.value])
                            ),
                        });

                        recomputeModalSummary();
                    }

                    editModal?.classList.remove('hidden');
                    editModal?.classList.add('flex');
                };

                savedList?.addEventListener('click', event => {
                    const trigger = event.target.closest('.beneficiary-address-jump');
                    if (!trigger) return;

                    const barangayId = trigger.dataset.jumpBarangayId;
                    const row = root.querySelector(`.beneficiary-address-row[data-barangay-id="${barangayId}"]`);
                    const name = trigger.querySelector('.block.truncate')?.textContent?.trim() || 'This barangay';

                    openEditModal(name, row);
                });

                document.getElementById('beneficiaryAddressEditModalClose')?.addEventListener('click', closeEditModal);
                document.getElementById('beneficiaryAddressEditModalCancel')?.addEventListener('click', closeEditModal);
                editModal?.addEventListener('click', event => {
                    if (event.target === editModal) closeEditModal();
                });
                editModalFields?.addEventListener('input', recomputeModalSummary);

                editModalSave?.addEventListener('click', () => {
                    if (!editModalRow) return;

                    const totalInput = editModalRow.querySelector('.beneficiary-address-total');
                    const femaleInput = editModalRow.querySelector('.beneficiary-address-female');
                    const hazardousInput = editModalRow.querySelector('.beneficiary-hazardous-workers');
                    const completeSetInput = editModalRow.querySelector('.beneficiary-complete-set-workers');
                    const modalHazardous = editModalPpe.querySelector('.modal-hazardous-workers');
                    const modalCompleteSet = editModalPpe.querySelector('.modal-complete-set-workers');

                    const writeBack = (input, value) => {
                        if (!input) return;
                        input.value = value;
                        input.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                    };

                    writeBack(totalInput, editModalTotal.value);
                    writeBack(femaleInput, editModalFemale.value);
                    writeBack(hazardousInput, modalHazardous?.value ?? '');
                    writeBack(completeSetInput, modalCompleteSet?.value ?? '');

                    editModalPpe.querySelectorAll('.modal-ppe-item-input').forEach(modalInput => {
                        const realInput = editModalRow.querySelector(
                            `.beneficiary-ppe-item-input[data-ppe-item-id="${modalInput.dataset.modalPpeItemId}"]`
                        );
                        writeBack(realInput, modalInput.value);
                    });

                    const changedRow = editModalRow;
                    closeEditModal();

                    changedRow.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    changedRow.classList.add('ring-2', 'ring-blue-400');
                    setTimeout(() => changedRow.classList.remove('ring-2', 'ring-blue-400'), 1500);
                });

                form.addEventListener('submit', event => {
                    if (!updateStatus()) {
                        event.preventDefault();
                        status.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
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
        @foreach ([
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
                        @foreach ($categories as $category)
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
