{{-- Evaluation & Approval --}}

<section id="evaluation" data-workspace-panel="workflow"
    class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">

        <h2 class="text-sm font-semibold text-slate-900">
            Evaluation & Approval
        </h2>

        <p class="mt-1 text-xs text-slate-500">
            Current project status: {{ $project->status->label() }}
        </p>

    </div>

    <div class="p-5">

        {{-- TSSD Evaluation / Compliance --}}

        @if (in_array(
                $project->status,
                [\App\Enums\ProjectStatus::TSSD_EVALUATION, \App\Enums\ProjectStatus::FOR_COMPLIANCE],
                true))

            @if ($project->status === \App\Enums\ProjectStatus::FOR_COMPLIANCE)

                @php
                    $latestEvaluation = $project->evaluations
                        ->where('result', 'for_compliance')
                        ->sortByDesc('evaluated_at')
                        ->first();

                    $complianceAgingDays = $latestEvaluation?->evaluated_at
                        ? (int) $latestEvaluation->evaluated_at
                            ->copy()
                            ->startOfDay()
                            ->diffInDays(now()->startOfDay())
                        : 0;
                @endphp

                <div class="mb-5 overflow-hidden rounded-xl border border-amber-200 bg-amber-50">

                    <div class="border-b border-amber-200 px-4 py-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                            <div>
                                <div class="text-sm font-semibold text-amber-900">
                                    Project for Compliance
                                </div>

                                <p class="mt-1 text-xs leading-5 text-amber-700">
                                    Record the Date of Compliance. Saving automatically moves this project to For
                                    Approval.
                                </p>
                            </div>

                            <div class="rounded-lg border border-amber-300 bg-white px-4 py-2 text-right">
                                <div class="text-[10px] font-bold uppercase tracking-wide text-amber-600">
                                    Aging
                                </div>

                                <div class="mt-0.5 text-lg font-bold text-amber-900">
                                    {{ number_format($complianceAgingDays) }}
                                    <span class="text-xs font-semibold">
                                        day(s)
                                    </span>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="p-4">

                        @if ($latestEvaluation)
                            <div class="grid gap-4 lg:grid-cols-2">

                                <div class="rounded-lg border border-amber-200 bg-white p-4">
                                    <div class="text-xs font-semibold text-amber-800">
                                        Findings
                                    </div>

                                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                                        {{ $latestEvaluation->findings ?: '—' }}
                                    </p>
                                </div>

                                <div class="rounded-lg border border-amber-200 bg-white p-4">
                                    <div class="text-xs font-semibold text-amber-800">
                                        Required Documents
                                    </div>

                                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">
                                        {{ $latestEvaluation->required_documents ?: '—' }}
                                    </p>

                                    @include('projects.partials.evaluation-attachment-links', [
                                        'attachments' => $latestEvaluation->evaluationAttachments(),
                                        'projectId' => $project->id,
                                    ])
                                </div>

                            </div>

                            <div class="mt-4 text-xs text-amber-700">
                                Compliance requested:
                                <span class="font-semibold">
                                    {{ $latestEvaluation->evaluated_at->format('F d, Y') }}
                                </span>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('projects.compliance.store', $project) }}"
                            enctype="multipart/form-data" class="mt-5">

                            @csrf

                            <div class="grid gap-4 md:grid-cols-2 md:items-end">

                                <div>
                                    <label for="compliance-date"
                                        class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date of Compliance
                                        <span class="text-rose-600">*</span>
                                    </label>

                                    <input id="compliance-date" name="compliance_date" type="date" required
                                        min="{{ $latestEvaluation?->evaluated_at?->toDateString() }}"
                                        value="{{ old('compliance_date', now()->toDateString()) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                    @error('compliance_date')
                                        <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                            </div>

                            <div class="mt-4">
                                <label for="compliance-remarks"
                                    class="mb-2 block text-xs font-semibold text-slate-700">
                                    Compliance Remarks
                                    <span class="text-rose-600">*</span>
                                </label>

                                <textarea id="compliance-remarks" name="compliance_remarks" rows="3" required maxlength="5000"
                                    placeholder="State what was submitted to comply, e.g. the specific documents provided for each required item above..."
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('compliance_remarks') }}</textarea>

                                <p class="mt-1 text-[11px] leading-4 text-amber-700">
                                    Describe the complied documents against the Required Documents listed above.
                                    This
                                    is kept on record in the project's evaluation history and Compliance History.
                                </p>

                                @error('compliance_remarks')
                                    <p class="mt-1 text-[10px] font-semibold text-rose-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="mt-4">
                                <label for="compliance-attachments"
                                    class="mb-2 block text-xs font-semibold text-slate-700">
                                    Compliance Attachments
                                    <span class="font-normal text-slate-400">(optional, up to 10 files)</span>
                                </label>

                                <input id="compliance-attachments" name="attachments[]" type="file" multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:h-10 file:border-0 file:bg-amber-50 file:px-4 file:text-sm file:font-semibold file:text-amber-800 hover:file:bg-amber-100">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Upload the complied documents (PDF, JPG, PNG, Word, or Excel; 10 MB max each).
                                </p>

                                @error('attachments')
                                    <p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>
                                @enderror
                                @foreach ($errors->get('attachments.*') as $attachmentMessages)
                                    @foreach ($attachmentMessages as $attachmentMessage)
                                        <p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $attachmentMessage }}</p>
                                    @endforeach
                                @endforeach
                            </div>

                            <div class="mt-4 flex md:justify-end">
                                <button type="submit"
                                    class="h-10 rounded-lg bg-amber-700 px-5 text-sm font-semibold text-white hover:bg-amber-800">
                                    Save Compliance
                                </button>
                            </div>

                        </form>

                    </div>

                </div>

            @endif

            @if ($project->status === \App\Enums\ProjectStatus::TSSD_EVALUATION)
                <form method="POST" action="{{ route('projects.evaluation.store', $project) }}"
                    enctype="multipart/form-data" class="space-y-4">

                    @csrf

                    <div class="grid gap-4 md:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Evaluation Result
                            </label>

                            <select id="evaluation-result" name="result" required
                                class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">

                                <option value="">
                                    Select result
                                </option>

                                <option value="for_compliance" @selected(old('result') === 'for_compliance')>
                                    For Compliance
                                </option>

                                <option value="for_approval" @selected(old('result') === 'for_approval')>
                                    For Approval
                                </option>

                            </select>

                        </div>

                    </div>

                    <div id="for-approval-note"
                        class="hidden rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <div class="text-xs font-semibold text-emerald-900">
                            Ready for Approval
                        </div>

                        <p class="mt-1 text-xs leading-5 text-emerald-700">
                            Findings and Required Documents are not required when the evaluation result is For
                            Approval.
                        </p>
                    </div>

                    <div id="compliance-fields" class="space-y-4">
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">

                            <div class="text-xs font-semibold text-amber-900">
                                Compliance Details Required
                            </div>

                            <p class="mt-1 text-xs leading-5 text-amber-700">
                                Both Findings and Required Documents are required when the result is For Compliance.
                            </p>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Findings
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea id="evaluation-findings" name="findings" rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="State the findings that require compliance...">{{ old('findings') }}</textarea>

                        </div>

                        <div>

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Required Documents
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea id="evaluation-required-documents" name="required_documents" rows="3"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                placeholder="List the documentary requirements to be complied with...">{{ old('required_documents') }}</textarea>

                        </div>

                        <div>
                            <label for="evaluation-attachments" class="mb-2 block text-xs font-semibold text-slate-700">
                                Evaluation Attachments
                                <span class="font-normal text-slate-400">(optional, up to 10 files)</span>
                            </label>

                            <input id="evaluation-attachments" name="attachments[]" type="file" multiple
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">

                            <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                Attach the evaluation checklist, findings memo, or sample documents the TC must comply with
                                (PDF, JPG, PNG, Word, or Excel; 10 MB max each).
                            </p>

                            @error('attachments')
                                <p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $message }}</p>
                            @enderror
                            @foreach ($errors->get('attachments.*') as $attachmentMessages)
                                @foreach ($attachmentMessages as $attachmentMessage)
                                    <p class="mt-1 text-[10px] font-semibold text-rose-600">{{ $attachmentMessage }}</p>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    <div>

                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

                    </div>

                    <div class="flex justify-end">

                        <button type="submit"
                            class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Evaluation
                        </button>

                    </div>

                </form>
            @endif

        @endif

        {{-- For Approval --}}

        @if ($project->status === \App\Enums\ProjectStatus::FOR_APPROVAL)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">

                <div class="text-sm font-semibold text-emerald-800">
                    Project ready for approval
                </div>

                <p class="mt-1 text-xs leading-5 text-emerald-700">
                    The official Project Code is generated automatically by the system the moment this
                    project is approved &mdash; it is derived from the coordinator's assigned province, the
                    project's municipality, and the approval date, and cannot be typed or edited.
                    Saving approval automatically updates the project status to Approved.
                </p>

            </div>

            <form method="POST" action="{{ route('projects.approval.store', $project) }}"
                class="mt-5 space-y-4" data-confirm-title="Approve this project?" data-confirm="The system generates the official project code and moves the project to the next stage. This cannot be undone." data-confirm-button="Approve Project">

                @csrf

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Date of Approval
                    </label>

                    <input name="approval_date" type="date"
                        value="{{ old('approval_date', now()->format('Y-m-d')) }}" required
                        class="h-10 w-full max-w-xs rounded-lg border border-slate-300 px-3 text-sm">

                    @error('approval')
                        <p class="mt-1.5 text-[10px] font-semibold text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <div>

                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                        Approval Remarks
                    </label>

                    <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>

                </div>

                <div class="flex justify-end">

                    <button type="submit"
                        class="h-10 rounded-lg bg-emerald-700 px-5 text-sm font-semibold text-white hover:bg-emerald-800">
                        Approve Project
                    </button>

                </div>

            </form>
        @endif

        {{-- Approved --}}

        @if (in_array(
                $project->status,
                [
                    \App\Enums\ProjectStatus::APPROVED,
                    \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
                    \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                    \App\Enums\ProjectStatus::FOR_PAYMENT,
                    \App\Enums\ProjectStatus::COMPLETED,
                ],
                true) && $project->approval)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                            Approved Project
                        </div>

                        <div class="mt-1 text-xl font-bold text-emerald-900">
                            {{ $project->approval->project_code }}
                        </div>

                    </div>

                    <div class="text-left sm:text-right">

                        <div class="text-xs text-emerald-600">
                            Date of Approval
                        </div>

                        <div class="mt-1 text-sm font-semibold text-emerald-900">
                            {{ $project->approval->approval_date->format('F d, Y') }}
                        </div>

                    </div>

                </div>

            </div>
        @endif

    </div>

</section>
