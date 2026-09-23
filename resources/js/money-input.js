/**
 * Live thousands-separator formatting for peso amount inputs.
 *
 * Any <input data-money-input> is switched from type="number" (which cannot
 * display commas at all) to a plain text input that formats with commas as
 * the user types, while keeping the field's live numeric value exactly what
 * it would have been without formatting — inline scripts elsewhere (project
 * cost previews, ADL total, realignment preview, target totals, ...) read
 * that value through unformatMoney()/window.TupadMoney.unformat() instead of
 * assuming it is always comma-free.
 */

function formatWithCommas(digits) {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/** Strip everything except digits and a single decimal point. */
export function unformatMoney(raw) {
    let value = String(raw ?? '').replace(/[^\d.]/g, '');
    const firstDot = value.indexOf('.');

    if (firstDot !== -1) {
        value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
    }

    return value;
}

function formatMoneyDisplay(raw) {
    const clean = unformatMoney(raw);

    if (clean === '' || clean === '.') {
        return clean;
    }

    const [whole, decimal] = clean.split('.');
    const formattedWhole = formatWithCommas(whole || '0');

    return decimal === undefined ? formattedWhole : `${formattedWhole}.${decimal}`;
}

const wiredForms = new WeakSet();

function wireFormSubmit(form) {
    if (!(form instanceof HTMLFormElement) || wiredForms.has(form)) return;
    wiredForms.add(form);

    // Belt-and-suspenders: the fields already hold a clean value at every
    // keystroke (see enableMoneyInput below), but strip commas again right
    // before submission in case a value was set programmatically elsewhere.
    form.addEventListener('submit', () => {
        form.querySelectorAll('[data-money-input]').forEach((input) => {
            input.value = unformatMoney(input.value);
        });
    });
}

export function enableMoneyInput(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.moneyInputReady === 'true') {
        return;
    }

    input.dataset.moneyInputReady = 'true';

    if (input.type === 'number') {
        input.type = 'text';
    }

    input.setAttribute('inputmode', 'decimal');
    input.setAttribute('autocomplete', 'off');

    if (input.value !== '') {
        input.value = formatMoneyDisplay(input.value);
    }

    input.addEventListener('input', () => {
        const cursorPosition = input.selectionStart ?? input.value.length;
        const digitsBeforeCursor = (input.value.slice(0, cursorPosition).match(/[\d.]/g) || []).length;

        input.value = formatMoneyDisplay(input.value);

        let seen = 0;
        let newPosition = input.value.length;

        for (let i = 0; i < input.value.length; i++) {
            if (/[\d.]/.test(input.value[i])) seen++;
            if (seen >= digitsBeforeCursor) {
                newPosition = i + 1;
                break;
            }
        }

        input.setSelectionRange(newPosition, newPosition);
    });

    if (input.form) {
        wireFormSubmit(input.form);
    }
}

export function initializeMoneyInputs(root = document) {
    root.querySelectorAll('[data-money-input]').forEach(enableMoneyInput);
}

// Exposed globally so the many plain (non-module) @push('scripts') blocks
// across the Blade views can strip commas before parsing a money field's
// value, and can enable formatting on inputs they insert dynamically
// (e.g. an "Add PPE Item" row), without each of them needing its own copy
// of this logic.
window.TupadMoney = {
    unformat: unformatMoney,
    format: formatMoneyDisplay,
    enable: enableMoneyInput,
    initialize: initializeMoneyInputs,
};
