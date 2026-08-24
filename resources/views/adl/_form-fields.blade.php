<div class="space-y-6">
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">ADL Reference</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="xl:col-span-2">
                <label class="mb-2 block text-xs font-semibold text-slate-700">ADL Number</label>
                <input name="adl_number" required value="{{ old('adl_number', $adl->adl_number ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold text-slate-700">Date Received</label>
                <input type="date" name="date_received" value="{{ old('date_received', isset($adl) ? $adl->date_received?->format('Y-m-d') : '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold text-slate-700">Batch</label>
                <input name="batch" value="{{ old('batch', $adl->batch ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold text-slate-700">Tranche</label>
                <input name="tranche" value="{{ old('tranche', $adl->tranche ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-xs font-semibold text-slate-700">Sponsor / Funding Reference</label>
                <input name="sponsor_reference" value="{{ old('sponsor_reference', $adl->sponsor_reference ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">NFA / NTA</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><label class="mb-2 block text-xs font-semibold text-slate-700">NFA Date</label><input type="date" name="nfa_date" value="{{ old('nfa_date', isset($adl) ? $adl->nfa_date?->format('Y-m-d') : '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
            <div><label class="mb-2 block text-xs font-semibold text-slate-700">NFA Number</label><input name="nfa_number" value="{{ old('nfa_number', $adl->nfa_number ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
            <div><label class="mb-2 block text-xs font-semibold text-slate-700">NTA Date</label><input type="date" name="nta_date" value="{{ old('nta_date', isset($adl) ? $adl->nta_date?->format('Y-m-d') : '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
            <div><label class="mb-2 block text-xs font-semibold text-slate-700">NTA Number</label><input name="nta_number" value="{{ old('nta_number', $adl->nta_number ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Funding</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div><label class="mb-2 block text-xs font-semibold text-slate-700">Grants</label><input type="number" step="0.01" min="0" name="grants" required value="{{ old('grants', $adl->grants ?? '') }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
            <div><label class="mb-2 block text-xs font-semibold text-slate-700">Administrative Cost</label><input type="number" step="0.01" min="0" name="admin_cost" value="{{ old('admin_cost', $adl->admin_cost ?? 0) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm"></div>
        </div>
    </section>
</div>
