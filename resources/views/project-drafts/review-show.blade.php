@extends('layouts.app')

@section('title', 'Review Project Draft')

@section('content')

    <div class="mx-auto max-w-6xl">

        <div class="mb-6">

            <a href="{{ route('project-draft-reviews.index') }}"
                class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← GIP Draft Reviews
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                {{ $draft->project_title }}
            </h1>

            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500">

                <span>
                    Encoder:
                    <strong class="text-slate-700">
                        {{ $draft->encoder->name }}
                    </strong>
                </span>

                <span>
                    Submitted:
                    <strong class="text-slate-700">
                        {{ $draft->submitted_at?->format('M d, Y g:i A') }}
                    </strong>
                </span>

            </div>

        </div>

        @if ($errors->any())

            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4">

                <ul class="list-disc space-y-1 pl-5 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>

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
                <div class="text-xs text-slate-500">Total Project Cost</div>
                <div class="mt-2 text-xl font-bold text-slate-900">
                    ₱{{ number_format($draft->total_project_cost, 2) }}
                </div>
            </div>

        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-2">

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Project Information
                    </h2>
                </div>

                <dl class="divide-y divide-slate-100">

                    @foreach ([
            'ADL' => $draft->allocation->adl->adl_number,
            'Sponsor' => $draft->allocation->fund_sponsor,
            'Partner' => $draft->allocation->partner,
            'Date Received' => $draft->date_received->format('F d, Y'),
            'Nature of Work' => $draft->nature_of_work,
            'Implementation Mode' => $draft->implementation_mode->label(),
            'Duration' => "{$draft->number_of_days} days - {$draft->term->label()}",
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

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">
                        Location & Beneficiaries
                    </h2>
                </div>

                <dl class="divide-y divide-slate-100">

                    @foreach ([
            'Province' => $draft->province,
            'District' => $draft->district,
            'Municipality' => $draft->municipality,
            'Barangay' => $draft->barangay,
            'Income Class' => $draft->income_class ?: 'Not assigned',
            'Beneficiaries' => number_format($draft->beneficiaries_total),
            'Female' => number_format($draft->beneficiaries_female),
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

        </div>

        <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">
                    PPE Requirements
                </h2>
            </div>

            <div class="overflow-x-auto">

                <table class="min-w-full">

                    <thead class="bg-slate-50">

                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Type
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Product
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Beneficiaries
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Unit Amount
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Total
                            </th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @forelse($draft->ppeItems as $item)
                            <tr>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $item->ppe_type->label() }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-700">
                                    {{ $item->product }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm text-slate-600">
                                    {{ number_format($item->beneficiary_count) }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm text-slate-600">
                                    ₱{{ number_format($item->unit_amount, 2) }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                    ₱{{ number_format($item->total_amount, 2) }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">
                                    No PPE items.
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

        <div class="mt-5 grid gap-5 xl:grid-cols-2">

            {{-- <------------------------------------------- Return ------------------------------/> --}}

            <form method="POST" action="{{ route('project-draft-reviews.return', $draft) }}"
                class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">

                @csrf

                <h2 class="text-sm font-semibold text-red-800">
                    Return for Correction
                </h2>

                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Explain what the GIP encoder needs to correct.
                </p>

                <textarea name="tc_review_remarks" rows="4" required
                    class="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Required corrections..."></textarea>

                <button type="submit"
                    class="mt-4 h-10 rounded-lg border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-700 hover:bg-red-100">
                    Return to GIP
                </button>

            </form>

            {{-- <------------------------------------------- Confirm ------------------------------/> --}}

            <form method="POST" action="{{ route('project-draft-reviews.confirm', $draft) }}"
                class="rounded-xl border border-emerald-200 bg-white p-5 shadow-sm">

                @csrf

                <h2 class="text-sm font-semibold text-emerald-800">
                    Confirm Project
                </h2>

                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Confirmation creates the official project and commits its cost against the selected allocation.
                </p>

                <textarea name="tc_review_remarks" rows="4"
                    class="mt-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Optional review remarks..."></textarea>

                <button type="submit"
                    class="mt-4 h-10 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800">
                    Confirm as Official Project
                </button>

            </form>

        </div>

    </div>

@endsection
