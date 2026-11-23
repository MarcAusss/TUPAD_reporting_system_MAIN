/*
|--------------------------------------------------------------------------
| Form wizard (page or modal)
|--------------------------------------------------------------------------
| Splits one existing <form> into steps without changing what it submits:
| every field stays inside the form, only the inactive steps are hidden. With
| JavaScript off, all steps render and the form submits as one page.
|
|   <form data-wizard data-wizard-errors='["field_name", ...]'>
|       <div data-wizard-stepper></div>                 stepper is rendered here
|       <p data-wizard-progress></p>                    "Step 2 of 5"
|       <div data-wizard-step data-wizard-title="Funding"> ... </div>
|       <div data-wizard-step data-wizard-title="Review">
|           <div data-wizard-review></div>              summary of earlier steps
|       </div>
|       <button data-wizard-prev hidden>  <button data-wizard-next hidden>
|       <button type="submit" data-wizard-submit>
|   </form>
|
| Mark values to show on the review step with data-wizard-summary="Label" (on a
| field, or on any element whose text is the value).
|
| Extra checks for a step: listen for "wizard:validate" on the step element and
| call event.preventDefault() to keep the user on it.
|
| Modal: wrap the form in <dialog data-wizard-dialog id="x"> and open it with
| <button data-wizard-open="x">. Elements with [data-wizard-close] close it,
| asking first when something was typed. Add data-wizard-open-on-load to the
| dialog to open it straight away (e.g. after a failed server validation).
*/

const FIELD_SELECTOR = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea';

const CHECK_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>';

/** "ppe.0.qty" -> "ppe[0][qty]" so Laravel error keys can be matched to field names. */
function errorKeyToName(key) {
    const [first, ...rest] = String(key).split('.');
    return first + rest.map((part) => `[${part}]`).join('');
}

function summaryValue(element) {
    if (element instanceof HTMLSelectElement) {
        // "Other" choices: show what was typed in the companion field instead.
        const other = element.dataset.wizardSummaryOther ? document.querySelector(element.dataset.wizardSummaryOther) : null;
        if (other && element.value === '__other__') return other.value.trim();

        return element.value ? element.selectedOptions[0]?.textContent.trim() : '';
    }

    if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement) {
        return element.value.trim();
    }

    return element.textContent.replace(/\s+/g, ' ').trim();
}

