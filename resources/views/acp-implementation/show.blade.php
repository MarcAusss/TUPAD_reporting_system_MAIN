@extends('layouts.app')

@section('title', 'Through ACP Implementation — '.$project->project_title)

@section('content')
@php
    $implementation = $project->implementation;
@endphp

<x-page-header
    eyebrow="Through ACP"
    :title="$project->project_title"
    description="Schedule and monitor the implementation period after the check has been released to the proponent."
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
        <div class="text-sm font-semibold text-rose-900">Implementation period was not saved</div>
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
                <h2 class="text-sm font-semibold text-slate-900">ACP Implementation Reference</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Implementation is available only after the audited check-release record exists. The end date is calculated from the approved project duration.
                </p>
            </div>
            <x-status-badge tone="info">{{ $project->status->label() }}</x-status-badge>
        </div>
    </div>

    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Project Code' => $project->approval?->project_code ?: '—',
            'ADL Number' => $project->allocation->adl->adl_number,
            'Check Number' => $project->acpCheckRelease?->check_number ?: 'Not recorded',
            'Check Released' => $project->acpCheckRelease?->released_date?->format('F d, Y') ?: 'Not recorded',
            'Approved Duration' => $project->number_of_days.' day(s)',
            'Start Date' => $implementation?->start_date?->format('F d, Y') ?: 'Not scheduled',
            'End Date' => $implementation?->end_date?->format('F d, Y') ?: 'Not scheduled',
            'Implementation Mode' => $project->implementation_mode->label(),
        ] as $label => $value)
            <div class="bg-white p-4">
                <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-sm font-semibold leading-5 text-slate-900">{{ $value }}</div>
            </div>
        @endforeach
    </div>
</section>

@if($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)
    <section class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
        <div class="border-b border-blue-100 bg-blue-50 px-5 py-4">
            <h2 class="text-sm font-semibold text-blue-950">Set Through ACP Implementation Period</h2>
            <p class="mt-1 text-xs text-blue-700">
                Choose only the start date. The system calculates the end date from the approved {{ $project->number_of_days }}-day duration and synchronizes the project status using Asia/Manila dates.
            </p>
        </div>

        <form method="POST" action="{{ route('projects.acp-implementation.store', $project) }}" class="p-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="start_date" class="mb-2 block text-xs font-semibold text-slate-700">Implementation Start Date</label>
                    <input
                        id="start_date"
                        name="start_date"
                        type="date"
                        required
                        min="{{ $project->acpCheckRelease?->released_date?->toDateString() }}"
                        value="{{ old('start_date', $implementation?->start_date?->toDateString() ?? now()->toDateString()) }}"
                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"
                    >
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">Calculated End Date</label>
                    <input
                        value="{{ $implementation?->end_date?->format('F d, Y') ?: 'Calculated after saving' }}"
                        readonly
                        class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-700"
                    >
                </div>
                <div class="md:col-span-2">
                    <label for="remarks" class="mb-2 block text-xs font-semibold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="remarks" name="remarks" rows="3" maxlength="3000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $implementation?->remarks) }}</textarea>
                </div>
            </div>

            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Save Implementation Period
                </button>
            </div>
        </form>
    </section>
@endif

@if($implementation)
    <section class="mt-5 rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.1em] text-emerald-700">Implementation Period Recorded</div>
                <div class="mt-1 text-lg font-bold text-slate-900">
                    {{ $implementation->start_date->format('F d, Y') }} → {{ $implementation->end_date->format('F d, Y') }}
                </div>
            </div>
            <div class="text-right text-xs text-slate-500">
                Recorded by {{ $implementation->recorder?->name ?: 'System' }}<br>
                {{ $implementation->created_at?->format('F d, Y h:i A') }}
            </div>
        </div>
        @if($implementation->remarks)
            <div class="mt-4 rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                {{ $implementation->remarks }}
            </div>
        @endif
    </section>
@endif

@if(in_array($project->status, [\App\Enums\ProjectStatus::FOR_LIQUIDATION, \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED, \App\Enums\ProjectStatus::COMPLETED], true))
    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
        The implementation period has ended. Financial liquidation is handled by the authorized Admin/Focal workflow.
    </div>
@endif
@endsection
