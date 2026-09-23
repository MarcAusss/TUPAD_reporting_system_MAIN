{{-- =====================================================
Program snapshot
====================================================== --}}
<section>

    <div class="mb-3">
        <h2 class="text-sm font-semibold text-slate-900">
            Program Snapshot
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Current official project and beneficiary totals.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">

        <article class="tupad-card tupad-metric-card p-5">
            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Active Projects
            </div>

            <div class="mt-2 text-2xl font-extrabold text-slate-900">
                {{ number_format($activeProjects) }}
            </div>

            <div class="mt-1 text-xs text-slate-500">
                {{ number_format($totalProjects) }} total official projects
            </div>
        </article>

        <article class="tupad-card tupad-metric-card p-5">
            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Beneficiaries
            </div>

            <div class="mt-2 text-2xl font-extrabold text-slate-900">
                {{ number_format($totalBeneficiaries) }}
            </div>

            <div class="mt-1 text-xs text-slate-500">
                {{ number_format($femaleBeneficiaries) }} female beneficiaries
            </div>
        </article>

        <article class="tupad-card tupad-metric-card p-5">
            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Completed Projects
            </div>

            <div class="mt-2 text-2xl font-extrabold text-slate-900">
                {{ number_format($completedProjects) }}
            </div>

            <div class="mt-1 text-xs text-slate-500">
                Completed official workflow
            </div>
        </article>

        <article class="tupad-card tupad-metric-card p-5">
            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Total Program Budget
            </div>

            <div class="mt-2 truncate text-2xl font-extrabold text-slate-900">
                ₱{{ number_format($totalBudget, 2) }}
            </div>

            <div class="mt-1 text-xs text-slate-500">
                Current adjusted ADL fund basis
            </div>
        </article>

    </div>

</section>
