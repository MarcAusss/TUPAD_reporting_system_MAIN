@extends('layouts.app')

@section('title', 'Targets')

@section('content')

    <x-page-header
        eyebrow="Fund Management"
        title="NGA Targets"
        description="Manage per-NGA beneficiary and financial targets, accomplishments, and remaining balance."
    >
        <x-slot:actions>
            <a
                href="{{ route('targets.create') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800"
            >
                <span class="text-base leading-none">+</span>
                Create Target
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-page-alerts />

    <form method="GET" action="{{ route('targets.index') }}" class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <label for="target-search" class="sr-only">Search NGA</label>
                <input id="target-search" name="q" value="{{ $search }}"
                    placeholder="Search NGA / Partner..."
                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>

            <button type="submit" class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Search
            </button>

            @if($search !== '')
                <a href="{{ route('targets.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Per-NGA Targets</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($targets->total()) }} target record(s)
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">NGA / Partner</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Total Beneficiaries</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Amount</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Accomplishments</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Balance</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    @forelse($targets as $target)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $target->nga }}</div>
                                <div class="mt-1 text-[11px] text-slate-400">
                                    {{ $target->accomplishmentPercent() }}% accomplished
                                </div>
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-600">
                                {{ number_format($target->total_beneficiaries) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-slate-600">
                                ₱{{ number_format($target->amount, 2) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-slate-800">
                                {{ number_format($target->accomplishments) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold {{ $target->balance <= 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ number_format($target->balance) }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <a
                                    href="{{ route('targets.edit', $target) }}"
                                    class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-950"
                                >
                                    Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state
                                    title="No NGA targets yet"
                                    message="Create the first target to begin tracking beneficiary and financial accomplishment per NGA."
                                >
                                    <x-slot:action>
                                        <a
                                            href="{{ route('targets.create') }}"
                                            class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800"
                                        >
                                            Create Target
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
        {{ $targets->links() }}
    </div>

@endsection
