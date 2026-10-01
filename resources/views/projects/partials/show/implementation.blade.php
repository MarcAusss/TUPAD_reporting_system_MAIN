@if ((in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::APPROVED,
            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
            \App\Enums\ProjectStatus::FOR_SUBMISSION_OF_POST_DOCS,
        ],
        true) && $project->implementation_mode === \App\Enums\ImplementationMode::DIRECT_ADMINISTRATION)
    || ($implementationIsAcp && ! $acpWorkflowService->isLegacy($project) && in_array(
        $project->status,
        [
            \App\Enums\ProjectStatus::FOR_IMPLEMENTATION,
            \App\Enums\ProjectStatus::ONGOING_IMPLEMENTATION,
        ],
        true)))

    <section id="implementation" data-workspace-panel="workflow"
        class="scroll-mt-32 mt-5 rounded-xl border border-slate-200 bg-white shadow-sm {{ $workspace['default_tab'] !== 'workflow' ? 'hidden' : '' }}">

        <div class="border-b border-slate-200 px-5 py-4">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <h2 class="text-sm font-semibold text-slate-900">
                        Project Implementation
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        @if ($implementationIsAcp)
                            Through ACP workflow (after the check release): GSIS Enrollment, PPE, NAFA, Notice to
                            Proceed, Orientation, and Work Period.
                        @else
                            Direct Administration workflow: Insurance, PPE, Notice to Proceed, Orientation, and Work
                            Period.
                        @endif
                    </p>

                </div>

                @if ($project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION)
                    <span
                        class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                        Ready for Implementation
                    </span>
                @endif

            </div>

        </div>

        @php
            // Through ACP adds the NAFA before the Notice to Proceed and, like
            // Direct Administration, unlocks Orientation and the Work Period
            // only once every preparation requirement is recorded.
            $stepOrder = $implementationIsAcp ? ['insurance', 'ppe', 'nafa', 'ntp'] : ['insurance', 'ppe', 'ntp'];
            $schedulingUnlocked = $project->status === \App\Enums\ProjectStatus::FOR_IMPLEMENTATION
                && (! $implementationIsAcp || $acpPreparationComplete);

            if ($schedulingUnlocked) {
                $stepOrder[] = 'orientation';
                $stepOrder[] = 'implementation-period';
            }

            $stepLabels = [
                'insurance' => $implementationIsAcp ? 'GSIS Enrollment' : 'Insurance',
                'ppe' => 'PPE Delivery',
            ] + ($implementationIsAcp ? ['nafa' => 'NAFA'] : []) + [
                'ntp' => 'Notice to Proceed',
                'orientation' => 'Orientation',
                'implementation-period' => 'Implementation Period',
            ];

            $stepComplete = [
                'insurance' => (bool) $project->insuranceEnrollment,
                'ppe' => $project->ppeDeliveries->isNotEmpty(),
            ] + ($implementationIsAcp ? ['nafa' => (bool) $project->nafa] : []) + [
                'ntp' => (bool) $project->noticeToProceed,
                'orientation' => (bool) $project->orientation,
                'implementation-period' => (bool) $project->implementation,
            ];

            $completedPreparation = collect($stepComplete)->filter()->count();

            $preparationPercent =
                count($stepComplete) > 0 ? ($completedPreparation / count($stepComplete)) * 100 : 0;

            $defaultStep = collect($stepOrder)->first(fn ($step) => ! $stepComplete[$step]) ?? end($stepOrder);
        @endphp

        <div class="border-b border-slate-200 p-5">

            <div class="flex items-center justify-between">

                <span class="text-xs font-semibold text-slate-600">
                    Preparation Completion
                </span>

                <span class="text-xs font-semibold text-slate-800">
                    {{ $completedPreparation }}/{{ count($stepComplete) }}
                </span>

            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">

                <div class="h-full rounded-full bg-slate-800" style="width: {{ $preparationPercent }}%;"></div>

            </div>

            <p class="mt-3 text-[11px] leading-4 text-slate-500">
                Click any item below to jump straight to it &mdash; your entered data is kept whether you move
                back or forward.
            </p>

            <div class="mt-3 flex flex-wrap gap-2" id="preparation-step-pills">

                @foreach ($stepLabels as $stepKey => $label)
                    @php $unlocked = in_array($stepKey, $stepOrder, true); @endphp
                    <button type="button" data-step-pill="{{ $stepKey }}"
                        @if (! $unlocked) disabled title="Complete the earlier requirements first" @endif
                        class="inline-flex items-center gap-1 rounded-full border border-transparent px-2.5 py-1 text-xs font-semibold transition
                        {{ $stepComplete[$stepKey] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}
                        {{ $unlocked ? 'cursor-pointer hover:border-slate-300' : 'cursor-not-allowed opacity-50' }}">
                        <span>{{ $stepComplete[$stepKey] ? '✓' : '•' }}</span>
                        <span>{{ $label }}</span>
                    </button>
                @endforeach

            </div>

        </div>

        @if (in_array(
                $project->status,
                [\App\Enums\ProjectStatus::APPROVED, \App\Enums\ProjectStatus::FOR_IMPLEMENTATION],
                true))

            <div class="p-5" id="preparation-step-container" data-default-step="{{ $defaultStep }}">

                {{-- Insurance Enrollment --}}

                <div data-step-panel="insurance" class="hidden">
                    <form method="POST" action="{{ route('projects.implementation.insurance', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">
                            @csrf
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 1
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        Insurance Enrollment
                                    </h4>
                                </div>

                                @if ($project->insuranceEnrollment)
                                    <span
                                        class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                                <div class="text-xs font-semibold text-blue-900">
                                    Approved project values are locked
                                </div>

                                <p class="mt-1 text-xs leading-5 text-blue-700">
                                    Insurance Beneficiaries and Insurance Amount use the approved
                                    project values and cannot be edited here.
                                </p>
                            </div>

                            <div class="mt-4 grid gap-4">

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date Enrolled
                                    </label>

                                    <input name="date_enrolled" type="date" required
                                        value="{{ old('date_enrolled', $project->insuranceEnrollment?->date_enrolled?->format('Y-m-d')) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                    @error('date_enrolled')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Beneficiaries
                                        </label>

                                        <div
                                            class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                            <span class="font-semibold text-slate-900">
                                                {{ number_format($project->insurance_beneficiaries ?? $project->beneficiaries_total) }}
                                            </span>

                                            <span
                                                class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Locked
                                            </span>
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Uses the approved insurance beneficiary count.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="mb-2 block text-xs font-semibold text-slate-700">
                                            Insurance Amount
                                        </label>

                                        <div
                                            class="flex h-10 items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm">
                                            <span class="font-semibold text-slate-900">
                                                ₱{{ number_format($project->insurance_total, 2) }}
                                            </span>

                                            <span
                                                class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Locked
                                            </span>
                                        </div>

                                        <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                            Uses the approved project insurance amount.
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Mode of Payment
                                    </label>

                                    <select name="payment_mode" required
                                        class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                                        <option value="">
                                            Select mode
                                        </option>

                                        <option value="voucher" @selected(old('payment_mode', $project->insuranceEnrollment?->payment_mode) === 'voucher')>
                                            Voucher
                                        </option>

                                        <option value="ca" @selected(old('payment_mode', $project->insuranceEnrollment?->payment_mode) === 'ca')>
                                            CA
                                        </option>
                                    </select>

                                    @error('payment_mode')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        OR Number
                                    </label>

                                    <input name="or_number"
                                        value="{{ old('or_number', $project->insuranceEnrollment?->or_number) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Policy Number
                                    </label>

                                    <input name="policy_number"
                                        value="{{ old('policy_number', $project->insuranceEnrollment?->policy_number) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Remarks
                                    </label>

                                    <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->insuranceEnrollment?->remarks) }}</textarea>
                                </div>

                            </div>

                            <button type="submit"
                                class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                Save Insurance Enrollment
                            </button>
                        </form>
                </div>

                {{-- PPE Delivery --}}

                <div data-step-panel="ppe" class="hidden">
                    <div class="rounded-xl border border-slate-200 p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 2
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        PPE Delivery
                                    </h4>
                                </div>

                                @if ($project->ppeDeliveries->isNotEmpty())
                                    <span
                                        class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            @if ($project->ppeDeliveries->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach ($project->ppeDeliveries as $delivery)
                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-semibold text-emerald-900">
                                                    {{ $delivery->delivery_receipt_date->format('M d, Y') }}
                                                </span>
                                                <span class="text-[10px] text-emerald-700">
                                                    By {{ $delivery->recorder?->name ?? 'System' }}
                                                </span>
                                            </div>

                                            <ul class="mt-1.5 space-y-0.5 text-[11px] leading-4 text-slate-700">
                                                @forelse($delivery->items as $deliveryItem)
                                                    <li>
                                                        {{ $deliveryItem->ppeItem?->product ?? 'PPE item' }}
                                                        &times; {{ number_format($deliveryItem->quantity) }}
                                                    </li>
                                                @empty
                                                    <li class="text-slate-400">{{ $delivery->ppe_provided }}</li>
                                                @endforelse
                                            </ul>

                                            @if ($delivery->remarks)
                                                <p class="mt-1.5 text-[11px] leading-4 text-slate-500">
                                                    {{ $delivery->remarks }}
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @php
                                $ppeDeliveryItems = $project->ppeItems->map(fn ($item) => [
                                    'id' => $item->id,
                                    'product' => $item->product,
                                    'type_label' => $item->ppe_type->label(),
                                    'planned' => $item->plannedQuantity(),
                                    'remaining' => $item->remainingDeliverableQuantity(),
                                ])->values();
                            @endphp

                            <form method="POST" action="{{ route('projects.implementation.ppe', $project) }}"
                                class="ppe-delivery-form mt-4 border-t border-slate-200 pt-4" data-ppe-delivery-form>
                                @csrf

                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-xs font-semibold text-slate-700">
                                        Add Delivery Receipt(s)
                                    </div>

                                    <button type="button" data-add-delivery-receipt
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                        + Add Another Receipt
                                    </button>
                                </div>

                                <p class="mt-1 hidden text-xs font-medium text-red-600" data-error="deliveries"></p>

                                <div data-delivery-receipts class="mt-3 space-y-4"></div>

                                <button type="submit"
                                    class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                    Save Delivery Receipt(s)
                                </button>
                            </form>
                        </div>
                </div>

                        <script>
                            (() => {
                                const form = document.querySelector('[data-ppe-delivery-form]');
                                if (!form) return;

                                const list = form.querySelector('[data-delivery-receipts]');
                                const addButton = form.querySelector('[data-add-delivery-receipt]');
                                const ppeDeliveryItems = @json($ppeDeliveryItems);
                                const initialDeliveries = Object.values(@json(old('deliveries')) || {});
                                const serverErrors = @json($errors->getMessages());
                                let receiptIndex = 0;

                                const escapeHtml = value => String(value ?? '')
                                    .replaceAll('&', '&amp;')
                                    .replaceAll('<', '&lt;')
                                    .replaceAll('>', '&gt;')
                                    .replaceAll('"', '&quot;')
                                    .replaceAll("'", '&#039;');

                                const itemBoxMarkup = (index, item, selected, quantity) => `
                                    <div class="ppe-item-toggle rounded-lg border border-slate-300 p-2.5 ${item.remaining <= 0 ? 'opacity-50' : ''} ${selected ? 'ppe-item-selected' : ''}" data-item-id="${item.id}">
                                        <button type="button"
                                            class="ppe-item-button flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs font-semibold text-slate-700 ${selected ? 'text-blue-800' : ''}"
                                            data-item-id="${item.id}"
                                            ${item.remaining <= 0 ? 'disabled' : ''}>
                                            <span class="min-w-0 truncate">
                                                ${escapeHtml(item.product)}
                                                <span class="font-normal text-slate-400">(${escapeHtml(item.type_label)})</span>
                                            </span>
                                            <span class="ppe-item-check ${selected ? '' : 'hidden'} text-emerald-600">&check;</span>
                                        </button>

                                        <div class="mt-0.5 px-2 text-[10px] text-slate-400" data-item-remaining-label="${item.id}">
                                            Remaining: ${item.remaining.toLocaleString()} / ${item.planned.toLocaleString()}
                                        </div>

                                        <div class="ppe-item-quantity mt-2 px-2 ${selected ? '' : 'hidden'}">
                                            <input type="hidden" name="deliveries[${index}][items][${item.id}][ppe_item_id]" value="${item.id}" ${selected ? '' : 'disabled'}>
                                            <input type="number" data-item-quantity-input="${item.id}"
                                                name="deliveries[${index}][items][${item.id}][quantity]"
                                                min="0" max="${item.remaining}" placeholder="0 if none in this receipt"
                                                value="${escapeHtml(quantity ?? '')}"
                                                ${selected ? '' : 'disabled'}
                                                class="h-8 w-full rounded-md border border-slate-300 px-2 text-xs">
                                            <p class="mt-1 hidden text-[10px] font-medium text-red-600" data-error="deliveries.${index}.items.${item.id}.quantity"></p>
                                            <p class="mt-1 hidden text-[10px] font-semibold text-red-600" data-item-exceeds-message="${item.id}"></p>
                                        </div>
                                    </div>`;

                                const receiptMarkup = (index, initial = {}) => {
                                    const selectedItems = initial.items || {};

                                    const itemsHtml = ppeDeliveryItems.length === 0
                                        ? `<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-[11px] leading-4 text-slate-500">
                                                No PPE items were declared for this project, so no items need to be selected here. Recording the receipt date is sufficient.
                                            </div>`
                                        : `<p class="mb-2 text-[11px] leading-4 text-slate-500">Click every PPE item included in this receipt, then enter the quantity delivered for each.</p>
                                            <div class="grid gap-2 sm:grid-cols-2">
                                                ${ppeDeliveryItems.map(item => itemBoxMarkup(
                                                    index,
                                                    item,
                                                    Object.prototype.hasOwnProperty.call(selectedItems, item.id),
                                                    selectedItems[item.id]?.quantity,
                                                )).join('')}
                                            </div>`;

                                    return `
                                        <div class="delivery-receipt-card rounded-lg border border-slate-200 bg-slate-50/60 p-4" data-delivery-receipt data-index="${index}">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Receipt ${index + 1}</div>
                                                <button type="button" data-remove-delivery-receipt class="text-[11px] font-semibold text-red-600 hover:underline">Remove</button>
                                            </div>

                                            <div class="mt-3">
                                                <label class="mb-2 block text-xs font-semibold text-slate-700">Date of Delivery Receipt</label>
                                                <input name="deliveries[${index}][delivery_receipt_date]" type="date" required
                                                    value="${escapeHtml(initial.delivery_receipt_date ?? '')}"
                                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                                <p class="mt-1 hidden text-xs font-medium text-red-600" data-error="deliveries.${index}.delivery_receipt_date"></p>
                                            </div>

                                            <div class="mt-4" data-items-container>
                                                <label class="mb-2 block text-xs font-semibold text-slate-700">PPE Provided</label>
                                                ${itemsHtml}
                                                <p class="mt-2 hidden text-xs font-medium text-red-600" data-error="deliveries.${index}.items"></p>
                                            </div>

                                            <div class="mt-4">
                                                <label class="mb-2 block text-xs font-semibold text-slate-700">Remarks</label>
                                                <textarea name="deliveries[${index}][remarks]" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">${escapeHtml(initial.remarks ?? '')}</textarea>
                                            </div>
                                        </div>`;
                                };

                                // Recomputes, for every item box in every card, how much of that
                                // item is still available to THIS box once every OTHER card's
                                // current entry for the same item is subtracted from the item's
                                // baseline remaining — so a second (or third, or Nth) receipt
                                // always reflects what earlier receipts already claimed, live,
                                // with no cap on how many receipts this applies across.
                                const updateRemainingDisplays = () => {
                                    const usedByItemAndCard = {};

                                    form.querySelectorAll('[data-item-quantity-input]').forEach(input => {
                                        if (input.disabled) return;
                                        const id = input.dataset.itemQuantityInput;
                                        const cardIndex = input.closest('[data-delivery-receipt]')?.dataset.index;
                                        if (cardIndex === undefined) return;

                                        usedByItemAndCard[id] = usedByItemAndCard[id] || {};
                                        usedByItemAndCard[id][cardIndex] = (usedByItemAndCard[id][cardIndex] || 0) + (Number(input.value) || 0);
                                    });

                                    form.querySelectorAll('.ppe-item-toggle[data-item-id]').forEach(wrapper => {
                                        const id = wrapper.dataset.itemId;
                                        const item = ppeDeliveryItems.find(candidate => String(candidate.id) === id);
                                        if (!item) return;

                                        const cardIndex = wrapper.closest('[data-delivery-receipt]')?.dataset.index;
                                        const perCard = usedByItemAndCard[id] || {};
                                        const ownValue = cardIndex !== undefined ? (perCard[cardIndex] || 0) : 0;

                                        const usedElsewhere = Object.entries(perCard)
                                            .filter(([otherIndex]) => otherIndex !== cardIndex)
                                            .reduce((sum, [, qty]) => sum + qty, 0);

                                        const liveRemaining = Math.max(0, item.remaining - usedElsewhere);
                                        const exceeds = ownValue > liveRemaining;
                                        const isSelected = wrapper.classList.contains('ppe-item-selected');

                                        const label = wrapper.querySelector('[data-item-remaining-label]');
                                        if (label) {
                                            label.textContent = `Remaining: ${liveRemaining.toLocaleString()} / ${item.planned.toLocaleString()}`;
                                        }

                                        const quantityInput = wrapper.querySelector('[data-item-quantity-input]');
                                        if (quantityInput) {
                                            quantityInput.max = String(liveRemaining);
                                        }

                                        wrapper.classList.remove('border-slate-300', 'border-blue-400', 'bg-blue-50', 'border-red-400', 'bg-red-50');
                                        if (exceeds) {
                                            wrapper.classList.add('border-red-400', 'bg-red-50');
                                        } else if (isSelected) {
                                            wrapper.classList.add('border-blue-400', 'bg-blue-50');
                                        } else {
                                            wrapper.classList.add('border-slate-300');
                                        }

                                        const exceedsMessage = wrapper.querySelector('[data-item-exceeds-message]');
                                        if (exceedsMessage) {
                                            if (exceeds) {
                                                exceedsMessage.textContent = `Exceeds remaining stock by ${(ownValue - liveRemaining).toLocaleString()} unit(s).`;
                                                exceedsMessage.classList.remove('hidden');
                                            } else {
                                                exceedsMessage.textContent = '';
                                                exceedsMessage.classList.add('hidden');
                                            }
                                        }

                                        const button = wrapper.querySelector('.ppe-item-button');
                                        if (button && !isSelected) {
                                            const exhausted = liveRemaining <= 0;
                                            button.disabled = exhausted;
                                            wrapper.classList.toggle('opacity-50', exhausted);
                                        }
                                    });
                                };

                                const applyServerErrors = () => {
                                    Object.entries(serverErrors).forEach(([key, messages]) => {
                                        const el = form.querySelector(`[data-error="${CSS.escape(key)}"]`);
                                        if (!el) return;
                                        el.textContent = messages[0];
                                        el.classList.remove('hidden');
                                    });
                                };

                                const serializeCard = cardEl => {
                                    const items = {};

                                    cardEl.querySelectorAll('[data-item-quantity-input]').forEach(input => {
                                        if (input.disabled) return;
                                        items[input.dataset.itemQuantityInput] = { quantity: input.value };
                                    });

                                    return {
                                        delivery_receipt_date: cardEl.querySelector('input[type="date"]').value,
                                        remarks: cardEl.querySelector('textarea').value,
                                        items,
                                    };
                                };

                                const reindexAll = () => {
                                    const states = Array.from(list.querySelectorAll('[data-delivery-receipt]')).map(serializeCard);

                                    list.innerHTML = '';
                                    receiptIndex = 0;

                                    states.forEach(state => {
                                        list.insertAdjacentHTML('beforeend', receiptMarkup(receiptIndex++, state));
                                    });

                                    updateRemainingDisplays();
                                };

                                list.addEventListener('click', event => {
                                    const button = event.target.closest('.ppe-item-button');
                                    if (button) {
                                        if (button.disabled) return;

                                        const wrapper = button.closest('.ppe-item-toggle');
                                        const check = wrapper.querySelector('.ppe-item-check');
                                        const quantityBlock = wrapper.querySelector('.ppe-item-quantity');
                                        const inputs = quantityBlock.querySelectorAll('input');
                                        const selected = !wrapper.classList.contains('ppe-item-selected');

                                        wrapper.classList.toggle('ppe-item-selected', selected);
                                        button.classList.toggle('text-blue-800', selected);
                                        check.classList.toggle('hidden', !selected);
                                        quantityBlock.classList.toggle('hidden', !selected);

                                        inputs.forEach(input => {
                                            input.disabled = !selected;
                                        });

                                        if (selected) {
                                            quantityBlock.querySelector('input[type="number"]')?.focus();
                                        }

                                        updateRemainingDisplays();
                                        return;
                                    }

                                    const removeButton = event.target.closest('[data-remove-delivery-receipt]');
                                    if (removeButton) {
                                        if (list.querySelectorAll('[data-delivery-receipt]').length <= 1) return;
                                        removeButton.closest('[data-delivery-receipt]').remove();
                                        reindexAll();
                                    }
                                });

                                list.addEventListener('input', event => {
                                    if (event.target.matches('[data-item-quantity-input]')) {
                                        updateRemainingDisplays();
                                    }
                                });

                                addButton.addEventListener('click', () => {
                                    list.insertAdjacentHTML('beforeend', receiptMarkup(receiptIndex++));
                                    updateRemainingDisplays();
                                });

                                if (initialDeliveries.length > 0) {
                                    initialDeliveries.forEach(delivery => {
                                        list.insertAdjacentHTML('beforeend', receiptMarkup(receiptIndex++, delivery));
                                    });
                                } else {
                                    list.insertAdjacentHTML('beforeend', receiptMarkup(receiptIndex++));
                                }

                                applyServerErrors();
                                updateRemainingDisplays();
                            })();
                        </script>

                {{-- NAFA (Notice of Availability of Fund) — Through ACP only --}}

                @if ($implementationIsAcp)
                    <div data-step-panel="nafa" class="hidden">
                        <form method="POST" action="{{ route('projects.implementation.nafa', $project) }}"
                            enctype="multipart/form-data" class="rounded-xl border border-slate-200 p-5">
                            @csrf
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement 3
                                    </div>
                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        NAFA (Notice of Availability of Fund)
                                    </h4>
                                </div>

                                @if ($project->nafa)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="nafa-date" class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date of NAFA <span class="text-red-500">*</span>
                                    </label>
                                    <input id="nafa-date" name="nafa_date" type="date" required
                                        value="{{ old('nafa_date', $project->nafa?->nafa_date?->toDateString()) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('nafa_date')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="nafa-release-date" class="mb-2 block text-xs font-semibold text-slate-700">
                                        Release Date <span class="text-red-500">*</span>
                                    </label>
                                    <input id="nafa-release-date" name="release_date" type="date" required
                                        value="{{ old('release_date', $project->nafa?->release_date?->toDateString()) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">
                                    @error('release_date')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label for="nafa-attachments" class="mb-2 block text-xs font-semibold text-slate-700">
                                    NAFA File
                                    @if ($project->nafa?->attachments->isNotEmpty())
                                        <span class="font-normal text-slate-400">(optional — adds to the files below)</span>
                                    @else
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                <input id="nafa-attachments" name="attachments[]" type="file" multiple
                                    @if (! $project->nafa?->attachments->isNotEmpty()) required @endif
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
                                    class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-4 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                                <p class="mt-1 text-[11px] text-slate-500">PDF, JPG, PNG, Word, or Excel; up to 10 files, 10 MB each.</p>
                                @error('attachments')
                                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                                @foreach ($errors->get('attachments.*') as $nafaFileMessages)
                                    @foreach ($nafaFileMessages as $nafaFileMessage)
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $nafaFileMessage }}</p>
                                    @endforeach
                                @endforeach

                                @if ($project->nafa?->attachments->isNotEmpty())
                                    <ul class="mt-2 space-y-1">
                                        @foreach ($project->nafa->attachments as $nafaAttachment)
                                            <li>
                                                <a href="{{ route('projects.nafa.attachments.download', [$project, $nafaAttachment]) }}"
                                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#063b86] hover:underline">
                                                    <span aria-hidden="true">📎</span> {{ $nafaAttachment->original_name }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">Remarks</label>
                                <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->nafa?->remarks) }}</textarea>
                            </div>

                            <button type="submit"
                                class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                Save NAFA
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Notice to Proceed --}}

                <div data-step-panel="ntp" class="hidden">
                    <form method="POST" action="{{ route('projects.implementation.ntp', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">
                            @csrf
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">
                                        Requirement {{ $implementationIsAcp ? 4 : 3 }}
                                    </div>

                                    <h4 class="mt-1 text-sm font-semibold text-slate-900">
                                        Notice to Proceed
                                    </h4>
                                </div>

                                @if ($project->noticeToProceed)
                                    <span
                                        class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                                        Saved
                                    </span>
                                @endif
                            </div>

                            <div class="mt-4 grid gap-4">
                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date &amp; Time Issued
                                    </label>

                                    <input name="date_issued" type="datetime-local" required
                                        value="{{ old('date_issued', $project->noticeToProceed?->date_issued?->format('Y-m-d\TH:i')) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                    @error('date_issued')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-xs font-semibold text-slate-700">
                                        Date &amp; Time Released
                                    </label>

                                    <input name="date_released" type="datetime-local" required
                                        value="{{ old('date_released', $project->noticeToProceed?->date_released?->format('Y-m-d\TH:i')) }}"
                                        class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                    @error('date_released')
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Remarks
                                </label>

                                <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->noticeToProceed?->remarks) }}</textarea>
                            </div>

                            <button type="submit"
                                class="mt-4 h-10 rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b]">
                                Save Notice to Proceed
                            </button>
                        </form>
                </div>

                @if ($schedulingUnlocked)

                    {{-- Orientation --}}

                    <div data-step-panel="orientation" class="hidden">
                    <form method="POST" action="{{ route('projects.implementation.orientation', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            Orientation
                        </h3>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Date of Orientation
                                </label>

                                <input name="orientation_date" type="date" required
                                    value="{{ old('orientation_date', $project->orientation?->orientation_date?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                @error('orientation_date')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Total Beneficiaries Oriented
                                </label>

                                <input name="beneficiaries_oriented" type="number" min="0"
                                    max="{{ $project->beneficiaries_total }}" required
                                    value="{{ old('beneficiaries_oriented', $project->orientation?->beneficiaries_oriented) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Maximum: {{ number_format($project->beneficiaries_total) }} declared
                                    beneficiaries.
                                </p>

                                @error('beneficiaries_oriented')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Venue
                                </label>

                                <input name="venue" type="text" maxlength="255" required
                                    placeholder="e.g. Barangay Covered Court"
                                    value="{{ old('venue', $project->orientation?->venue) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                @error('venue')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div>
                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Oriented By
                                </label>

                                <input name="oriented_by" type="text" maxlength="255" required
                                    placeholder="Name of facilitator"
                                    value="{{ old('oriented_by', $project->orientation?->oriented_by) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                @error('oriented_by')
                                    <p class="mt-1 text-xs font-medium text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                        </div>

                        <div class="mt-4">
                            <div class="mb-2 text-xs font-semibold text-slate-700">
                                Program Coverage for Monthly Reporting
                            </div>
                            <p class="mb-3 text-[11px] leading-4 text-slate-500">
                                Mark the beneficiary programs actually covered during this orientation. Legacy
                                records may remain unspecified.
                            </p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                    <input type="checkbox" name="alkansssya_conducted" value="1"
                                        @checked(old('alkansssya_conducted', $project->orientation?->alkansssya_conducted))
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#063b86]">
                                    <span>
                                        <span class="block text-xs font-semibold text-slate-800">AlkanSSSya</span>
                                        <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Included in
                                            the recorded TUPAD beneficiary orientation.</span>
                                    </span>
                                </label>
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
                                    <input type="checkbox" name="yakap_conducted" value="1"
                                        @checked(old('yakap_conducted', $project->orientation?->yakap_conducted))
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-[#063b86]">
                                    <span>
                                        <span class="block text-xs font-semibold text-slate-800">YAKAP Program for
                                            TUPAD Beneficiaries</span>
                                        <span class="mt-0.5 block text-[11px] leading-4 text-slate-500">Included in
                                            the recorded TUPAD beneficiary orientation.</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4">

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Remarks
                            </label>

                            <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->orientation?->remarks) }}</textarea>

                        </div>

                        <button type="submit"
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Orientation
                        </button>

                    </form>
                    </div>

                    {{-- Implementation Period --}}

                    <div data-step-panel="implementation-period" class="hidden">
                    <form method="POST" action="{{ route('projects.implementation.period', $project) }}"
                        class="rounded-xl border border-slate-200 p-5">

                        @csrf

                        <h3 class="text-sm font-semibold text-slate-900">
                            Implementation Period
                        </h3>

                        <p class="mt-1 text-xs text-slate-500">
                            Enter the planned implementation Start Date and End Date.
                            The approved {{ $project->number_of_days }}-day duration is shown as reference only.
                        </p>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    Start Date
                                </label>

                                <input id="implementation-start-date" name="start_date" type="date" required
                                    value="{{ old('start_date', $project->implementation?->start_date?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                            </div>

                            <div>

                                <label class="mb-2 block text-xs font-semibold text-slate-700">
                                    End Date
                                </label>

                                <input id="implementation-end-date" name="end_date" type="date" required
                                    value="{{ old('end_date', $project->implementation?->end_date?->format('Y-m-d')) }}"
                                    class="h-10 w-full rounded-lg border border-slate-300 px-3 text-sm">

                                <p class="mt-1 text-[11px] leading-4 text-slate-500">
                                    Enter the actual planned End Date. It cannot be earlier than the Start Date.
                                </p>

                            </div>

                        </div>

                        <div class="mt-4">

                            <label class="mb-2 block text-xs font-semibold text-slate-700">
                                Remarks
                            </label>

                            <textarea name="remarks" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks', $project->implementation?->remarks) }}</textarea>

                        </div>

                        <button type="submit"
                            class="mt-4 h-10 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                            Save Implementation Period
                        </button>

                    </form>
                    </div>
                @endif

                @if ($project->status === \App\Enums\ProjectStatus::APPROVED)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
                        <div class="text-xs font-semibold text-amber-900">
                            Orientation and Work Period are not open yet
                        </div>
                        <p class="mt-1 text-xs leading-5 text-amber-800">
                            Complete Insurance, PPE, and Notice to Proceed first. When all three are complete,
                            the project automatically moves to For Implementation and scheduling becomes available.
                        </p>
                    </div>
                @endif

            </div>

            <script>
                (() => {
                    const container = document.getElementById('preparation-step-container');
                    if (!container) return;

                    const panels = container.querySelectorAll('[data-step-panel]');
                    const pills = document.querySelectorAll('#preparation-step-pills [data-step-pill]');

                    const isValidStep = step => Array.from(panels)
                        .some(panel => panel.dataset.stepPanel === step);

                    const applyStep = step => {
                        if (!isValidStep(step)) return;

                        panels.forEach(panel => {
                            panel.classList.toggle('hidden', panel.dataset.stepPanel !== step);
                        });

                        pills.forEach(pill => {
                            const active = pill.dataset.stepPill === step;
                            pill.classList.toggle('ring-2', active);
                            pill.classList.toggle('ring-slate-400', active);
                            pill.classList.toggle('ring-offset-1', active);
                        });

                        container.dataset.activeStep = step;
                    };

                    const showStep = step => {
                        applyStep(step);

                        window.requestAnimationFrame(() => {
                            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    };

                    pills.forEach(pill => {
                        if (pill.disabled) return;

                        pill.addEventListener('click', () => showStep(pill.dataset.stepPill));
                    });

                    document.addEventListener('workspace:set-step', event => {
                        showStep(event.detail?.step);
                    });

                    const requestedStep = new URLSearchParams(window.location.search).get('step');

                    applyStep(requestedStep && isValidStep(requestedStep) ? requestedStep : container.dataset.defaultStep);
                })();
            </script>

        @endif

    </section>
@endif
