@extends('layouts.app')

@section('title', 'Yearly Summary')

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        :title="'Summary '.$fiscalYear"
        description="Summary of enrolled TUPAD beneficiaries to GPAI for the fiscal year, with semester sub-totals."
    >
        <x-slot:actions>
            @include('gsis.partials.year-filter', ['route' => 'gsis.summary'])
            <a href="{{ route('gsis.summary', ['fiscal_year' => $fiscalYear, 'print' => 1]) }}" target="_blank"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Print Summary
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            @include('gsis.partials.summary-table', ['tableClass' => 'tupad-system-table min-w-full text-sm'])
        </div>
    </section>
@endsection
