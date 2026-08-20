@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    {{-- <------------------------------------------- Header ------------------------------/> --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Dashboard
            </h1>

            <p class="mt-1 text-sm text-slate-500">
                Overview of TUPAD program activities and project monitoring.
            </p>

        </div>

        <div class="w-fit rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600">
            {{ now()->format('F d, Y') }}
        </div>

    </div>

    {{-- <------------------------------------------- Summary Cards ------------------------------/> --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="flex items-center gap-3">

                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">

                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M4 7h16"></path>
                        <path d="M4 12h16"></path>
                        <path d="M4 17h10"></path>
                    </svg>

                </div>

                <span class="text-xs font-semibold text-slate-500">
                    Total ADLs
                </span>

            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                0
            </div>

            <p class="mt-1 text-xs text-slate-400">
                No ADL records encoded yet.
            </p>

        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="flex items-center gap-3">

                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">

                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M8 6h13"></path>
                        <path d="M8 12h13"></path>
                        <path d="M8 18h13"></path>
                        <path d="M3 6h.01"></path>
                        <path d="M3 12h.01"></path>
                        <path d="M3 18h.01"></path>
                    </svg>

                </div>

                <span class="text-xs font-semibold text-slate-500">
                    Total Projects
                </span>

            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                0
            </div>

            <p class="mt-1 text-xs text-slate-400">
                Official projects recorded in the system.
            </p>

        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="flex items-center gap-3">

                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">

                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M12 2v20"></path>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>

                </div>

                <span class="text-xs font-semibold text-slate-500">
                    Total Allocation
                </span>

            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                ₱0.00
            </div>

            <p class="mt-1 text-xs text-slate-400">
                Total allocated grant amount.
            </p>

        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

            <div class="flex items-center gap-3">

                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-700">

                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v5l3 2"></path>
                    </svg>

                </div>

                <span class="text-xs font-semibold text-slate-500">
                    Ongoing Projects
                </span>

            </div>

            <div class="mt-4 text-2xl font-bold text-slate-900">
                0
            </div>

            <p class="mt-1 text-xs text-slate-400">
                Projects currently under implementation.
            </p>

        </article>

    </div>

    {{-- <------------------------------------------- Main Grid ------------------------------/> --}}
    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[1.5fr_0.8fr]">

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Project Status Overview
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Distribution of projects by current workflow status.
                </p>

            </div>

            <div class="flex min-h-64 flex-col items-center justify-center px-6 text-center">

                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">

                    <svg
                        class="h-6 w-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                    >
                        <path d="M4 19V9"></path>
                        <path d="M10 19V5"></path>
                        <path d="M16 19v-7"></path>
                        <path d="M22 19H2"></path>
                    </svg>

                </div>

                <div class="mt-3 text-sm font-semibold text-slate-600">
                    No project data available
                </div>

                <p class="mt-1 max-w-sm text-xs leading-5 text-slate-400">
                    Project statistics will appear here once project records are created.
                </p>

            </div>

        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="border-b border-slate-200 px-5 py-4">

                <h2 class="text-sm font-semibold text-slate-900">
                    Fund Utilization
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    Current utilization of available ADL grants.
                </p>

            </div>

            <div class="p-5">

                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>
                        Total Grants
                    </span>

                    <strong class="text-sm text-slate-900">
                        ₱0.00
                    </strong>
                </div>

                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">

                    <div
                        class="h-full rounded-full bg-slate-800"
                        style="width: 0%;"
                    ></div>

                </div>

                <div class="mt-5 divide-y divide-slate-100">

                    <div class="flex items-center justify-between py-3 text-xs text-slate-500">

                        <span>
                            Allocated
                        </span>

                        <strong class="text-slate-800">
                            ₱0.00
                        </strong>

                    </div>

                    <div class="flex items-center justify-between py-3 text-xs text-slate-500">

                        <span>
                            Remaining
                        </span>

                        <strong class="text-slate-800">
                            ₱0.00
                        </strong>

                    </div>

                </div>

            </div>

        </section>

    </div>

    {{-- <------------------------------------------- Recent Activity ------------------------------/> --}}
    <section class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Recent Activity
            </h2>

            <p class="mt-1 text-xs text-slate-500">
                Latest actions recorded in the TUPAD Reporting System.
            </p>

        </div>

        <div class="overflow-x-auto">

            <table class="min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Date & Time
                        </th>

                        <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            User
                        </th>

                        <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Module
                        </th>

                        <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Activity
                        </th>

                        <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                            Status
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    <tr>

                        <td
                            colspan="5"
                            class="px-5 py-10 text-center text-xs text-slate-400"
                        >
                            No activity has been recorded yet.
                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </section>

@endsection