function createWizard(form) {
    const steps = Array.from(form.querySelectorAll('[data-wizard-step]'))
        .filter((step) => step.closest('[data-wizard]') === form);

    if (steps.length < 2) return null;

    const stepper = form.querySelector('[data-wizard-stepper]');
    const progress = form.querySelector('[data-wizard-progress]');
    const prevButton = form.querySelector('[data-wizard-prev]');
    const nextButton = form.querySelector('[data-wizard-next]');
    const submitButtons = Array.from(form.querySelectorAll('[data-wizard-submit]'));
    const review = form.querySelector('[data-wizard-review]');
    const scrollTarget = form.closest('[data-wizard-dialog]')?.querySelector('[data-wizard-scroll]') ?? null;

    const live = document.createElement('p');
    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    form.append(live);

    let current = 0;
    let furthest = 0;
    let stepButtons = [];

    const titleOf = (index) => steps[index].dataset.wizardTitle || `Step ${index + 1}`;

    function renderStepper() {
        if (!stepper) return;

        const list = document.createElement('ol');
        list.className = 'tupad-wizard-steps';
        list.setAttribute('aria-label', 'Form steps');

        stepButtons = steps.map((step, index) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tupad-wizard-step';
            button.dataset.wizardGoto = String(index);
            button.innerHTML = '<span class="tupad-wizard-step-marker" aria-hidden="true"></span><span class="tupad-wizard-step-label"></span>';
            button.querySelector('.tupad-wizard-step-label').textContent = titleOf(index);
            button.addEventListener('click', () => goTo(index));
            item.append(button);
            list.append(item);
            return button;
        });

        stepper.replaceChildren(list);
    }

    function updateStepper() {
        stepButtons.forEach((button, index) => {
            // complete = behind the current step; visited = ahead but already seen.
            const state = index === current
                ? 'current'
                : index < current ? 'complete' : index <= furthest ? 'visited' : 'upcoming';
            const marker = button.querySelector('.tupad-wizard-step-marker');

            button.dataset.state = state;
            // Steps can be skipped to only once they were reached (or are next).
            button.disabled = state === 'upcoming' && index !== current + 1;

            if (state === 'complete') {
                marker.innerHTML = CHECK_ICON;
            } else {
                marker.textContent = String(index + 1);
            }

            if (index === current) {
                button.setAttribute('aria-current', 'step');
            } else {
                button.removeAttribute('aria-current');
            }

            const suffix = { current: ' (current)', complete: ' (done)', visited: '', upcoming: '' }[state];
            button.setAttribute('aria-label', `Step ${index + 1} of ${steps.length}: ${titleOf(index)}${suffix}`);
        });

        // Keep the current step visible in a horizontally scrolling stepper
        // without scrolling the page itself.
        const active = stepButtons[current];
        const list = active?.closest('.tupad-wizard-steps');
        if (active && list && list.scrollWidth > list.clientWidth) {
            const item = active.parentElement;
            list.scrollLeft = Math.max(0, item.offsetLeft - (list.clientWidth - item.offsetWidth) / 2);
        }
    }

    function renderReview() {
        if (!review) return;

        const blocks = steps.slice(0, -1).map((step, index) => {
            const rows = Array.from(step.querySelectorAll('[data-wizard-summary]'))
                .map((element) => [element.dataset.wizardSummary, summaryValue(element)]);

            const block = document.createElement('section');
            block.className = 'tupad-wizard-review-block';

            const header = document.createElement('div');
            header.className = 'tupad-wizard-review-header';

            const heading = document.createElement('h3');
            heading.textContent = `${index + 1}. ${titleOf(index)}`;

            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'tupad-wizard-review-edit';
            edit.textContent = 'Edit';
            edit.setAttribute('aria-label', `Edit ${titleOf(index)}`);
            edit.addEventListener('click', () => goTo(index));

            header.append(heading, edit);
            block.append(header);

            if (rows.length) {
                const list = document.createElement('dl');
                rows.forEach(([label, value]) => {
                    const term = document.createElement('dt');
                    term.textContent = label;
                    const detail = document.createElement('dd');
                    detail.textContent = value || '—';
                    if (!value) detail.classList.add('is-empty');
                    list.append(term, detail);
                });
                block.append(list);
            }

            return block;
        });

        review.replaceChildren(...blocks);
    }

    function fieldsIn(step) {
        return Array.from(step.querySelectorAll(FIELD_SELECTOR))
            .filter((field) => field.willValidate && !field.disabled);
    }

    /** Validates one step; shows inline errors (via the "invalid" event) and focuses the first problem. */
    function validateStep(index, { focus = true } = {}) {
        const step = steps[index];
        const invalid = fieldsIn(step).filter((field) => !field.checkValidity());

        const event = new CustomEvent('wizard:validate', { cancelable: true, detail: { step: index } });
        step.dispatchEvent(event);

        if (invalid.length === 0 && !event.defaultPrevented) return true;

        if (focus) {
            invalid[0]?.focus({ preventScroll: false });
            invalid[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        const count = invalid.length;
        live.textContent = count
            ? `${count} field${count === 1 ? ' needs' : 's need'} attention in ${titleOf(index)}.`
            : `Please review ${titleOf(index)} before continuing.`;

        return false;
    }

    function show(index, { focusHeading = true } = {}) {
        current = Math.max(0, Math.min(index, steps.length - 1));
        furthest = Math.max(furthest, current);

        steps.forEach((step, stepIndex) => {
            step.hidden = stepIndex !== current;
        });

        const isLast = current === steps.length - 1;
        if (prevButton) prevButton.hidden = current === 0;
        if (nextButton) {
            nextButton.hidden = isLast;
            const label = nextButton.querySelector('[data-wizard-next-label]');
            if (label) label.textContent = `Next: ${titleOf(Math.min(current + 1, steps.length - 1))}`;
        }
        submitButtons.forEach((button) => { button.hidden = !isLast; });

        if (progress) progress.textContent = `Step ${current + 1} of ${steps.length} · ${titleOf(current)}`;

        if (isLast) renderReview();
        updateStepper();

        live.textContent = `Step ${current + 1} of ${steps.length}: ${titleOf(current)}`;
        form.dispatchEvent(new CustomEvent('wizard:change', { detail: { step: current } }));

        if (!focusHeading) return;

        const heading = steps[current].querySelector('h2, h3') ?? steps[current];
        if (!heading.hasAttribute('tabindex')) heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });

        if (scrollTarget) {
            scrollTarget.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            // Leave room for the sticky top bar.
            const top = form.getBoundingClientRect().top + window.scrollY - 96;
            if (window.scrollY > top) window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }
    }

    /** Going forward checks every step in between; going back is always allowed. */
    function goTo(index) {
        if (index === current) return;

        if (index > current) {
            for (let step = current; step < index; step += 1) {
                if (!validateStep(step)) {
                    if (step !== current) show(step, { focusHeading: false });
                    validateStep(step);
                    return;
                }
            }
        }

        show(index);
    }

    function stepOf(element) {
        return steps.findIndex((step) => step.contains(element));
    }

    /** Opens the step holding an element (e.g. a field another script flagged). */
    function reveal(element) {
        const index = stepOf(element);
        if (index >= 0 && index !== current) show(index, { focusHeading: false });
        return index;
    }

    prevButton?.addEventListener('click', () => goTo(current - 1));
    nextButton?.addEventListener('click', () => goTo(current + 1));

    // A browser can't focus a field inside a hidden step, so open that step
    // before the browser reports the problem on submit.
    let revealedThisAttempt = false;
    form.addEventListener('invalid', (event) => {
        if (revealedThisAttempt) return;
        revealedThisAttempt = true;
        queueMicrotask(() => { revealedThisAttempt = false; });

        const index = stepOf(event.target);
        if (index >= 0 && index !== current) {
            show(index, { focusHeading: false });
            live.textContent = `Please complete ${titleOf(index)} before saving.`;
        }
    }, true);

    // Enter in a text field moves to the next step instead of submitting early.
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.isComposing || current === steps.length - 1) return;
        const target = event.target;
        if (!(target instanceof HTMLInputElement) || ['checkbox', 'radio', 'submit', 'button'].includes(target.type)) return;

        event.preventDefault();
        goTo(current + 1);
    });

    // The final step runs the step checks too (custom validators included).
    form.addEventListener('submit', (event) => {
        for (let index = 0; index < steps.length; index += 1) {
            const step = steps[index];
            const check = new CustomEvent('wizard:validate', { cancelable: true, detail: { step: index, submitting: true } });
            step.dispatchEvent(check);

            if (check.defaultPrevented) {
                event.preventDefault();
                event.stopImmediatePropagation();
                show(index, { focusHeading: false });
                validateStep(index);
                return;
            }
        }
    }, true);

    renderStepper();
    form.classList.add('is-wizard-ready');

    // After a failed server validation, open the first step with an error.
    let startAt = 0;
    try {
        const errorKeys = JSON.parse(form.dataset.wizardErrors || '[]');
        const names = errorKeys.map(errorKeyToName);
        const errorField = Array.from(form.querySelectorAll('[name]'))
            .find((field) => names.some((name) => field.name === name || field.name === `${name}[]` || field.name.startsWith(`${name}[`)));
        const flagged = errorField ?? form.querySelector('[data-wizard-step] .text-red-600, [data-wizard-step] [aria-invalid="true"]');

        if (errorKeys.length && flagged) {
            startAt = Math.max(0, stepOf(flagged));
            furthest = steps.length - 1;
        } else if (errorKeys.length) {
            furthest = steps.length - 1;
        }
    } catch {
        startAt = 0;
    }

    show(startAt, { focusHeading: false });

    return { goTo, reveal, validateStep, get current() { return current; }, steps };
}

