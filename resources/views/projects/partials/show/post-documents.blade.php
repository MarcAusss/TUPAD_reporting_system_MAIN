{{-- Post-Documentary Requirements --}}

@if (
    $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION &&
        in_array(
            $project->status,
            [
                \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
                \App\Enums\ProjectStatus::FOR_PAYMENT,
                \App\Enums\ProjectStatus::COMPLETED,
            ],
            true))

    <section id="post-documents" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Submission of Post-Documentary Requirements
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        TC/Admin records the complete post-implementation documentary submission.
                        Financial processing begins only after the required submission is recorded.
                    </p>
                </div>

                @if ($project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS)
                    <span
                        class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700">
                        Auto-update → For Payment
                    </span>
                @endif
            </div>
        </div>

        @if (
            $project->status === \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS &&
                (auth()->user()->isAdmin() || auth()->user()->isTc()))
            <form id="post-documents-form" method="POST" action="{{ route('projects.post-documents.store', $project) }}"
                class="border-b border-slate-200 p-5">
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Received
                        </label>

                        <input type="date" name="date_received" required
                            value="{{ old('date_received', now()->format('Y-m-d')) }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                            Date Forwarded to IMSD
                        </label>

                        <input type="date" name="date_forwarded_to_imsd" required
                            value="{{ old('date_forwarded_to_imsd') }}"
                            class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
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
                        class="h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                        Save Post-Documentary Requirements
                    </button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">

                    <tr>

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

                    @forelse($project->postDocuments as $document)
                        <tr>

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

                            <td colspan="4" class="p-0"><x-empty-state size="sm" icon="document" title="No post-documentary requirements recorded." action-label="Record Post-Documents" action-target="post-documents-form" action-tab="workflow" message="The post-documentary requirements appear here once they are submitted after implementation." /></td>

                        </tr>
                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endif
