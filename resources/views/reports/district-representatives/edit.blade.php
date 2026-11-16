@extends('layouts.app')

@section('title', 'District Representatives')

@section('content')
    <div class="mb-5">
        <x-page-header
            eyebrow="Focal Management"
            title="District Representatives"
            description="Update the representative names after each congressional election. Changes apply to the Fund Status Summary per District report. Only the Focal account can save changes."
        />
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <div class="font-semibold">The representatives could not be saved.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($representatives->isEmpty())
        <section class="rounded-xl border border-slate-200 bg-white px-5 py-12 text-center shadow-sm">
            <div class="text-sm font-semibold text-slate-700">No congressional districts are set up yet.</div>
        </section>
    @else
        <form method="POST" action="{{ route('reports.district-representatives.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_to" value="admin">

            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($representatives->groupBy('province') as $province => $entries)
                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-3">
                            <h2 class="text-[11px] font-extrabold uppercase tracking-[0.12em] text-[#063b86]">{{ $province }}</h2>
                        </div>
                        <div class="space-y-2 px-5 py-4">
                            @foreach ($entries as $entry)
                                @php
                                    $index = $loop->parent->index * 100 + $loop->index;
                                @endphp
                                <div class="grid grid-cols-[7rem_1fr] items-center gap-3">
                                    <label for="representative-{{ $entry->id }}" class="text-xs font-semibold text-slate-600">{{ $entry->district }}</label>
                                    <input type="hidden" name="representatives[{{ $index }}][id]" value="{{ $entry->id }}">
                                    <input id="representative-{{ $entry->id }}" name="representatives[{{ $index }}][representative]"
                                        value="{{ old("representatives.$index.representative", $entry->representative) }}" maxlength="255"
                                        placeholder="Representative name"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="sticky bottom-0 mt-5 flex justify-end gap-2 border-t border-slate-200 bg-white/95 py-3 backdrop-blur">
                <a href="{{ route('reports.workspace.fund-status', ['view' => 'district']) }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    View District Report
                </a>
                <button type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Save Representatives
                </button>
            </div>
        </form>
    @endif
@endsection
