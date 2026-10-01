const FIELD_SELECTOR = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea';

/*
|--------------------------------------------------------------------------
| Confirmation dialog
|--------------------------------------------------------------------------
| window.TupadConfirm({ title, message, details, confirmText, cancelText, tone })
| resolves to true/false. Pass cancelText: null for a single-button notice.
*/
function createConfirm() {
    const dialog = document.querySelector('[data-tupad-confirm]');

    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') {
        return (options = {}) => Promise.resolve(
            options.cancelText === null
                ? (window.alert(options.message || ''), true)
                : window.confirm([options.title, options.message].filter(Boolean).join('\n\n')),
        );
    }

    const title = dialog.querySelector('[data-confirm-title]');
    const message = dialog.querySelector('[data-confirm-message]');
    const details = dialog.querySelector('[data-confirm-details]');
    const icon = dialog.querySelector('[data-confirm-icon]');
    const accept = dialog.querySelector('[data-confirm-accept]');
    const cancel = dialog.querySelector('[data-confirm-cancel]');
    let settle = null;

    const close = (result) => {
        if (!settle) return;
        const done = settle;
        settle = null;
        dialog.close();
        done(result);
    };

    accept.addEventListener('click', () => close(true));
    cancel.addEventListener('click', () => close(false));
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close(false);
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) close(false);
    });

    return (options = {}) => {
        if (settle) close(false);

        const danger = options.tone === 'danger';
        const list = (options.details || []).filter(Boolean);

        title.textContent = options.title || 'Please confirm';
        message.textContent = options.message || 'Are you sure you want to continue?';
        details.replaceChildren(...list.map((text) => {
            const item = document.createElement('li');
            item.textContent = text;
            return item;
        }));
        details.classList.toggle('hidden', list.length === 0);

        icon.classList.toggle('bg-rose-50', danger);
        icon.classList.toggle('text-rose-600', danger);
        icon.classList.toggle('bg-blue-50', !danger);
        icon.classList.toggle('text-[#063b86]', !danger);
        icon.querySelector('[data-confirm-icon-danger]')?.classList.toggle('hidden', !danger);
        icon.querySelector('[data-confirm-icon-primary]')?.classList.toggle('hidden', danger);

        accept.textContent = options.confirmText || (options.cancelText === null ? 'OK' : 'Continue');
        accept.classList.toggle('bg-rose-600', danger);
        accept.classList.toggle('hover:bg-rose-700', danger);
        accept.classList.toggle('bg-[#063b86]', !danger);
        accept.classList.toggle('hover:bg-[#052f6b]', !danger);

        cancel.textContent = options.cancelText || 'Cancel';
        cancel.classList.toggle('hidden', options.cancelText === null);

        dialog.showModal();
        // Danger prompts default to the safe choice.
        (danger && options.cancelText !== null ? cancel : accept).focus();

        return new Promise((resolve) => { settle = resolve; });
    };
}

function confirmOptionsFrom(element) {
    const details = element.getAttribute('data-confirm-details');

    return {
        title: element.getAttribute('data-confirm-title') || undefined,
        message: element.getAttribute('data-confirm') || undefined,
        confirmText: element.getAttribute('data-confirm-button') || undefined,
        tone: element.getAttribute('data-confirm-tone') || 'primary',
        details: details ? details.split('|') : [],
    };
}

function initializeConfirmations() {
    window.TupadConfirm = createConfirm();

    // Buttons and links: <button data-confirm="…">, <a data-confirm="…">.
    document.addEventListener('click', async (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-confirm]:not(form)') : null;
        if (!trigger) return;

        event.preventDefault();
        event.stopImmediatePropagation();

        if (!(await window.TupadConfirm(confirmOptionsFrom(trigger)))) return;

        const form = trigger.form ?? trigger.closest('form');
        const isSubmit = (trigger instanceof HTMLButtonElement && trigger.type === 'submit')
            || (trigger instanceof HTMLInputElement && trigger.type === 'submit');

        if (isSubmit && form) {
            form.dataset.confirmed = 'true';
            form.requestSubmit(trigger);
        } else if (trigger instanceof HTMLAnchorElement && trigger.href) {
            window.location.href = trigger.href;
        }
    }, true);

    // Whole forms: <form data-confirm="…">, confirmed before any submit handler runs.
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;

        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const submitter = event.submitter;
        if (await window.TupadConfirm(confirmOptionsFrom(form))) {
            form.dataset.confirmed = 'true';
            form.requestSubmit(submitter ?? undefined);
        }
    }, true);
}