function initializeWizardDialog(dialog) {
    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') return;

    const form = dialog.querySelector('form');
    let dirty = false;

    form?.addEventListener('input', () => { dirty = true; });
    form?.addEventListener('change', () => { dirty = true; });
    form?.addEventListener('submit', () => { dirty = false; });

    const requestClose = async () => {
        if (dirty && window.TupadConfirm) {
            const discard = await window.TupadConfirm({
                title: 'Close without saving?',
                message: 'What you entered stays here until you leave the page, but it has not been saved yet.',
                confirmText: 'Close',
                cancelText: 'Keep editing',
            });
            if (!discard) return;
        }
        dialog.close();
    };

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        requestClose();
    });

    dialog.querySelectorAll('[data-wizard-close]').forEach((button) => {
        button.addEventListener('click', requestClose);
    });

    let opener = null;
    const open = (trigger = null) => {
        opener = trigger;
        dialog.showModal();
        document.body.classList.add('overflow-hidden');
        const heading = dialog.querySelector('[data-wizard-step]:not([hidden]) h2, [data-wizard-step]:not([hidden]) h3, h2');
        if (heading && !heading.hasAttribute('tabindex')) heading.setAttribute('tabindex', '-1');
        heading?.focus();
    };

    dialog.addEventListener('close', () => {
        document.body.classList.remove('overflow-hidden');
        opener?.focus();
    });

    document.querySelectorAll(`[data-wizard-open="${CSS.escape(dialog.id)}"]`).forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(trigger);
        });
    });

    if (dialog.hasAttribute('data-wizard-open-on-load')) {
        requestAnimationFrame(() => open(document.querySelector(`[data-wizard-open="${CSS.escape(dialog.id)}"]`)));
    }
}

export function initializeFormWizards() {
    document.querySelectorAll('form[data-wizard]').forEach((form) => {
        const wizard = createWizard(form);
        if (wizard) form.tupadWizard = wizard;
    });

    document.querySelectorAll('dialog[data-wizard-dialog]').forEach(initializeWizardDialog);
}
