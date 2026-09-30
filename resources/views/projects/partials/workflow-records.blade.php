@php
    $sectionRegistry = app(\App\Services\Projects\ProjectSectionRegistry::class);
    $sectionInstances = $sectionRegistry->instancesFor($project);
    $sectionUser = auth()->user();
    $canEditDirectly = $sectionUser->isAdmin() || $sectionUser->isFocal();
    $sectionRequests = $project->editRequests->groupBy(fn ($editRequest) => $editRequest->section.':'.$editRequest->record_id);
    $sectionLogs = $project->editLogs->groupBy(fn ($editLog) => $editLog->section.':'.$editLog->record_id);
    $releaseController = \App\Http\Controllers\ProjectReleaseOfAssistanceController::class;
    $sectionInputClass = 'h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-100';
    $editedSectionCount = collect($sectionInstances)
        ->filter(fn (array $instance): bool => $sectionLogs->has($instance['key'].':'.$instance['record']->getKey()))
        ->count();
@endphp

<section id="workflow-records" data-workspace-panel="overview" data-workflow-records
    class="scroll-mt-32 mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'overview' ? 'hidden' : '' }}">

    {{-- Header --}}
    <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>
            </span>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-bold text-slate-900">Project &amp; Workflow Records</h2>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ count($sectionInstances) }} sections</span>
                    @if ($editedSectionCount > 0)
                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{{ $editedSectionCount }} edited</span>
                    @endif
                </div>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500">
                    Everything recorded for this project, one card per workflow step.
                    @if ($canEditDirectly)
                        You can edit any section directly — every change is noted in its section.
                    @else
                        To correct a section, click <span class="font-semibold text-slate-700">Request Edit</span>. Once the Focal approves, the section unlocks for one save.
                    @endif
                </p>
            </div>
        </div>

        <div class="flex shrink-0 gap-2">
            <button type="button" data-records-toggle="open"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 15 5 5 5-5M7 9l5-5 5 5"/></svg>
                Expand all
            </button>
            <button type="button" data-records-toggle="close"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 20 5-5 5 5M7 4l5 5 5-5"/></svg>
                Collapse all
            </button>
        </div>
    </div>

    {{-- Jump-to chips --}}
    @if (count($sectionInstances) > 1)
        <nav class="flex gap-2 overflow-x-auto border-b border-slate-100 bg-slate-50/70 px-5 py-3" aria-label="Jump to a section">
            @foreach ($sectionInstances as $index => $instance)
                <a href="#{{ $instance['anchor'] }}" data-records-jump="{{ $instance['anchor'] }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-[#063b86] hover:text-[#063b86]">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-100 text-[9px] text-slate-500">{{ $index + 1 }}</span>
                    {{ $instance['title'] }}
                </a>
            @endforeach
        </nav>
    @endif

    <div class="space-y-3 bg-slate-50/40 p-4 sm:p-5">
        @foreach ($sectionInstances as $index => $instance)
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
                $sectionAttachments = isset($definition['attachments']) ? $definition['attachments']($record, $project) : [];
                $canRequestEdit = ! $canEditNow && ! $pendingRequest && $sectionUser->isTc() && ! $firstEntryOpen;
            @endphp

            <details id="{{ $instance['anchor'] }}" open data-record-card
                class="group/record scroll-mt-32 overflow-hidden rounded-xl border bg-white shadow-sm transition {{ $bag->any() ? 'border-rose-300 ring-2 ring-rose-100' : 'border-slate-200' }}">

                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-3 px-4 py-3 marker:content-none hover:bg-slate-50/80 [&::-webkit-details-marker]:hidden">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#063b86]/10 text-xs font-bold text-[#063b86]">
                        {{ $index + 1 }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $instance['label'] }}</div>
                        <h3 class="truncate text-sm font-semibold text-slate-900">{{ $instance['title'] }}</h3>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        @if ($sectionAttachments !== [])
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                📎 {{ count($sectionAttachments) }}
                            </span>
                        @endif
                        @if ($logs->isNotEmpty())
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-200">
                                ✎ Edited
                            </span>
                        @endif
                        @if (! $canEditDirectly && $approvedRequest)
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                Edit approved{{ $approvedRequest->decider ? ' by '.$approvedRequest->decider->name : '' }} · one save
                            </span>
                        @elseif (! $canEditDirectly && $pendingRequest)
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 ring-1 ring-blue-200">
                                Edit request pending Focal approval
                            </span>
                        @endif
                    </div>

                    <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open/record:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>

                <div class="border-t border-slate-100 px-4 pb-4">

                    @if ($customView)
                        @include($customView, ['deductionProject' => $project, 'canEditNow' => $canEditNow, 'bag' => $bag, 'usesOld' => $usesOld])
                    @else
                        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($definition['fields'] as $fieldName => $field)
                                <div class="{{ $field['type'] === 'textarea' ? 'sm:col-span-2 xl:col-span-3' : '' }}">
                                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $field['label'] }}</dt>
                                    <dd class="mt-1 whitespace-pre-line break-words text-sm font-medium text-slate-800">{{ $sectionRegistry->display($field, $record->getAttribute($fieldName)) }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        @if ($extra !== [])
                            <dl class="mt-4 grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($extra as $extraLabel => $extraValue)
                                    <div>
                                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $extraLabel }}</dt>
                                        <dd class="mt-0.5 text-xs font-semibold text-slate-700">{{ $extraValue ?: '—' }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    @endif

                    @if ($sectionAttachments !== [])
                        <div class="mt-4">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Attachments</div>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($sectionAttachments as $sectionAttachment)
                                    <li>
                                        <a href="{{ $sectionAttachment['url'] }}"
                                            class="inline-flex max-w-xs items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-[#063b86] shadow-sm transition hover:border-[#063b86] hover:bg-blue-50">
                                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                                            <span class="truncate">{{ $sectionAttachment['name'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($logs->isNotEmpty())
                        <div class="mt-4">
                            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Edit history</div>
                            <ol class="mt-2 space-y-3 border-l-2 border-amber-200 pl-4">
                                @foreach ($logs as $log)
                                    <li class="relative">
                                        <span class="absolute -left-[1.4rem] top-1 h-3 w-3 rounded-full border-2 border-white bg-amber-400" aria-hidden="true"></span>
                                        <div class="text-xs font-semibold text-slate-800">
                                            Edited by {{ $log->editor?->name ?? 'Unknown user' }}
                                            @if ($log->approver)
                                                <span class="font-normal text-slate-500">(approved by {{ $log->approver->name }})</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400">on {{ $log->created_at->format('F d, Y h:i A') }}</div>
                                        <ul class="mt-1.5 space-y-1">
                                            @foreach ($log->changes as $change)
                                                <li class="flex flex-wrap items-center gap-1.5 text-xs">
                                                    <span class="font-semibold text-slate-700">{{ $change['field'] }}:</span>
                                                    <span class="rounded bg-rose-50 px-1.5 py-0.5 text-rose-700 line-through decoration-rose-300">{{ $change['old'] }}</span>
                                                    <span class="text-slate-400" aria-hidden="true">→</span>
                                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 font-medium text-emerald-800">{{ $change['new'] }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    @if ($canEditNow && ! $customView)
                        <details class="group/editor mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50/70" @if ($bag->any()) open @endif>
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-2.5 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#063b86] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#052f6b]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                    <span class="group-open/editor:hidden">Edit {{ $instance['label'] }}</span>
                                    <span class="hidden group-open/editor:inline">Close editor</span>
                                </span>
                                @if (! $canEditDirectly)
                                    <span class="text-[11px] text-emerald-700">Approved for one save</span>
                                @endif
                            </summary>

                            <form method="POST" action="{{ route('projects.sections.update', [$project, $instance['key'], $recordId]) }}"
                                class="border-t border-slate-200 bg-white p-4" data-section-form>
                                @csrf
                                @method('PUT')

                                @if ($bag->any())
                                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700" role="alert">
                                        <div class="mb-1 font-semibold">Please fix the following:</div>
                                        <ul class="list-disc pl-4">
                                            @foreach ($bag->all() as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($definition['fields'] as $fieldName => $field)
                                        @php
                                            $fieldValue = $usesOld ? old($fieldName) : $sectionRegistry->formValue($field, $record, $fieldName);
                                            $fieldId = $bagName.'_'.$fieldName;
                                        @endphp

                                        <div class="{{ $field['type'] === 'textarea' ? 'sm:col-span-2 xl:col-span-3' : '' }}">
                                            @if ($field['type'] === 'boolean')
                                                <input type="hidden" name="{{ $fieldName }}" value="0">
                                                <label class="mt-6 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700">
                                                    <input type="checkbox" name="{{ $fieldName }}" value="1" @checked((bool) $fieldValue)
                                                        class="h-4 w-4 rounded border-slate-300 text-[#063b86]">
                                                    {{ $field['label'] }}
                                                </label>
                                            @else
                                                <label for="{{ $fieldId }}" class="mb-1.5 block text-xs font-semibold text-slate-700">{{ $field['label'] }}</label>

                                                @if ($field['type'] === 'textarea')
                                                    <textarea id="{{ $fieldId }}" name="{{ $fieldName }}" rows="3"
                                                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-100">{{ $fieldValue }}</textarea>
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

                                <div class="mt-5 flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-[11px] text-slate-500">
                                        @if ($canEditDirectly)
                                            Saving records who edited this section and what changed.
                                        @else
                                            Your approval covers one save. Request again if another correction is needed.
                                        @endif
                                    </p>
                                    <button type="submit"
                                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#052f6b]">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                        Save Section Changes
                                    </button>
                                </div>
                            </form>
                        </details>
                    @elseif ($canRequestEdit)
                        <details class="group/request mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50/70">
                            <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-2.5 marker:content-none [&::-webkit-details-marker]:hidden">
                                <span class="inline-flex h-9 items-center gap-2 rounded-lg border border-[#063b86] bg-white px-4 text-xs font-semibold text-[#063b86] shadow-sm transition hover:bg-blue-50">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                    Request Edit
                                </span>
                                <span class="text-[11px] text-slate-500">Needs Focal approval</span>
                            </summary>
                            <form method="POST" action="{{ route('projects.sections.edit-requests.store', [$project, $instance['key'], $recordId]) }}"
                                class="border-t border-slate-200 bg-white p-4">
                                @csrf
                                <label class="mb-1.5 block text-xs font-semibold text-slate-700" for="{{ $bagName }}_reason">
                                    What needs to be corrected? <span class="font-normal text-slate-400">(optional)</span>
                                </label>
                                <textarea id="{{ $bagName }}_reason" name="reason" rows="2" maxlength="1000"
                                    placeholder="e.g. Wrong date of NAFA; should be September 3"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-[#063b86] focus:outline-none focus:ring-2 focus:ring-blue-100"></textarea>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit"
                                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#052f6b]">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
                                        Send Edit Request to Focal
                                    </button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            </details>
        @endforeach
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-workflow-records]');
            if (!root) return;

            const cards = () => root.querySelectorAll('[data-record-card]');

            root.querySelectorAll('[data-records-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const open = button.dataset.recordsToggle === 'open';
                    cards().forEach((card) => { card.open = open; });
                });
            });

            // Jumping to a section (chips, notification links) always opens it.
            const openTarget = (id) => {
                const card = id ? document.getElementById(id) : null;
                if (card && card.matches('[data-record-card]')) card.open = true;
            };

            root.querySelectorAll('[data-records-jump]').forEach((chip) => {
                chip.addEventListener('click', () => openTarget(chip.dataset.recordsJump));
            });

            openTarget(window.location.hash.slice(1));
            window.addEventListener('hashchange', () => openTarget(window.location.hash.slice(1)));

            root.querySelectorAll('[data-section-form]').forEach((form) => {
                const select = form.querySelector('[data-payout-mode]');
                const other = form.querySelector('[data-payout-mode-other]');

                select?.addEventListener('change', () => {
                    other?.classList.toggle('hidden', select.value !== @js($releaseController::OTHER_MODE));
                });
            });
        })();
    </script>
</section>
