@extends('layouts.app')
@section('title', $adl->adl_number)
@section('content')
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div><a href="{{ route('adl.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">← ADL Management</a><h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $adl->adl_number }}</h1><p class="mt-1 text-sm text-slate-500">Fund control, allocation, re-alignment and PER ADL monitoring.</p></div>
    <div class="flex gap-2"><a href="{{ route('fund-monitoring.per-adl-current', ['adl_id' => $adl->id]) }}" class="inline-flex h-10 items-center rounded-lg border border-[#b9cbe3] bg-white px-4 text-sm font-semibold text-[#063b86]">PER ADL Summary</a><a href="{{ route('adl.edit', $adl) }}" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white">Edit ADL</a></div>
</div>
<x-page-alerts />

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['Original Grants', $adl->grants],
        ['Re-alignment', $adl->total_realignment],
        ['Adjusted Grants', $adl->adjusted_grants],
        ['Remaining Grant Balance', $adl->remaining_balance],
    ] as [$label,$value])
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase text-slate-400">{{ $label }}</div><div class="mt-3 text-xl font-bold text-slate-900">₱{{ number_format($value,2) }}</div></div>
    @endforeach
</div>

<section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">ADL Reference Information</h2></div>
    <div class="grid gap-x-8 gap-y-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Date Received' => $adl->date_received?->format('M d, Y') ?: '—',
            'Batch' => $adl->batch ?: '—', 'Tranche' => $adl->tranche ?: '—',
            'Sponsor / Reference' => $adl->sponsor_reference ?: '—',
            'NFA' => collect([$adl->nfa_date?->format('M d, Y'), $adl->nfa_number])->filter()->implode(' / ') ?: '—',
            'NTA' => collect([$adl->nta_date?->format('M d, Y'), $adl->nta_number])->filter()->implode(' / ') ?: '—',
            'Admin Cost' => '₱'.number_format($adl->admin_cost,2), 'Total ADL' => '₱'.number_format($adl->total,2),
        ] as $label=>$value)
        <div><div class="text-xs text-slate-500">{{ $label }}</div><div class="mt-1 text-sm font-semibold text-slate-800">{{ $value }}</div></div>
        @endforeach
    </div>
</section>

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><div><h2 class="text-sm font-semibold text-slate-900">PER ADL (Current) Summary</h2><p class="mt-1 text-xs text-slate-500">Calculated from allocations, official projects, obligations and project statuses.</p></div></div>
    <div class="overflow-x-auto"><table class="min-w-[2200px] w-full text-xs">
        <thead class="bg-[#fff0bf] text-[#10294f]"><tr><th class="p-3 text-left">Fund Sponsor</th><th class="p-3 text-left">LCE / Party-list</th><th class="p-3 text-left">Province</th><th class="p-3 text-left">District</th><th class="p-3 text-left">Municipality</th><th class="p-3 text-right">Allocation Grants</th><th class="p-3 text-right">Admin Cost</th><th class="p-3 text-right">Allocation Total</th><th class="p-3 text-right">Re-alignment</th><th class="p-3 text-left">MAF</th><th class="p-3 text-right">Target Grants</th><th class="p-3 text-right">Target Ben.</th><th class="p-3 text-right">Obligated</th><th class="p-3 text-right">%</th><th class="p-3 text-right">Wages</th><th class="p-3 text-right">PPE</th><th class="p-3 text-right">Insurance</th><th class="p-3 text-right">Ben.</th><th class="p-3 text-right">Female</th><th class="p-3 text-right">Unutilized</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse($perAdlRows as $row)<tr class="hover:bg-slate-50"><td class="p-3">{{ $row['fund_sponsor'] }}</td><td class="p-3">{{ $row['lce_partylist'] }}</td><td class="p-3">{{ $row['province'] ?: '—' }}</td><td class="p-3">{{ $row['district'] ?: '—' }}</td><td class="p-3">{{ $row['municipality'] ?: '—' }}</td>@foreach(['allocation_grants','allocation_admin_cost','allocation_total','realignment_grants'] as $k)<td class="p-3 text-right">₱{{ number_format($row[$k],2) }}</td>@endforeach<td class="p-3">{{ $row['maf'] ?: '—' }}</td><td class="p-3 text-right">₱{{ number_format($row['target_grants'],2) }}</td><td class="p-3 text-right">{{ number_format($row['target_beneficiaries']) }}</td><td class="p-3 text-right">₱{{ number_format($row['obligated_grants'],2) }}</td><td class="p-3 text-right">{{ number_format($row['utilization'],2) }}%</td>@foreach(['wages','ppe','insurance'] as $k)<td class="p-3 text-right">₱{{ number_format($row[$k],2) }}</td>@endforeach<td class="p-3 text-right">{{ number_format($row['beneficiaries']) }}</td><td class="p-3 text-right">{{ number_format($row['female']) }}</td><td class="p-3 text-right">₱{{ number_format($row['unutilized'],2) }}</td></tr>@empty<tr><td colspan="20" class="p-8 text-center text-slate-400">No allocations yet.</td></tr>@endforelse</tbody>
    </table></div>
