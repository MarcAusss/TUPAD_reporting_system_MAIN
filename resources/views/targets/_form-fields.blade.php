@php
    $editing = isset($target);
    $initialTotalBeneficiaries = old('total_beneficiaries', $editing ? $target->total_beneficiaries : '');
    $initialAccomplishments = old('accomplishments', $editing ? $target->accomplishments : 0);
@endphp

<div class="space-y-5">
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4">
            <h2 class="text-sm font-bold text-[#10294f]">NGA</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Enter the National Government Agency or partner this target belongs to.
            </p>
        </div>

        <div class="p-5">
            <label for="nga" class="mb-2 block text-xs font-semibold text-slate-700">
                NGA / Partner <span class="text-red-600">*</span>
            </label>

            <input
                id="nga"
                name="nga"
                type="text"
                list="ngaSuggestions"
                required
                maxlength="255"
                value="{{ old('nga', $editing ? $target->nga : '') }}"
                placeholder="e.g. DSWD"
                class="h-11 w-full max-w-md rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
            >

            <datalist id="ngaSuggestions">
                @foreach($ngaSuggestions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>

            <p class="mt-1.5 text-[11px] text-slate-500">
                Suggestions are drawn from Partner names already encoded on ADL allocations. Typing a new name is
                fine &mdash; this is free text, not a managed list.
            </p>

            @error('nga')
                <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-4">
            <h2 class="text-sm font-bold text-[#10294f]">Target &amp; Accomplishment</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Balance is Total Beneficiaries minus Accomplishments.
            </p>
        </div>

        <div class="grid gap-5 p-5 lg:grid-cols-2">
            <div>
                <label for="total_beneficiaries" class="mb-2 block text-xs font-semibold text-slate-700">
                    Total Number of Beneficiaries <span class="text-red-600">*</span>
                </label>

                <input
                    id="total_beneficiaries"
                    name="total_beneficiaries"
                    type="number"
                    min="0"
                    required
                    value="{{ $initialTotalBeneficiaries }}"
                    placeholder="0"
                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                >

                @error('total_beneficiaries')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="amount" class="mb-2 block text-xs font-semibold text-slate-700">
                    Amount <span class="text-red-600">*</span>
                </label>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-medium text-slate-500">₱</span>
                    <input
                        id="amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        data-money-input
                        value="{{ old('amount', $editing ? $target->amount : '') }}"
                        placeholder="0.00"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                    >
                </div>

                @error('amount')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="accomplishments" class="mb-2 block text-xs font-semibold text-slate-700">
                    Accomplishments <span class="text-red-600">*</span>
                </label>

                <input
                    id="accomplishments"
                    name="accomplishments"
                    type="number"
                    min="0"
                    required
                    value="{{ $initialAccomplishments }}"
                    placeholder="0"
                    class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-[#1765d8] focus:ring-2 focus:ring-[#1765d8]/15"
                >

                <p class="mt-1.5 text-[11px] text-slate-500">
                    How many of the Total Beneficiaries have been served so far.
                </p>

                @error('accomplishments')
                    <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="balance" class="mb-2 block text-xs font-semibold text-slate-700">
                    Balance
                </label>

                <input
                    id="balance"
                    type="number"
                    readonly
                    tabindex="-1"
                    value="{{ (int) $initialTotalBeneficiaries - (int) $initialAccomplishments }}"
                    class="h-11 w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3.5 text-sm font-semibold text-slate-700 outline-none"
                >

                <p class="mt-1.5 text-[11px] text-slate-500">
                    Automatically follows Total Beneficiaries &minus; Accomplishments.
                </p>
            </div>
        </div>
    </section>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const totalInput = document.getElementById('total_beneficiaries');
            const accomplishmentsInput = document.getElementById('accomplishments');
            const balanceInput = document.getElementById('balance');

            if (!totalInput || !accomplishmentsInput || !balanceInput) {
                return;
            }

            const syncBalance = () => {
                const total = Number(totalInput.value || 0);
                const accomplishments = Number(accomplishmentsInput.value || 0);
                balanceInput.value = total - accomplishments;
            };

            totalInput.addEventListener('input', syncBalance);
            accomplishmentsInput.addEventListener('input', syncBalance);
            syncBalance();
        });
    </script>
@endpush
