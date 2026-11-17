{{-- Payment of Wages --}}

@if (
    $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION &&
        in_array($project->status, [\App\Enums\ProjectStatus::FOR_PAYMENT, \App\Enums\ProjectStatus::COMPLETED], true))

    <section id="payment" data-workspace-panel="financial"
        class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'financial' ? 'hidden' : '' }}">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">
                Payment of Wages
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Wage obligations and disbursements are managed by the Focal/Admin account.
            </p>
        </div>

        <div class="p-5">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="text-sm font-semibold text-blue-950">
                    Obligation and Disbursement Processing
                </div>
                <p class="mt-1 text-xs leading-5 text-blue-700">
                    Official project references, totals, payment tranches, and their corresponding disbursements are
                    consolidated in the Payment of Wages interface.
                </p>

                @if (auth()->user()->isAdmin() || auth()->user()->isFocal())
                    <a href="{{ route('payments.show', $project) }}"
                        class="mt-3 inline-flex h-9 items-center rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white hover:bg-[#052f6b]">
                        Manage Payment of Wages
                    </a>
                @else
                    <p class="mt-3 text-xs font-semibold text-blue-800">
                        Waiting for Focal/Admin payment action.
                    </p>
                @endif
            </div>
        </div>
    </section>

@endif
