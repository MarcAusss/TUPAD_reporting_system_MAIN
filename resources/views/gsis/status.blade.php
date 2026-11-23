@extends('layouts.app')

@section('title', 'Yearly Status')

@php
    // Read-only mode: Admin, TUPAD Coordinators, and TC-Focal (no editing).
    $readOnly = $readOnly ?? false;
    $statusRoute = $statusRoute ?? 'gsis.status';
    $visibleProjectIds = $visibleProjectIds ?? null;
    $isOps = $table === 'ops';
    $peso = fn (int $cents): string => number_format($cents / 100, 2);
    $dateTime = fn ($value): string => $value ? $value->format('j-M-Y (g:i A)') : '';
    $date = fn ($value): string => $value ? $value->format('j-M-Y') : '';
    // Current filters, so every link keeps the others.
    $query = array_filter(['fiscal_year' => $fiscalYear, 'stage' => $stage, 'mode' => $mode, 'table' => $isOps ? 'ops' : null]);
    $statusUrl = fn (array $changes): string => route($statusRoute, array_filter([...$query, ...$changes]));
    $billingTone = fn (?string $status): string => match ($status) {
        null => 'bg-slate-100 text-slate-500',
        \App\Models\GpaiEnrollment::BILLING_SIGNED => 'bg-emerald-50 text-emerald-700',
        default => 'bg-amber-50 text-amber-800',
    };
    $opsTone = fn (?string $status): string => match ($status) {
        null => 'bg-slate-100 text-slate-500',
        'complete' => 'bg-emerald-50 text-emerald-700',
        default => 'bg-violet-50 text-violet-700',
    };
    $totals = [
        'beneficiaries' => $enrollments->sum(fn ($enrollment) => $enrollment->beneficiaries()),
        'projects' => $enrollments->sum(fn ($enrollment) => $enrollment->items->count()),
        'doc_stamp' => $enrollments->sum(fn ($enrollment) => $enrollment->docStampCents()),
        'amount' => $enrollments->sum(fn ($enrollment) => $enrollment->totalCents()),
    ];
    // Columns before the money columns (for the total row), and all columns (for the empty row).
    $leadColumns = 3;
    $columnCount = $isOps ? 11 : 12;
