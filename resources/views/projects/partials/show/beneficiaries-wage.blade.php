{{-- Beneficiaries & Wage --}}

<section data-workspace-panel="beneficiaries"
    class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'beneficiaries' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Beneficiaries & Wage
        </h2>

    </div>

    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">

        <div>

            <div class="text-xs text-slate-500">
                Declared Beneficiaries
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->beneficiaries_total) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Female Beneficiaries
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                {{ number_format($project->beneficiaries_female) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Wage Rate
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                ₱{{ number_format($project->wage_rate, 2) }}
            </div>

        </div>

        <div>

            <div class="text-xs text-slate-500">
                Insurance Rate
            </div>

            <div class="mt-1 text-lg font-bold text-slate-900">
                ₱{{ number_format($project->insurance_rate, 2) }}
            </div>

        </div>

    </div>

</section>
