@extends('layouts.app')

@section('title', 'ADL Management')

@section('content')

    <x-page-header
        eyebrow="Fund Management"
        title="ADL Management"
        description="Manage ADL grant amounts, re-alignments, allocations, and remaining balances."
    >
        <x-slot:actions>
            <a
                href="{{ route('adl.create') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
            >
                <span class="text-base leading-none">+</span>
                Add ADL
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">ADL Registry</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($adls->total()) }} ADL record(s)
                </p>
            </div>
        </div>

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

                                <a
                                    href="{{ route('adl.show', $adl) }}"
                                    class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-950"
                                >
                                    Open ADL
                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state
                                    title="No ADL records yet"
                                    message="Create the first ADL record to begin fund allocation and monitoring."
                                >
                                    <x-slot:action>
                                        <a
                                            href="{{ route('adl.create') }}"
                                            class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            Add ADL
                                        </a>
                                    </x-slot:action>
                                </x-empty-state>
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
