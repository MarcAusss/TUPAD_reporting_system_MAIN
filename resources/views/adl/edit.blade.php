@extends('layouts.app')

@section('title', 'Edit ADL')

@section('content')

    <div class="mx-auto max-w-3xl">

        <div class="mb-6">

            <a href="{{ route('adl.show', $adl) }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Back to ADL
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                Edit ADL
            </h1>

        </div>

        <form method="POST" action="{{ route('adl.update', $adl) }}"
            class="rounded-xl border border-slate-200 bg-white shadow-sm">

            @csrf
            @method('PUT')

            <div class="space-y-5 p-6">

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        ADL Number
                    </label>

                    <input name="adl_number" type="text" required value="{{ old('adl_number', $adl->adl_number) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    @error('adl_number')
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <div class="grid gap-5 md:grid-cols-2">

                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Grants
                        </label>

                        <input name="grants" type="number" step="0.01" min="0" required
                            value="{{ old('grants', $adl->grants) }}"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                        @error('grants')
                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>
                        @enderror

                    </div>

                    <div>

                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Administrative Cost
                        </label>

                        <input name="admin_cost" type="number" step="0.01" min="0"
                            value="{{ old('admin_cost', $adl->admin_cost) }}"
                            class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200">

                    </div>

                </div>

            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 px-6 py-4">

                <a href="{{ route('adl.show', $adl) }}"
                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600">
                    Cancel
                </a>

                <button
                    class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Save Changes
                </button>

            </div>

        </form>

    </div>

@endsection
