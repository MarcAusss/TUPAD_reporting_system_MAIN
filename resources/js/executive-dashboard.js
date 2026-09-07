export function initializeExecutiveDashboard() {
    initializeDependentFilters();
    initializePresentationMode();
}

function initializeDependentFilters() {
    const form = document.querySelector('[data-executive-filters]');

    if (!form) {
        return;
    }

    const province = form.querySelector('[name="province_id"]');
    const district = form.querySelector('[name="district"]');
    const municipality = form.querySelector('[name="municipality_id"]');
    const barangay = form.querySelector('[name="barangay_id"]');
    const fiscalYear = form.querySelector('[name="fiscal_year"]');
    const quarter = form.querySelector('[data-executive-quarter-input]');
    const quarterButtons = Array.from(form.querySelectorAll('[data-executive-quarter]'));
    const month = form.querySelector('[name="month"]');

    const refreshLocations = () => {
        const provinceId = province?.value ?? '';

        filterOptions(district, (option) => {
            return !provinceId || !option.dataset.provinceId || option.dataset.provinceId === provinceId;
        });

        const districtValue = district?.value ?? '';
        filterOptions(municipality, (option) => {
            const provinceMatches = !provinceId || !option.dataset.provinceId || option.dataset.provinceId === provinceId;
            const districtMatches = !districtValue || !option.dataset.district || option.dataset.district === districtValue;

            return provinceMatches && districtMatches;
        });

        const municipalityId = municipality?.value ?? '';
        const selectedMunicipality = municipality?.selectedOptions?.[0];
        const effectiveMunicipalityId = selectedMunicipality?.hidden ? '' : municipalityId;

        filterOptions(barangay, (option) => {
            if (effectiveMunicipalityId) {
                return option.dataset.municipalityId === effectiveMunicipalityId;
            }

            const provinceMatches = !provinceId || !option.dataset.provinceId || option.dataset.provinceId === provinceId;
            const districtMatches = !districtValue || !option.dataset.district || option.dataset.district === districtValue;

            return provinceMatches && districtMatches;
        });
    };

    const refreshPeriods = () => {
        const hasYear = Boolean(fiscalYear?.value);
        const hasMonth = Boolean(month?.value);

        if (quarter && !hasYear) {
            quarter.value = '';
        }

        quarterButtons.forEach((button) => {
            const selected = (quarter?.value ?? '') === button.dataset.executiveQuarter;
            const disabled = !hasYear || hasMonth;

            button.disabled = disabled;
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.classList.toggle('bg-[#0f3b72]', selected);
            button.classList.toggle('text-white', selected);
            button.classList.toggle('bg-white', !selected);
            button.classList.toggle('text-slate-600', !selected);
            button.classList.toggle('opacity-45', disabled);
            button.classList.toggle('cursor-not-allowed', disabled);
        });

        if (month) {
            month.disabled = !hasYear || Boolean(quarter?.value);
            if (!hasYear) month.value = '';
        }
    };

    province?.addEventListener('change', refreshLocations);
    district?.addEventListener('change', refreshLocations);
    municipality?.addEventListener('change', () => {
        const selected = municipality.selectedOptions?.[0];
        const municipalityDistrict = selected?.dataset.district ?? '';

        if (district && municipalityDistrict) {
            const matchingDistrict = Array.from(district.options).find(
                (option) => !option.hidden && option.value === municipalityDistrict,
            );

            if (matchingDistrict) {
                district.value = municipalityDistrict;
            }
        }

        refreshLocations();
    });
    fiscalYear?.addEventListener('change', refreshPeriods);
    quarterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!quarter || button.disabled) return;

            quarter.value = button.dataset.executiveQuarter ?? '';
            if (month) month.value = '';
            refreshPeriods();
        });
    });
    month?.addEventListener('change', () => {
        if (quarter && month?.value) quarter.value = '';
        refreshPeriods();
    });

    refreshLocations();
    refreshPeriods();
}

function filterOptions(select, predicate) {
    if (!select) return;

    for (const option of select.options) {
        if (!option.value) {
            option.hidden = false;
            continue;
        }

        const visible = predicate(option);
        option.hidden = !visible;

        if (!visible && option.selected) {
            select.value = '';
        }
    }
}

function initializePresentationMode() {
    const root = document.querySelector('[data-presentation-mode]');

    if (!root) {
        return;
    }

    const slides = Array.from(root.querySelectorAll('[data-presentation-slide]'));
    const previous = root.querySelector('[data-presentation-previous]');
    const next = root.querySelector('[data-presentation-next]');
    const counter = root.querySelector('[data-presentation-counter]');
    const fullscreen = root.querySelector('[data-presentation-fullscreen]');
    let current = 0;

    const render = () => {
        slides.forEach((slide, index) => {
            slide.hidden = index !== current;
        });

        if (counter) {
            counter.textContent = `${current + 1} / ${slides.length}`;
        }

        if (previous) previous.disabled = current === 0;
        if (next) next.disabled = current === slides.length - 1;
        slides[current]?.focus({ preventScroll: true });
    };

    previous?.addEventListener('click', () => {
        if (current > 0) {
            current -= 1;
            render();
        }
    });

    next?.addEventListener('click', () => {
        if (current < slides.length - 1) {
            current += 1;
            render();
        }
    });

    fullscreen?.addEventListener('click', async () => {
        if (!document.fullscreenElement) {
            await document.documentElement.requestFullscreen?.();
        } else {
            await document.exitFullscreen?.();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight' && current < slides.length - 1) {
            current += 1;
            render();
        }

        if (event.key === 'ArrowLeft' && current > 0) {
            current -= 1;
            render();
        }
    });

    render();
}
