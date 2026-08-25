@extends('layouts.app')
@section('title', 'PER PROVINCE (Current)')
@section('content')
<x-page-header
    eyebrow="Monitoring"
    title="PER PROVINCE (Current)"
    description="Regional aggregation of the current PER ADL monitoring register by province."
/>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Provincial Fund Position</h2>
                <p class="mt-1 text-xs text-slate-500">Compare utilization and remaining balances across provinces.</p>
            </div>
            <div class="text-[11px] font-semibold text-slate-400">Horizontal scroll available</div>
        </div>
        <div class="tupad-data-scroll overflow-x-auto"><table class="tupad-wide-table min-w-[1500px] w-full text-xs"><thead class="bg-[#ffe49a]"><tr>@foreach(['Province','Allocation','Target Grants','Target Ben.','Obligated','Utilization','Wages','PPE','Insurance','Ben.','Female','Unutilized','On Payment','Post-Docs','Ongoing','For Impl.','Approved','For Approval','Evaluation','Available Balance'] as $h)<th class="p-3 text-left">{{ $h }}</th>@endforeach</tr></thead><tbody class="divide-y">@foreach($provinces as $p)<tr><td class="p-3 font-bold text-[#063b86]">{{ $p['province'] }}</td>@foreach(['allocation_total','target_grants'] as $k)<td class="p-3 text-right">₱{{ number_format($p[$k],2) }}</td>@endforeach<td class="p-3 text-right">{{ number_format($p['target_beneficiaries']) }}</td><td class="p-3 text-right">₱{{ number_format($p['obligated_grants'],2) }}</td><td class="p-3 text-right">{{ number_format($p['utilization'],2) }}%</td>@foreach(['wages','ppe','insurance'] as $k)<td class="p-3 text-right">₱{{ number_format($p[$k],2) }}</td>@endforeach<td class="p-3 text-right">{{ number_format($p['beneficiaries']) }}</td><td class="p-3 text-right">{{ number_format($p['female']) }}</td>@foreach(['unutilized','for_payment','post_docs','ongoing_implementation','for_implementation','approved','for_approval','under_evaluation','available_balance'] as $k)<td class="p-3 text-right">₱{{ number_format($p[$k],2) }}</td>@endforeach</tr>@endforeach</tbody></table></div></section>
@endsection
