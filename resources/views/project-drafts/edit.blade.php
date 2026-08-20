@extends('layouts.app')

@section('title', 'Edit Project Draft')

@section('content')

    <div class="mx-auto max-w-6xl">

        <div class="mb-6">

            <a href="{{ route('project-drafts.show', $draft) }}"
                class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Project Draft
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                Edit Project Draft
            </h1>

            @if ($draft->status->value === 'returned_for_correction')
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">

                    <div class="text-sm font-semibold text-red-800">
                        TC requested corrections
                    </div>

                    <p class="mt-1 text-sm leading-6 text-red-700">
                        {{ $draft->tc_review_remarks }}
                    </p>

                </div>
            @endif

        </div>

        @if ($errors->any())

            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4">

                <ul class="list-disc space-y-1 pl-5 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>

            </div>

        @endif

        <form method="POST" action="{{ route('project-drafts.update', $draft) }}" class="space-y-5">

            @csrf
            @method('PUT')

            @include('project-drafts._form')

            <div class="flex justify-end gap-3">

                <a href="{{ route('project-drafts.show', $draft) }}"
                    class="inline-flex h-11 items-center rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-600">
                    Cancel
                </a>

                <button type="submit"
                    class="inline-flex h-11 items-center rounded-lg bg-slate-900 px-6 text-sm font-semibold text-white hover:bg-slate-800">
                    Save Changes
                </button>

            </div>

        </form>

    </div>

@endsection
