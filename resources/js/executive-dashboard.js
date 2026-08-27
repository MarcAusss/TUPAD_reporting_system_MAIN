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
    const quarter = form.querySelector('[name="quarter"]');
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

        if (quarter) {
            quarter.disabled = !hasYear || Boolean(month?.value);
            if (!hasYear) quarter.value = '';
        }

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
    quarter?.addEventListener('change', refreshPeriods);
    month?.addEventListener('change', refreshPeriods);

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
