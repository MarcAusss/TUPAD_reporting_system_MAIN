{{-- Insurance Claim (Incident Report) --}}

@if ($canRecordInsuranceClaim)
    <section id="insurance-claim" data-workspace-panel="beneficiaries"
        class="mt-5 overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-red-700">
                Insurance Claim
            </div>
            <h2 class="mt-1 text-sm font-semibold text-slate-900">
                Record Insurance Claim
            </h2>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                Use this when one or more beneficiaries were injured during project implementation. Record
                what happened, when, the approximate time, and the full name and address of every injured
                beneficiary. Once saved, a claim record cannot be edited or removed &mdash; it stays on file
                as reported.
            </p>
        </div>

        <details id="insuranceClaimDetails">
            <summary
                class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 marker:content-none [&::-webkit-details-marker]:hidden">
                <span class="text-xs font-semibold text-red-800">Record Insurance Claim</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">Expand /
                    Collapse</span>
            </summary>

            <form id="insuranceClaimForm" method="POST"
                action="{{ route('projects.insurance-claims.store', $project) }}"
                class="border-t border-slate-200 p-5">
                @csrf

                @if (
                    $errors->has('incident_description') ||
                        $errors->has('incident_date') ||
                        $errors->has('incident_time') ||
                        $errors->has('beneficiaries'))
                    <div
                        class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-medium leading-5 text-red-700">
                        {{ $errors->first('incident_description') ?: $errors->first('incident_date') ?: $errors->first('incident_time') ?: $errors->first('beneficiaries') }}
                    </div>
                @endif

                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        What Happened <span class="text-rose-600">*</span>
                    </label>
                    <textarea name="incident_description" required maxlength="2000" rows="3"
                        placeholder="Describe the incident that led to the injury/injuries"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('incident_description') }}</textarea>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date of Incident <span class="text-rose-600">*</span>
                        </label>
                        <input name="incident_date" type="date" required max="{{ now()->format('Y-m-d') }}"
                            value="{{ old('incident_date') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Approximate Time <span class="font-normal text-slate-400">(optional)</span>
                        </label>
                        <input name="incident_time" type="time" value="{{ old('incident_time') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Injured Beneficiary(ies)
                    </h3>
                    <p class="mt-1 text-[11px] leading-4 text-slate-500">
                        Select from the project roster to auto-fill the name, or leave the dropdown on
                        "Select from Roster (Optional)" to enter someone not yet encoded on the roster.
                    </p>

                    <div id="insuranceClaimRows" class="mt-3 space-y-2"></div>

                    <button type="button" id="addInsuranceClaimRow"
                        class="mt-3 inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-3 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                        + Add Injured Beneficiary
                    </button>
                </div>

                <div class="mt-5 flex justify-end">
                    <button type="submit"
                        class="h-10 rounded-lg bg-red-700 px-5 text-sm font-semibold text-white hover:bg-red-800">
                        Save Insurance Claim
                    </button>
                </div>

            </form>
        </details>

    </section>

    <script>
        (() => {
            const form = document.getElementById('insuranceClaimForm');
            if (!form) return;

            const roster = @json(
                $project->beneficiaries->map(fn($b) => [
                            'id' => $b->id,
                            'name' => $b->full_name,
                        ])->values());

            const escapeHtml = value => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const rows = document.getElementById('insuranceClaimRows');
            let rowIndex = 0;

            const rosterOptionsHtml = roster
                .map(b => `<option value="${b.id}" data-name="${escapeHtml(b.name)}">${escapeHtml(b.name)}</option>`)
                .join('');

            const addRow = () => {
                const index = rowIndex++;
                const wrapper = document.createElement('div');
                wrapper.className =
                    'insurance-claim-row grid gap-2 rounded-lg border border-slate-200 bg-white p-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]';
                wrapper.innerHTML = `
            <select data-role="beneficiary-picker" class="h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs">
                <option value="">Select from Roster (Optional)</option>
                ${rosterOptionsHtml}
            </select>
            <input type="hidden" name="beneficiaries[${index}][beneficiary_id]" data-role="beneficiary-id">
            <input type="text" name="beneficiaries[${index}][full_name]" placeholder="Full Name" required
                class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
            <input type="text" name="beneficiaries[${index}][address]" placeholder="Address" required
                class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
            <button type="button" data-remove-row class="inline-flex h-8 items-center justify-center rounded-md border border-red-200 bg-white px-2 text-[10px] font-semibold text-red-600 hover:bg-red-50">
                Remove
            </button>
        `;

                const picker = wrapper.querySelector('[data-role="beneficiary-picker"]');
                const beneficiaryIdInput = wrapper.querySelector('[data-role="beneficiary-id"]');
                const fullNameInput = wrapper.querySelector('input[name$="[full_name]"]');

                picker.addEventListener('change', () => {
                    const selected = picker.selectedOptions[0];
                    beneficiaryIdInput.value = picker.value;
                    if (picker.value) {
                        fullNameInput.value = selected.dataset.name ?? '';
                    }
                });

                wrapper.querySelector('[data-remove-row]').addEventListener('click', () => {
                    wrapper.remove();
                });

                rows.appendChild(wrapper);
            };

            document.getElementById('addInsuranceClaimRow').addEventListener('click', addRow);

            // Start with one row so the form isn't empty on expand.
            addRow();
        })();
    </script>
@endif
