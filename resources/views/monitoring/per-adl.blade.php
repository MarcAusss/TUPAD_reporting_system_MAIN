@extends('layouts.app')
@section('title', 'PER ADL (Current)')
@section('content')
<x-page-header
    eyebrow="Monitoring"
    title="PER ADL (Current)"
    description="Workbook-aligned Focal fund monitoring generated from ADL allocations and the official project workflow."
/>

<section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <form method="GET" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="min-w-0 flex-1">
            <label class="mb-2 block text-xs font-semibold text-slate-700">
                ADL Filter
            </label>

            <select
                name="adl_id"
                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm sm:max-w-md"
            >
                <option value="">All ADLs</option>

                @foreach($adls as $adl)
                    <option value="{{ $adl->id }}" @selected(request('adl_id') == $adl->id)>
                        {{ $adl->adl_number }}
                    </option>
                @endforeach
            </select>
        </div>

        <button class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
            Apply Filter
        </button>

        @if(request()->filled('adl_id'))
            <a
                href="{{ route('fund-monitoring.per-adl-current') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
            >
                Clear
            </a>
        @endif
    </form>
</section>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Current ADL Monitoring Register</h2>
            <p class="mt-1 text-xs text-slate-500">
                Financial figures are system-generated. Use Manage ADL to update the source allocation record.
            </p>
        </div>

        <div class="text-[11px] font-semibold text-slate-400">
            Horizontal scroll available
        </div>
    </div>

    <div class="tupad-data-scroll overflow-x-auto">
        <table class="tupad-wide-table min-w-[3300px] w-full text-[11px]"><thead class="bg-[#ffe49a] text-[#172b4d]"><tr>
@foreach(['ADL No.','Fund Sponsor','LCE / Party-list','Province','District','City / Municipality','Alloc Grants','Admin Cost','Alloc Total','Realignment Grants','Date / No. MAF','Target Grants','Target Ben.','Obligated Grants','%','Wages','PPE','Insurance','Ben. Total','Female','Unutilized','IMSD / Payment','Implemented / Post-Docs','Ongoing Implementation','With NTP / For Implementation','Approved','For Approval','Under Evaluation','Available Balance','Remaining','Unused','Remarks','Action'] as $h)<th class="whitespace-nowrap border-r border-[#e7c96e] p-3 text-left font-bold">{{ $h }}</th>@endforeach
</tr></thead><tbody class="divide-y divide-slate-100">@forelse($rows as $r)<tr class="hover:bg-blue-50/30"><td class="p-3 font-bold text-[#063b86]">{{ $r['adl_number'] }}</td><td class="p-3">{{ $r['fund_sponsor'] }}</td><td class="p-3">{{ $r['lce_partylist'] }}</td><td class="p-3">{{ $r['province'] ?: '—' }}</td><td class="p-3">{{ $r['district'] ?: '—' }}</td><td class="p-3">{{ $r['municipality'] ?: '—' }}</td>
@foreach(['allocation_grants','allocation_admin_cost','allocation_total','realignment_grants'] as $k)<td class="p-3 text-right">₱{{ number_format($r[$k],2) }}</td>@endforeach<td class="p-3">{{ $r['maf'] ?: '—' }}</td><td class="p-3 text-right">₱{{ number_format($r['target_grants'],2) }}</td><td class="p-3 text-right">{{ number_format($r['target_beneficiaries']) }}</td><td class="p-3 text-right">₱{{ number_format($r['obligated_grants'],2) }}</td><td class="p-3 text-right font-semibold">{{ number_format($r['utilization'],2) }}%</td>
@foreach(['wages','ppe','insurance'] as $k)<td class="p-3 text-right">₱{{ number_format($r[$k],2) }}</td>@endforeach<td class="p-3 text-right">{{ number_format($r['beneficiaries']) }}</td><td class="p-3 text-right">{{ number_format($r['female']) }}</td>
@foreach(['unutilized','for_payment','post_docs','ongoing_implementation','for_implementation','approved','for_approval','under_evaluation','available_balance','remaining','unused'] as $k)<td class="p-3 text-right">₱{{ number_format($r[$k],2) }}</td>@endforeach<td class="p-3 max-w-[220px]">{{ $r['remarks'] ?: '—' }}</td><td class="p-3"><a href="{{ route('adl.show',$r['adl_id']) }}" class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-2.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">Manage ADL</a></td></tr>@empty<tr><td colspan="33" class="p-0"><x-empty-state title="No monitoring rows found" message="Create or allocate an ADL to populate the current monitoring register." /></td></tr>@endforelse</tbody></table></div></section>
@endsection
