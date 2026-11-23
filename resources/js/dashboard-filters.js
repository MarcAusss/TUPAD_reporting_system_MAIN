/*
| Dashboard filter bar (resources/views/dashboard/partials/focal-filters.blade.php)
| - Province -> loads its municipalities into the Municipality select.
| - Quarter -> limits Month to the months of that quarter.
| - Leaves "All" (empty) selects out of the URL on submit.
*/
export function initializeDashboardFilters() {
    initializeFilterToggle();

    const form = document.querySelector('[data-dashboard-filter-form]');
    if (!form) return;

    const province = form.querySelector('[data-filter-province]');
    const municipality = form.querySelector('[data-filter-municipality]');
    const quarter = form.querySelector('[data-filter-quarter]');
    const month = form.querySelector('[data-filter-month]');

    const resetMunicipality = (placeholder, disabled) => {
        const all = document.createElement('option');
        all.value = '';
        all.textContent = placeholder;
        municipality.replaceChildren(all);
        municipality.disabled = disabled;
    };

    province?.addEventListener('change', async () => {
        if (!municipality) return;

        if (!province.value) {
            resetMunicipality('Select a province first', true);
            return;
        }

        resetMunicipality('Loading…', true);

        try {
            const url = form.dataset.municipalityUrl.replace('__ID__', encodeURIComponent(province.value));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Request failed');
            const items = await response.json();

            resetMunicipality('All municipalities', false);
            items.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                municipality.append(option);
            });
        } catch {
            resetMunicipality('Could not load municipalities', true);
        }
    });

    const syncMonths = () => {
        if (!quarter || !month) return;

        Array.from(month.options).forEach((option) => {
            if (!option.value) return;
            const outside = quarter.value !== '' && option.dataset.quarter !== quarter.value;
            option.disabled = outside;
            option.hidden = outside;
        });

        if (month.selectedOptions[0]?.disabled) month.value = '';
    };

    quarter?.addEventListener('change', syncMonths);
    syncMonths();

    // Keep the URL clean: "All" selects are not sent.
    form.addEventListener('submit', () => {
        form.querySelectorAll('select').forEach((select) => {
            if (select.value === '') select.disabled = true;
        });
    });

    // Back/forward cache: re-enable selects disabled on submit.
    window.addEventListener('pageshow', () => {
        form.querySelectorAll('select').forEach((select) => {
            // Municipality waits for a province, unless the province is locked (no select).
            if (select !== municipality || !province || province.value) select.disabled = false;
        });
    });
}

/*
| "Filters" button: shows / hides the filter panel. Escape closes it and
| returns focus to the button.
*/
function initializeFilterToggle() {
    const toggle = document.querySelector('[data-filter-toggle]');
    const panel = document.querySelector('[data-filter-panel]');
    if (!toggle || !panel) return;

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) panel.querySelector('select:not(:disabled)')?.focus();
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));

    panel.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        setOpen(false);
        toggle.focus();
    });
}
