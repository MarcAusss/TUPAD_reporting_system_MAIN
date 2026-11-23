@extends('layouts.app')

@section('title', 'Municipal Income Classes')

@section('content')
    <div class="mb-5">
        <x-page-header
            eyebrow="Focal Management"
            title="Municipal Income Classes"
            description="Maintain the income class of each municipality and city in the Bicol provinces. Changes apply to project records and reports that show income class. Only the Focal account can save changes."
        />
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <div class="font-semibold">The income classes could not be saved.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-5 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
        <form method="GET" action="{{ route('locations.income-classes.edit') }}" class="flex flex-wrap items-end gap-2">
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
            @if ($unsetCount > 0)
                <span class="ml-auto rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                    {{ $unsetCount }} {{ str('municipality')->plural($unsetCount) }} without an income class
                </span>
            @endif
        </form>
    </section>

    <form method="POST" action="{{ route('locations.income-classes.update') }}" id="income-class-form">
        @csrf
        @method('PUT')
        @if ($selectedProvinceId)
            <input type="hidden" name="province_id" value="{{ $selectedProvinceId }}">
        @endif

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($provinces as $province)
                @continue(! $municipalitiesByProvince->has($province->id))
                @php($rows = $municipalitiesByProvince[$province->id])

                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-[#063b86] px-5 py-3 text-white">
                        <h2 class="text-sm font-extrabold uppercase tracking-wide">{{ $province->name }}</h2>
                        <span class="text-xs font-semibold text-blue-100">{{ $rows->count() }} LGUs</span>
                    </div>

                    <div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-[#e8f0fa] text-[#17325c]">
                                <th class="border-b border-slate-200 px-4 py-2 text-left text-xs font-bold uppercase tracking-wide">Municipality / City</th>
                                <th class="w-56 border-b border-slate-200 px-4 py-2 text-left text-xs font-bold uppercase tracking-wide">Income Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $municipality)
                                @php($current = old('income_classes.'.$municipality->id, $municipality->income_class))
                                <tr class="odd:bg-white even:bg-slate-50">
                                    <td class="border-b border-slate-100 px-4 py-2">
                                        <div class="font-semibold text-slate-900">{{ $municipality->name }}</div>
                                        @if ($municipality->income_class_updated_at)
                                            <div class="text-[11px] text-slate-400">
                                                Edited {{ $municipality->income_class_updated_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                                                @if ($municipality->incomeClassUpdatedBy) by {{ $municipality->incomeClassUpdatedBy->name }} @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="border-b border-slate-100 px-4 py-2">
                                        <select name="income_classes[{{ $municipality->id }}]"
                                            data-original="{{ $municipality->income_class }}"
                                            aria-label="Income class of {{ $municipality->name }}"
                                            class="h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 data-[changed]:border-amber-400 data-[changed]:bg-amber-50">
                                            <option value="">— Not set —</option>
                                            @foreach ($options as $option)
                                                <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                </section>
            @endforeach
        </div>

        <div class="sticky bottom-0 mt-5 flex flex-col gap-3 rounded-xl border border-slate-200 bg-white/95 px-5 py-4 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs leading-5 text-slate-600">
                <span id="income-class-change-count" class="font-semibold text-slate-900">No changes yet.</span>
                Changed rows are highlighted until you save.
            </p>
            <button type="submit" class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6d]">
                Save Income Classes
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('income-class-form');
            const counter = document.getElementById('income-class-change-count');

            if (!form || !counter) {
                return;
            }

            function refresh() {
                let changed = 0;

                form.querySelectorAll('select[data-original]').forEach(function (select) {
                    const isChanged = select.value !== (select.dataset.original || '');
                    select.toggleAttribute('data-changed', isChanged);
                    changed += isChanged ? 1 : 0;
                });

                counter.textContent = changed === 0
                    ? 'No changes yet.'
                    : changed + (changed === 1 ? ' municipality changed.' : ' municipalities changed.');
            }

            form.addEventListener('change', refresh);
            refresh();
        });
    </script>
@endpush
