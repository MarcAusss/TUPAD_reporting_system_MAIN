{{-- <------------------------------------------- Beneficiary Registry ------------------------------/> --}}

@if (auth()->user()->isAdmin() || auth()->user()->isTc())

    @php
        $registeredBeneficiaries = $project->beneficiaries->count();

        $registryComplete = $registeredBeneficiaries === (int) $project->beneficiaries_total;
    @endphp

    <section class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">

        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">

            <div>

                <h2 class="text-sm font-semibold text-slate-900">
                    Beneficiary Registry
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($registeredBeneficiaries) }}
                    of
                    {{ number_format($project->beneficiaries_total) }}
                    beneficiaries encoded.
                </p>

            </div>

            <div class="flex items-center gap-3">

                <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                        {{ $registryComplete ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $registryComplete ? 'Complete' : 'Incomplete' }}
                </span>

                @if ($project->status === \App\Enums\ProjectStatus::ONGOING_PROFILING)
                    <a href="{{ route('projects.beneficiaries.index', $project) }}"
                        class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                        Manage Registry
                    </a>
                @endif

            </div>

        </div>

    </section>

@endif