/*
|--------------------------------------------------------------------------
| Forms: required markers, inline validation, saving state, unsaved changes
|--------------------------------------------------------------------------
*/
function labelFor(field) {
    if (field.id) {
        const explicit = document.querySelector(`label[for="${CSS.escape(field.id)}"]`);
        if (explicit) return explicit;
    }

    const wrapping = field.closest('label');
    if (wrapping) return wrapping;

    const container = field.parentElement?.closest('div');
    const label = container?.querySelector('label');

    // Only use a sibling label that is not tied to another control.
    return label && !label.htmlFor && !label.contains(field) && container.querySelectorAll(FIELD_SELECTOR).length === 1
        ? label
        : null;
}

function markRequiredFields(root = document) {
    root.querySelectorAll(`${FIELD_SELECTOR}`).forEach((field) => {
        if (!field.required || field.type === 'checkbox' || field.type === 'radio') return;

        const label = labelFor(field);
        if (!label || label.dataset.requiredMarked || label.textContent.includes('*')) return;

        const mark = document.createElement('span');
        mark.className = 'tupad-required-mark';
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = '*';
        label.append(mark);
        label.dataset.requiredMarked = 'true';
    });
}

function inlineErrorFor(field) {
    let error = field.parentElement?.querySelector(':scope > [data-inline-error]');

    if (!error) {
        error = document.createElement('p');
        error.dataset.inlineError = '';
        error.className = 'mt-1 text-xs font-medium text-rose-600';
        error.setAttribute('role', 'alert');
        field.insertAdjacentElement('afterend', error);
    }

    return error;
}

function showFieldError(field) {
    if (field.validity.valid) return;
    field.classList.add('tupad-field-invalid');
    field.setAttribute('aria-invalid', 'true');
    inlineErrorFor(field).textContent = field.validationMessage;
}

function clearFieldError(field) {
    if (!field.classList.contains('tupad-field-invalid') || !field.validity.valid) return;
    field.classList.remove('tupad-field-invalid');
    field.removeAttribute('aria-invalid');
    field.parentElement?.querySelector(':scope > [data-inline-error]')?.remove();
}

function initializeInlineValidation() {
    document.addEventListener('invalid', (event) => {
        if (event.target instanceof HTMLElement && event.target.matches(FIELD_SELECTOR)) {
            showFieldError(event.target);
        }
    }, true);

    document.addEventListener('focusout', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches(FIELD_SELECTOR) || !field.form || field.form.noValidate) return;
        if (field.value !== '' || field.dataset.touched) {
            field.dataset.visited = 'true';
            showFieldError(field);
        }
    });

    // Validate as the user types once a field has been visited (or flagged),
    // so the message updates live and clears the moment the value is valid.
    ['input', 'change'].forEach((type) => document.addEventListener(type, (event) => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches(FIELD_SELECTOR)) return;
        field.dataset.touched = 'true';

        if (field.validity.valid) {
            clearFieldError(field);
        } else if (field.form && !field.form.noValidate
            && (field.dataset.visited || field.classList.contains('tupad-field-invalid'))) {
            showFieldError(field);
        }
    }));
}

function setSaving(button) {
    button.disabled = true;
    button.classList.add('cursor-wait', 'opacity-70');

    if (!(button instanceof HTMLButtonElement) || button.dataset.originalHtml !== undefined) return;

    button.dataset.originalHtml = button.innerHTML;
    const spinner = document.createElement('span');
    spinner.className = 'tupad-spinner';
    spinner.setAttribute('aria-hidden', 'true');
    button.replaceChildren(spinner, document.createTextNode(button.dataset.processingText || 'Saving…'));
}

function restoreButtons() {
    document.querySelectorAll('form[aria-busy="true"]').forEach((form) => form.removeAttribute('aria-busy'));
    document.querySelectorAll('button[data-original-html], input[type="submit"][disabled]').forEach((button) => {
        button.disabled = false;
        button.classList.remove('cursor-wait', 'opacity-70');
        if (button.dataset.originalHtml !== undefined) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    });
}

