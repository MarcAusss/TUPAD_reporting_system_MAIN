{{--
    Read-only Yearly Status for Admin, Focal and TUPAD Coordinators: one row
    per enrolled project, each with its GPAI No. and payment method. A TUPAD
    Coordinator only sees the projects of their own province.
--}}
@php
    $projectRows = $enrollments->flatMap(fn ($enrollment) => $enrollment->items
        ->filter(fn ($item) => $visibleProjectIds === null || in_array($item->project_id, $visibleProjectIds, true))
        ->map(fn ($item) => ['enrollment' => $enrollment, 'item' => $item]))
        ->values();
    $columnCount = $isOps ? 15 : 14;
@endphp

<div class="tupad-data-scroll overflow-x-auto">
    <table class="tupad-system-table tupad-wide-table w-full {{ $isOps ? 'min-w-[1900px]' : 'min-w-[1800px]' }} text-xs">
        <thead>
            <tr>
                <th class="p-3 text-left">No.</th>
                <th class="p-3 text-left">GPAI No.</th>
                <th class="p-3 text-left">Payment</th>
                <th class="p-3 text-left">{{ $visibleProjectIds !== null ? 'Your Projects' : 'Project' }}</th>
                <th class="p-3 text-left">Location</th>
                <th class="p-3 text-left">ADL No.</th>
                <th class="p-3 text-right">Benef. Enrolled</th>
                <th class="p-3 text-right">Premium</th>
                @if ($isOps)
                    <th class="p-3 text-left">Date of On-call Payment Slip</th>
                    <th class="p-3 text-left">Validated On-call Payment Slip in Cashier</th>
                    <th class="p-3 text-left">Emailed GSIS for OR &amp; Policy</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">OR Number</th>
                    <th class="p-3 text-left">File / Document</th>
                    <th class="p-3 text-left">Remarks</th>
                @else
                    <th class="p-3 text-left">Emailed for Issuance of Insurance Bill</th>
                    <th class="p-3 text-left">Received SOA / Billing</th>
                    <th class="p-3 text-left">Release for Routing</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Distributed to PCs</th>
                    <th class="p-3 text-left">Remarks</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @if ($projectRows->isNotEmpty())
                <tr class="font-bold">
                    <td class="p-3" colspan="6">Total ({{ $projectRows->count() }} {{ str('project')->plural($projectRows->count()) }})</td>
                    <td class="p-3 text-right">{{ number_format($projectRows->sum(fn ($row) => $row['item']->beneficiaries)) }}</td>
                    <td class="p-3 text-right">{{ $peso($projectRows->sum(fn ($row) => $row['item']->premiumCents())) }}</td>
                    <td colspan="{{ $columnCount - 8 }}"></td>
                </tr>
            @endif
            @forelse ($projectRows as $row)
                @php
                    $enrollment = $row['enrollment'];
                    $item = $row['item'];
                    $project = $item->project;
                    $statusLabel = $isOps ? $enrollment->opsStatusLabel() : $enrollment->billingStatusLabel();
                    $tone = $isOps ? $opsTone($enrollment->ops_status) : $billingTone($enrollment->billing_status);
                @endphp
                <tr>
                    <td class="p-3 text-slate-500">{{ $loop->iteration }}</td>
                    <td class="whitespace-nowrap p-3 font-semibold text-slate-900">{{ $enrollment->gpai_number }}</td>
                    <td class="whitespace-nowrap p-3">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $enrollment->payment_mode === \App\Models\GpaiEnrollment::MODE_CASH_ADVANCE ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }}">
                            {{ \App\Models\GpaiEnrollment::modeLabel($enrollment->payment_mode) }}{{ $enrollment->cashAdvance ? ' · '.$enrollment->cashAdvance->label() : '' }}
                        </span>
                    </td>
                    <td class="min-w-64 p-3 font-semibold text-slate-800">{{ $project?->project_title ?? '—' }}</td>
                    <td class="min-w-40 p-3 text-slate-600">{{ collect([$project?->municipality, $project?->province])->filter()->implode(', ') ?: '—' }}</td>
                    <td class="whitespace-nowrap p-3 text-slate-600">{{ $item->adl_number ?: '—' }}</td>
                    <td class="p-3 text-right">{{ number_format($item->beneficiaries) }}</td>
                    <td class="p-3 text-right font-semibold">{{ $peso($item->premiumCents()) }}</td>
                    @if ($isOps)
                        <td class="whitespace-nowrap p-3">{{ $date($enrollment->payment_slip_on) }}</td>
                        <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->payment_slip_validated_at) }}</td>
                        <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->or_policy_requested_at) }}</td>
                        <td class="whitespace-nowrap p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $tone }}">{{ $statusLabel ?? '—' }}</span></td>
                        <td class="whitespace-nowrap p-3">{{ $enrollment->or_number }}</td>
                        <td class="whitespace-nowrap p-3">
                            @if ($enrollment->ops_document_url)
                                <a href="{{ $enrollment->ops_document_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#063b86] hover:underline">Open Google Drive ↗</a>
                            @endif
                        </td>
                        <td class="min-w-48 p-3 text-slate-600">{{ $enrollment->ops_remarks }}</td>
                    @else
                        <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->billing_emailed_at) }}</td>
                        <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->soa_received_at) }}</td>
                        <td class="whitespace-nowrap p-3">{{ $date($enrollment->routing_released_on) }}</td>
                        <td class="whitespace-nowrap p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $tone }}">{{ $statusLabel ?? '—' }}</span></td>
                        <td class="whitespace-nowrap p-3">{{ $dateTime($enrollment->distributed_on) }}</td>
                        <td class="min-w-48 p-3 text-slate-600">
                            @if ($enrollment->receivedViaLabel())
                                <span class="mr-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-[#063b86]">Received via {{ $enrollment->receivedViaLabel() }}</span>
                            @endif
                            {{ $enrollment->remarks }}
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount }}" class="p-8 text-center text-sm text-slate-400">
                        @if ($isOps)
                            No Signed/Completed transmittals {{ $mode ? 'paid by '.\App\Models\GpaiEnrollment::modeLabel($mode).' ' : '' }}for FY {{ $fiscalYear }} yet. They appear here once their Table 1 status is Signed/Completed.
                        @else
                            No enrolled projects {{ $stage ? 'at this stage ' : '' }}{{ $mode ? 'paid by '.\App\Models\GpaiEnrollment::modeLabel($mode).' ' : '' }}for FY {{ $fiscalYear }}.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
