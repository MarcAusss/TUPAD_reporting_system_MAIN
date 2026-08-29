@extends('layouts.app')

@section('title', 'Through ACP Liquidation — '.$project->project_title)

@section('content')
@php
    $moneyFromCents = fn (int $cents): string => number_format($cents / 100, 2);
@endphp

<x-page-header
    eyebrow="Through ACP"
    :title="$project->project_title"
    description="Record audited liquidation submissions against the amount released to the ACP proponent."
>
    <x-slot:actions>
        <a
            href="{{ route('projects.show', $project) }}"
            class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            ← Project Record
        </a>
    </x-slot:actions>
</x-page-header>

@if($errors->any())
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
        <div class="text-sm font-semibold text-rose-900">Liquidation was not saved</div>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-700">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Liquidation Summary</h2>
                <p class="mt-1 text-xs text-slate-500">
                    The required liquidatable amount comes from the recorded check release. Each submission reduces the remaining balance; over-liquidation is rejected server-side.
                </p>
            </div>
            <x-status-badge tone="info">{{ $project->status->label() }}</x-status-badge>
        </div>
    </div>

    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        <div class="bg-white p-4">
            <div class="text-[10px] font-bold uppercase text-slate-400">Released Amount</div>
            <div class="mt-1 text-xl font-bold text-slate-900">₱{{ $moneyFromCents($summary['required_cents']) }}</div>
        </div>
        <div class="bg-white p-4">
            <div class="text-[10px] font-bold uppercase text-slate-400">Liquidated</div>
            <div class="mt-1 text-xl font-bold text-slate-900">₱{{ $moneyFromCents($summary['liquidated_cents']) }}</div>
        </div>
        <div class="bg-white p-4">
            <div class="text-[10px] font-bold uppercase text-slate-400">Remaining Balance</div>
            <div class="mt-1 text-xl font-bold text-slate-900">₱{{ $moneyFromCents($summary['remaining_cents']) }}</div>
        </div>
        <div class="bg-white p-4">
            <div class="text-[10px] font-bold uppercase text-slate-400">Liquidation Progress</div>
            <div class="mt-1 text-xl font-bold text-slate-900">{{ $summary['progress_percent'] }}%</div>
        </div>
    </div>
</section>

@if(
    in_array(
        $project->status,
        [\App\Enums\ProjectStatus::FOR_LIQUIDATION, \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED],
        true
    )
    && $summary['remaining_cents'] > 0
)
    <section class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
        <div class="border-b border-blue-100 bg-blue-50 px-5 py-4">
            <h2 class="text-sm font-semibold text-blue-950">Record Liquidation Submission</h2>
            <p class="mt-1 text-xs text-blue-700">
                A partial amount moves the project to Partially Liquidated. Reaching the full released amount completes the Through ACP project.
            </p>
        </div>

        <form method="POST" action="{{ route('projects.acp-liquidations.store', $project) }}" enctype="multipart/form-data" class="p-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="liquidation_date" class="mb-2 block text-xs font-semibold text-slate-700">Liquidation Date</label>
                    <input
                        id="liquidation_date"
                        name="liquidation_date"
                        type="date"
                        required
                        min="{{ $project->implementation?->end_date?->toDateString() }}"
                        value="{{ old('liquidation_date', now()->toDateString()) }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                </div>
                <div>
                    <label for="amount" class="mb-2 block text-xs font-semibold text-slate-700">Liquidation Amount</label>
                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                        value="{{ old('amount') }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                    <p class="mt-1 text-[11px] text-slate-400">Maximum remaining balance: ₱{{ $moneyFromCents($summary['remaining_cents']) }}</p>
                </div>
                <div>
                    <label for="liquidation_reference" class="mb-2 block text-xs font-semibold text-slate-700">Liquidation Reference <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="liquidation_reference" name="liquidation_reference" maxlength="150" value="{{ old('liquidation_reference') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div>
                    <label for="attachments" class="mb-2 block text-xs font-semibold text-slate-700">Liquidation Attachments</label>
                    <input id="attachments" name="attachments[]" type="file" multiple required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <p class="mt-1 text-[11px] text-slate-400">PDF, image, Word, or Excel · maximum 10 MB per file.</p>
                </div>
                <div class="md:col-span-2">
                    <label for="remarks" class="mb-2 block text-xs font-semibold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="3000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Save Liquidation
                </button>
            </div>
        </form>
    </section>
@endif

<section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Liquidation History</h2>
        <p class="mt-1 text-xs text-slate-500">Audited submissions are retained as separate records and are not overwritten.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Reference</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Amount</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Recorded By</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Attachments</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($project->acpLiquidations as $liquidation)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $liquidation->liquidation_date->format('F d, Y') }}</td>
                        <td class="px-5 py-4 text-sm text-slate-600">{{ $liquidation->liquidation_reference ?: '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-slate-900">₱{{ number_format((float) $liquidation->amount, 2) }}</td>
                        <td class="px-5 py-4 text-sm text-slate-600">{{ $liquidation->recorder?->name ?: 'System' }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach($liquidation->attachments as $attachment)
                                    <a
                                        href="{{ route('projects.acp-liquidations.attachments.download', [$project, $attachment]) }}"
                                        class="inline-flex rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#063b86] hover:bg-slate-50"
                                    >
                                        {{ $attachment->original_name }}
                                    </a>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">No Through ACP liquidation has been recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