function tracksUnsavedChanges(form) {
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return false;
    if (form.hasAttribute('data-no-unsaved-warning')) return false;

    return form.hasAttribute('data-warn-unsaved')
        || form.closest('[data-project-workspace], [data-warn-unsaved-scope]') !== null;
}

function initializeForms() {
    const dirtyForms = new Set();

    document.querySelectorAll('form').forEach((form) => {
        if (tracksUnsavedChanges(form)) {
            const markDirty = (event) => {
                if (event.target instanceof HTMLElement && event.target.matches(FIELD_SELECTOR)) dirtyForms.add(form);
            };
            form.addEventListener('input', markDirty);
            form.addEventListener('change', markDirty);
            form.addEventListener('reset', () => dirtyForms.delete(form));
        }

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || (!form.noValidate && !form.checkValidity())) {
                return;
            }

            dirtyForms.delete(form);
            form.setAttribute('aria-busy', 'true');

            // Disabled buttons are left out of the submitted data, so keep the
            // clicked button's name/value (e.g. intent=save|complete) as a
            // hidden field before disabling it.
            const submitter = event.submitter;
            if (submitter?.name && submitter.dataset.allowRepeatSubmit !== 'true') {
                form.querySelector('input[data-submitter-value]')?.remove();

                const carrier = document.createElement('input');
                carrier.type = 'hidden';
                carrier.name = submitter.name;
                carrier.value = submitter.value;
                carrier.dataset.submitterValue = '';
                form.appendChild(carrier);
            }

            // Show "Saving…" on the clicked button; just disable the others.
            form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
                if (button.dataset.allowRepeatSubmit === 'true') return;

                if (button === submitter || (!submitter && button.type === 'submit')) {
                    setSaving(button);
                } else {
                    button.disabled = true;
                    button.classList.add('opacity-70');
                }
            });
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (dirtyForms.size === 0) return;
        event.preventDefault();
        event.returnValue = '';
    });

    // Coming back through the browser's back/forward cache must not leave
    // buttons stuck on "Saving…".
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) restoreButtons();
    });
}

/*
|--------------------------------------------------------------------------
| Empty-state actions
|--------------------------------------------------------------------------
| <a data-empty-action data-empty-action-target="formId"> jumps to a form on
| the page; data-empty-action-click="selector" clicks a button instead. The
| button stays hidden when its target is not on the page (e.g. no permission).
*/
function focusFirstField(container) {
    const field = Array.from(container.querySelectorAll(FIELD_SELECTOR))
        .find((candidate) => !candidate.disabled && candidate.offsetParent !== null);
    field?.focus({ preventScroll: true });
}

function initializeEmptyStateActions() {
    document.querySelectorAll('[data-empty-action]').forEach((link) => {
        const clickSelector = link.dataset.emptyActionClick;
        const target = clickSelector
            ? document.querySelector(clickSelector)
            : document.getElementById(link.dataset.emptyActionTarget || '');

        if (!target) return;

        link.hidden = false;
        link.addEventListener('click', (event) => {
            if (clickSelector) {
                event.preventDefault();
                target.click();
                return;
            }

            // Links with data-workspace-open-tab are also scrolled by the
            // project workspace tabs; everything else scrolls here.
            if (!link.hasAttribute('data-workspace-open-tab')) {
                event.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            window.setTimeout(() => focusFirstField(target), 450);
        });
    });
}

export function initializeTupadUi() {
    const firstInvalid = document.querySelector('[aria-invalid="true"], .border-red-300, [data-invalid]');
    const summary = document.querySelector('[data-validation-summary]');

    if (summary) {
        requestAnimationFrame(() => summary.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    } else if (firstInvalid instanceof HTMLElement) {
        requestAnimationFrame(() => firstInvalid.focus({ preventScroll: false }));
    }

    initializeConfirmations();
    markRequiredFields();
    initializeInlineValidation();
    initializeForms();
    initializeEmptyStateActions();

    window.TupadUi = { markRequiredFields, restoreButtons };

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (!sidebar || !overlay || window.innerWidth >= 1024) return;
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    });
}
