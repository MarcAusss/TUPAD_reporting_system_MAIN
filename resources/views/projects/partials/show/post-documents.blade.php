{{-- Post-Documentary Requirements --}}

@php
    $postDocTranches = app(\App\Services\Projects\LongTermTrancheService::class);
    $postDocLongTerm = $postDocTranches->applies($project);
    $postDocOpen = $postDocTranches->postDocumentsOpen($project);
    $postDocCanRecord = $postDocOpen && (auth()->user()->isAdmin() || auth()->user()->isTc());
    $postDocCanEditCoverage = auth()->user()->isAdmin() || auth()->user()->isTc();
    $postDocVisibleStatuses = $postDocLongTerm
        ? [...\App\Services\Projects\LongTermTrancheService::OPEN_STATUSES, \App\Enums\ProjectStatus::COMPLETED]
        : [
            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
            \App\Enums\ProjectStatus::FOR_PAYMENT,
            \App\Enums\ProjectStatus::COMPLETED,
        ];
    $postDocNextTranche = $postDocLongTerm ? $postDocTranches->nextTrancheNumber($project) : null;
    $postDocObligatedIds = $project->obligations->pluck('project_post_document_id')->filter()->all();
@endphp

@if (
    $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION &&
        in_array($project->status, $postDocVisibleStatuses, true))

    <section id="post-documents" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Submission of Post-Documentary Requirements
                    </h2>

                    @if ($postDocLongTerm)
                        <p class="mt-1 text-xs text-slate-500">
                            Long-term project: record one submission per tranche. The TC states the tranche's
                            beneficiaries and the dates it covers; the Focal then processes that tranche's payment,
                            even while implementation is ongoing. The project completes only after the work period
                            ends and every tranche is obligated, disbursed, and released.
                        </p>
                    @else
                        <p class="mt-1 text-xs text-slate-500">
                            TC/Admin records the complete post-implementation documentary submission.
                            Financial processing begins only after the required submission is recorded.
                        </p>
                    @endif
                </div>

                @if ($postDocLongTerm)
                    <span
                        class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">
                        Long-Term · Per Tranche
                    </span>
                @elseif ($project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS)
                    <span
                        class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                        Auto-update → For Payment
                    </span>
                @endif
            </div>
        </div>

        @if ($postDocCanRecord)
            <form id="post-documents-form" method="POST" action="{{ route('projects.post-documents.store', $project) }}"
                class="border-b border-slate-200 p-5">
                @csrf

                @if ($postDocLongTerm)
                    <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="text-[11px] font-bold uppercase tracking-widest text-blue-700">
                            Tranche {{ $postDocNextTranche }}
                        </div>
                        <p class="mt-1 text-xs text-blue-800">
                            The Focal sees these beneficiary counts on the payment form (and may still adjust them).
                            Only the TC/Admin can change the coverage dates.
                            @if ($project->implementation)
                                Work period: {{ $project->implementation->start_date->format('M d, Y') }} –
                                {{ $project->implementation->end_date->format('M d, Y') }}.
                            @endif
                        </p>

                        <div class="mt-3 grid gap-4 md:grid-cols-4">
                            <div>
                                <label for="post-doc-beneficiaries" class="mb-2 block text-xs font-semibold text-slate-700">
                                    No. of Beneficiaries
                                </label>
                                <input id="post-doc-beneficiaries" type="number" name="beneficiaries_total" required min="1"
                                    max="{{ (int) $project->beneficiaries_total }}" step="1"
                                    value="{{ old('beneficiaries_total', (int) $project->beneficiaries_total) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                <p class="mt-1 text-[11px] text-slate-500">Project: {{ number_format($project->beneficiaries_total) }}</p>
                                @error('beneficiaries_total')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="post-doc-female" class="mb-2 block text-xs font-semibold text-slate-700">
                                    Female
                                </label>
                                <input id="post-doc-female" type="number" name="beneficiaries_female" required min="0"
                                    max="{{ (int) $project->beneficiaries_female }}" step="1"
                                    value="{{ old('beneficiaries_female', (int) $project->beneficiaries_female) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                <p class="mt-1 text-[11px] text-slate-500">Project: {{ number_format($project->beneficiaries_female) }}</p>
                                @error('beneficiaries_female')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="post-doc-coverage-start" class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Covered – Start
                                </label>
                                <input id="post-doc-coverage-start" type="date" name="coverage_start_date" required
                                    value="{{ old('coverage_start_date') }}"
                                    @if ($project->implementation)
                                        min="{{ $project->implementation->start_date->toDateString() }}"
                                        max="{{ $project->implementation->end_date->toDateString() }}"
                                    @endif
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                @error('coverage_start_date')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="post-doc-coverage-end" class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date Covered – End
                                </label>
                                <input id="post-doc-coverage-end" type="date" name="coverage_end_date" required
                                    value="{{ old('coverage_end_date') }}"
                                    @if ($project->implementation)
                                        min="{{ $project->implementation->start_date->toDateString() }}"
                                        max="{{ $project->implementation->end_date->toDateString() }}"
                                    @endif
                                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                @error('coverage_end_date')
                                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Received
                        </label>

                        <input type="date" name="date_received" required
                            value="{{ old('date_received', now()->format('Y-m-d')) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        @error('date_received')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Forwarded to IMSD
                        </label>

                        <input type="date" name="date_forwarded_to_imsd" required
                            value="{{ old('date_forwarded_to_imsd') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                        @error('date_forwarded_to_imsd')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="post-documents-received" class="mb-2 block text-xs font-semibold text-slate-700">
                            Documents Received
                        </label>

                        <input id="post-documents-received" type="text" name="document_type" maxlength="255"
                            value="{{ old('document_type') }}"
                            placeholder="e.g. Payroll, Accomplishment Report, DTR"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                        <p class="mt-1 text-[11px] text-slate-400">
                            Optional. List the post-documentary requirements received.
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Remarks
                        </label>

                        <textarea name="remarks" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit"
                        class="h-10 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6d]">
                        {{ $postDocLongTerm ? 'Save Tranche '.$postDocNextTranche.' Post-Documents' : 'Save Post-Documentary Requirements' }}
                    </button>
                </div>
            </form>
        @elseif ($postDocLongTerm && $project->obligations_completed_at !== null && $project->status !== \App\Enums\ProjectStatus::COMPLETED)
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-600">
                The Focal completed the obligation tranches, so no more tranches can be added.
            </div>
        @endif

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        @if ($postDocLongTerm)
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Tranche
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Beneficiaries
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Date Covered
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Balance Days
                            </th>
                        @endif

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date Received
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Document
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Forwarded to IMSD
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Remarks
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @forelse($project->postDocuments->sortBy(fn ($document) => [$document->tranche_number ?? 0, $document->id]) as $document)
                        @php
                            $coverageBag = $errors->getBag(\App\Http\Controllers\ProjectPostDocumentController::coverageErrorBag($document));
                            $canEditThisCoverage = $postDocCanEditCoverage && $postDocTranches->coverageEditable($project, $document);
                        @endphp
                        <tr class="align-top">

                            @if ($postDocLongTerm)
                                <td class="px-5 py-4 text-sm">
                                    @if ($document->isTranche())
                                        <div class="font-semibold text-slate-900">Tranche {{ $document->tranche_number }}</div>
                                        <div class="mt-1 text-[11px] {{ in_array($document->id, $postDocObligatedIds, true) ? 'text-emerald-700' : 'text-amber-700' }}">
                                            {{ in_array($document->id, $postDocObligatedIds, true) ? 'Obligated by Focal' : 'Waiting for Focal payment' }}
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right text-sm tabular-nums text-slate-700">
                                    @if ($document->isTranche())
                                        {{ number_format($document->beneficiaries_total) }}
                                        <div class="text-[11px] text-slate-500">{{ number_format($document->beneficiaries_female) }} female</div>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-700">
                                    @if ($document->isTranche())
                                        <div class="whitespace-nowrap">
                                            {{ $document->coverage_start_date->format('M d, Y') }} –
                                            {{ $document->coverage_end_date->format('M d, Y') }}
                                        </div>
                                        <div class="text-[11px] text-slate-500">{{ number_format($document->coverageDays()) }} day(s)</div>

                                        @if ($canEditThisCoverage)
                                            <details class="mt-2" @if ($coverageBag->any()) open @endif>
                                                <summary class="inline-flex cursor-pointer items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-[#063b86] hover:bg-slate-50">
                                                    Edit
                                                </summary>

                                                <form method="POST"
                                                    action="{{ route('projects.post-documents.coverage.update', [$project, $document]) }}"
                                                    class="mt-2 w-64 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                    @csrf
                                                    @method('PUT')

                                                    <label class="block text-[11px] font-semibold text-slate-700">
                                                        Start
                                                        <input type="date" name="coverage_start_date" required
                                                            value="{{ $coverageBag->any() ? old('coverage_start_date') : $document->coverage_start_date->toDateString() }}"
                                                            class="mt-1 h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs">
                                                    </label>

                                                    <label class="block text-[11px] font-semibold text-slate-700">
                                                        End
                                                        <input type="date" name="coverage_end_date" required
                                                            value="{{ $coverageBag->any() ? old('coverage_end_date') : $document->coverage_end_date->toDateString() }}"
                                                            class="mt-1 h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs">
                                                    </label>

                                                    @foreach ($coverageBag->all() as $coverageError)
                                                        <p class="text-[11px] text-rose-600">{{ $coverageError }}</p>
                                                    @endforeach

                                                    <button type="submit"
                                                        class="inline-flex h-8 items-center rounded-lg bg-[#063b86] px-3 text-[11px] font-semibold text-white hover:bg-[#052f6d]">
                                                        Save Dates
                                                    </button>
                                                </form>
                                            </details>
                                        @endif

                                        @if ($document->coverage_updated_at)
                                            <div class="mt-1 text-[10px] text-slate-400">
                                                Edited {{ $document->coverage_updated_at->format('M d, Y h:i A') }}
                                                @if ($document->coverageUpdater) by {{ $document->coverageUpdater->name }} @endif
                                            </div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right text-sm tabular-nums">
                                    @if ($document->isTranche())
                                        @php $balanceDays = $document->balanceDays(); @endphp
                                        <span class="font-semibold {{ $balanceDays > 0 ? 'text-blue-800' : 'text-slate-500' }}">
                                            {{ number_format($balanceDays) }}
                                        </span>
                                        <div class="text-[11px] text-slate-500">{{ $balanceDays > 0 ? 'day(s) left' : 'coverage ended' }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->date_received->format('M d, Y') }}
                            </td>

                            <td class="px-5 py-4 text-sm font-medium text-slate-800">
                                {{ $document->document_type }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->date_forwarded_to_imsd?->format('M d, Y') ?? 'Not yet forwarded' }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $document->remarks ?: '—' }}
                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="{{ $postDocLongTerm ? 8 : 4 }}" class="p-0"><x-empty-state size="sm" icon="document" title="No post-documentary requirements recorded." :action-label="$postDocCanRecord ? 'Record Post-Documents' : null" action-target="post-documents-form" action-tab="workflow" :message="$postDocLongTerm ? 'Each tranche\'s post-documents appear here once the TC records them, starting during implementation.' : 'The post-documentary requirements appear here once they are submitted after implementation.'" /></td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endif
