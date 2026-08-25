@extends('layouts.app')
@section('title', $provinceName.' Project Monitoring')
@section('content')
<x-page-header
    eyebrow="Provincial Project Register"
    :title="strtoupper($provinceName).' Project Monitoring'"
    description="TC project register aligned with the provincial workbook sheet and official project workflow."
/>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Project Monitoring Register</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Dense workbook-aligned register. Use the actions on the right to update monitoring details or open the official project.
                </p>
            </div>

            <div class="text-[11px] font-semibold text-slate-400">
                Horizontal scroll available
            </div>
        </div>

        <div class="tupad-data-scroll overflow-x-auto">
            <table class="tupad-wide-table min-w-[3100px] w-full text-[11px]"><thead class="bg-[#eaf2ff] text-[#10294f]"><tr>@foreach(['Status','ADL No.','Fund Sponsor','LCE / Party-list','Project Code','Project Series','Receipt Month','Receipt Date & Time','Cycle Days','Project Title','Nature','Proponent','Mode','Days','Barangay','Municipality','Province','District','Income Class','Beneficiaries','Female','Female Amount','Youth','Youth Amount','Youth Female','Youth Female Amount','Senior','Senior Amount','Senior Female','Senior Female Amount','PWD','PWD Amount','PWD Female','PWD Female Amount','Rebel Returnee','RR Amount','RR Female','RR Female Amount','Wages','PPE','Insurance','Total Cost','NTP','Work Start','Work End','Obligation','Post-Docs','Action'] as $h)<th class="whitespace-nowrap border-r border-[#d8e4f3] p-3 text-left font-bold">{{ $h }}</th>@endforeach</tr></thead><tbody class="divide-y">@forelse($projects as $project)
@php
$beneficiaries=$project->beneficiaries; $female=$beneficiaries->where('sex','female');
$youth=$beneficiaries->filter(fn($b)=>$b->isYouth()); $senior=$beneficiaries->filter(fn($b)=>$b->isSeniorCitizen()); $pwd=$beneficiaries->where('is_pwd',true); $rr=$beneficiaries->where('is_rebel_returnee',true);
@endphp
<tr class="hover:bg-slate-50"><td class="p-3 font-semibold">{{ $project->status->label() }}</td><td class="p-3">{{ $project->allocation->adl->adl_number }}</td><td class="p-3">{{ $project->fund_sponsor }}</td><td class="p-3">{{ $project->partner }}</td><td class="p-3">{{ $project->approval?->project_code ?: '—' }}</td><td class="p-3">{{ $project->monitoringDetail?->project_series ?: '—' }}</td><td class="p-3">{{ $project->monitoringDetail?->receipt_month ?: $project->date_received->format('F') }}</td><td class="p-3">{{ $project->monitoringDetail?->receipt_datetime?->format('m/d/Y h:i A') ?: $project->date_received->format('m/d/Y') }}</td><td class="p-3 text-right">{{ $project->monitoringDetail?->process_cycle_days ?? '—' }}</td><td class="p-3 max-w-[260px]">{{ $project->project_title }}</td><td class="p-3 max-w-[260px]">{{ $project->nature_of_work }}</td><td class="p-3">{{ $project->monitoringDetail?->proponent ?: '—' }}</td><td class="p-3">{{ $project->implementation_mode->label() }}</td><td class="p-3 text-right">{{ $project->number_of_days }}</td><td class="p-3">{{ $project->barangay }}</td><td class="p-3">{{ $project->municipality }}</td><td class="p-3">{{ $project->province }}</td><td class="p-3">{{ $project->district }}</td><td class="p-3">{{ $project->income_class ?: '—' }}</td><td class="p-3 text-right">{{ $project->beneficiaries_total }}</td><td class="p-3 text-right">{{ $project->beneficiaries_female }}</td><td class="p-3 text-right">₱{{ number_format($female->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $youth->count() }}</td><td class="p-3 text-right">₱{{ number_format($youth->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $youth->where('sex','female')->count() }}</td><td class="p-3 text-right">₱{{ number_format($youth->where('sex','female')->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $senior->count() }}</td><td class="p-3 text-right">₱{{ number_format($senior->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $senior->where('sex','female')->count() }}</td><td class="p-3 text-right">₱{{ number_format($senior->where('sex','female')->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $pwd->count() }}</td><td class="p-3 text-right">₱{{ number_format($pwd->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $pwd->where('sex','female')->count() }}</td><td class="p-3 text-right">₱{{ number_format($pwd->where('sex','female')->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $rr->count() }}</td><td class="p-3 text-right">₱{{ number_format($rr->sum('grant_amount'),2) }}</td><td class="p-3 text-right">{{ $rr->where('sex','female')->count() }}</td><td class="p-3 text-right">₱{{ number_format($rr->where('sex','female')->sum('grant_amount'),2) }}</td><td class="p-3 text-right">₱{{ number_format($project->wages_total,2) }}</td><td class="p-3 text-right">₱{{ number_format($project->ppe_total,2) }}</td><td class="p-3 text-right">₱{{ number_format($project->insurance_total,2) }}</td><td class="p-3 text-right font-semibold">₱{{ number_format($project->total_project_cost,2) }}</td><td class="p-3">{{ $project->noticeToProceed?->date_released?->format('m/d/Y') ?: '—' }}</td><td class="p-3">{{ $project->implementation?->start_date?->format('m/d/Y') ?: '—' }}</td><td class="p-3">{{ $project->implementation?->end_date?->format('m/d/Y') ?: '—' }}</td><td class="p-3 text-right">{{ $project->obligation ? '₱'.number_format($project->obligation->amount,2) : '—' }}</td><td class="p-3 text-center">{{ $project->postDocuments->count() }}</td><td class="p-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('projects.monitoring.edit',$project) }}" class="inline-flex h-8 items-center rounded-md border border-blue-200 bg-blue-50 px-2.5 font-semibold text-blue-800 hover:bg-blue-100">
                Edit Register
            </a>
            <a href="{{ route('projects.show',$project) }}" class="inline-flex h-8 items-center rounded-md border border-slate-300 bg-white px-2.5 font-semibold text-slate-700 hover:bg-slate-50">
                Open Project
            </a>
        </div>
    </td></tr>
@empty<tr><td colspan="48" class="p-0"><x-empty-state :title="'No projects found for '.$provinceName" message="Projects will appear here when official project records exist for this province." /></td></tr>@endforelse</tbody></table></div></section><div class="mt-5">{{ $projects->links() }}</div>
@endsection
