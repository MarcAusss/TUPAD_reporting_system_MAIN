@extends('layouts.app')

@section('title', 'Enroll Projects to GPAI')

@php
    $selectedIds = collect(old('project_ids', array_filter([request()->integer('project')])))->map(fn ($id) => (int) $id)->all();
    $mode = old('payment_mode', \App\Models\GpaiEnrollment::MODE_VOUCHER);
@endphp

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        :breadcrumbs="[['label' => 'Yearly Status', 'url' => route('gsis.status')], ['label' => 'Enroll Projects']]"
        title="Enroll Projects to GPAI"
        description="Select the projects to enroll in one transmittal. The premium is the insurance budget the TUPAD Coordinator set in Project Create. Saving records each project's GSIS Enrollment requirement and notifies its TUPAD Coordinator."
    />

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <div class="font-semibold">The enrollment could not be saved.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('gsis.enrollments.store') }}" id="gpaiEnrollForm">
        @csrf

        <div class="grid gap-5 xl:grid-cols-[1fr_22rem]">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Projects Awaiting Enrollment</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ number_format($projects->count()) }} {{ str('project')->plural($projects->count()) }} with an insurance budget, from TSSD Evaluation onward. <span id="gpaiShownCount" class="font-semibold text-slate-700" aria-live="polite"></span></p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="gpaiSearch" class="sr-only">Search projects</label>
                        <input id="gpaiSearch" type="search" autocomplete="off"
                            placeholder="Search project, location, ADL No., status"
                            class="h-9 w-64 rounded-lg border border-slate-300 bg-white px-3 text-xs">
                        <label for="gpaiProvinceFilter" class="text-xs font-semibold text-slate-600">Province</label>
                        <select id="gpaiProvinceFilter" class="h-9 rounded-lg border border-slate-300 bg-white px-2 text-xs">
                            <option value="">All provinces</option>
                            @foreach ($projects->pluck('province')->filter()->unique()->sort() as $province)
                                <option value="{{ $province }}">{{ $province }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="tupad-system-table min-w-full text-sm" data-no-enhance>
                        <thead>
                            <tr>
                                <th class="w-10 p-3 text-left">
                                    <input type="checkbox" id="gpaiSelectAll" aria-label="Select all shown projects" class="h-4 w-4 rounded border-slate-300">
                                </th>
                                <th class="p-3 text-left">Project</th>
                                <th class="p-3 text-left">Location</th>
                                <th class="p-3 text-left">ADL No.</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-right">Insured Beneficiaries</th>
                                <th class="p-3 text-right">Premium</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php($requestedCount = $projects->filter(fn ($project) => $project->latestGpaiEnrollmentRequest)->count())
                            @forelse ($projects as $project)
                                @php($gpaiRequest = $project->latestGpaiEnrollmentRequest)
                                @if ($loop->first && $requestedCount > 0)
                                    <tr data-gpai-group="requested" class="bg-amber-100/70">
                                        <td colspan="7" class="p-2 px-3 text-xs font-bold uppercase tracking-wide text-amber-800">
                                            📌 Requested by TUPAD Coordinators ({{ $requestedCount }}) — enroll these first
                                        </td>
                                    </tr>
                                @endif
                                @if ($requestedCount > 0 && $loop->index === $requestedCount)
                                    <tr data-gpai-group="others" class="bg-slate-50">
                                        <td colspan="7" class="p-2 px-3 text-xs font-bold uppercase tracking-wide text-slate-500">Other projects awaiting enrollment</td>
                                    </tr>
                                @endif
                                <tr data-gpai-row data-province="{{ $project->province }}" @if ($gpaiRequest) data-gpai-requested @endif
                                    class="cursor-pointer select-none hover:bg-slate-50 {{ $gpaiRequest ? 'bg-amber-50' : '' }}"
                                    @if ($gpaiRequest) style="box-shadow: inset 4px 0 0 #f59e0b;" @endif
                                    data-search="{{ mb_strtolower(collect([$project->project_title, $project->municipality, $project->province, $project->allocation?->adl?->adl_number, $project->status->label(), $project->implementation_mode?->label()])->filter()->implode(' ')) }}">
                                    <td class="p-3">
                                        <input type="checkbox" name="project_ids[]" value="{{ $project->id }}"
                                            data-beneficiaries="{{ (int) $project->insurance_beneficiaries }}"
                                            data-premium="{{ number_format((float) $project->insurance_total, 2, '.', '') }}"
                                            aria-label="Enroll {{ $project->project_title }}"
                                            @checked(in_array($project->id, $selectedIds, true))
                                            class="h-4 w-4 rounded border-slate-300">
                                    </td>
                                    <td class="p-3">
                                        <div class="font-semibold text-slate-900">{{ $project->project_title }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $project->implementation_mode?->label() }}</div>
                                        @if ($gpaiRequest)
                                            <div class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800" data-gpai-request-badge>
                                                Requested {{ $gpaiRequest->created_at->diffForHumans() }} by {{ $gpaiRequest->requester?->name ?? 'a TUPAD Coordinator' }}{{ $project->gpai_enrollment_requests_count > 1 ? ' · '.$project->gpai_enrollment_requests_count.'×' : '' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="p-3 text-slate-600">{{ collect([$project->municipality, $project->province])->filter()->implode(', ') ?: '—' }}</td>
                                    <td class="p-3 text-slate-600">{{ $project->allocation?->adl?->adl_number ?: '—' }}</td>
                                    <td class="p-3 text-slate-600">{{ $project->status->label() }}</td>
                                    <td class="p-3 text-right">{{ number_format((int) $project->insurance_beneficiaries) }}</td>
                                    <td class="p-3 text-right font-semibold">₱{{ number_format((float) $project->insurance_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="p-8 text-center text-sm text-slate-400">No projects are waiting for GPAI enrollment.</td></tr>
                            @endforelse
                            <tr id="gpaiNoMatch" hidden>
                                <td colspan="7" class="p-8 text-center text-sm text-slate-400">No projects match your search.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-900">Transmittal</h2>

                    <fieldset class="mt-3">
                        <legend class="mb-1 text-xs font-semibold text-slate-700">Payment</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach ([\App\Models\GpaiEnrollment::MODE_VOUCHER => 'Voucher', \App\Models\GpaiEnrollment::MODE_CASH_ADVANCE => 'Cash Advance'] as $value => $label)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-[#063b86] has-[:checked]:bg-blue-50">
                                    <input type="radio" name="payment_mode" value="{{ $value }}" @checked($mode === $value) class="h-4 w-4">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-[11px] text-slate-500">Number: <span id="gpaiNextNumber" class="font-semibold text-slate-700">{{ $nextNumbers[$mode] ?? '' }}</span></p>
                    </fieldset>

                    <div id="gpaiCashAdvanceField" class="mt-3 {{ $mode === \App\Models\GpaiEnrollment::MODE_CASH_ADVANCE ? '' : 'hidden' }}">
                        <label for="gpai_cash_advance_id" class="mb-1 block text-xs font-semibold text-slate-700">Cash Advance</label>
                        <select id="gpai_cash_advance_id" name="gpai_cash_advance_id" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            <option value="">Select cash advance</option>
                            @foreach ($cashAdvances->reverse() as $cashAdvance)
                                <option value="{{ $cashAdvance->id }}" data-balance="{{ $cashAdvance->balanceCents() / 100 }}"
                                    @selected((string) old('gpai_cash_advance_id', $loop->first ? $cashAdvance->id : '') === (string) $cashAdvance->id)>
                                    {{ $cashAdvance->label() }} — balance ₱{{ number_format($cashAdvance->balanceCents() / 100, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @if ($cashAdvances->isEmpty())
                            <p class="mt-1 text-[11px] text-amber-700">No cash advance yet. <a href="{{ route('gsis.cash-advances') }}" class="font-semibold underline">Add one</a> first.</p>
                        @endif
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <label for="transmittal_date" class="mb-1 block text-xs font-semibold text-slate-700">Transmittal Date</label>
                            <input id="transmittal_date" type="date" name="transmittal_date" required value="{{ old('transmittal_date', now()->toDateString()) }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-2 text-sm">
                        </div>
                        <div>
                            <label for="coverage_start" class="mb-1 block text-xs font-semibold text-slate-700">Coverage Starts</label>
                            <input id="coverage_start" type="date" name="coverage_start" value="{{ old('coverage_start') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-2 text-sm">
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500">Leave Coverage Starts blank for the day after the transmittal. Coverage runs one year.</p>

                    <div class="mt-3">
                        <label for="doc_stamp" class="mb-1 block text-xs font-semibold text-slate-700">Documentary Stamp Tax</label>
                        <input id="doc_stamp" type="number" step="0.01" min="0" name="doc_stamp" value="{{ old('doc_stamp') }}" placeholder="Auto"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        <p class="mt-1 text-[11px] text-slate-500">Auto: 20% of the premium, up to ₱200.00.</p>
                    </div>

                    <div class="mt-3">
                        <label for="remarks" class="mb-1 block text-xs font-semibold text-slate-700">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('remarks') }}</textarea>
                    </div>
                </section>

                <section class="rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm shadow-sm" aria-live="polite">
                    <h2 class="text-xs font-bold uppercase tracking-wide text-blue-800">Selected</h2>
                    <dl class="mt-2 space-y-1 text-blue-950">
                        <div class="flex justify-between"><dt>Projects</dt><dd id="gpaiSelCount" class="font-semibold">0</dd></div>
                        <div class="flex justify-between"><dt>Beneficiaries</dt><dd id="gpaiSelBenef" class="font-semibold">0</dd></div>
                        <div class="flex justify-between"><dt>Premium (Sub-total)</dt><dd id="gpaiSelPremium" class="font-semibold">₱0.00</dd></div>
                        <div class="flex justify-between"><dt>Doc. Stamp</dt><dd id="gpaiSelStamp" class="font-semibold">₱0.00</dd></div>
                        <div class="flex justify-between border-t border-blue-200 pt-1 text-base"><dt class="font-bold">Total</dt><dd id="gpaiSelTotal" class="font-extrabold">₱0.00</dd></div>
                    </dl>
                    <p id="gpaiBalanceWarning" class="mt-2 hidden text-xs font-semibold text-red-700"></p>
                    <button type="submit" id="gpaiSubmit" disabled
                        class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b] disabled:cursor-not-allowed disabled:opacity-50">
                        Enroll Selected Projects
                    </button>
                </section>
            </aside>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('gpaiEnrollForm');
            const nextNumbers = @json($nextNumbers);
            const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            const checkboxes = () => Array.from(form.querySelectorAll('input[name="project_ids[]"]'));
            const docStamp = document.getElementById('doc_stamp');
            const caSelect = document.getElementById('gpai_cash_advance_id');

            function mode() {
                return form.querySelector('input[name="payment_mode"]:checked')?.value ?? 'voucher';
            }

            function refresh() {
                const selected = checkboxes().filter((box) => box.checked);
                const beneficiaries = selected.reduce((sum, box) => sum + Number(box.dataset.beneficiaries || 0), 0);
                const premium = selected.reduce((sum, box) => sum + Number(box.dataset.premium || 0), 0);
                const autoStamp = Math.min(200, Math.round(premium * 0.20 * 100) / 100);
                const stamp = docStamp.value === '' ? (selected.length ? autoStamp : 0) : Number(docStamp.value);
                const total = premium + stamp;
                const isCa = mode() === 'ca';

                docStamp.placeholder = selected.length ? `Auto (${autoStamp.toFixed(2)})` : 'Auto';
                document.getElementById('gpaiSelCount').textContent = selected.length.toLocaleString();
                document.getElementById('gpaiSelBenef').textContent = beneficiaries.toLocaleString();
                document.getElementById('gpaiSelPremium').textContent = peso.format(premium);
                document.getElementById('gpaiSelStamp').textContent = peso.format(stamp);
                document.getElementById('gpaiSelTotal').textContent = peso.format(total);
                document.getElementById('gpaiNextNumber').textContent = nextNumbers[mode()] ?? '';
                document.getElementById('gpaiCashAdvanceField').classList.toggle('hidden', !isCa);
                caSelect.required = isCa;

                const warning = document.getElementById('gpaiBalanceWarning');
                const balance = Number(caSelect.selectedOptions[0]?.dataset.balance ?? NaN);
                const overBalance = isCa && !Number.isNaN(balance) && total > balance + 0.00001;

                warning.classList.toggle('hidden', !overBalance);
                warning.textContent = overBalance
                    ? `The total exceeds the cash advance balance of ${peso.format(balance)}.`
                    : '';

                document.getElementById('gpaiSubmit').disabled = selected.length === 0 || overBalance;
            }

            document.getElementById('gpaiSelectAll')?.addEventListener('change', function () {
                checkboxes()
                    .filter((box) => !box.closest('[data-gpai-row]').hidden)
                    .forEach((box) => { box.checked = this.checked; });
                refresh();
            });

            // Clicking anywhere on a project row toggles its checkbox.
            form.querySelectorAll('[data-gpai-row]').forEach(function (row) {
                const box = row.querySelector('input[name="project_ids[]"]');

                row.addEventListener('click', function (event) {
                    if (!box || event.target === box || event.target.closest('a, button, label, select, textarea')) {
                        return;
                    }

                    box.checked = !box.checked;
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            function markSelectedRows() {
                checkboxes().forEach(function (box) {
                    box.closest('[data-gpai-row]')?.classList.toggle('bg-blue-50', box.checked);
                });
            }

            form.addEventListener('change', markSelectedRows);
            markSelectedRows();

            const searchInput = document.getElementById('gpaiSearch');
            const provinceFilter = document.getElementById('gpaiProvinceFilter');

            // Search + province filter, applied as you type.
            function applyFilters() {
                const terms = (searchInput?.value ?? '').toLowerCase().trim().split(/\s+/).filter(Boolean);
                const province = provinceFilter?.value ?? '';
                const rows = Array.from(form.querySelectorAll('[data-gpai-row]'));
                let shown = 0;

                rows.forEach(function (row) {
                    const text = row.dataset.search || '';
                    const visible = (province === '' || row.dataset.province === province)
                        && terms.every((term) => text.includes(term));

                    row.hidden = !visible;
                    shown += visible ? 1 : 0;
                });

                // Group headings (Requested / Others) only while their group has visible rows.
                const requestedHeading = form.querySelector('[data-gpai-group="requested"]');
                const othersHeading = form.querySelector('[data-gpai-group="others"]');
                if (requestedHeading) requestedHeading.hidden = !rows.some((row) => !row.hidden && 'gpaiRequested' in row.dataset);
                if (othersHeading) othersHeading.hidden = !rows.some((row) => !row.hidden && !('gpaiRequested' in row.dataset));

                const filtering = terms.length > 0 || province !== '';
                document.getElementById('gpaiNoMatch').hidden = rows.length === 0 || shown > 0;
                document.getElementById('gpaiShownCount').textContent = filtering && rows.length > 0
                    ? `Showing ${shown.toLocaleString()} of ${rows.length.toLocaleString()}.`
                    : '';

                const selectAll = document.getElementById('gpaiSelectAll');
                if (selectAll) selectAll.checked = false;
            }

            searchInput?.addEventListener('input', function (event) {
                event.stopPropagation();
                applyFilters();
            });

            // Enter in the search box must not submit the enrollment.
            searchInput?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });

            provinceFilter?.addEventListener('change', function (event) {
                event.stopPropagation();
                applyFilters();
            });

            form.addEventListener('change', refresh);
            form.addEventListener('input', refresh);
            refresh();
        });
    </script>
@endpush
