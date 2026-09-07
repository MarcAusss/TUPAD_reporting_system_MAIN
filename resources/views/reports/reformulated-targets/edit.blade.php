@extends('layouts.app')

@section('title', 'Manage Reformulated Target')

@section('content')
    @php
        $initialPhysicalTotal = collect($rows)->sum('physical_target');
        $initialFinancialTotal = collect($rows)->sum(
            fn (array $row): float => (float) $row['financial_target']
        );
    @endphp

    <div class="mb-5 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <x-page-header
            eyebrow="Focal Management"
            title="Reformulated Target"
            description="Maintain the official annual physical and financial target by Bicol province. Only the Focal account can save changes."
        />

        <a href="{{ route('reports.workspace.physical-financial', ['fiscal_year' => $fiscalYear]) }}"
            class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Back to Physical & Financial Report
        </a>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
            <div class="font-semibold">The reformulated targets could not be saved.</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($missingProvinceCount > 0)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
            {{ $missingProvinceCount }} Bicol province reference record(s) are missing or inactive. Restore the province reference data before encoding a complete regional target.
        </div>
    @endif

    <section class="mb-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-[11px] font-extrabold uppercase tracking-[0.15em] text-blue-700">Target Period</div>
                <p class="mt-1 text-sm text-slate-500">Targets are stored separately per fiscal year.</p>
            </div>

            <form method="GET" action="{{ route('reports.reformulated-targets.edit') }}" class="flex items-end gap-2">
                <div>
                    <label for="fiscal_year_filter" class="mb-1 block text-xs font-semibold text-slate-700">Fiscal Year</label>
                    <input id="fiscal_year_filter" name="fiscal_year" type="number" min="2000" max="2100"
                        value="{{ $fiscalYear }}"
                        class="h-10 w-32 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                </div>
                <button type="submit"
                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Load Year
                </button>
            </form>
        </div>
    </section>

    <form method="POST" action="{{ route('reports.reformulated-targets.update') }}" id="reformulated-target-form">
        @csrf
        @method('PUT')
        <input type="hidden" name="fiscal_year" value="{{ $fiscalYear }}">

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">FY{{ $fiscalYear }} Province Targets</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Rows marked "Current report baseline" have not yet been manually saved. Saving this form converts the displayed values into official Focal-maintained targets for FY{{ $fiscalYear }}.
                    </p>
                </div>
                <div class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800">
                    Focal-only update control
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-sm">
                    <thead>
                        <tr class="bg-[#063b86] text-white">
                            <th class="border border-blue-900 px-4 py-3 text-left text-xs font-extrabold uppercase tracking-wide">Province</th>
                            <th class="border border-blue-900 px-4 py-3 text-right text-xs font-extrabold uppercase tracking-wide">Physical Target</th>
                            <th class="border border-blue-900 px-4 py-3 text-right text-xs font-extrabold uppercase tracking-wide">Financial Target</th>
                            <th class="border border-blue-900 px-4 py-3 text-left text-xs font-extrabold uppercase tracking-wide">Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            <tr class="odd:bg-white even:bg-slate-50">
                                <td class="border border-slate-200 px-4 py-3 font-semibold text-slate-900">
                                    {{ $row['province'] }}
                                    <input type="hidden" name="targets[{{ $index }}][province_id]" value="{{ $row['province_id'] }}">
                                </td>
                                <td class="border border-slate-200 px-4 py-3">
                                    <input
                                        name="targets[{{ $index }}][physical_target]"
                                        type="number"
                                        min="0"
                                        max="100000000"
                                        step="1"
                                        value="{{ old('targets.'.$index.'.physical_target', $row['physical_target']) }}"
                                        data-physical-target
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-right font-semibold tabular-nums text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                        required>
                                </td>
                                <td class="border border-slate-200 px-4 py-3">
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-500">₱</span>
                                        <input
                                            name="targets[{{ $index }}][financial_target]"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value="{{ old('targets.'.$index.'.financial_target', $row['financial_target']) }}"
                                            data-financial-target
                                            class="h-10 w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3 text-right font-semibold tabular-nums text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                                            required>
                                    </div>
                                </td>
                                <td class="border border-slate-200 px-4 py-3 text-xs">
                                    @if ($row['saved'])
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">Saved target</span>
                                        @if ($row['updated_at'])
                                            <div class="mt-1 text-[11px] text-slate-500">Updated {{ $row['updated_at']->timezone('Asia/Manila')->format('M j, Y g:i A') }}</div>
                                        @endif
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">Current report baseline</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-900 text-white">
                            <td class="border border-slate-700 px-4 py-3 text-xs font-extrabold uppercase tracking-wide">Regional Total</td>
                            <td id="physical-total" class="border border-slate-700 px-4 py-3 text-right font-extrabold tabular-nums">{{ number_format($initialPhysicalTotal) }}</td>
                            <td id="financial-total" class="border border-slate-700 px-4 py-3 text-right font-extrabold tabular-nums">₱{{ number_format($initialFinancialTotal, 2) }}</td>
                            <td class="border border-slate-700 px-4 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-5 text-slate-600">
                    Saved values automatically feed the Physical & Financial report, print view, PDF, Excel, and CSV when FY{{ $fiscalYear }} is selected.
                </p>
                <button type="submit"
                    class="inline-flex h-10 shrink-0 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6d]">
                    Save Reformulated Target
                </button>
            </div>
        </section>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('reformulated-target-form');
            const physicalTotal = document.getElementById('physical-total');
            const financialTotal = document.getElementById('financial-total');

            if (!form || !physicalTotal || !financialTotal) {
                return;
            }

            const numberFormatter = new Intl.NumberFormat('en-PH', {
                maximumFractionDigits: 0,
            });
            const moneyFormatter = new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            function refreshTotals() {
                let physical = 0;
                let financial = 0;

                form.querySelectorAll('[data-physical-target]').forEach(function (input) {
                    physical += Number.parseInt(input.value || '0', 10) || 0;
                });

                form.querySelectorAll('[data-financial-target]').forEach(function (input) {
                    financial += Number.parseFloat(input.value || '0') || 0;
                });

                physicalTotal.textContent = numberFormatter.format(physical);
                financialTotal.textContent = moneyFormatter.format(financial).replace('PHP', '₱').trim();
            }

            form.addEventListener('input', refreshTotals);
            refreshTotals();
        });
    </script>
@endpush
