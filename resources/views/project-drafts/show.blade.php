@extends('layouts.app')

@section('title', $draft->project_title)

@section('content')

    <div class="mx-auto max-w-6xl">

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>

                <a href="{{ route('project-drafts.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                    ← Project Drafts
                </a>

                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                    {{ $draft->project_title }}
                </h1>

                <div class="mt-2">
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        {{ $draft->status->label() }}
                    </span>
                </div>

            </div>

            @if ($draft->canBeEdited())
                <a href="{{ route('project-drafts.edit', $draft) }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Edit Draft
                </a>
            @endif

        </div>

        @if (session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($draft->status->value === 'returned_for_correction')
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-5">

                <div class="text-sm font-semibold text-red-800">
                    Returned for Correction
                </div>

                <p class="mt-2 text-sm leading-6 text-red-700">
                    {{ $draft->tc_review_remarks }}
                </p>

            </div>
        @endif

        @if ($draft->isConfirmed() && $draft->confirmedProject)
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-5">

                <div class="text-sm font-semibold text-emerald-800">
                    Confirmed as Official Project
                </div>

                <a href="{{ route('projects.show', $draft->confirmedProject) }}"
                    class="mt-2 inline-block text-sm font-semibold text-emerald-700 underline">
                    View official project
                </a>

            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs text-slate-500">Wages</div>
                <div class="mt-2 text-xl font-bold text-slate-900">
                    ₱{{ number_format($draft->wages_total, 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs text-slate-500">PPE</div>
                <div class="mt-2 text-xl font-bold text-slate-900">
                    ₱{{ number_format($draft->ppe_total, 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs text-slate-500">Insurance</div>
                <div class="mt-2 text-xl font-bold text-slate-900">
                    ₱{{ number_format($draft->insurance_total, 2) }}
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs text-slate-500">Draft Project Cost</div>
                <div class="mt-2 text-xl font-bold text-slate-900">
                    ₱{{ number_format($draft->total_project_cost, 2) }}
                </div>
            </div>

        </div>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">
                    Draft Information
                </h2>
            </div>

            <dl class="divide-y divide-slate-100">

                @foreach ([
            'ADL Number' => $draft->allocation->adl->adl_number,
            'Fund Sponsor' => $draft->allocation->fund_sponsor,
            'Partner' => $draft->allocation->partner,
            'Assigned TC' => $draft->assignedTc->name,
            'Location' => "{$draft->barangay}, {$draft->municipality}, {$draft->province}",
            'Income Class' => $draft->income_class ?: 'Not assigned',
            'Duration' => "{$draft->number_of_days} days - {$draft->term->label()}",
            'Beneficiaries' => number_format($draft->beneficiaries_total),
            'Female Beneficiaries' => number_format($draft->beneficiaries_female),
            'Wage Rate' => '₱' . number_format($draft->wage_rate, 2),
        ] as $label => $value)
                    <div class="grid grid-cols-2 gap-4 px-5 py-3">

                        <dt class="text-xs text-slate-500">
                            {{ $label }}
                        </dt>

                        <dd class="text-right text-sm font-medium text-slate-800">
                            {{ $value }}
                        </dd>

                    </div>
                @endforeach

            </dl>

        </section>

        @if ($draft->canBeSubmitted())
            <div class="mt-5 flex justify-end">

                <form method="POST" action="{{ route('project-drafts.submit', $draft) }}">

                    @csrf

                    <button type="submit"
                        class="h-11 rounded-lg bg-slate-900 px-6 text-sm font-semibold text-white hover:bg-slate-800">
                        Submit to TC for Review
                    </button>

                </form>

            </div>
        @endif

    </div>

@endsection
