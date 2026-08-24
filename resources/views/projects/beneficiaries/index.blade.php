@extends('layouts.app')

@section('title', 'Beneficiary Registry')

@section('content')

    @php
        $registeredCount = $project->beneficiaries()->count();

        $femaleCount = $project->beneficiaries()->where('sex', 'female')->count();

        $maleCount = $project->beneficiaries()->where('sex', 'male')->count();

        $remaining = max(0, $project->beneficiaries_total - $registeredCount);

        $complete = $registeredCount === (int) $project->beneficiaries_total;

        $percent =
            $project->beneficiaries_total > 0 ? min(100, ($registeredCount / $project->beneficiaries_total) * 100) : 0;
    @endphp

    <div class="mx-auto max-w-7xl">

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

            <div>

                <a href="{{ route('projects.show', $project) }}"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800">
                    ← Project
                </a>

                <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                    Beneficiary Registry
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    {{ $project->project_title }}
                </p>

            </div>

            <div
                class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold
                {{ $complete ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                {{ $complete ? 'Registry Complete' : 'Registry Incomplete' }}
            </div>

        </div>

        @if (session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())

            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4">

                <ul class="list-disc space-y-1 pl-5 text-sm text-red-700">

                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach

                </ul>

            </div>

        @endif

        {{-- Summary --}}

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                <div class="text-xs font-semibold text-slate-500">
                    Declared Beneficiaries
                </div>

                <div class="mt-3 text-2xl font-bold text-slate-900">
                    {{ number_format($project->beneficiaries_total) }}
                </div>

            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                <div class="text-xs font-semibold text-slate-500">
                    Registered
                </div>

                <div class="mt-3 text-2xl font-bold text-slate-900">
                    {{ number_format($registeredCount) }}
                </div>

            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                <div class="text-xs font-semibold text-slate-500">
                    Female
                </div>

                <div class="mt-3 text-2xl font-bold text-slate-900">
                    {{ number_format($femaleCount) }}
                </div>

            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

                <div class="text-xs font-semibold text-slate-500">
                    Remaining
                </div>

                <div class="mt-3 text-2xl font-bold text-slate-900">
                    {{ number_format($remaining) }}
                </div>

            </article>

        </div>

        <section class="mt-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="flex items-center justify-between text-xs">

                <span class="font-semibold text-slate-600">
                    Registry Completion
                </span>

                <span class="font-semibold text-slate-800">
                    {{ $registeredCount }}
                    /
                    {{ $project->beneficiaries_total }}
                </span>

            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">

                <div class="h-full rounded-full bg-slate-800" style="width: {{ $percent }}%;"></div>

            </div>

            <div class="mt-3 flex gap-4 text-xs text-slate-500">

                <span>
                    Male:
                    <strong class="text-slate-700">
                        {{ $maleCount }}
                    </strong>
                </span>

                <span>
                    Female:
                    <strong class="text-slate-700">
                        {{ $femaleCount }}
                    </strong>
                </span>

            </div>

        </section>

        {{-- Add Beneficiary --}}

        @if (!$complete)
            <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

                <div class="border-b border-slate-200 px-5 py-4">

                    <h2 class="text-sm font-semibold text-slate-900">
                        Add Beneficiary
                    </h2>

                </div>

                <form method="POST"
                    action="{{ route('projects.beneficiaries.store', $project) }}"
                    class="p-5">

                    @csrf

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                First Name
                            </label>

                            <input name="first_name" required value="{{ old('first_name') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Middle Name
                            </label>

                            <input name="middle_name" value="{{ old('middle_name') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Last Name
                            </label>

                            <input name="last_name" required value="{{ old('last_name') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Suffix
                            </label>

                            <input name="suffix" value="{{ old('suffix') }}" placeholder="Jr., Sr., III"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Sex
                            </label>

                            <select name="sex" required
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                <option value="">
                                    Select
                                </option>

                                <option value="male" @selected(old('sex') === 'male')>
                                    Male
                                </option>

                                <option value="female" @selected(old('sex') === 'female')>
                                    Female
                                </option>

                            </select>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Birth Date
                            </label>

                            <input name="birth_date" type="date" value="{{ old('birth_date') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Contact Number
                            </label>

                            <input name="contact_number" value="{{ old('contact_number') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Remarks
                            </label>

                            <input name="remarks" value="{{ old('remarks') }}"
                                class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        </div>

                    </div>

                    <div class="mt-4 flex justify-end">

                        <button type="submit"
                            class="h-10 rounded-lg bg-slate-900 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                            Add Beneficiary
                        </button>

                    </div>

                </form>

            </section>
        @endif

        {{-- Beneficiary Table --}}

        <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Registered Beneficiaries
                </h2>

            </div>

            <div class="overflow-x-auto">

                <table class="min-w-full">

                    <thead class="bg-slate-50">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                #
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Name
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Sex
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Birth Date
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Contact
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @forelse($beneficiaries as $beneficiary)
                            <tr>

                                <td class="px-5 py-4 text-sm text-slate-400">

                                    {{ $beneficiaries->firstItem() + $loop->index }}

                                </td>

                                <td class="px-5 py-4">

                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $beneficiary->full_name }}
                                    </div>

                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ ucfirst($beneficiary->sex) }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">

                                    {{ $beneficiary->birth_date ? $beneficiary->birth_date->format('M d, Y') : '—' }}

                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ $beneficiary->contact_number ?: '—' }}
                                </td>

                                <td class="px-5 py-4">

                                    <div class="flex justify-end gap-3">

                                        <a href="{{ route('projects.beneficiaries.edit', [
                                            'project' => $project,
                                            'beneficiary' => $beneficiary,
                                        ]) }}"
                                            class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                                            Edit
                                        </a>

                                        <form method="POST"
                                            action="{{ route('projects.beneficiaries.destroy', [
                                                'project' => $project,
                                                'beneficiary' => $beneficiary,
                                            ]) }}"
                                            onsubmit="return confirm('Remove this beneficiary?');">

                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="text-sm font-semibold text-red-600 hover:text-red-800">
                                                Remove
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="6" class="px-5 py-12 text-center text-sm text-slate-400">
                                    No beneficiaries have been encoded.
                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

        <div class="mt-5">
            {{ $beneficiaries->links() }}
        </div>

    </div>

@endsection
