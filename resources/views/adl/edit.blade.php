@extends('layouts.app')
@section('title', 'Edit ADL')
@section('content')
<div class="mx-auto max-w-5xl">
    <div class="mb-6"><a href="{{ route('adl.show', $adl) }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">← ADL Detail</a><h1 class="mt-3 text-2xl font-bold text-slate-900">Edit {{ $adl->adl_number }}</h1></div>
    <x-page-alerts />
    <form method="POST" action="{{ route('adl.update', $adl) }}" class="space-y-5">@csrf @method('PUT') @include('adl._form-fields', ['adl' => $adl])
        <div class="flex justify-end gap-3"><a href="{{ route('adl.show', $adl) }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-600">Cancel</a><button class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white">Save Changes</button></div>
    </form>
</div>
@endsection
