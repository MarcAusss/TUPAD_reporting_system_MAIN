@extends('layouts.app')

@section('title', 'Add ADL')

@section('content')

    <div class="mx-auto max-w-3xl">

        <div class="mb-6">

            <a href="{{ route('adl.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Back to ADL Management
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                Add ADL Record
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Record the initial ADL grant information.
            </p>

        </div>

        <form method="POST" action="{{ route('adl.store') }}" class="rounded-xl border border-slate-200 bg-white shadow-sm">

            @csrf

            <div class="space-y-5 p-6">

                <div>

                    <label for="adl_number" class="mb-2 block text-sm font-semibold text-slate-700">
                        ADL Number
                    </label>

                    <input id="adl_number" name="adl_number" type="text" value="{{ old('adl_number') }}" required
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    @error('adl_number')
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <div class="grid gap-5 md:grid-cols-2">

                    <div>

                        <label for="grants" class="mb-2 block text-sm font-semibold text-slate-700">
                            Grants
                        </label>

                        <input id="grants" name="grants" type="number" step="0.01" min="0"
                            value="{{ old('grants') }}" required
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                        @error('grants')
                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>

                    <div>

                        <label for="admin_cost" class="mb-2 block text-sm font-semibold text-slate-700">
                            Administrative Cost
                        </label>

                        <input id="admin_cost" name="admin_cost" type="number" step="0.01" min="0"
                            value="{{ old('admin_cost', 0) }}"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                        @error('admin_cost')
                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>

                </div>

                <div class="rounded-lg bg-slate-50 p-4 text-xs leading-5 text-slate-500">
                    Re-alignment is not entered here. It can be recorded later from the ADL details page when a partner
                    communicates a fund adjustment.
                </div>

            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">

                <a href="{{ route('adl.index') }}"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50">
                    Cancel
                </a>

                <button type="submit"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Save ADL
                </button>

            </div>

        </form>

    </div>

@endsection
