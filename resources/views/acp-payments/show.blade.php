@extends('layouts.app')

@section('title', 'Through ACP Payment — '.$project->project_title)

@section('content')
@php
    $money = fn ($amount): string => number_format((float) $amount, 2);
    $officialAmount = $project->acpPayment?->amount ?? $project->total_project_cost;
@endphp

<x-page-header
    eyebrow="Through ACP"
    :title="$project->project_title"
    description="Record the approved ACP payment and the audited release of check to the proponent."
>
    <x-slot:actions>
        <a
            href="{{ route('dashboard') }}"
            class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            ← Dashboard
        </a>
        @if(! auth()->user()->isFocal() || in_array($project->status, [\App\Enums\ProjectStatus::FOR_PAYMENT, \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT], true))
            <a
                href="{{ route('projects.show', $project) }}"
                class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]"
            >
                Open Project Record
            </a>
        @endif
    </x-slot:actions>
</x-page-header>

@if($errors->any())
    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
        <div class="text-sm font-semibold text-rose-900">ACP transaction was not saved</div>
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
                <h2 class="text-sm font-semibold text-slate-900">ACP Financial Reference</h2>
                <p class="mt-1 text-xs text-slate-500">
                    The transaction amount is derived from the approved project cost on the server and is not accepted from hidden browser fields.
                </p>
            </div>
            <x-status-badge tone="info">{{ $project->status->label() }}</x-status-badge>
        </div>
    </div>

    <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'Project Code' => $project->approval?->project_code ?: '—',
            'ADL Number' => $project->allocation->adl->adl_number,
            'Implementation Mode' => $project->implementation_mode->label(),
            'Approved Project Cost' => '₱'.$money($project->total_project_cost),
            'Payee / Proponent' => $project->acpPayment?->payee ?: 'Not yet recorded',
            'Payment Date' => $project->acpPayment?->payment_date?->format('F d, Y') ?: 'Not yet recorded',
            'Check Number' => $project->acpCheckRelease?->check_number ?: 'Not yet released',
            'Released Date' => $project->acpCheckRelease?->released_date?->format('F d, Y') ?: 'Not yet released',
        ] as $label => $value)
            <div class="bg-white p-4">
                <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-sm font-semibold leading-5 text-slate-900">{{ $value }}</div>
            </div>
        @endforeach
    </div>
</section>

@if($project->status === \App\Enums\ProjectStatus::FOR_PAYMENT && ! $project->acpPayment)
    <section class="mt-5 overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
        <div class="border-b border-blue-100 bg-blue-50 px-5 py-4">
            <h2 class="text-sm font-semibold text-blue-950">Record Through ACP Payment</h2>
            <p class="mt-1 text-xs text-blue-700">
                Saving this record moves the project to For Release of Check to Proponent.
            </p>
        </div>

        <form method="POST" action="{{ route('projects.acp-payment.store', $project) }}" class="p-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">Official Payment Amount</label>
                    <input value="₱{{ $money($project->total_project_cost) }}" readonly class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-700">
                </div>
                <div>
                    <label for="payment_date" class="mb-2 block text-xs font-semibold text-slate-700">Payment Date</label>
                    <input id="payment_date" name="payment_date" type="date" required value="{{ old('payment_date', now()->toDateString()) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div>
                    <label for="payee" class="mb-2 block text-xs font-semibold text-slate-700">Payee / Proponent</label>
                    <input id="payee" name="payee" required maxlength="255" value="{{ old('payee', $project->partner) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div>
                    <label for="payment_reference" class="mb-2 block text-xs font-semibold text-slate-700">Payment Reference <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="payment_reference" name="payment_reference" maxlength="150" value="{{ old('payment_reference') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="payment_remarks" class="mb-2 block text-xs font-semibold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="payment_remarks" name="remarks" rows="3" maxlength="3000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Save ACP Payment
                </button>
            </div>
        </form>
    </section>
@endif

