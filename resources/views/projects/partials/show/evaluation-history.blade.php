{{-- Evaluation History --}}

@if ($project->evaluations->isNotEmpty())

    <section id="evaluation-history" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">

            <h2 class="text-sm font-semibold text-slate-900">
                Evaluation History
            </h2>

        </div>

        <div class="overflow-x-auto">

            <table class="tupad-system-table min-w-full">

                <thead class="bg-slate-50">

                    <tr>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Evaluator
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Result
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Findings
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Required Documents
                        </th>

                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                            Compliance Remarks
                        </th>

                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @foreach ($project->evaluations->sortByDesc('evaluated_at') as $evaluation)
                        <tr>

                            <td class="px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->evaluated_at->format('M d, Y g:i A') }}
                            </td>

                            <td class="px-5 py-4 text-sm text-slate-700">
                                {{ $evaluation->evaluator?->name ?? 'System' }}
                            </td>

                            <td class="px-5 py-4">

                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                {{ $evaluation->result === 'for_approval' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $evaluation->result === 'for_approval' ? 'For Approval' : 'For Compliance' }}
                                </span>

                                @if ($evaluation->result === 'for_compliance')
                                    <div class="mt-2 text-[10px] leading-4 text-slate-500">
                                        @if ($evaluation->isComplied())
                                            Compliance:
                                            <span class="font-semibold text-slate-700">
                                                {{ $evaluation->compliance_date->format('M d, Y') }}
                                            </span>
                                            · Aging:
                                            <span class="font-semibold text-slate-700">
                                                {{ number_format($evaluation->agingDays()) }} day(s)
                                            </span>
                                        @else
                                            Compliance pending
                                            · Aging:
                                            <span class="font-semibold text-amber-700">
                                                {{ number_format($evaluation->agingDays()) }} day(s)
                                            </span>
                                        @endif
                                    </div>
                                @endif

                            </td>

                            <td class="max-w-xs whitespace-pre-line px-5 py-4 text-sm text-slate-600">
                                {{ $evaluation->findings ?: '—' }}
                            </td>

                            <td class="max-w-xs px-5 py-4 text-sm text-slate-600">
                                <div class="whitespace-pre-line">{{ $evaluation->required_documents ?: '—' }}</div>

                                @include('projects.partials.evaluation-attachment-links', [
                                    'attachments' => $evaluation->evaluationAttachments(),
                                    'projectId' => $project->id,
                                ])
                            </td>

                            <td class="max-w-xs px-5 py-4 text-sm text-slate-600">
                                <div class="whitespace-pre-line">{{ $evaluation->compliance_remarks ?: '—' }}</div>

                                @include('projects.partials.evaluation-attachment-links', [
                                    'attachments' => $evaluation->complianceAttachments(),
                                    'projectId' => $project->id,
                                ])
                            </td>

                        </tr>
                    @endforeach

                </tbody>

            </table>

        </div>

    </section>

@endif
