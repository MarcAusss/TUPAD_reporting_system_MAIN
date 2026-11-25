@extends('layouts.app')

@section('title', $enrollment->gpai_number)

@php
    $peso = fn (int $cents): string => '₱'.number_format($cents / 100, 2);
    $dt = fn ($value): string => $value ? $value->format('Y-m-d\TH:i') : '';
    $d = fn ($value): string => $value ? $value->format('Y-m-d') : '';
    $nextStep = $enrollment->nextStep();
@endphp

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        :breadcrumbs="[['label' => 'Yearly Status', 'url' => route('gsis.status', ['fiscal_year' => $enrollment->fiscal_year])], ['label' => $enrollment->gpai_number]]"
        :title="$enrollment->gpai_number"
        :description="$enrollment->natureOfPayment()"
    >
        <x-slot:actions>
            <button type="button" data-details-open
                class="inline-flex h-10 items-center justify-center rounded-lg border border-[#063b86] bg-white px-4 text-sm font-semibold text-[#063b86] hover:bg-blue-50">
                Edit Details
            </button>
            <a href="{{ route('gsis.enrollments.transmittal', $enrollment) }}" target="_blank"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Print Transmittal
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($nextStep)
        <a href="#step-{{ $nextStep }}"
            class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-900 hover:bg-blue-100">
            <span>
                <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-blue-700">Next step</span>
                <span class="ml-2 font-semibold">{{ $enrollment->nextStepLabel() }}</span>
            </span>
            <span class="text-xs font-semibold">Record it in GSIS Processing →</span>
        </a>
    @else
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            All GSIS processing steps are recorded for this transmittal.
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['Status', $enrollment->stageLabel()],
            ['Payment', \App\Models\GpaiEnrollment::modeLabel($enrollment->payment_mode).($enrollment->cashAdvance ? ' · '.$enrollment->cashAdvance->label() : '')],
            ['Beneficiaries', number_format($enrollment->beneficiaries())],
            ['Total', $peso($enrollment->totalCents())],
            ['Coverage', $enrollment->coverage_start->format('M j, Y').' – '.$enrollment->coverage_end->format('M j, Y')],
        ] as [$label, $value])
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $label }}</div>
                <div class="mt-1.5 text-sm font-bold text-slate-900">{{ $value }}</div>
            </article>
        @endforeach
    </section>

    <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_24rem]">
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Enrolled Projects</h2>
                <p class="mt-1 text-xs text-slate-500">Transmitted {{ $enrollment->transmittal_date->format('F j, Y') }} by {{ $enrollment->creator?->name ?? '—' }}.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="tupad-system-table min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="p-3 text-left">No.</th>
                            <th class="p-3 text-left">ADL No.</th>
                            <th class="p-3 text-left">Particulars</th>
                            <th class="p-3 text-left">Project</th>
                            <th class="p-3 text-right">Beneficiaries</th>
                            <th class="p-3 text-right">Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enrollment->items as $item)
                            <tr>
                                <td class="p-3">{{ $loop->iteration }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $item->adl_number ?: '—' }}</td>
                                <td class="p-3">{{ $item->particulars }}</td>
                                <td class="p-3 text-slate-600">{{ $item->project?->project_title }}</td>
                                <td class="p-3 text-right">{{ number_format($item->beneficiaries) }}</td>
                                <td class="p-3 text-right">{{ $peso($item->premiumCents()) }}</td>
                            </tr>
                        @endforeach
                        <tr class="font-semibold">
                            <td colspan="4" class="p-3 text-right">Sub-total</td>
                            <td class="p-3 text-right">{{ number_format($enrollment->beneficiaries()) }}</td>
                            <td class="p-3 text-right">{{ $peso($enrollment->premiumCents()) }}</td>
                        </tr>
                        <tr>
                            <td colspan="5" class="p-3 text-right">Add Documentary Stamp Tax</td>
                            <td class="p-3 text-right">{{ $peso($enrollment->docStampCents()) }}</td>
                        </tr>
                        <tr class="font-bold">
                            <td colspan="4" class="p-3 text-right">TOTAL</td>
                            <td class="p-3 text-right">{{ number_format($enrollment->beneficiaries()) }}</td>
                            <td class="p-3 text-right">{{ $peso($enrollment->totalCents()) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        @php
            // GSIS Processing wizard pages. "Distributed to PCs" is always last and automatic.
            $wizardPages = [
                ['title' => 'Billing', 'hint' => 'Request the insurance bill from GSIS and record when the SOA / Billing arrives.', 'fields' => ['billing_emailed_at', 'soa_received_at'], 'extra' => 'doc_stamp'],
                ['title' => 'Routing', 'hint' => 'Release the documents for routing and track who has them (Table 1 status).', 'fields' => ['routing_released_on'], 'extra' => 'billing'],
                ['title' => 'On-call Payment Slip', 'hint' => 'Record the on-call payment slip and when the validated slip came back from the Cashier.', 'fields' => ['payment_slip_on', 'payment_slip_validated_at'], 'extra' => null],
                ['title' => 'OR & Policy', 'hint' => 'Ask GSIS for the OR and Policy, then record them once received (Table 2 status).', 'fields' => ['or_policy_requested_at', 'or_policy_received_on'], 'extra' => 'or_policy'],
                ['title' => 'Distributed to PCs', 'hint' => 'Recorded automatically when the system notifies the TUPAD Coordinators.', 'fields' => [], 'extra' => 'distributed'],
            ];
            $stepFormat = fn (string $field) => \App\Models\GpaiEnrollment::STEPS[$field][1] === 'date' ? $d : $dt;
            $errorFields = array_keys($errors->getMessages());
        @endphp

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">GSIS Processing</h2>
                <p class="mt-1 text-xs text-slate-500">Record each step in the wizard as it happens. It is Complete once the OR and Policy are received.</p>
            </div>
            <ol class="space-y-2.5 p-5">
                @foreach (\App\Models\GpaiEnrollment::STEPS as $field => [$label, $type])
                    @php
                        $value = $enrollment->{$field};
                        $isNext = $field === $nextStep;
                    @endphp
                    <li class="flex items-start gap-3 rounded-lg {{ $isNext ? 'bg-blue-50 p-2' : '' }}">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold {{ $value ? 'bg-emerald-100 text-emerald-700' : ($isNext ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400') }}">
                            {{ $value ? '✓' : $loop->iteration }}
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold {{ $isNext ? 'text-blue-900' : 'text-slate-700' }}">{{ $label }}</span>
                            <span class="block text-[11px] text-slate-500">
                                @if ($value)
                                    {{ $type === 'date' ? $value->format('M j, Y') : $value->format('M j, Y g:i A') }}
                                @elseif ($type === 'auto')
                                    Automatic when the TUPAD Coordinators are notified
                                @else
                                    —
                                @endif
                            </span>
                        </span>
                    </li>
                @endforeach
            </ol>
            <div class="space-y-1 border-t border-slate-100 px-5 py-3 text-[11px] text-slate-600">
                <div><span class="font-semibold">Table 1 status:</span> {{ $enrollment->billingStatusLabel() ?? '—' }}</div>
                <div><span class="font-semibold">Table 2 status:</span> {{ $enrollment->opsStatusLabel() ?? '—' }}</div>
                <div><span class="font-semibold">OR No. / Policy ID:</span> {{ $enrollment->or_number ?: '—' }} / {{ $enrollment->policy_id ?: '—' }}</div>
            </div>
            <div class="border-t border-slate-100 p-5">
                <button type="button" data-wizard-open
                    class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    {{ $nextStep ? 'Continue: '.$enrollment->nextStepLabel() : 'Open GSIS Processing' }} →
                </button>
            </div>
        </section>
    </div>

    @php
        $detailErrors = $errors->getBag('details');
    @endphp
    <dialog id="gsisDetails" data-details-dialog @if ($detailErrors->any()) data-details-error @endif aria-labelledby="gsisDetailsTitle"
        class="tupad-dialog w-[calc(100%-2rem)] max-w-4xl rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl">
        <form method="POST" action="{{ route('gsis.enrollments.details', $enrollment) }}">
            @csrf
            @method('PUT')

            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 pt-5 pb-4">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">{{ $enrollment->gpai_number }}</div>
                    <h2 id="gsisDetailsTitle" class="mt-1 text-lg font-bold text-slate-900">Edit Transmittal Details</h2>
                    <p class="mt-1 text-xs text-slate-500">Saved directly, no Focal approval needed. The payment mode stays {{ \App\Models\GpaiEnrollment::modeLabel($enrollment->payment_mode) }} (it sets the GPAI number series). The projects' GSIS Enrollment (Insurance) records are updated too.</p>
                </div>
                <button type="button" data-details-close class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>

            <div class="max-h-[65vh] space-y-4 overflow-y-auto px-6 py-5">
                @if ($detailErrors->any())
                    <ul class="list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 py-2 pr-3 pl-7 text-xs text-red-800">
                        @foreach ($detailErrors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="detail_transmittal_date" class="mb-1 block text-xs font-semibold text-slate-700">Transmittal Date</label>
                        <input id="detail_transmittal_date" type="date" name="transmittal_date" required
                            value="{{ $detailErrors->any() ? old('transmittal_date') : $d($enrollment->transmittal_date) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                    <div>
                        <label for="detail_coverage_start" class="mb-1 block text-xs font-semibold text-slate-700">Coverage Start</label>
                        <input id="detail_coverage_start" type="date" name="coverage_start" required
                            value="{{ $detailErrors->any() ? old('coverage_start') : $d($enrollment->coverage_start) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                    <div>
                        <label for="detail_coverage_end" class="mb-1 block text-xs font-semibold text-slate-700">Coverage End</label>
                        <input id="detail_coverage_end" type="date" name="coverage_end" required
                            value="{{ $detailErrors->any() ? old('coverage_end') : $d($enrollment->coverage_end) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>
                </div>

                @if ($enrollment->payment_mode === \App\Models\GpaiEnrollment::MODE_CASH_ADVANCE)
                    <div>
                        <label for="detail_cash_advance" class="mb-1 block text-xs font-semibold text-slate-700">Paid from Cash Advance</label>
                        <select id="detail_cash_advance" name="gpai_cash_advance_id" required class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            @foreach ($cashAdvances as $cashAdvance)
                                <option value="{{ $cashAdvance->id }}" @selected((int) ($detailErrors->any() ? old('gpai_cash_advance_id') : $enrollment->gpai_cash_advance_id) === $cashAdvance->id)>
                                    {{ $cashAdvance->label() }} · balance {{ $peso($cashAdvance->balanceCents()) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="tupad-system-table min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="p-2 text-left">Project</th>
                                <th class="p-2 text-left">ADL No.</th>
                                <th class="p-2 text-left">Particulars</th>
                                <th class="p-2 text-right">Beneficiaries</th>
                                <th class="p-2 text-right">Premium</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($enrollment->items as $item)
                                @php
                                    $oldItem = $detailErrors->any() ? old('items.'.$item->id, []) : [];
                                @endphp
                                <tr class="align-top">
                                    <td class="p-2 text-xs text-slate-600">{{ $item->project?->project_title ?? '—' }}</td>
                                    <td class="p-2">
                                        <input name="items[{{ $item->id }}][adl_number]" maxlength="100" value="{{ $oldItem['adl_number'] ?? $item->adl_number }}"
                                            class="h-9 w-32 rounded-lg border border-slate-300 px-2 text-xs">
                                    </td>
                                    <td class="p-2">
                                        <textarea name="items[{{ $item->id }}][particulars]" rows="2" maxlength="500" required
                                            class="w-64 rounded-lg border border-slate-300 p-2 text-xs">{{ $oldItem['particulars'] ?? $item->particulars }}</textarea>
                                    </td>
                                    <td class="p-2 text-right">
                                        <input type="number" min="1" step="1" required name="items[{{ $item->id }}][beneficiaries]" value="{{ $oldItem['beneficiaries'] ?? $item->beneficiaries }}"
                                            class="h-9 w-24 rounded-lg border border-slate-300 px-2 text-right text-xs">
                                    </td>
                                    <td class="p-2 text-right">
                                        <input type="number" min="0" step="0.01" required name="items[{{ $item->id }}][premium]" value="{{ $oldItem['premium'] ?? number_format((float) $item->premium, 2, '.', '') }}"
                                            class="h-9 w-28 rounded-lg border border-slate-300 px-2 text-right text-xs">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-slate-500">The documentary stamp, remarks, statuses, and GSIS processing dates are edited in GSIS Processing.</p>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-3">
                <button type="button" data-details-close
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Save Details
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="gsisWizard" data-wizard aria-labelledby="gsisWizardTitle"
        @if ($errorFields !== []) data-wizard-error-fields="{{ implode(',', $errorFields) }}" @endif
        class="tupad-dialog w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl">
        <form method="POST" action="{{ route('gsis.enrollments.update', $enrollment) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 pt-5 pb-4">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">GSIS Processing · {{ $enrollment->gpai_number }}</div>
                    <h2 id="gsisWizardTitle" class="mt-1 text-lg font-bold text-slate-900" data-wizard-title>{{ $wizardPages[0]['title'] }}</h2>
                </div>
                <button type="button" data-wizard-close class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                </button>
            </div>

            @if (session('success'))
                <div class="mx-6 mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif

            {{-- Stepper --}}
            <ol class="flex gap-1 px-6 pt-4" aria-label="Wizard steps">
                @foreach ($wizardPages as $index => $page)
                    <li class="min-w-0 flex-1">
                        <button type="button" data-wizard-goto="{{ $index }}" class="w-full text-left">
                            <span data-wizard-bar class="block h-1.5 rounded-full bg-slate-200"></span>
                            <span data-wizard-label class="mt-1.5 block truncate text-[11px] font-semibold text-slate-400">{{ $index + 1 }}. {{ $page['title'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <div class="max-h-[60vh] overflow-y-auto px-6 py-5">
                @foreach ($wizardPages as $index => $page)
                    <div data-wizard-page="{{ $index }}" data-wizard-title-text="{{ $page['title'] }}" class="space-y-4 {{ $index === 0 ? '' : 'hidden' }}">
                        <p class="text-xs text-slate-500">{{ $page['hint'] }}</p>

                        @foreach ($page['fields'] as $field)
                            @php
                                [$label, $type] = \App\Models\GpaiEnrollment::STEPS[$field];
                                $isNext = $field === $nextStep;
                            @endphp
                            <div id="step-{{ $field }}" class="rounded-lg {{ $isNext ? 'border border-blue-300 bg-blue-50 p-3' : '' }}">
                                <label for="{{ $field }}" class="mb-1 flex items-center gap-2 text-xs font-semibold {{ $isNext ? 'text-blue-900' : 'text-slate-700' }}">
                                    @if ($enrollment->{$field} !== null)
                                        <span class="text-emerald-600" aria-hidden="true">✓</span>
                                    @elseif ($isNext)
                                        <span class="rounded-full bg-blue-600 px-1.5 py-0.5 text-[11px] font-bold uppercase text-white">Next</span>
                                    @endif
                                    {{ $label }}
                                </label>
                                <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}"
                                    value="{{ old($field, $stepFormat($field)($enrollment->{$field})) }}"
                                    @if ($isNext) data-wizard-focus @endif
                                    class="h-10 w-full rounded-lg border {{ $isNext ? 'border-blue-400' : 'border-slate-300' }} bg-white px-3 text-sm">
                            </div>
                        @endforeach

                        @if ($page['extra'] === 'doc_stamp')
                            <div>
                                <label for="doc_stamp" class="mb-1 block text-xs font-semibold text-slate-700">Documentary Stamp Tax</label>
                                <input id="doc_stamp" type="number" step="0.01" min="0" name="doc_stamp"
                                    value="{{ old('doc_stamp', number_format((float) $enrollment->doc_stamp, 2, '.', '')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                            </div>
                        @elseif ($page['extra'] === 'billing')
                            <div>
                                <label for="billing_status" class="mb-1 block text-xs font-semibold text-slate-700">Status (Table 1 · Billing &amp; Routing)</label>
                                <select id="billing_status" name="billing_status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">— Set status —</option>
                                    @foreach (\App\Models\GpaiEnrollment::BILLING_STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_status', $enrollment->billing_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-slate-500">Signed/Completed moves this transmittal to Table 2 (On-call Payment Slip).</p>
                            </div>
                            <div>
                                <label for="billing_received_via" class="mb-1 block text-xs font-semibold text-slate-700">Received via</label>
                                <select id="billing_received_via" name="billing_received_via" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">— Select —</option>
                                    @foreach (\App\Models\GpaiEnrollment::RECEIVED_VIA as $value => $label)
                                        <option value="{{ $value }}" @selected(old('billing_received_via', $enrollment->billing_received_via) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="remarks" class="mb-1 block text-xs font-semibold text-slate-700">Remarks (Table 1)</label>
                                <textarea id="remarks" name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('remarks', $enrollment->remarks) }}</textarea>
                            </div>
                        @elseif ($page['extra'] === 'or_policy')
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="or_number" class="mb-1 block text-xs font-semibold text-slate-700">OR Number</label>
                                    <input id="or_number" name="or_number" maxlength="150" value="{{ old('or_number', $enrollment->or_number) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>
                                <div>
                                    <label for="policy_id" class="mb-1 block text-xs font-semibold text-slate-700">Policy ID</label>
                                    <input id="policy_id" name="policy_id" maxlength="150" value="{{ old('policy_id', $enrollment->policy_id) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>
                            </div>
                            <div id="ops-details">
                                @unless ($enrollment->isBillingSigned())
                                    <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-800">Shown in Table 2 once the Table 1 status is Signed/Completed.</p>
                                @endunless
                                <label for="ops_status" class="mb-1 block text-xs font-semibold text-slate-700">Status (Table 2 · On-call Payment Slip)</label>
                                <select id="ops_status" name="ops_status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">— Set status —</option>
                                    @foreach (\App\Models\GpaiEnrollment::OPS_STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('ops_status', $enrollment->ops_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-slate-500">
                                    Set automatically: <b>For OR and Policy</b> once the On-call Payment Slip Date is recorded,
                                    and <b>Complete</b> once the OR and Policy are received. Change it only for
                                    "For OR only" / "For Policy only".
                                </p>
                            </div>
                            <div>
                                <label for="ops_document_url" class="mb-1 block text-xs font-semibold text-slate-700">File / Document (Google Drive link)</label>
                                <input id="ops_document_url" type="url" name="ops_document_url" maxlength="2048"
                                    value="{{ old('ops_document_url', $enrollment->ops_document_url) }}"
                                    placeholder="https://drive.google.com/…"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                @if ($enrollment->ops_document_url)
                                    <a href="{{ $enrollment->ops_document_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs font-semibold text-[#063b86] hover:underline">Open Google Drive ↗</a>
                                @endif
                                <p class="mt-1 text-[11px] text-slate-500">Share the Drive file or folder with the people who need it, then paste its link.</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="ops_remarks" class="mb-1 block text-xs font-semibold text-slate-700">Remarks (Table 2)</label>
                                    <textarea id="ops_remarks" name="ops_remarks" rows="2" class="w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('ops_remarks', $enrollment->ops_remarks) }}</textarea>
                                </div>
                                <div>
                                    <label for="ops_notes" class="mb-1 block text-xs font-semibold text-slate-700">Notes</label>
                                    <textarea id="ops_notes" name="ops_notes" rows="2" class="w-full rounded-lg border border-slate-300 p-2 text-sm">{{ old('ops_notes', $enrollment->ops_notes) }}</textarea>
                                </div>
                            </div>
                        @elseif ($page['extra'] === 'distributed')
                            <div id="step-distributed_on" class="rounded-xl border p-4 {{ $enrollment->distributed_on ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' }}">
                                <div class="text-[11px] font-bold uppercase tracking-[0.08em] {{ $enrollment->distributed_on ? 'text-emerald-700' : 'text-slate-400' }}">Distributed to PCs</div>
                                @if ($enrollment->distributed_on)
                                    <div class="mt-1 text-base font-bold text-emerald-900">{{ $enrollment->distributed_on->format('F j, Y · g:i A') }}</div>
                                    <p class="mt-1 text-xs text-emerald-800">The TUPAD Coordinators were notified through the system that the OR and Policy were received.</p>
                                @else
                                    <div class="mt-1 text-sm font-semibold text-slate-600">Not yet</div>
                                    <p class="mt-1 text-xs text-slate-500">Once the OR and Policy are received (or the Table 2 status is Complete) and you save, the system notifies the TUPAD Coordinators and records that date and time here.</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-3">
                <button type="button" data-wizard-back
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40">
                    ← Back
                </button>
                <div class="flex items-center gap-2">
                    <button type="submit"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-[#063b86] bg-white px-4 text-sm font-semibold text-[#063b86] hover:bg-blue-50">
                        Save Progress
                    </button>
                    <button type="button" data-wizard-next
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        Next →
                    </button>
                </div>
            </div>
        </form>
    </dialog>
@endsection

@push('scripts')
    <script>
        (function () {
            const details = document.querySelector('[data-details-dialog]');
            if (!details) return;
            document.querySelectorAll('[data-details-open]').forEach((button) => button.addEventListener('click', () => details.showModal()));
            details.querySelectorAll('[data-details-close]').forEach((button) => button.addEventListener('click', () => details.close()));
            if (details.hasAttribute('data-details-error')) details.showModal();
        })();

        (function () {
            const dialog = document.querySelector('[data-wizard]');
            if (!dialog) return;

            const pages = Array.from(dialog.querySelectorAll('[data-wizard-page]'));
            const bars = dialog.querySelectorAll('[data-wizard-bar]');
            const labels = dialog.querySelectorAll('[data-wizard-label]');
            const back = dialog.querySelector('[data-wizard-back]');
            const next = dialog.querySelector('[data-wizard-next]');
            const title = dialog.querySelector('[data-wizard-title]');
            let current = 0;

            function show(index) {
                current = Math.max(0, Math.min(index, pages.length - 1));
                pages.forEach((page, i) => page.classList.toggle('hidden', i !== current));
                bars.forEach((bar, i) => {
                    bar.style.background = i < current ? '#10b981' : (i === current ? '#063b86' : '');
                });
                labels.forEach((label, i) => {
                    label.style.color = i === current ? '#063b86' : '';
                });
                title.textContent = pages[current].dataset.wizardTitleText;
                back.disabled = current === 0;
                next.classList.toggle('hidden', current === pages.length - 1);
            }

            function pageOf(element) {
                const page = element ? element.closest('[data-wizard-page]') : null;
                return page ? Number(page.dataset.wizardPage) : null;
            }

            function open(index, focusTarget) {
                show(index);
                if (!dialog.open) dialog.showModal();
                const target = focusTarget || pages[current].querySelector('[data-wizard-focus]') || pages[current].querySelector('input, select, textarea');
                if (target) target.focus();
            }

            // Open on the page holding the next step (or the first page when all are done).
            function openAtNextStep() {
                const focus = dialog.querySelector('[data-wizard-focus]');
                open(pageOf(focus) ?? pages.length - 1, focus);
            }

            // "#step-field" links (Continue buttons, banner, Save Progress) open that page.
            function openFromHash() {
                if (!location.hash.startsWith('#step-')) return false;
                const step = document.getElementById(location.hash.slice(1));
                const index = pageOf(step);
                if (index === null) return false;
                open(index, step.querySelector('input'));
                return true;
            }

            back.addEventListener('click', () => show(current - 1));
            next.addEventListener('click', () => {
                show(current + 1);
                const first = pages[current].querySelector('input, select, textarea');
                if (first) first.focus();
            });
            dialog.querySelectorAll('[data-wizard-goto]').forEach((button) => {
                button.addEventListener('click', () => show(Number(button.dataset.wizardGoto)));
            });
            dialog.querySelector('[data-wizard-close]').addEventListener('click', () => dialog.close());
            document.querySelectorAll('[data-wizard-open]').forEach((button) => button.addEventListener('click', openAtNextStep));
            window.addEventListener('hashchange', openFromHash);
            document.querySelectorAll('a[href^="#step-"]').forEach((link) => link.addEventListener('click', (event) => {
                event.preventDefault();
                history.replaceState(null, '', link.getAttribute('href'));
                openFromHash();
            }));

            // Reopen on the first field with a validation error, else follow the URL.
            const errorFields = (dialog.dataset.wizardErrorFields || '').split(',').filter(Boolean);
            const errorInput = errorFields.map((name) => dialog.querySelector('[name="' + name + '"]')).find(Boolean);
            if (errorInput) {
                open(pageOf(errorInput), errorInput);
            } else {
                openFromHash();
            }
        })();
    </script>
@endpush
