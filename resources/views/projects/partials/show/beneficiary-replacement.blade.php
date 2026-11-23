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
            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-blue-700">
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

        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-slate-600">
                A short guided form: reason, who is leaving, who replaces them, then review.
            </p>
            <button type="button" data-wizard-open="replaceBeneficiaryDialog"
                class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d] focus:outline-none focus:ring-2 focus:ring-blue-300">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 3h5v5"/><path d="M21 3 14 10"/><path d="M8 21H3v-5"/><path d="m3 21 7-7"/></svg>
                Replace Beneficiary
            </button>
        </div>

    </section>

    @php
        $replacementErrorKeys = collect($errors->keys())
            ->filter(fn ($key) => \Illuminate\Support\Str::startsWith($key, ['reason', 'removed_existing', 'removed_new', 'added', 'insurance_beneficiaries', 'beneficiary_addresses']))
            ->values();
    @endphp

    {{-- Modal wizard (resources/js/form-wizard.js). Kept outside the tab panel so it can open on any tab. --}}
    <dialog id="replaceBeneficiaryDialog" data-wizard-dialog
        @if ($replacementErrorKeys->isNotEmpty()) data-wizard-open-on-load @endif
        aria-labelledby="replaceBeneficiaryDialogTitle"
        class="tupad-dialog tupad-wizard-dialog w-[calc(100%-1.5rem)] max-w-3xl rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl">

            <form id="replaceBeneficiaryForm" method="POST"
                action="{{ route('projects.beneficiary-replacements.store', $project) }}"
                data-wizard data-wizard-errors='@json($replacementErrorKeys)'>
                @csrf

                <div class="shrink-0 border-b border-slate-200 px-5 pt-4 pb-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-blue-700">
                                Beneficiary Replacement
                            </div>
                            <h2 id="replaceBeneficiaryDialogTitle" class="mt-0.5 text-base font-bold text-slate-900">
                                Replace Beneficiary
                            </h2>
                            <p data-wizard-progress class="mt-0.5 text-xs text-slate-500"></p>
                        </div>
                        <button type="button" data-wizard-close aria-label="Close"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>
                    <div data-wizard-stepper class="mt-3"></div>
                </div>

                <div data-wizard-scroll class="min-h-0 flex-1 overflow-y-auto px-5 py-5">

                @if ($replacementErrorKeys->isNotEmpty())
                    <div role="alert"
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                        {{ $errors->first('reason') ?: $errors->first('removed_existing') ?: $errors->first('added') ?: $errors->first('insurance_beneficiaries') ?: $errors->first('beneficiary_addresses') ?: $errors->first($replacementErrorKeys->first()) }}
                    </div>
                @endif

                {{-- Step 1: Reason --}}
                <div data-wizard-step data-wizard-title="Reason">
                    <h3 class="text-sm font-semibold text-slate-900">Why is this beneficiary being replaced?</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Original beneficiaries are never deleted. They stay on record as replaced.
                    </p>
                    <div class="mt-4">
                        <label for="replacementReason" class="mb-2 block text-sm font-semibold text-slate-700">
                            Reason for Replacement
                        </label>
                        <textarea id="replacementReason" name="reason" required maxlength="2000" rows="3"
                            placeholder="e.g. Beneficiary withdrawal / became unavailable"
                            data-wizard-summary="Reason"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                    </div>
                </div>

                {{-- Step 2: Beneficiaries Being Replaced --}}
                <div data-wizard-step data-wizard-title="Being Replaced">
                    <h3 class="text-sm font-semibold text-slate-900">Who is leaving the project?</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Tick everyone being replaced. If someone is not on the roster yet, record them below.
                    </p>

                        @if ($activeBeneficiaries->isNotEmpty())
                            <fieldset class="mt-4">
                                <legend class="sr-only">Active beneficiaries on the roster</legend>
                                <div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
                                @foreach ($activeBeneficiaries as $beneficiary)
                                    <label
                                        class="flex min-h-10 cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                        <input type="checkbox" name="removed_existing[]"
                                            value="{{ $beneficiary->id }}"
                                            @checked(in_array((string) $beneficiary->id, array_map('strval', (array) old('removed_existing', [])), true))
                                            class="replacement-removed-checkbox h-4 w-4 rounded border-slate-300 text-blue-700">
                                        {{ $beneficiary->full_name }}
                                        <span class="text-xs text-slate-500">({{ ucfirst($beneficiary->sex) }})</span>
                                    </label>
                                @endforeach
                                </div>
                            </fieldset>
                        @else
                            <p class="mt-4 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                                No beneficiaries have been encoded on this project's roster yet. Record who is
                                leaving below.
                            </p>
                        @endif

                        <div id="removedNewRows" class="mt-3 space-y-2"></div>

                        <button type="button" id="addRemovedNew"
                            class="mt-3 inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            + Record a Beneficiary Not Yet on the Roster
                        </button>

                        <p id="replacementRemovedSummary" data-wizard-summary="Being replaced" class="mt-4 text-xs font-semibold text-slate-700" aria-live="polite">
                            0 selected
                        </p>
                </div>

                {{-- Step 3: Replacement Beneficiaries --}}
                <div data-wizard-step data-wizard-title="Replacements">
                    <h3 class="text-sm font-semibold text-slate-900">Who replaces them?</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Add the same number of replacement beneficiaries as the number being replaced.
                    </p>

                        <div id="addedRows" class="mt-4 space-y-2"></div>

                        <button type="button" id="addAddedRow"
                            class="mt-3 inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                            + Add Replacement Beneficiary
                        </button>

                <div id="replacementCountStatus" data-wizard-summary="Status" aria-live="polite"
                    class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-xs font-medium leading-5 text-amber-800">
                    Select/record who is being replaced, then add an equal number of replacement beneficiaries.
                </div>
                </div>

                {{-- Step 4: Optional updates + review --}}
                <div data-wizard-step data-wizard-title="Review & Save">
                    <h3 class="text-sm font-semibold text-slate-900">Review and save</h3>

                    <div data-wizard-review class="mt-4 space-y-3"></div>

                {{-- Optional Updated Project Details --}}
                <details class="mt-5 rounded-xl border border-slate-200" @if ($errors->has('insurance_beneficiaries') || $errors->has('beneficiary_addresses')) open @endif>
                    <summary
                        class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3 marker:content-none [&::-webkit-details-marker]:hidden">
                        <span class="text-sm font-semibold text-slate-700">
                            Optional &middot; Updated Project Details
                        </span>
                        <span class="text-xs font-medium text-slate-500">
                            Only use if something actually changes because of this replacement
                        </span>
                    </summary>

                    <div class="space-y-5 border-t border-slate-200 p-4">

                        <div>
                            <label for="replacementInsuranceBeneficiaries" class="mb-2 block text-xs font-semibold text-slate-700">
                                Insurance Beneficiaries <span class="font-normal text-slate-500">(leave unchanged
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
                                            <label for="replacementMunicipality"
                                                class="mb-1.5 block text-xs font-semibold text-slate-600">Municipality
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
                </div>

                </div>

                <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/80 px-5 py-3">
                    <button type="button" data-wizard-close
                        class="inline-flex h-10 items-center rounded-lg px-3 text-sm font-semibold text-slate-600 hover:bg-slate-200/60">
                        Cancel
                    </button>
                    <div class="flex flex-1 justify-end gap-2">
                        <button type="button" data-wizard-prev hidden
                            class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            Back
                        </button>
                        <button type="button" data-wizard-next hidden
                            class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                            <span data-wizard-next-label>Next</span>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                        <button type="submit" data-wizard-submit
                            class="inline-flex h-10 items-center rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800">
                            Save Beneficiary Replacement
                        </button>
                    </div>
                </div>

            </form>
    </dialog>

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

            const personRowHtml = (name, index, requirePwdFlags) => {
                const who = name === 'added' ? 'Replacement' : 'Leaving';
                const rowNumber = index + 1;
                const fieldId = (field) => `${name}_${index}_${field}`;

                return `
        <div class="replacement-person-row grid gap-2 rounded-lg border border-slate-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_110px_auto] sm:items-end">
            <div>
                <label for="${fieldId('first')}" class="mb-1 block text-xs font-semibold text-slate-600">First name</label>
                <input id="${fieldId('first')}" type="text" name="${name}[${index}][first_name]" required
                    aria-label="${who} ${rowNumber} first name"
                    class="h-10 w-full rounded-md border border-slate-300 px-2.5 text-sm">
            </div>
            <div>
                <label for="${fieldId('last')}" class="mb-1 block text-xs font-semibold text-slate-600">Last name</label>
                <input id="${fieldId('last')}" type="text" name="${name}[${index}][last_name]" required
                    aria-label="${who} ${rowNumber} last name"
                    class="h-10 w-full rounded-md border border-slate-300 px-2.5 text-sm">
            </div>
            <div>
                <label for="${fieldId('sex')}" class="mb-1 block text-xs font-semibold text-slate-600">Sex</label>
                <select id="${fieldId('sex')}" name="${name}[${index}][sex]" required
                    aria-label="${who} ${rowNumber} sex"
                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-2 text-sm">
                    <option value="">Select</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            <button type="button" data-remove-person-row aria-label="Remove ${who.toLowerCase()} ${rowNumber}"
                class="inline-flex h-10 items-center justify-center rounded-md border border-red-200 bg-white px-3 text-xs font-semibold text-red-600 hover:bg-red-50">
                Remove
            </button>
            ${requirePwdFlags ? `
            <div class="flex flex-wrap gap-x-5 gap-y-2 sm:col-span-4">
                <label class="flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" name="${name}[${index}][is_pwd]" value="1" class="h-4 w-4 rounded border-slate-300">
                    PWD
                </label>
                <label class="flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" name="${name}[${index}][is_rebel_returnee]" value="1" class="h-4 w-4 rounded border-slate-300">
                    Rebel Returnee
                </label>
            </div>
            ` : ''}
        </div>
    `;
            };

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
                    row.querySelector('input')?.focus();
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
            const removedSummary = document.getElementById('replacementRemovedSummary');
            const warnClass =
                'mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-xs font-medium leading-5 text-amber-800';
            const errorClass =
                'mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-3 text-xs font-semibold leading-5 text-red-700';

            const counts = () => {
                const removed = form.querySelectorAll('.replacement-removed-checkbox:checked').length +
                    document.getElementById('removedNewRows').children.length;
                const added = document.getElementById('addedRows').children.length;
                return { removed, added };
            };

            const plural = (count, word) => `${count} ${count === 1 ? word
                : (word.endsWith('y') ? `${word.slice(0, -1)}ies` : `${word}s`)}`;

            function updateCountStatus() {
                const { removed, added } = counts();

                if (removedSummary) {
                    removedSummary.textContent = removed ? `${plural(removed, 'beneficiary')} selected` : 'None selected yet';
                    removedSummary.className = 'mt-4 text-xs font-semibold text-slate-700';
                }

                if (!status) return removed > 0 && removed === added;

                if (removed === 0 && added === 0) {
                    status.className = warnClass;
                    status.textContent =
                        'Select/record who is being replaced, then add an equal number of replacement beneficiaries.';
                    return false;
                }

                if (removed !== added) {
                    status.className = warnClass;
                    const diff = removed - added;
                    status.textContent = diff > 0
                        ? `${plural(removed, 'beneficiary')} being replaced, ${added} added. Add ${diff} more replacement${diff === 1 ? '' : 's'}.`
                        : `${plural(removed, 'beneficiary')} being replaced, ${added} added. Remove ${-diff} replacement${diff === -1 ? '' : 's'}, or go back and select more.`;
                    return false;
                }

                status.className =
                    'mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3 text-xs font-semibold leading-5 text-emerald-700';
                status.textContent =
                    `Ready: ${plural(removed, 'beneficiary')} will be replaced by ${plural(added, 'new beneficiary')}.`;
                return true;
            }

            form.querySelectorAll('.replacement-removed-checkbox').forEach(el => el.addEventListener('change',
                updateCountStatus));
            updateCountStatus();

            // Wizard checks (resources/js/form-wizard.js): step 2 needs at least one
            // person leaving; step 3 needs the same number of replacements.
            const wizardSteps = form.querySelectorAll('[data-wizard-step]');

            wizardSteps[1]?.addEventListener('wizard:validate', event => {
                if (counts().removed > 0) return;
                event.preventDefault();
                if (removedSummary) {
                    removedSummary.textContent = 'Select or record at least one beneficiary who is being replaced.';
                    removedSummary.className = 'mt-4 text-xs font-semibold text-red-700';
                }
                (form.querySelector('.replacement-removed-checkbox') ?? document.getElementById('addRemovedNew'))?.focus();
            });

            wizardSteps[2]?.addEventListener('wizard:validate', event => {
                if (updateCountStatus()) return;
                event.preventDefault();
                status.className = errorClass;
                status.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (counts().added < counts().removed) document.getElementById('addAddedRow')?.focus();
            });

            form.addEventListener('submit', event => {
                if (!updateCountStatus()) {
                    event.preventDefault();
                    form.tupadWizard?.reveal(status);
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
