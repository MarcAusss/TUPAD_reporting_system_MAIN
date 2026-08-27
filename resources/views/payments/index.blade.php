@extends('layouts.app')

@section('title', 'Payment of Wages Queue')

@section('content')
<x-page-header
    eyebrow="Payment"
    title="Payment of Wages Queue"
    description="Record wage obligations and their corresponding disbursements for projects that completed post-document processing."
/>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">Payment Processing</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ number_format($projects->total()) }} project record(s)
            </p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-[1050px] w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Project</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">ADL / Code</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Payable Wages</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Obligated</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Disbursed</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Payment State</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse($projects as $project)
                    @php
                        $totalObligated = (float) $project->obligations->sum('amount');
                        $totalDisbursed = (float) $project->obligations->sum(
                            fn ($obligation) => (float) $obligation->disbursements->sum('amount')
                        );
                        $fullyDisbursed =
                            $totalObligated === (float) $project->wages_total
                            && $totalDisbursed === (float) $project->wages_total;

                        $paymentState = match (true) {
                            $project->status === \App\Enums\ProjectStatus::COMPLETED
                                && $fullyDisbursed =>
                                ['Fully Disbursed', 'success'],
                            $project->status === \App\Enums\ProjectStatus::COMPLETED =>
                                ['Legacy Completed', 'warning'],
                            $totalDisbursed > 0 =>
                                ['Partially Disbursed', 'warning'],
                            $totalObligated > 0 =>
                                ['Obligation Recorded', 'info'],
                            default => ['Add Obligation', 'warning'],
                        };
                    @endphp

                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-4">
                            <div class="text-sm font-semibold text-slate-900">
                                {{ $project->project_title }}
                            </div>
                            <div class="mt-1 text-xs text-slate-400">
                                {{ $project->payment_location_summary }}
                            </div>
                        </td>

                        <td class="px-5 py-4 text-xs text-slate-600">
                            <div class="font-semibold text-slate-800">
                                {{ $project->allocation->adl->adl_number }}
                            </div>
                            <div class="mt-1">
                                {{ $project->approval?->project_code ?: 'No project code' }}
                            </div>
                        </td>

                        <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                            ₱{{ number_format($project->wages_total, 2) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-700">
                            ₱{{ number_format($totalObligated, 2) }}
                        </td>

                        <td class="px-5 py-4 text-right text-sm text-slate-700">
                            ₱{{ number_format($totalDisbursed, 2) }}
                        </td>

                        <td class="px-5 py-4">
                            <x-status-badge :tone="$paymentState[1]">
                                {{ $paymentState[0] }}
                            </x-status-badge>
                        </td>

                        <td class="px-5 py-4 text-right">
                            <a
                                href="{{ route('payments.show', $project) }}"
                                class="inline-flex h-9 items-center justify-center rounded-lg bg-[#063b86] px-3 text-xs font-semibold text-white hover:bg-[#052f6b]"
                            >
                                Manage Payment
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-0">
                            <x-empty-state
                                title="No projects waiting for payment"
                                message="Projects appear here after complete post-documentary requirements move them to For Payment."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="mt-5">
    {{ $projects->links() }}
</div>
@endsection
