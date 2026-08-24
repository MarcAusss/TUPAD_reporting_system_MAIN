@extends('layouts.app')

@section('title', 'Edit Beneficiary')

@section('content')

    <div class="mx-auto max-w-4xl">

        <div class="mb-6">

            <a href="{{ route('projects.beneficiaries.index', $project) }}"
                class="text-sm font-medium text-slate-500 hover:text-slate-800">
                ← Beneficiary Registry
            </a>

            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                Edit Beneficiary
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                {{ $project->project_title }}
            </p>

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

        <form method="POST"
            action="{{ route('projects.beneficiaries.update', [
                'project' => $project,
                'beneficiary' => $beneficiary,
            ]) }}"
            class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">

            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        First Name
                    </label>

                    <input name="first_name" required
                        value="{{ old('first_name', $beneficiary->first_name) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Middle Name
                    </label>

                    <input name="middle_name"
                        value="{{ old('middle_name', $beneficiary->middle_name) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Last Name
                    </label>

                    <input name="last_name" required
                        value="{{ old('last_name', $beneficiary->last_name) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Suffix
                    </label>

                    <input name="suffix"
                        value="{{ old('suffix', $beneficiary->suffix) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Sex
                    </label>

                    <select name="sex" required
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                        <option value="male" @selected(old('sex', $beneficiary->sex) === 'male')>
                            Male
                        </option>

                        <option value="female" @selected(old('sex', $beneficiary->sex) === 'female')>
                            Female
                        </option>

                    </select>

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Birth Date
                    </label>

                    <input name="birth_date" type="date"
                        value="{{ old('birth_date', $beneficiary->birth_date?->format('Y-m-d')) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Contact Number
                    </label>

                    <input name="contact_number"
                        value="{{ old('contact_number', $beneficiary->contact_number) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

                <div>

                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                        Remarks
                    </label>

                    <input name="remarks"
                        value="{{ old('remarks', $beneficiary->remarks) }}"
                        class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">

                </div>

            </div>

            <div class="mt-6 flex justify-end gap-3">

                <a href="{{ route('projects.beneficiaries.index', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600">
                    Cancel
                </a>

                <button type="submit"
                    class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Save Changes
                </button>

            </div>

        </form>

    </div>

@endsection
