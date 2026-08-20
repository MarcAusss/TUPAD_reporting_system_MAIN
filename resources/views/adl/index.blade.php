@extends('layouts.app')

@section('title', 'ADL Management')

@section('content')

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                ADL Management
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Manage ADL grants, re-alignments and fund allocations.
            </p>
        </div>

        <a href="{{ route('adl.create') }}"
            class="inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
            Add ADL
        </a>

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
                            ADL Number
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Original Grants
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Re-alignment
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Adjusted Grants
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Allocated
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Remaining
                        </th>

                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                            Action
                        </th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($adls as $adl)
                        <tr class="hover:bg-slate-50/70">

                            <td class="px-5 py-4">
                                <div class="text-sm font-semibold text-slate-900">
                                    {{ $adl->adl_number }}
                                </div>
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-600">
                                ₱{{ number_format($adl->grants, 2) }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm
                            {{ $adl->total_realignment < 0 ? 'text-red-600' : 'text-slate-600' }}">
                                ₱{{ number_format($adl->total_realignment, 2) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800">
                                ₱{{ number_format($adl->adjusted_grants, 2) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-600">
                                ₱{{ number_format($adl->total_allocated, 2) }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold
                            {{ $adl->remaining_balance <= 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                ₱{{ number_format($adl->remaining_balance, 2) }}
                            </td>

                            <td class="px-5 py-4 text-right">

                                <a href="{{ route('adl.show', $adl) }}"
                                    class="text-sm font-semibold text-slate-700 hover:text-slate-950">
                                    View
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-slate-400">
                                No ADL records have been created.
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

    <div class="mt-5">
        {{ $adls->links() }}
    </div>

@endsection
