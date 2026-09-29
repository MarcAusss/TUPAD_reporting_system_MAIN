export function initializeTupadUi() {
    const firstInvalid = document.querySelector('[aria-invalid="true"], .border-red-300, [data-invalid]');
    const summary = document.querySelector('[data-validation-summary]');

    if (summary) {
        requestAnimationFrame(() => summary.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    } else if (firstInvalid instanceof HTMLElement) {
        requestAnimationFrame(() => firstInvalid.focus({ preventScroll: false }));
    }

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || !form.checkValidity()) {
                return;
            }

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

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                if (button.dataset.allowRepeatSubmit === 'true') return;
                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-70');
                if (button instanceof HTMLButtonElement && !button.dataset.originalText) {
                    button.dataset.originalText = button.textContent?.trim() ?? '';
                    if (button.dataset.processingText) button.textContent = button.dataset.processingText;
                }
            });
        });
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.getAttribute('data-confirm') || 'Are you sure you want to continue?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });

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
