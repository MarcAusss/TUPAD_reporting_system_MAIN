@if (in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::APPROVED,
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
            \App\Enums\ProjectStatus::FOR_LIQUIDATION,
            \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
            \App\Enums\ProjectStatus::COMPLETED,
        ],
        true) && $project->implementation_mode === \App\Enums\ImplementationMode::THROUGH_ACP)
    <section id="acp-workflow" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 rounded-xl border border-violet-200 bg-violet-50 p-5 {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
        <div class="text-sm font-semibold text-violet-950">
            Through ACP Workflow
        </div>
        <p class="mt-1 text-xs leading-5 text-violet-800">
            Evaluation → Approval → ACP Payment → Check Release → GSIS Enrollment, PPE, NAFA, Notice to Proceed →
            Orientation &amp; Work Period → Release of Assistance → Liquidation. Post-Documentary Requirements and
            Payment of Wages apply only to Direct Administration projects.
        </p>

        @if (
            (auth()->user()->isAdmin() || auth()->user()->isFocal()) &&
                in_array(
                    $project->status,
                    [
                        \App\Enums\ProjectStatus::FOR_PAYMENT,
                        \App\Enums\ProjectStatus::FOR_RELEASE_OF_CHECK_TO_PROPONENT,
                        \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    ],
                    true))
            <div class="mt-4">
                <a href="{{ route('acp-payments.show', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Open Through ACP Payment & Check Release
                </a>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            @if (
                (auth()->user()->isAdmin() || auth()->user()->isTc()) &&
                    in_array(
                        $project->status,
                        [
                            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                            \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                            \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                            \App\Enums\ProjectStatus::COMPLETED,
                        ],
                        true))
                <a href="{{ route('acp-implementation.show', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg border border-violet-300 bg-white px-4 text-sm font-semibold text-violet-800 hover:bg-violet-100">
                    Open ACP Implementation
                </a>
            @endif

            @if (
                (auth()->user()->isAdmin() || auth()->user()->isFocal()) &&
                    in_array(
                        $project->status,
                        [
                            \App\Enums\ProjectStatus::FOR_LIQUIDATION,
                            \App\Enums\ProjectStatus::PARTIALLY_LIQUIDATED,
                            \App\Enums\ProjectStatus::COMPLETED,
                        ],
                        true))
                <a href="{{ route('acp-liquidations.show', $project) }}"
                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                    Open ACP Liquidation
                </a>
            @endif
        </div>
    </section>

@endif