@if($project->acpPayment)
    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.1em] text-slate-400">Payment Recorded</div>
                <div class="mt-1 text-xl font-bold text-slate-900">₱{{ $money($project->acpPayment->amount) }}</div>
            </div>
            <div class="text-right text-xs text-slate-500">
                Recorded by {{ $project->acpPayment->recorder?->name ?: 'System' }}<br>
                {{ $project->acpPayment->created_at?->format('F d, Y h:i A') }}
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase text-slate-400">Payment Date</div><div class="mt-1 text-sm font-semibold">{{ $project->acpPayment->payment_date->format('F d, Y') }}</div></div>
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase text-slate-400">Payee / Proponent</div><div class="mt-1 text-sm font-semibold">{{ $project->acpPayment->payee }}</div></div>
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase text-slate-400">Reference</div><div class="mt-1 text-sm font-semibold">{{ $project->acpPayment->payment_reference ?: '—' }}</div></div>
        </div>
    </section>
@endif

@if($project->status === \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT && $project->acpPayment && ! $project->acpCheckRelease)
    <section class="mt-5 overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
        <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
            <h2 class="text-sm font-semibold text-amber-950">Release Check to Proponent</h2>
            <p class="mt-1 text-xs text-amber-800">
                At least one proof-of-release attachment is required. Saving moves the project to For Implementation.
            </p>
        </div>

        <form method="POST" action="{{ route('projects.acp-check-release.store', $project) }}" enctype="multipart/form-data" class="p-5">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="check_number" class="mb-2 block text-xs font-semibold text-slate-700">Check Number</label>
                    <input id="check_number" name="check_number" required maxlength="150" value="{{ old('check_number') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm uppercase">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold text-slate-700">Check Amount</label>
                    <input value="₱{{ $money($officialAmount) }}" readonly class="h-10 w-full rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-700">
                </div>
                <div>
                    <label for="check_date" class="mb-2 block text-xs font-semibold text-slate-700">Check Date</label>
                    <input id="check_date" name="check_date" type="date" required value="{{ old('check_date', now()->toDateString()) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div>
                    <label for="released_date" class="mb-2 block text-xs font-semibold text-slate-700">Date Released to Proponent</label>
                    <input id="released_date" name="released_date" type="date" required value="{{ old('released_date', now()->toDateString()) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="released_to" class="mb-2 block text-xs font-semibold text-slate-700">Released To</label>
                    <input id="released_to" name="released_to" required maxlength="255" value="{{ old('released_to', $project->acpPayment->payee) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="attachments" class="mb-2 block text-xs font-semibold text-slate-700">Proof of Check Release</label>
                    <input id="attachments" name="attachments[]" type="file" multiple required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <p class="mt-1 text-[11px] text-slate-400">PDF, image, Word, or Excel · maximum 10 MB per file.</p>
                </div>
                <div class="md:col-span-2">
                    <label for="release_remarks" class="mb-2 block text-xs font-semibold text-slate-700">Remarks <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="release_remarks" name="remarks" rows="3" maxlength="3000" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Record Check Release
                </button>
            </div>
        </form>
    </section>
@endif

@if($project->acpCheckRelease)
    <section class="mt-5 rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.1em] text-emerald-700">Check Released</div>
                <div class="mt-1 text-xl font-bold text-slate-900">{{ $project->acpCheckRelease->check_number }} · ₱{{ $money($project->acpCheckRelease->amount) }}</div>
            </div>
            <div class="text-right text-xs text-slate-500">
                Recorded by {{ $project->acpCheckRelease->recorder?->name ?: 'System' }}<br>
                {{ $project->acpCheckRelease->created_at?->format('F d, Y h:i A') }}
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase text-slate-400">Check Date</div><div class="mt-1 text-sm font-semibold">{{ $project->acpCheckRelease->check_date->format('F d, Y') }}</div></div>
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase text-slate-400">Released Date</div><div class="mt-1 text-sm font-semibold">{{ $project->acpCheckRelease->released_date->format('F d, Y') }}</div></div>
            <div class="rounded-lg bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase text-slate-400">Released To</div><div class="mt-1 text-sm font-semibold">{{ $project->acpCheckRelease->released_to }}</div></div>
        </div>

        <div class="mt-4">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Proof Attachments</div>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($project->acpCheckRelease->attachments as $attachment)
                    <a
                        href="{{ route('projects.acp-check-release.attachments.download', [$project, $attachment]) }}"
                        class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-[#063b86] hover:bg-slate-50"
                    >
                        {{ $attachment->original_name }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
