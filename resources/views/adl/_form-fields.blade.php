@php
    $editing = isset($adl);
    $initialGrants = old('grants', $editing ? $adl->grants : '');
    $initialAdminCost = old('admin_cost', $editing ? $adl->admin_cost : 0);
@endphp

<div class="space-y-5">
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4">
            <h2 class="text-sm font-bold text-[#10294f]">ADL Information</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Enter only the ADL information required by the approved TUPAD workflow document.
            </p>
        </div>

        <div class="grid gap-5 p-5 md:grid-cols-2">
            <div>
                <label for="adl_number" class="mb-2 block text-xs font-semibold text-slate-700">
                    ADL Number <span class="text-red-600">*</span>
                </label>

                <input
                    id="adl_number"
                    name="adl_number"
                    type="text"
                    required
                    maxlength="100"
                    value="{{ old('adl_number', $editing ? $adl->adl_number : '') }}"
                    placeholder="Example: ADL-2026-001"
                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                >

                @error('adl_number')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="date_received" class="mb-2 block text-xs font-semibold text-slate-700">
                    Date Received
                </label>

                <input
                    id="date_received"
                    name="date_received"
                    type="date"
                    value="{{ old('date_received', $editing ? $adl->date_received?->format('Y-m-d') : '') }}"
                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                >

                @error('date_received')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4">
            <h2 class="text-sm font-bold text-[#10294f]">Amount</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Grants is the official Total amount. Administrative Cost is recorded separately and is not added to Total.
            </p>
        </div>

        <div class="grid gap-5 p-5 lg:grid-cols-3">
            <div>
                <label for="grants" class="mb-2 block text-xs font-semibold text-slate-700">
                    Grants <span class="text-red-600">*</span>
                </label>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-500">₱</span>
                    <input
                        id="grants"
                        name="grants"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        value="{{ $initialGrants }}"
                        placeholder="0.00"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                    >
                </div>

                @error('grants')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="admin_cost" class="mb-2 block text-xs font-semibold text-slate-700">
                    Administrative Cost
                </label>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-500">₱</span>
                    <input
                        id="admin_cost"
                        name="admin_cost"
                        type="number"
                        step="0.01"
                        min="0"
                        value="{{ $initialAdminCost }}"
                        placeholder="0.00"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                    >
                </div>

                <p class="mt-1.5 text-[11px] text-slate-500">
                    Tracked separately. This amount does not change Total.
                </p>

                @error('admin_cost')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="total" class="mb-2 block text-xs font-semibold text-slate-700">
                    Total
                </label>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-500">₱</span>
                    <input
                        id="total"
                        name="total"
                        type="number"
                        step="0.01"
                        min="0"
                        readonly
                        tabindex="-1"
                        value="{{ $initialGrants }}"
                        class="h-11 w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 pl-8 pr-3.5 text-sm font-semibold text-slate-700 outline-none"
                    >
                </div>

                <p class="mt-1.5 text-[11px] text-slate-500">
                    Automatically follows the Grants amount.
                </p>
            </div>
        </div>
    </section>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const grantsInput = document.getElementById('grants');
            const totalInput = document.getElementById('total');

            if (!grantsInput || !totalInput) {
                return;
            }

            const syncTotal = () => {
                totalInput.value = grantsInput.value;
            };

            grantsInput.addEventListener('input', syncTotal);
            grantsInput.addEventListener('change', syncTotal);
            syncTotal();
        });
    </script>
@endpush
