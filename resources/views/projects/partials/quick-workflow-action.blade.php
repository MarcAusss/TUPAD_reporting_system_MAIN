@php
    $user = auth()->user();
    $canAdvanceEarlyWorkflow = $user->isAdmin() || $user->isTc();

    $quickModalStatus = in_array($project->status, [
        \App\Enums\ProjectStatus::ONGOING_PROFILING,
        \App\Enums\ProjectStatus::TSSD_EVALUATION,
        \App\Enums\ProjectStatus::FOR_COMPLIANCE,
        \App\Enums\ProjectStatus::FOR_APPROVAL,
    ], true);

    $quickModalAvailable = $canAdvanceEarlyWorkflow && $quickModalStatus;

    $nextStatusLabel = match ($project->status) {
        \App\Enums\ProjectStatus::ONGOING_PROFILING => 'TSSD Evaluation',
        \App\Enums\ProjectStatus::TSSD_EVALUATION => 'For Compliance / For Approval',
        \App\Enums\ProjectStatus::FOR_COMPLIANCE => 'For Approval',
        \App\Enums\ProjectStatus::FOR_APPROVAL => 'Approved',
        default => $workspace['action']['title'],
    };
@endphp

<section class="sticky top-[86px] z-20 mt-4 rounded-xl border border-blue-200 bg-white/95 shadow-md backdrop-blur" data-quick-workflow-dock>
    <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-700">Next Workflow Action</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-600">
                    {{ $project->status->label() }} → {{ $nextStatusLabel }}
                </span>
            </div>
            <div class="mt-1 truncate text-sm font-bold text-slate-950">{{ $workspace['action']['title'] }}</div>
            <p class="mt-0.5 hidden text-xs text-slate-500 md:block">{{ $workspace['action']['description'] }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            @if($quickModalAvailable)
                <button type="button" data-quick-workflow-open
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b] focus:outline-none focus:ring-2 focus:ring-blue-300">
                    Continue Workflow
                </button>
            @elseif($workspace['action']['label'] && $workspace['action']['href'])
                @if($workspace['action']['external'])
                    <a href="{{ $workspace['action']['href'] }}"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        {{ $workspace['action']['label'] }}
                    </a>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['workspace' => $workspace['action']['tab']]).'#'.$workspace['action']['anchor'] }}"
                        data-workspace-open-tab="{{ $workspace['action']['tab'] }}"
                        data-workspace-anchor="{{ $workspace['action']['anchor'] }}"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">
                        {{ $workspace['action']['label'] }}
                    </a>
                @endif
            @endif
        </div>
    </div>
</section>

