{{-- Financial Summary --}}

<div id="financial-summary" data-workspace-panel="financial"
    class="scroll-mt-32 mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4 {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Wages
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->wages_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            PPE
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->ppe_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Insurance
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->insurance_total, 2) }}
        </div>

    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">

        <div class="text-xs font-semibold uppercase text-slate-400">
            Total Project Cost
        </div>

        <div class="mt-3 text-xl font-bold text-slate-900">
            ₱{{ number_format($project->total_project_cost, 2) }}
        </div>

    </div>

</div>
