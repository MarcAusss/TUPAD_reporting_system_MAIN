{{-- Beneficiary Summary --}}

<section data-workspace-panel="beneficiaries"
    class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Beneficiary Summary</h2>
        <p class="mt-1 text-xs text-slate-500">
            Only aggregate beneficiary counts are recorded. Individual personal records are not encoded; beneficiary
            residence geography is stored separately as aggregate address allocations.
        </p>
    </div>

    <div class="grid gap-4 p-5 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Beneficiaries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($project->beneficiaries_total) }}
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Female Beneficiaries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">
                {{ number_format($project->beneficiaries_female) }}</div>
        </div>
    </div>
</section>
