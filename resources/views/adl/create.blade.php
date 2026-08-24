@extends('layouts.app')
@section('title', 'Add ADL')
@section('content')
<div class="mx-auto max-w-5xl">
    <div class="mb-6"><a href="{{ route('adl.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">← ADL Management</a><h1 class="mt-3 text-2xl font-bold text-slate-900">Add ADL Record</h1><p class="mt-1 text-sm text-slate-500">FY2025 monitoring-aligned ADL, NFA/NTA and funding information.</p></div>
    <x-page-alerts />
    <form method="POST" action="{{ route('adl.store') }}" class="space-y-5">@csrf @include('adl._form-fields')
        <div class="flex justify-end gap-3"><a href="{{ route('adl.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600">Cancel</a><button class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white">Save ADL</button></div>
    </form>
</div>
@endsection
