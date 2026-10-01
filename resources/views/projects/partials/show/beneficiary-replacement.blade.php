{{-- Beneficiary Replacement --}}

@if ($canManageProject && !$projectEditingLocked)
    @php
        $activeBeneficiaries = $project->beneficiaries
            ->reject(fn($beneficiary) => $beneficiary->isReplaced())
            ->values();

        $replacedCount = $project->beneficiaries->count() - $activeBeneficiaries->count();

        $currentInsuranceBeneficiaries = $project->insurance_beneficiaries ?? $project->beneficiaries_total;

        $replacementProvinceId =
            $project->province_id ?: $project->projectLocations()->orderBy('sort_order')->value('province_id');
    @endphp

    <section id="beneficiary-replacement" data-workspace-panel="beneficiaries"
        class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-blue-700">
                Beneficiary Replacement
            </div>
            <h2 class="mt-1 text-sm font-semibold text-slate-900">
                Replace Beneficiary
            </h2>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                Use this when a beneficiary quits/withdraws and is replaced by someone else. Original beneficiaries
                are never deleted &mdash; they stay on record as replaced. The number of replacement beneficiaries
                added must match the number being replaced.
            </p>

            <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-600">
                <span><span class="font-bold text-slate-900">{{ $project->beneficiaries->count() }}</span> encoded
                    on roster</span>
                <span><span class="font-bold text-emerald-700">{{ $activeBeneficiaries->count() }}</span>
                    active</span>
                <span><span class="font-bold text-amber-700">{{ $replacedCount }}</span> replaced
                    historically</span>
            </div>
        </div>

        <details id="replaceBeneficiaryDetails">
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
                <span class="text-xs font-semibold text-blue-800">Replace Beneficiary</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand /
                    Collapse</span>
            </summary>

            <form id="replaceBeneficiaryForm" method="POST"
                action="{{ route('projects.beneficiary-replacements.store', $project) }}"
                class="border-t border-slate-200 p-5">
                @csrf

                @if (
                    $errors->has('reason') ||
                        $errors->has('removed_existing') ||
                        $errors->has('added') ||
                        $errors->has('insurance_beneficiaries') ||
                        $errors->has('beneficiary_addresses'))
                    <div
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                        {{ $errors->first('reason') ?: $errors->first('removed_existing') ?: $errors->first('added') ?: $errors->first('insurance_beneficiaries') ?: $errors->first('beneficiary_addresses') }}
                    </div>
                @endif

                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Reason for Replacement <span class="text-rose-600">*</span>
                    </label>
                    <textarea name="reason" required maxlength="2000" rows="2"
                        placeholder="e.g. Beneficiary withdrawal / became unavailable"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-2">

                    {{-- Step A: Beneficiaries Being Replaced --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Step A &middot; Being Replaced
                        </h3>

                        @if ($activeBeneficiaries->isNotEmpty())
                            <div
                                class="mt-3 max-h-48 space-y-1.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
                                @foreach ($activeBeneficiaries as $beneficiary)
                                    <label
                                        class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" name="removed_existing[]"
                                            value="{{ $beneficiary->id }}"
                                            class="replacement-removed-checkbox h-4 w-4 rounded border-slate-300 text-blue-700">
                                        {{ $beneficiary->full_name }}
                                        <span class="text-slate-400">({{ ucfirst($beneficiary->sex) }})</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-2 text-[11px] leading-4 text-slate-400">
                                No beneficiaries have been encoded on this project's roster yet. Record who is
                                leaving below.
                            </p>
                        @endif

                        <div id="removedNewRows" class="mt-3 space-y-2"></div>

                        <button type="button" id="addRemovedNew"
                            class="mt-3 inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                            + Record a Beneficiary Not Yet on the Roster
                        </button>
                    </div>

                    {{-- Step B: Replacement Beneficiaries --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                            Step B &middot; Replacement Beneficiaries
                        </h3>

                        <div id="addedRows" class="mt-3 space-y-2"></div>

                        <button type="button" id="addAddedRow"
                            class="mt-3 inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                            + Add Replacement Beneficiary
                        </button>
                    </div>

                </div>

                <div id="replacementCountStatus"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800">
                    Select/record who is being replaced, then add an equal number of replacement beneficiaries.
                </div>

                {{-- Optional Updated Project Details --}}
                <details class="mt-5 rounded-xl border border-slate-200">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 marker:content-none [&::-webkit-details-marker]:hidden">
                        <span class="text-xs font-semibold text-slate-700">
                            Optional &middot; Updated Project Details
                        </span>
                        <span class="text-[10px] font-medium text-slate-400">
                            Only use if something actually changes because of this replacement
                        </span>
                    </summary>

                    <div class="space-y-5 border-t border-slate-200 p-4">

                        <div>
                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Insurance Beneficiaries <span class="font-normal text-slate-400">(leave unchanged
                                    if not applicable)</span>
                            </label>
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="number" id="replacementInsuranceBeneficiaries"
                                    name="insurance_beneficiaries" min="0"
                                    max="{{ $project->beneficiaries_total }}"
                                    value="{{ old('insurance_beneficiaries', $currentInsuranceBeneficiaries) }}"
                                    class="h-10 w-40 rounded-lg border border-slate-300 px-3 text-sm">
                                <span class="text-[11px] leading-4 text-slate-500">
                                    Current: {{ number_format($currentInsuranceBeneficiaries) }} &middot;
                                    Total Project Cost will become
                                    <span id="replacementCostPreview" class="font-semibold text-slate-800">
                                        ₱{{ number_format($project->total_project_cost, 2) }}
                                    </span>
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                Total Project Cost is recomputed automatically from this value &mdash; it is not
                                typed in directly. Leaving this at its current value records no revision.
                            </p>
                        </div>

                        <div>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" id="replacementUpdateAddress"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-700">
                                Update Beneficiary Address
                            </label>

                            <div id="replacementAddressFields"
                                class="mt-3 hidden rounded-lg border border-slate-200 bg-slate-50 p-3">
                                @if ($replacementProvinceId)
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label
                                                class="mb-1.5 block text-[11px] font-semibold text-slate-600">Municipality
                                                / City</label>
                                            <select id="replacementMunicipality"
                                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                                                <option value="">Select municipality / city</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="replacementBarangayOptions"
                                        class="mt-3 max-h-40 overflow-y-auto rounded-md border border-slate-200 bg-white p-2">
                                        <div class="px-2 py-4 text-center text-[11px] text-slate-400">Select a
                                            municipality/city first.</div>
                                    </div>

                                    <div id="replacementBarangayRows" class="mt-3 space-y-2"></div>

                                    <p class="mt-2 text-[11px] leading-4 text-slate-500">
                                        Barangay Total/Female allocations must sum to this project's declared Total
                                        ({{ number_format($project->beneficiaries_total) }}) and Female
                                        ({{ number_format($project->beneficiaries_female) }}) beneficiaries.
                                        For addresses spanning multiple municipalities, use the Beneficiary Mapping
                                        Source section below instead.
                                    </p>
                                @else
                                    <p class="text-[11px] leading-4 text-amber-700">
                                        This project has no resolvable province, so the address cannot be updated
                                        here. Use the Beneficiary Mapping Source section below once a province is
                                        assigned.
                                    </p>
                                @endif
                            </div>
                        </div>

                    </div>
                </details>

                <div class="mt-5 flex justify-end">
                    <button type="submit"
                        class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        Save Beneficiary Replacement
                    </button>
                </div>

            </form>
        </details>

    </section>

    <script>
        (() => {
            const form = document.getElementById('replaceBeneficiaryForm');
            if (!form) return;

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
                    }
                });
                if (!response.ok) throw new Error('Unable to load geographic reference data.');
                return response.json();
            };

            /*
            |--------------------------------------------------------------------------
            | Removed / Added Person Rows
            |--------------------------------------------------------------------------
            */

            const personRowHtml = (name, index, requirePwdFlags) => `
        <div class="replacement-person-row grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_90px_auto]">
            <input type="text" name="${name}[${index}][first_name]" placeholder="First Name" required
                class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
            <input type="text" name="${name}[${index}][last_name]" placeholder="Last Name" required
                class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
            <select name="${name}[${index}][sex]" required class="h-8 w-full rounded-md border border-slate-300 bg-white px-1 text-xs">
                <option value="">Sex</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
            <button type="button" data-remove-person-row class="inline-flex h-8 items-center justify-center rounded-md border border-red-200 bg-white px-2 text-[10px] font-semibold text-red-600 hover:bg-red-50">
                Remove
            </button>
            ${requirePwdFlags ? `
                                                                <label class="col-span-2 flex items-center gap-1.5 text-[10px] text-slate-600 sm:col-span-4">
                                                                    <input type="checkbox" name="${name}[${index}][is_pwd]" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                                                    PWD
                                                                </label>
                                                                <label class="col-span-2 flex items-center gap-1.5 text-[10px] text-slate-600 sm:col-span-4">
                                                                    <input type="checkbox" name="${name}[${index}][is_rebel_returnee]" value="1" class="h-3.5 w-3.5 rounded border-slate-300">
                                                                    Rebel Returnee
                                                                </label>
                                                            ` : ''}
        </div>
    `;

            const setupRowList = (containerId, buttonId, fieldName, requirePwdFlags) => {
                const container = document.getElementById(containerId);
                const button = document.getElementById(buttonId);
                if (!container || !button) return;

                let index = 0;

                button.addEventListener('click', () => {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = personRowHtml(fieldName, index++, requirePwdFlags);
                    const row = wrapper.firstElementChild;

                    row.querySelector('[data-remove-person-row]').addEventListener('click', () => {
                        row.remove();
                        updateCountStatus();
                    });

                    row.querySelectorAll('input, select').forEach(el => el.addEventListener('input',
                        updateCountStatus));

                    container.appendChild(row);
                    updateCountStatus();
                });
            };

            setupRowList('removedNewRows', 'addRemovedNew', 'removed_new', false);
            setupRowList('addedRows', 'addAddedRow', 'added', true);

            /*
            |--------------------------------------------------------------------------
            | Removed/Added Count Validation
            |--------------------------------------------------------------------------
            */

            const status = document.getElementById('replacementCountStatus');

            function updateCountStatus() {
                if (!status) return true;

                const removedExisting = form.querySelectorAll('.replacement-removed-checkbox:checked').length;
                const removedNew = document.getElementById('removedNewRows').children.length;
                const added = document.getElementById('addedRows').children.length;
                const removed = removedExisting + removedNew;

                if (removed === 0 && added === 0) {
                    status.className =
                        'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                    status.textContent =
                        'Select/record who is being replaced, then add an equal number of replacement beneficiaries.';
                    return false;
                }

                if (removed !== added) {
                    status.className =
                        'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-[11px] font-medium leading-5 text-amber-800';
                    status.textContent =
                        `Being replaced: ${removed}. Replacement beneficiaries added: ${added}. These must match.`;
                    return false;
                }

                status.className =
                    'mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-[11px] font-semibold leading-5 text-emerald-700';
                status.textContent =
                    `Ready: ${removed} beneficiary(ies) will be replaced by ${added} new beneficiary(ies).`;
                return true;
            }

            form.querySelectorAll('.replacement-removed-checkbox').forEach(el => el.addEventListener('change',
                updateCountStatus));
            updateCountStatus();

            form.addEventListener('submit', event => {
                if (!updateCountStatus()) {
                    event.preventDefault();
                    status.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });

            /*
            |--------------------------------------------------------------------------
            | Optional: Insurance Beneficiaries -> Total Project Cost Preview
            |--------------------------------------------------------------------------
            */

            const insuranceInput = document.getElementById('replacementInsuranceBeneficiaries');
            const costPreview = document.getElementById('replacementCostPreview');
            const insuranceRate = Number(@json((float) $project->insurance_rate));
            const wagesTotal = Number(@json((float) $project->wages_total));
            const ppeTotal = Number(@json((float) $project->ppe_total));

            const currency = value => new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(value || 0);

            insuranceInput?.addEventListener('input', () => {
                const count = Number(insuranceInput.value || 0);
                const insuranceTotal = insuranceRate * count;
                costPreview.textContent = currency(wagesTotal + ppeTotal + insuranceTotal);
            });

            /*
            |--------------------------------------------------------------------------
            | Optional: Update Beneficiary Address (single municipality)
            |--------------------------------------------------------------------------
            */

            const addressToggle = document.getElementById('replacementUpdateAddress');
            const addressFields = document.getElementById('replacementAddressFields');
            const municipalitySelect = document.getElementById('replacementMunicipality');
            const barangayOptions = document.getElementById('replacementBarangayOptions');
            const barangayRows = document.getElementById('replacementBarangayRows');
            const provinceId = @json($replacementProvinceId);

            addressToggle?.addEventListener('change', () => {
                addressFields.classList.toggle('hidden', !addressToggle.checked);
            });

            if (municipalitySelect && provinceId) {
                fetchJson(@json(route('locations.municipalities', $replacementProvinceId ?: 0)))
                    .then(municipalities => {
                        municipalities.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.id;
                            option.textContent = item.district ? `${item.name} — ${item.district}` : item
                                .name;
                            municipalitySelect.appendChild(option);
                        });
                    })
                    .catch(() => {
                        barangayOptions.innerHTML =
                            '<div class="px-2 py-4 text-center text-[11px] font-medium text-red-600">Unable to load municipalities.</div>';
                    });

                municipalitySelect.addEventListener('change', async () => {
                    barangayRows.innerHTML = '';

                    if (!municipalitySelect.value) {
                        barangayOptions.innerHTML =
                            '<div class="px-2 py-4 text-center text-[11px] text-slate-400">Select a municipality/city first.</div>';
                        return;
                    }

                    barangayOptions.innerHTML =
                        '<div class="px-2 py-4 text-center text-[11px] text-slate-400">Loading barangays...</div>';

                    try {
                        const barangays = await fetchJson(
                            `/locations/municipalities/${municipalitySelect.value}/barangays`);
                        barangayOptions.innerHTML = '';

                        barangays.forEach(barangay => {
                            const label = document.createElement('label');
                            label.className =
                                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-xs text-slate-700 hover:bg-slate-50';
                            label.innerHTML = `
                        <input type="checkbox" value="${barangay.id}" data-name="${escapeHtml(barangay.name)}" class="replacement-barangay-checkbox h-3.5 w-3.5 rounded border-slate-300 text-blue-700">
                        <span>${escapeHtml(barangay.name)}</span>
                    `;
                            label.querySelector('input').addEventListener('change',
                                renderBarangayRows);
                            barangayOptions.appendChild(label);
                        });
                    } catch (error) {
                        barangayOptions.innerHTML =
                            '<div class="px-2 py-4 text-center text-[11px] font-medium text-red-600">Unable to load barangays.</div>';
                    }
                });

                function renderBarangayRows() {
                    const existing = new Map(
                        Array.from(barangayRows.querySelectorAll('.replacement-barangay-row')).map(row => [
                            row.dataset.barangayId,
                            {
                                total: row.querySelector('.replacement-barangay-total')?.value ?? '',
                                female: row.querySelector('.replacement-barangay-female')?.value ?? '',
                            },
                        ])
                    );

                    const checked = Array.from(barangayOptions.querySelectorAll(
                        '.replacement-barangay-checkbox:checked'));
                    barangayRows.innerHTML = '';

                    checked.forEach((checkbox, index) => {
                        const barangayId = String(checkbox.value);
                        const current = existing.get(barangayId) ?? {
                            total: '',
                            female: ''
                        };

                        const row = document.createElement('div');
                        row.className =
                            'replacement-barangay-row grid grid-cols-[minmax(0,1fr)_90px_90px] items-center gap-2 rounded-md border border-blue-100 bg-blue-50/50 p-2';
                        row.dataset.barangayId = barangayId;
                        row.innerHTML = `
                    <div class="truncate text-[11px] font-semibold text-blue-900">${escapeHtml(checkbox.dataset.name)}</div>
                    <input type="number" min="0" step="1" required placeholder="Total"
                        name="beneficiary_addresses[0][barangays][${index}][beneficiaries_total]"
                        value="${escapeHtml(current.total)}"
                        class="replacement-barangay-total h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                    <input type="number" min="0" step="1" required placeholder="Female"
                        name="beneficiary_addresses[0][barangays][${index}][beneficiaries_female]"
                        value="${escapeHtml(current.female)}"
                        class="replacement-barangay-female h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                    <input type="hidden" name="beneficiary_addresses[0][barangays][${index}][barangay_id]" value="${barangayId}">
                `;
                        barangayRows.appendChild(row);
                    });

                    if (checked.length > 0) {
                        const municipalityInput = document.createElement('input');
                        municipalityInput.type = 'hidden';
                        municipalityInput.name = 'beneficiary_addresses[0][municipality_id]';
                        municipalityInput.value = municipalitySelect.value;
                        barangayRows.appendChild(municipalityInput);
                    }
                }
            }
        })();
    </script>
@endif
