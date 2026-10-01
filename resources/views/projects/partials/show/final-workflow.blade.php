{{-- Authoritative Project Workflow Guide --}}
<section id="final-workflow" data-workspace-panel="workflow"
    class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">
    <div class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">
        Authoritative {{ $project->implementation_mode->label() }} Workflow
    </div>

    @php
        $workflowStatuses = app(\App\Services\Projects\ProjectWorkflowDefinition::class)->happyPathFor(
            $project->implementation_mode,
        );
    @endphp

    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($workflowStatuses as $workflowStatus)
            <span
                class="inline-flex items-center rounded-full border px-3 py-1.5 text-[11px] font-semibold {{ $project->status === $workflowStatus
                    ? 'border-blue-300 bg-blue-50 text-blue-800'
                    : 'border-slate-200 bg-slate-50 text-slate-600' }}">
                {{ $loop->iteration }}. {{ $workflowStatus->label() }}
            </span>
        @endforeach
    </div>

    <p class="mt-3 text-[11px] leading-5 text-slate-500">
        For Compliance remains an optional TSSD evaluation branch before For Approval when deficiencies require
        corrective submission.
    </p>
</section>