@if($quickModalAvailable)
    @php
        $latestComplianceEvaluation = $project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE
            ? $project->evaluations->where('result', 'for_compliance')->sortByDesc('evaluated_at')->first()
            : null;
    @endphp

    <div class="fixed inset-0 z-[80] hidden" data-quick-workflow-modal data-auto-open="{{ old('quick_action') === '1' ? 'true' : 'false' }}" role="dialog" aria-modal="true" aria-labelledby="quick-workflow-title">
        <div class="absolute inset-0 bg-slate-950/50" data-quick-workflow-close></div>

        <div class="relative mx-auto flex min-h-full max-w-2xl items-center px-4 py-8">
            <section class="max-h-[88vh] w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-700">Quick Workflow Progression</div>
                        <h2 id="quick-workflow-title" class="mt-1 text-lg font-bold text-slate-950">{{ $project->status->label() }} → {{ $nextStatusLabel }}</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Complete the current workflow action here without scrolling through the full project record.</p>
                    </div>
                    <button type="button" data-quick-workflow-close aria-label="Close workflow dialog"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50">×</button>
                </header>

                <div class="p-5">
                    @if($errors->any() && old('quick_action') === '1')
                        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800">
                            <div class="font-bold">Review the highlighted workflow fields.</div>
                            <ul class="mt-1 list-disc space-y-1 pl-5">
                                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @if($project->status === \App\Enums\ProjectStatus::ONGOING_PROFILING)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            Confirm that the project profile, aggregate beneficiary information, location, implementation mode, and cost details are complete. This action moves the project to <strong>TSSD Evaluation</strong>.
                        </div>
                        <form method="POST" action="{{ route('projects.evaluation.start', $project) }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="quick_action" value="1">
                            <div class="flex justify-end gap-2">
                                <button type="button" data-quick-workflow-close class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="submit" class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">Submit to TSSD Evaluation</button>
                            </div>
                        </form>
                    @elseif($project->status === \App\Enums\ProjectStatus::TSSD_EVALUATION)
                        <form method="POST" action="{{ route('projects.evaluation.store', $project) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="quick_action" value="1">
                            <div>
                                <label for="quick-evaluation-result" class="mb-2 block text-xs font-semibold text-slate-700">Evaluation Result <span class="text-rose-600">*</span></label>
                                <select id="quick-evaluation-result" name="result" required data-quick-evaluation-result class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                    <option value="">Select result</option>
                                    <option value="for_compliance" @selected(old('result') === 'for_compliance')>For Compliance</option>
                                    <option value="for_approval" @selected(old('result') === 'for_approval')>For Approval</option>
                                </select>
                            </div>
                            <div data-quick-compliance-fields class="space-y-4">
                                <div>
                                    <label for="quick-evaluation-findings" class="mb-2 block text-xs font-semibold text-slate-700">Findings <span class="text-rose-600">*</span></label>
                                    <textarea id="quick-evaluation-findings" name="findings" rows="3" data-quick-evaluation-findings class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('findings') }}</textarea>
                                </div>
                                <div>
                                    <label for="quick-evaluation-required-documents" class="mb-2 block text-xs font-semibold text-slate-700">Required Documents <span class="text-rose-600">*</span></label>
                                    <textarea id="quick-evaluation-required-documents" name="required_documents" rows="3" data-quick-evaluation-documents class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('required_documents') }}</textarea>
                                </div>
                            </div>
                            <div data-quick-approval-note class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                                For Approval does not require findings or required documents.
                            </div>
                            <div>
                                <label for="quick-evaluation-remarks" class="mb-2 block text-xs font-semibold text-slate-700">Remarks</label>
                                <textarea id="quick-evaluation-remarks" name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" data-quick-workflow-close class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancel</button>
                                <button type="submit" class="h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white">Save Evaluation</button>
                            </div>
                        </form>
                    @elseif($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
                            @if($latestComplianceEvaluation)
                                <strong>Findings:</strong> {{ $latestComplianceEvaluation->findings ?: '—' }}<br>
                                <strong>Required documents:</strong> {{ $latestComplianceEvaluation->required_documents ?: '—' }}
                            @else
                                Record the date when all required compliance items have been satisfied.
                            @endif
                        </div>
                        <form method="POST" action="{{ route('projects.compliance.store', $project) }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="quick_action" value="1">
                            <div>
                                <label for="quick-compliance-date" class="mb-2 block text-xs font-semibold text-slate-700">Date of Compliance <span class="text-rose-600">*</span></label>
                                <input id="quick-compliance-date" name="compliance_date" type="date" required
                                    min="{{ $latestComplianceEvaluation?->evaluated_at?->toDateString() }}"
                                    value="{{ old('compliance_date', now()->toDateString()) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" data-quick-workflow-close class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancel</button>
                                <button type="submit" class="h-10 rounded-lg bg-amber-700 px-5 text-sm font-semibold text-white">Save Compliance & Continue</button>
                            </div>
                        </form>
                    @elseif($project->status === \App\Enums\ProjectStatus::FOR_APPROVAL)
                        <form method="POST" action="{{ route('projects.approval.store', $project) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="quick_action" value="1">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="quick-approval-date" class="mb-2 block text-xs font-semibold text-slate-700">Date of Approval <span class="text-rose-600">*</span></label>
                                    <input id="quick-approval-date" name="approval_date" type="date" required value="{{ old('approval_date', now()->format('Y-m-d')) }}" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>
                                <div>
                                    <label for="quick-project-code" class="mb-2 block text-xs font-semibold text-slate-700">Official Project Code <span class="text-rose-600">*</span></label>
                                    <input id="quick-project-code" name="project_code" type="text" required autocomplete="off" value="{{ old('project_code') }}" placeholder="Example: TUPAD-ALB-2026-001" class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm font-semibold uppercase tracking-wide">
                                </div>
                            </div>
                            <div>
                                <label for="quick-approval-remarks" class="mb-2 block text-xs font-semibold text-slate-700">Approval Remarks</label>
                                <textarea id="quick-approval-remarks" name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" data-quick-workflow-close class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancel</button>
                                <button type="submit" class="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white">Approve Project</button>
                            </div>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endif