</section>

<div class="mt-5 grid gap-5 xl:grid-cols-2">
<section class="rounded-xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Add Allocation</h2></div><form method="POST" action="{{ route('adl.allocations.store',$adl) }}" class="grid gap-4 p-5 md:grid-cols-2">@csrf
    <div><label class="mb-1 block text-xs font-semibold">Fund Sponsor</label><input name="fund_sponsor" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div><label class="mb-1 block text-xs font-semibold">Partner</label><input name="partner" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold">Local Chief Executive / Party-list</label><input name="local_chief_executive_partylist" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div><label class="mb-1 block text-xs font-semibold">Province</label><input name="province" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div><div><label class="mb-1 block text-xs font-semibold">District</label><input name="district" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div><label class="mb-1 block text-xs font-semibold">City / Municipality</label><input name="municipality" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div><div><label class="mb-1 block text-xs font-semibold">Location Display</label><input name="location" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div><label class="mb-1 block text-xs font-semibold">Grant Allocation</label><input type="number" step="0.01" min="0.01" name="grant_amount" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div><div><label class="mb-1 block text-xs font-semibold">Admin Cost Allocation</label><input type="number" step="0.01" min="0" name="admin_cost_amount" value="0" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold">Remarks</label><textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></div><div class="md:col-span-2 text-right"><button class="h-10 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white">Save Allocation</button></div>
</form></section>
<section class="rounded-xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Add Re-alignment / MAF</h2></div><form method="POST" action="{{ route('adl.realignments.store',$adl) }}" class="grid gap-4 p-5 md:grid-cols-2">@csrf
    <div><label class="mb-1 block text-xs font-semibold">Amount</label><input type="number" step="0.01" name="amount" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div><div><label class="mb-1 block text-xs font-semibold">Realignment Date</label><input type="date" name="realignment_date" required class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
    <div><label class="mb-1 block text-xs font-semibold">MAF Date</label><input type="date" name="maf_date" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div><div><label class="mb-1 block text-xs font-semibold">MAF Number</label><input name="maf_number" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div><input type="hidden" name="reference_number" value=""><div class="md:col-span-2"><label class="mb-1 block text-xs font-semibold">Reason / Remarks</label><textarea name="reason" rows="2" class="w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></div><div class="md:col-span-2 text-right"><button class="h-10 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white">Save Re-alignment</button></div>
</form></section>
</div>

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-200 px-5 py-4"><h2 class="text-sm font-semibold">Allocation Records</h2></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50"><tr><th class="p-3 text-left">Sponsor / LCE</th><th class="p-3 text-left">Location</th><th class="p-3 text-right">Grants</th><th class="p-3 text-right">Admin</th><th class="p-3 text-right">Total</th></tr></thead><tbody class="divide-y">@foreach($adl->allocations as $a)<tr><td class="p-3"><b>{{ $a->fund_sponsor }}</b><div class="text-xs text-slate-500">{{ $a->local_chief_executive_partylist ?: $a->partner }}</div></td><td class="p-3">{{ collect([$a->province,$a->district,$a->municipality])->filter()->implode(' / ') ?: $a->location }}</td><td class="p-3 text-right">₱{{ number_format($a->grant_amount ?? $a->amount,2) }}</td><td class="p-3 text-right">₱{{ number_format($a->admin_cost_amount ?? 0,2) }}</td><td class="p-3 text-right font-semibold">₱{{ number_format($a->total_amount ?? $a->amount,2) }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
