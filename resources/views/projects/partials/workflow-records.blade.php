@php
    $sectionRegistry = app(\App\Services\Projects\ProjectSectionRegistry::class);
    $sectionInstances = $sectionRegistry->instancesFor($project);
    $sectionUser = auth()->user();
    $canEditDirectly = $sectionUser->isAdmin() || $sectionUser->isFocal();
    $sectionRequests = $project->editRequests->groupBy(fn ($editRequest) => $editRequest->section.':'.$editRequest->record_id);
    $sectionLogs = $project->editLogs->groupBy(fn ($editLog) => $editLog->section.':'.$editLog->record_id);
    $releaseController = \App\Http\Controllers\ProjectReleaseOfAssistanceController::class;
    $sectionInputClass = 'h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm';
@endphp

<section id="workflow-records" data-workspace-panel="overview"
    class="scroll-mt-32 mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Project &amp; Workflow Records</h2>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Everything recorded for this project, one section per workflow step, in workflow order.
            @if ($canEditDirectly)
                Focal and Administrator accounts can edit a section directly.
            @else
                To correct a section, request an edit; the Focal approves it from their notifications and the section
                unlocks for one save.
            @endif
            Every edit is noted in its section.
        </p>
    </div>

    <div class="divide-y divide-slate-100">
        @foreach ($sectionInstances as $instance)
            @php
                $definition = $instance['definition'];
                $record = $instance['record'];
                $recordId = (int) $record->getKey();
                $targetKey = $instance['key'].':'.$recordId;
                $bagName = \App\Http\Controllers\ProjectSectionEditController::errorBag($instance['key'], $recordId);
                $bag = $errors->getBag($bagName);
                $usesOld = old('section_target') === $bagName;
                $requests = $sectionRequests->get($targetKey, collect());
                $approvedRequest = $requests->firstWhere('status', \App\Models\ProjectEditRequest::APPROVED);
                $pendingRequest = $requests->firstWhere('status', \App\Models\ProjectEditRequest::PENDING);
                $firstEntryOpen = isset($definition['first_entry_open']) && $definition['first_entry_open']($project);
                $canEditNow = $canEditDirectly || $approvedRequest !== null || ($firstEntryOpen && $sectionUser->isTc());
                $logs = $sectionLogs->get($targetKey, collect());
                $extra = isset($definition['extra']) ? $definition['extra']($record) : [];
                $customView = $definition['view'] ?? null;
            @endphp

            <article id="{{ $instance['anchor'] }}" class="scroll-mt-32 px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-blue-700">{{ $instance['label'] }}</div>
                        <h3 class="mt-0.5 text-sm font-semibold text-slate-900">{{ $instance['title'] }}</h3>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($logs->isNotEmpty())
                            <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800">
                                Edited
                            </span>
                        @endif

                        @if (! $canEditDirectly && $approvedRequest)
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700">
                                Edit approved{{ $approvedRequest->decider ? ' by '.$approvedRequest->decider->name : '' }} · one save
                            </span>
                        @elseif (! $canEditDirectly && $pendingRequest)
                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-bold text-blue-700">
                                Edit request pending Focal approval
                            </span>
                        @endif
                    </div>
                </div>

                @if ($customView)
                    @include($customView, ['deductionProject' => $project, 'canEditNow' => $canEditNow, 'bag' => $bag, 'usesOld' => $usesOld])
                @else
                <dl class="mt-3 grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($definition['fields'] as $fieldName => $field)
                        <div class="bg-white px-3 py-2 {{ $field['type'] === 'textarea' ? 'sm:col-span-2 xl:col-span-3' : '' }}">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $field['label'] }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-xs font-semibold text-slate-800">{{ $sectionRegistry->display($field, $record->getAttribute($fieldName)) }}</dd>
                        </div>
                    @endforeach
                    @foreach ($extra as $extraLabel => $extraValue)
                        <div class="bg-slate-50 px-3 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ $extraLabel }}</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-slate-700">{{ $extraValue ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @endif

                @if ($logs->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        @foreach ($logs as $log)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                <div class="font-semibold">
                                    Edited by {{ $log->editor?->name ?? 'Unknown user' }}
                                    @if ($log->approver)
                                        (approved by {{ $log->approver->name }})
                                    @endif
                                    on {{ $log->created_at->format('F d, Y h:i A') }}
                                </div>
                                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                                    @foreach ($log->changes as $change)
                                        <li>
                                            <span class="font-semibold">{{ $change['field'] }}:</span>
                                            <span class="line-through opacity-70">{{ $change['old'] }}</span>
                                            → {{ $change['new'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($canEditNow && ! $customView)
                    <details class="mt-3 rounded-lg border border-slate-200 bg-slate-50" @if ($bag->any()) open @endif>
                        <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-[#063b86]">
                            Edit {{ $instance['label'] }}
                        </summary>

                        <form method="POST" action="{{ route('projects.sections.update', [$project, $instance['key'], $recordId]) }}"
                            class="border-t border-slate-200 p-4" data-section-form>
                            @csrf
                            @method('PUT')

                            @if ($bag->any())
                                <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                                    <ul class="list-disc pl-4">
                                        @foreach ($bag->all() as $message)
                                            <li>{{ $message }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($definition['fields'] as $fieldName => $field)
                                    @php
                                        $fieldValue = $usesOld ? old($fieldName) : $sectionRegistry->formValue($field, $record, $fieldName);
                                        $fieldId = $bagName.'_'.$fieldName;
                                    @endphp

                                    <div class="{{ $field['type'] === 'textarea' ? 'sm:col-span-2 xl:col-span-3' : '' }}">
                                        @if ($field['type'] === 'boolean')
                                            <input type="hidden" name="{{ $fieldName }}" value="0">
                                            <label class="mt-6 inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                                <input type="checkbox" name="{{ $fieldName }}" value="1" @checked((bool) $fieldValue)
                                                    class="h-4 w-4 rounded border-slate-300">
                                                {{ $field['label'] }}
                                            </label>
                                        @else
                                            <label for="{{ $fieldId }}" class="mb-1 block text-xs font-semibold text-slate-700">{{ $field['label'] }}</label>

                                            @if ($field['type'] === 'textarea')
                                                <textarea id="{{ $fieldId }}" name="{{ $fieldName }}" rows="3"
                                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">{{ $fieldValue }}</textarea>
                                            @elseif ($field['type'] === 'select')
                                                <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="{{ $sectionInputClass }}">
                                                    @foreach ($field['options'] as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif ($field['type'] === 'payout_mode')
                                                @php
                                                    [$storedMode, $storedOther] = $usesOld
                                                        ? [old($fieldName), old($fieldName.'_other')]
                                                        : $releaseController::splitPayoutMode($record->getAttribute($fieldName));
                                                @endphp
                                                <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="{{ $sectionInputClass }}" data-payout-mode>
                                                    @foreach ($releaseController::PAYOUT_MODES as $payoutMode)
                                                        <option value="{{ $payoutMode }}" @selected($storedMode === $payoutMode)>
                                                            {{ $payoutMode === $releaseController::OTHER_MODE ? 'Others, specify' : $payoutMode }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div data-payout-mode-other class="mt-2 {{ $storedMode === $releaseController::OTHER_MODE ? '' : 'hidden' }}">
                                                    <input name="{{ $fieldName }}_other" maxlength="92" value="{{ $storedOther }}"
                                                        placeholder="Specify mode of payment" class="{{ $sectionInputClass }}">
                                                </div>
                                            @else
                                                <input id="{{ $fieldId }}" name="{{ $fieldName }}" value="{{ $fieldValue }}"
                                                    type="{{ match ($field['type']) { 'date' => 'date', 'number' => 'number', default => 'text' } }}"
                                                    @if ($field['type'] === 'money') data-money-input inputmode="decimal" @endif
                                                    class="{{ $sectionInputClass }}">
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-[11px] text-slate-500">
                                    @if ($canEditDirectly)
                                        Saving records who edited this section and what changed.
                                    @else
                                        Your approval covers one save. Request again if another correction is needed.
                                    @endif
                                </p>
                                <button type="submit"
                                    class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                    Save Section Changes
                                </button>
                            </div>
                        </form>
                    </details>
                @elseif (! $canEditNow && ! $pendingRequest && $sectionUser->isTc() && ! $firstEntryOpen)
                    <details class="mt-3 rounded-lg border border-slate-200 bg-slate-50">
                        <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-[#063b86]">
                            Request Edit
                        </summary>
                        <form method="POST" action="{{ route('projects.sections.edit-requests.store', [$project, $instance['key'], $recordId]) }}"
                            class="border-t border-slate-200 p-4">
                            @csrf
                            <label class="mb-1 block text-xs font-semibold text-slate-700" for="{{ $bagName }}_reason">
                                What needs to be corrected? <span class="font-normal text-slate-400">(optional)</span>
                            </label>
                            <textarea id="{{ $bagName }}_reason" name="reason" rows="2" maxlength="1000"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
                            <div class="mt-3 flex justify-end">
                                <button type="submit"
                                    class="inline-flex h-9 items-center rounded-lg border border-[#063b86] bg-white px-4 text-xs font-semibold text-[#063b86] hover:bg-blue-50">
                                    Send Edit Request to Focal
                                </button>
                            </div>
                        </form>
                    </details>
                @endif
            </article>
        @endforeach
    </div>

    <script>
        document.querySelectorAll('[data-section-form]').forEach((form) => {
            const select = form.querySelector('[data-payout-mode]');
            const other = form.querySelector('[data-payout-mode-other]');

            select?.addEventListener('change', () => {
                other?.classList.toggle('hidden', select.value !== @js($releaseController::OTHER_MODE));
            });
        });
    </script>
</section>
