@extends('layouts.app')

@section('title', 'GIP Draft Reviews')

@section('content')

    <div class="mb-6">

        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            GIP Draft Reviews
        </h1>

        <p class="mt-1 text-sm text-slate-500">
            Review project drafts submitted by GIP encoders.
        </p>

    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Project
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            GIP Encoder
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            ADL / Partner
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Draft Cost
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Submitted
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Action
                        </th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($drafts as $draft)
                        <tr>

                            <td class="px-5 py-4 text-sm font-semibold text-slate-900">
                                {{ $draft->project_title }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $draft->encoder->name }}
                            </td>

                            <td class="px-5 py-4">

                                <div class="text-sm text-slate-700">
                                    {{ $draft->allocation->adl->adl_number }}
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    {{ $draft->allocation->partner }}
                                </div>

                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-900">
                                ₱{{ number_format($draft->total_project_cost, 2) }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-500">
                                {{ $draft->submitted_at?->format('M d, Y g:i A') }}
                            </td>

                            <td class="px-5 py-4 text-right">

                                <a href="{{ route('project-draft-reviews.show', $draft) }}"
                                    class="text-sm font-semibold text-slate-700 hover:text-slate-950">
                                    Review
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">
                                No project drafts are currently waiting for review.
                            </td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <div class="mt-5">
        {{ $drafts->links() }}
    </div>

@endsection
