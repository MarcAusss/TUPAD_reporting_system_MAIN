function initializeWorkspaceTabs(root) {
    const buttons = Array.from(root.querySelectorAll('[data-workspace-tab-target]'));
    const panels = Array.from(root.querySelectorAll('[data-workspace-panel]'));
    const actionLinks = Array.from(root.querySelectorAll('[data-workspace-open-tab]'));

    if (buttons.length === 0 || panels.length === 0) {
        return;
    }

    const knownTabs = new Set(buttons.map((button) => button.dataset.workspaceTabTarget));

    const tabForAnchor = (anchor) => {
        if (!anchor) {
            return null;
        }

        const target = document.getElementById(anchor);

        return target?.closest('[data-workspace-panel]')?.dataset.workspacePanel ?? null;
    };

    const activateTab = (tab, options = {}) => {
        if (!knownTabs.has(tab)) {
            return;
        }

        buttons.forEach((button) => {
            const isActive = button.dataset.workspaceTabTarget === tab;

            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.classList.toggle('bg-[#063b86]', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('text-slate-600', !isActive);
            button.classList.toggle('hover:bg-slate-100', !isActive);
            button.classList.toggle('hover:text-slate-950', !isActive);
        });

        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.workspacePanel !== tab);
        });

        if (options.updateUrl !== false) {
            const url = new URL(window.location.href);
            url.searchParams.set('workspace', tab);
            window.history.replaceState({}, '', url);
        }
    };

    buttons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            activateTab(button.dataset.workspaceTabTarget);
        });
    });

    actionLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            const tab = link.dataset.workspaceOpenTab;
            const anchor = link.dataset.workspaceAnchor;

            if (!knownTabs.has(tab)) {
                return;
            }

            event.preventDefault();
            activateTab(tab);

            window.requestAnimationFrame(() => {
                const target = anchor ? document.getElementById(anchor) : null;

                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    const url = new URL(window.location.href);
                    url.hash = anchor;
                    window.history.replaceState({}, '', url);
                }
            });
        });
    });

    const hashAnchor = window.location.hash.replace('#', '');
    const hashTab = tabForAnchor(hashAnchor);
    const queryTab = new URL(window.location.href).searchParams.get('workspace');
    const initialTab = hashTab
        || (queryTab && knownTabs.has(queryTab) ? queryTab : null)
        || root.dataset.defaultTab
        || buttons[0].dataset.workspaceTabTarget;

    activateTab(initialTab, { updateUrl: false });

    if (hashAnchor && hashTab) {
        window.requestAnimationFrame(() => {
            document.getElementById(hashAnchor)?.scrollIntoView({ block: 'start' });
        });
    }
}

function initializeImplementationPeriod() {
    const startInput = document.getElementById('implementation-start-date');
    const endInput = document.getElementById('implementation-end-date');

    if (!startInput || !endInput) {
        return;
    }

    const durationDays = Number.parseInt(startInput.dataset.durationDays || '0', 10);

    const formatLocalDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const refreshEndDate = () => {
        if (!startInput.value || !Number.isFinite(durationDays) || durationDays < 1) {
            endInput.value = '';
            return;
        }

        const [year, month, day] = startInput.value.split('-').map(Number);
        const calculatedDate = new Date(year, month - 1, day);

        calculatedDate.setDate(calculatedDate.getDate() + durationDays);
        endInput.value = formatLocalDate(calculatedDate);
    };

    startInput.addEventListener('change', refreshEndDate);
    startInput.addEventListener('input', refreshEndDate);
    refreshEndDate();
}

function initializeEvaluationFields() {
    const resultSelect = document.getElementById('evaluation-result');

    if (!resultSelect) {
        return;
    }

    const complianceFields = document.getElementById('compliance-fields');
    const approvalNote = document.getElementById('for-approval-note');
    const findings = document.getElementById('evaluation-findings');
    const requiredDocuments = document.getElementById('evaluation-required-documents');

    const syncEvaluationFields = () => {
        const isCompliance = resultSelect.value === 'for_compliance';
        const isApproval = resultSelect.value === 'for_approval';

        complianceFields?.classList.toggle('hidden', !isCompliance);
        approvalNote?.classList.toggle('hidden', !isApproval);

        if (findings) {
            findings.required = isCompliance;
            findings.disabled = !isCompliance;
        }

        if (requiredDocuments) {
            requiredDocuments.required = isCompliance;
            requiredDocuments.disabled = !isCompliance;
        }
    };

    resultSelect.addEventListener('change', syncEvaluationFields);
    syncEvaluationFields();
}


function initializeQuickWorkflowModal() {
    const modal = document.querySelector('[data-quick-workflow-modal]');
    const openButtons = Array.from(document.querySelectorAll('[data-quick-workflow-open]'));

    if (!modal || openButtons.length === 0) {
        return;
    }

    const closeButtons = Array.from(modal.querySelectorAll('[data-quick-workflow-close]'));
    let previouslyFocused = null;

    const open = () => {
        previouslyFocused = document.activeElement;
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        window.requestAnimationFrame(() => {
            modal.querySelector('select, input:not([type="hidden"]), textarea, button')?.focus();
        });
    };

    const close = () => {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        previouslyFocused?.focus?.();
    };

    openButtons.forEach((button) => button.addEventListener('click', open));
    closeButtons.forEach((button) => button.addEventListener('click', close));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            close();
        }
    });

    const quickResult = modal.querySelector('[data-quick-evaluation-result]');
    const complianceFields = modal.querySelector('[data-quick-compliance-fields]');
    const approvalNote = modal.querySelector('[data-quick-approval-note]');
    const findings = modal.querySelector('[data-quick-evaluation-findings]');
    const documents = modal.querySelector('[data-quick-evaluation-documents]');

    const syncQuickEvaluation = () => {
        if (!quickResult) return;
        const isCompliance = quickResult.value === 'for_compliance';
        const isApproval = quickResult.value === 'for_approval';
        complianceFields?.classList.toggle('hidden', !isCompliance);
        approvalNote?.classList.toggle('hidden', !isApproval);
        if (findings) {
            findings.required = isCompliance;
            findings.disabled = !isCompliance;
        }
        if (documents) {
            documents.required = isCompliance;
            documents.disabled = !isCompliance;
        }
    };

    quickResult?.addEventListener('change', syncQuickEvaluation);
    syncQuickEvaluation();

    if (modal.dataset.autoOpen === 'true') {
        open();
    }
}

export function initializeProjectWorkspace() {
    const root = document.querySelector('[data-project-workspace]');

    if (!root) {
        return;
    }

    initializeWorkspaceTabs(root);
    initializeImplementationPeriod();
    initializeEvaluationFields();
    initializeQuickWorkflowModal();
}
