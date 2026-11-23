@extends('layouts.app')

@section('title', 'Mayors & Governors')

@section('content')
    <div class="mb-5">
        <x-page-header
            eyebrow="Focal Management"
            title="Mayors & Governors"
            description="Update the Governor of each province and the Mayor of each city / municipality after each local election. These names fill the Local Chief Executive (LCE) of new ADL allocations automatically. Only the Focal account can save changes."
        />
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <div class="font-semibold">The Mayors and Governors could not be saved.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-5 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <form method="GET" action="{{ route('locations.local-chief-executives.edit') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="province_filter" class="mb-1 block text-xs font-semibold text-slate-700">Province</label>
                <select id="province_filter" name="province_id" class="h-10 w-56 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">All provinces</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected($selectedProvinceId === $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Show
            </button>
            @if ($missingCount > 0)
                <span class="ml-auto rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                    {{ $missingCount }} {{ str('name')->plural($missingCount) }} not encoded yet
                </span>
            @endif
        </form>
    </section>

    @if ($shownProvinces->isEmpty())
        <section class="rounded-xl border border-slate-200 bg-white px-5 py-12 text-center shadow-sm">
            <div class="text-sm font-semibold text-slate-700">No active provinces are set up yet.</div>
        </section>
    @else
        <form method="POST" action="{{ route('locations.local-chief-executives.update') }}" id="lce-form">
            @csrf
            @method('PUT')
            @if ($selectedProvinceId)
                <input type="hidden" name="province_id" value="{{ $selectedProvinceId }}">
            @endif

            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($shownProvinces as $province)
                    @php($rows = $municipalitiesByProvince->get($province->id, collect()))

                    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-200 bg-[#063b86] px-5 py-3 text-white">
                            <h2 class="text-sm font-extrabold uppercase tracking-wide">{{ $province->name }}</h2>
                            <span class="text-xs font-semibold text-blue-100">{{ $rows->count() }} LGUs</span>
                        </div>

                        <div class="border-b border-slate-200 bg-[#f4f7fc] px-5 py-4">
                            <label for="governor-{{ $province->id }}" class="mb-1 block text-xs font-bold uppercase tracking-wide text-[#17325c]">
                                Governor
                            </label>
                            <input id="governor-{{ $province->id }}" name="governors[{{ $province->id }}]"
                                value="{{ old('governors.'.$province->id, $province->governor) }}"
                                data-original="{{ $province->governor }}" maxlength="255"
                                placeholder="Governor's name"
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 data-[changed]:border-amber-400 data-[changed]:bg-amber-50">
                            @if ($province->governor_updated_at)
                                <div class="mt-1 text-[11px] text-slate-400">
                                    Edited {{ $province->governor_updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                    @if ($province->governorUpdatedBy) by {{ $province->governorUpdatedBy->name }} @endif
                                </div>
                            @endif
                        </div>

                        @if ($rows->isEmpty())
                            <div class="px-5 py-6 text-center text-xs text-slate-400">No active cities / municipalities.</div>
                        @else
                            <div class="overflow-x-auto"><table class="tupad-system-table w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-[#e8f0fa] text-[#17325c]">
                                        <th class="border-b border-slate-200 px-4 py-2 text-left text-xs font-bold uppercase tracking-wide">City / Municipality</th>
                                        <th class="w-64 border-b border-slate-200 px-4 py-2 text-left text-xs font-bold uppercase tracking-wide">Mayor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $municipality)
                                        <tr>
                                            <td class="border-b border-slate-100 px-4 py-2">
                                                <label for="mayor-{{ $municipality->id }}" class="font-semibold text-slate-900">{{ $municipality->name }}</label>
                                                @if ($municipality->mayor_updated_at)
                                                    <div class="text-[11px] text-slate-400">
                                                        Edited {{ $municipality->mayor_updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                                        @if ($municipality->mayorUpdatedBy) by {{ $municipality->mayorUpdatedBy->name }} @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="border-b border-slate-100 px-4 py-2">
                                                <input id="mayor-{{ $municipality->id }}" name="mayors[{{ $municipality->id }}]"
                                                    value="{{ old('mayors.'.$municipality->id, $municipality->mayor) }}"
                                                    data-original="{{ $municipality->mayor }}" maxlength="255"
                                                    placeholder="Mayor's name"
                                                    class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 data-[changed]:border-amber-400 data-[changed]:bg-amber-50">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="sticky bottom-0 mt-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white/95 px-5 py-4 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-5 text-slate-600">
                    <span id="lce-change-count" class="font-semibold text-slate-900">No changes yet.</span>
                    Enter the name only; "Mayor" or "Governor" is added automatically when used as an LCE.
                </p>
                <button type="submit" class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6d]">
                    Save Mayors &amp; Governors
                </button>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('lce-form');
            const counter = document.getElementById('lce-change-count');

            if (!form || !counter) {
                return;
            }

            function refresh() {
                let changed = 0;

                form.querySelectorAll('input[data-original]').forEach(function (input) {
                    const isChanged = input.value.trim() !== (input.dataset.original || '');
                    input.toggleAttribute('data-changed', isChanged);
                    changed += isChanged ? 1 : 0;
                });

                counter.textContent = changed === 0
                    ? 'No changes yet.'
                    : `${changed} unsaved ${changed === 1 ? 'change' : 'changes'}.`;
            }

            form.addEventListener('input', refresh);
            refresh();
        });
    </script>
@endpush