@endphp

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        :title="$readOnly ? 'GPAI Enrollment Status '.$fiscalYear : $fiscalYear.' Status'"
        :description="$readOnly
            ? ($visibleProjectIds !== null
                ? 'GSIS GPAI enrollment progress of your projects, as recorded by the GSIS Enrollment Officer: billing and routing, then the on-call payment slip and OR / Policy.'
                : 'GSIS GPAI enrollment progress of all TUPAD projects, as recorded by the GSIS Enrollment Officer: billing and routing, then the on-call payment slip and OR / Policy.')
            : 'Table 1 tracks billing and routing of every transmittal. Once its status is Signed/Completed, the transmittal proceeds to Table 2 for the on-call payment slip and OR / Policy.'"
    >
        <x-slot:actions>
            @include('gsis.partials.year-filter', ['route' => $statusRoute, 'keep' => array_filter(['stage' => $stage, 'mode' => $mode, 'table' => $isOps ? 'ops' : null])])
            @unless ($readOnly)
            <a href="{{ route('gsis.enrollments.create') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                + Enroll Projects
            </a>
            @endunless
        </x-slot:actions>
    </x-page-header>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    {{-- Table switch (left) and payment filter (right, under Enroll Projects). --}}
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <nav class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Yearly Status tables">
            <a href="{{ $statusUrl(['table' => null]) }}" @if (! $isOps) aria-current="page" @endif
                class="rounded-lg px-4 py-2 text-xs font-semibold {{ ! $isOps ? 'bg-[#063b86] text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                1 · Billing &amp; Routing
                <span class="ml-1 rounded-full {{ ! $isOps ? 'bg-white/20' : 'bg-slate-100' }} px-2 py-0.5 text-[11px]">{{ $billingCount }}</span>
            </a>
            <a href="{{ $statusUrl(['table' => 'ops']) }}" @if ($isOps) aria-current="page" @endif
                class="rounded-lg px-4 py-2 text-xs font-semibold {{ $isOps ? 'bg-[#063b86] text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                2 · On-call Payment Slip
                <span class="ml-1 rounded-full {{ $isOps ? 'bg-white/20' : 'bg-slate-100' }} px-2 py-0.5 text-[11px]">{{ $opsCount }}</span>
            </a>
        </nav>

        <form method="GET" action="{{ route($statusRoute) }}" class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm" data-mode-filter>
            @foreach (array_filter(['fiscal_year' => $fiscalYear, 'stage' => $stage, 'table' => $isOps ? 'ops' : null]) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <span class="px-2 text-[11px] font-bold uppercase tracking-[0.08em] text-slate-400">Payment</span>
            @foreach (['' => 'All', \App\Models\GpaiEnrollment::MODE_VOUCHER => 'Voucher', \App\Models\GpaiEnrollment::MODE_CASH_ADVANCE => 'CA'] as $value => $label)
                <label class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold {{ (string) $mode === (string) $value ? 'bg-blue-50 text-[#063b86]' : 'text-slate-600 hover:bg-slate-50' }}">
                    <input type="radio" name="mode" value="{{ $value }}" @checked((string) $mode === (string) $value)
                        onchange="this.form.submit()" class="h-3.5 w-3.5 accent-[#063b86]">
                    {{ $label }}
                </label>
            @endforeach
            <noscript><button class="rounded-lg px-2 text-xs font-semibold text-[#063b86]">Apply</button></noscript>
        </form>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ $statusUrl(['stage' => null]) }}"
            class="rounded-lg border px-3 py-2 text-xs font-semibold {{ $stage === null ? 'border-[#063b86] bg-[#063b86] text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
            All ({{ $allEnrollments->count() }})
        </a>
        @foreach (\App\Models\GpaiEnrollment::STAGES as $key => $label)
            @php
                $count = $allEnrollments->filter(fn ($enrollment) => $enrollment->stage() === $key)->count();
            @endphp
            <a href="{{ $statusUrl(['stage' => $key]) }}"
                class="rounded-lg border px-3 py-2 text-xs font-semibold {{ $stage === $key ? 'border-[#063b86] bg-[#063b86] text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                {{ $label }} ({{ $count }})
            </a>
        @endforeach
    </div>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-5 py-2 text-[11px] font-medium text-slate-500">
            <span>
                @if ($isOps)
                    <strong class="text-slate-700">Table 2 · On-call Payment Slip.</strong> Transmittals whose status in Table 1 is Signed/Completed.
                @else
                    <strong class="text-slate-700">Table 1 · Billing &amp; Routing.</strong> Set the status to Signed/Completed to move a transmittal to Table 2.
                @endif
            </span>
            @unless ($readOnly)
                <span>Status and remarks save as soon as you change them.</span>
            @endunless
        </div>
        @if ($readOnly)
            {{-- Other accounts: one row per project. --}}
            @include('gsis.partials.status-readonly-table')
        @else
        <div class="tupad-data-scroll overflow-x-auto">
            <table class="tupad-system-table tupad-wide-table w-full {{ $isOps ? 'min-w-[1700px]' : 'min-w-[1600px]' }} text-xs">
                <thead>
                    <tr>
                        <th class="p-3 text-left">GPAI No.</th>
                        @unless ($readOnly)
                            <th class="p-3 text-left">Next Step</th>
                        @endunless
                        <th class="p-3 text-left">Nature of Payment</th>
                        @if ($readOnly)
                            <th class="p-3 text-left">{{ $visibleProjectIds !== null ? 'Your Projects' : 'Projects' }}</th>
                        @endif
                        @if ($isOps)
                            <th class="p-3 text-right">Amount</th>
                            <th class="p-3 text-left">Date of On-call Payment Slip</th>
                            <th class="p-3 text-left">Date &amp; Time Received of Validated On-call Payment Slip in Cashier</th>
                            <th class="p-3 text-left">Date &amp; Time Emailed GSIS for Issuance of OR &amp; Policy</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">OR Number</th>
                            <th class="p-3 text-left">File / Document</th>
                            <th class="p-3 text-left">Remarks</th>
                            <th class="p-3 text-left">Notes</th>
                        @else
                            <th class="p-3 text-right">No. of Benef. Enrolled</th>
                            <th class="p-3 text-right">#</th>
                            <th class="p-3 text-right">Doc. Stamp</th>
                            <th class="p-3 text-right">Amount</th>
                            <th class="p-3 text-left">Date &amp; Time Emailed for Issuance of Insurance Bill</th>
                            <th class="p-3 text-left">Date &amp; Time Received SOA / Billing</th>
                            <th class="p-3 text-left">Date Release for Routing</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">Remarks</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @if ($enrollments->isNotEmpty())
                        <tr class="font-bold">
                            <td class="p-3" colspan="{{ $leadColumns }}">Total ({{ $enrollments->count() }} transmittals)</td>
                            @if ($isOps)
                                <td class="p-3 text-right">{{ $peso($totals['amount']) }}</td>
                                <td colspan="8"></td>
                            @else
                                <td class="p-3 text-right">{{ number_format($totals['beneficiaries']) }}</td>
                                <td class="p-3 text-right">{{ number_format($totals['projects']) }}</td>
                                <td class="p-3 text-right">{{ $peso($totals['doc_stamp']) }}</td>
                                <td class="p-3 text-right">{{ $peso($totals['amount']) }}</td>
                                <td colspan="5"></td>
                            @endif
                        </tr>
                    @endif
                    @forelse ($enrollments as $enrollment)
                        @php
                            $progressUrl = $readOnly ? null : route('gsis.enrollments.edit', $enrollment).($enrollment->nextStep() ? '#step-'.$enrollment->nextStep() : '');
                            $statusField = $isOps ? 'ops_status' : 'billing_status';
                            $statusOptions = $isOps ? \App\Models\GpaiEnrollment::OPS_STATUSES : \App\Models\GpaiEnrollment::BILLING_STATUSES;
                            $statusLabel = $isOps ? $enrollment->opsStatusLabel() : $enrollment->billingStatusLabel();
                            $tone = $isOps ? $opsTone($enrollment->ops_status) : $billingTone($enrollment->billing_status);
                        @endphp
                        <tr @if ($progressUrl) data-row-href="{{ $progressUrl }}" class="cursor-pointer hover:bg-slate-50" title="Open {{ $enrollment->gpai_number }}" @endif>
                            <td class="whitespace-nowrap p-3">
                                @if ($readOnly)
                                    <span class="font-semibold text-slate-900">{{ $enrollment->gpai_number }}</span>
                                @else
                                    <a href="{{ $progressUrl }}" class="font-semibold text-[#063b86] hover:underline">{{ $enrollment->gpai_number }}</a>
                                @endif
                                <div class="mt-0.5 text-[11px] font-semibold uppercase text-slate-500">{{ \App\Models\GpaiEnrollment::modeLabel($enrollment->payment_mode) }}</div>
                            </td>
                            @unless ($readOnly)
                                <td class="whitespace-nowrap p-3">
                                    @if ($enrollment->nextStep())
                                        <a href="{{ $progressUrl }}"
                                            class="inline-flex items-center gap-1 rounded-lg bg-[#063b86] px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-[#052f6b]">
                                            Continue →
                                        </a>
                                        <div class="mt-1 max-w-48 truncate text-[11px] text-slate-500" title="{{ $enrollment->nextStepLabel() }}">{{ $enrollment->nextStepLabel() }}</div>
                                    @else
                                        <span class="text-[11px] font-semibold text-emerald-700">All steps recorded</span>
                                    @endif
                                </td>
                            @endunless
                            <td class="min-w-72 p-3">{{ $enrollment->natureOfPayment() }}</td>
                            @if ($readOnly)
                                <td class="min-w-72 p-3">
                                    <ul class="space-y-1">
                                        @foreach ($enrollment->items as $item)
                                            @continue($visibleProjectIds !== null && ! in_array($item->project_id, $visibleProjectIds, true))
                                            <li>
                                                <span class="font-semibold text-slate-800">{{ $item->project?->project_title }}</span>
                                                <span class="text-slate-500">· {{ number_format($item->beneficiaries) }} benef. · {{ number_format((float) $item->premium, 2) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </td>
                            @endif

                            @if ($isOps)
                                <td class="p-3 text-right font-semibold">{{ $peso($enrollment->totalCents()) }}</td>
                                <td class="whitespace-nowrap p-3">{{ $date($enrollment->payment_slip_on) }}</td>
                                <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->payment_slip_validated_at) }}</td>
                                <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->or_policy_requested_at) }}</td>
                            @else
                                <td class="p-3 text-right">{{ number_format($enrollment->beneficiaries()) }}</td>
                                <td class="p-3 text-right">{{ number_format($enrollment->items->count()) }}</td>
                                <td class="p-3 text-right">{{ $peso($enrollment->docStampCents()) }}</td>
                                <td class="p-3 text-right font-semibold">{{ $peso($enrollment->totalCents()) }}</td>
                                <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->billing_emailed_at) }}</td>
                                <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->soa_received_at) }}</td>
                                <td class="whitespace-nowrap p-3">{{ $date($enrollment->routing_released_on) }}</td>
                            @endif

                            {{-- Status: a dropdown that saves on change (read-only: a label). --}}
                            <td class="whitespace-nowrap p-3">
                                @if ($readOnly)
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $tone }}">{{ $statusLabel ?? '—' }}</span>
                                @else
                                    <form method="POST" action="{{ route('gsis.enrollments.status', $enrollment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="{{ $statusField }}" onchange="this.form.submit()" aria-label="{{ $enrollment->gpai_number }} status"
                                            class="h-8 min-w-44 rounded-lg border-0 px-2 text-[11px] font-semibold {{ $tone }}">
                                            <option value="">— Set status —</option>
                                            @foreach ($statusOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($enrollment->{$statusField} === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </td>

                            @if ($isOps)
                                <td class="whitespace-nowrap p-3">{{ $enrollment->or_number }}</td>
                                {{-- Google Drive link: opens in a new tab; edit inline (saved on Enter / leaving the field). --}}
                                <td class="min-w-56 p-3">
                                    @if ($enrollment->ops_document_url)
                                        <a href="{{ $enrollment->ops_document_url }}" target="_blank" rel="noopener noreferrer" class="mb-1 inline-block font-semibold text-[#063b86] hover:underline">Open Google Drive ↗</a>
                                    @endif
                                    <form method="POST" action="{{ route('gsis.enrollments.status', $enrollment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="url" name="ops_document_url" value="{{ $enrollment->ops_document_url }}" maxlength="2048"
                                            placeholder="Paste Google Drive link…" aria-label="{{ $enrollment->gpai_number }} Google Drive link"
                                            data-autosave data-original="{{ $enrollment->ops_document_url }}"
                                            class="h-8 w-full rounded-lg border border-transparent bg-transparent px-2 text-xs hover:border-slate-200 focus:border-slate-300 focus:bg-white">
                                    </form>
                                </td>
                            @endif

                            {{-- Remarks (and Notes in Table 2): saved when you leave the field or press Enter. --}}
                            @foreach ($isOps ? ['ops_remarks' => 'Remarks', 'ops_notes' => 'Notes'] : ['remarks' => 'Remarks'] as $field => $fieldLabel)
                                <td class="min-w-56 p-3 text-slate-600">
                                    @if ($field === 'remarks' && ! $readOnly)
                                        {{-- Received via IMSD / Email: saves as soon as it is picked. --}}
                                        <form method="POST" action="{{ route('gsis.enrollments.status', $enrollment) }}" class="mb-1">
                                            @csrf
                                            @method('PATCH')
                                            <select name="billing_received_via" onchange="this.form.submit()" aria-label="{{ $enrollment->gpai_number }} received via"
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-[11px] font-semibold {{ $enrollment->billing_received_via ? 'text-[#063b86]' : 'text-slate-400' }}">
                                                <option value="">Received via —</option>
                                                @foreach (\App\Models\GpaiEnrollment::RECEIVED_VIA as $value => $label)
                                                    <option value="{{ $value }}" @selected($enrollment->billing_received_via === $value)>Received via {{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                    @if ($readOnly)
                                        {{ $enrollment->{$field} }}
                                    @else
                                        <form method="POST" action="{{ route('gsis.enrollments.status', $enrollment) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input name="{{ $field }}" value="{{ $enrollment->{$field} }}" maxlength="3000"
                                                placeholder="Add {{ strtolower($fieldLabel) }}…" aria-label="{{ $enrollment->gpai_number }} {{ strtolower($fieldLabel) }}"
                                                data-autosave data-original="{{ $enrollment->{$field} }}"
                                                class="h-8 w-full rounded-lg border border-transparent bg-transparent px-2 text-xs hover:border-slate-200 focus:border-slate-300 focus:bg-white">
                                        </form>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $columnCount }}" class="p-8 text-center text-sm text-slate-400">
                                @if ($isOps)
                                    No Signed/Completed transmittals {{ $mode ? 'paid by '.\App\Models\GpaiEnrollment::modeLabel($mode).' ' : '' }}for FY {{ $fiscalYear }} yet. They appear here once their Table 1 status is Signed/Completed.
                                @else
                                    No GPAI transmittals {{ $stage ? 'at this stage ' : '' }}{{ $mode ? 'paid by '.\App\Models\GpaiEnrollment::modeLabel($mode).' ' : '' }}for FY {{ $fiscalYear }}.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        // Clicking a transmittal row opens it at its next GSIS step.
        document.addEventListener('click', function (event) {
            const row = event.target.closest('tr[data-row-href]');

            if (!row || event.target.closest('a, button, input, select, textarea, label, form')) {
                return;
            }

            window.location.href = row.dataset.rowHref;
        });

        // Inline remarks / notes save when changed (on blur or Enter).
        document.querySelectorAll('input[data-autosave]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.value !== input.dataset.original) {
                    input.form.submit();
                }
            });
        });
    </script>
@endpush
