import Chart from 'chart.js/auto';

export function initializeDashboardGeographicAnalytics() {
    const root = document.querySelector('[data-dashboard-geographic-analytics]');
    const dataNode = document.getElementById('focalGeographicAnalyticsInitialData');

    if (!root || !dataNode) {
        return;
    }

    let state;
    try {
        state = JSON.parse(dataNode.textContent || '{}');
    } catch {
        return;
    }

    const endpoint = root.dataset.endpoint;
    const canvas = root.querySelector('[data-geo-chart]');
    const chartWrap = root.querySelector('[data-geo-chart-wrap]');
    const backButton = root.querySelector('[data-geo-back]');
    const title = root.querySelector('[data-geo-title]');
    const description = root.querySelector('[data-geo-description]');
    const breadcrumb = root.querySelector('[data-geo-breadcrumb]');
    const note = root.querySelector('[data-geo-note]');
    const empty = root.querySelector('[data-geo-empty]');
    const sectorControls = root.querySelector('[data-sector-controls]');
    const sectorGroup = root.querySelector('[data-sector-group]');
    const sector = root.querySelector('[data-sector]');
    const interventionControls = root.querySelector('[data-intervention-controls]');
    const interventionFocus = root.querySelector('[data-intervention-focus]');

    let chart = null;
    let loading = false;

    function number(value) {
        return new Intl.NumberFormat('en-PH').format(Number(value || 0));
    }

    function setText(selector, value) {
        const node = root.querySelector(selector);
        if (node) node.textContent = value;
    }

    function setFamilyButtons() {
        root.querySelectorAll('[data-geo-family]').forEach((button) => {
            const active = button.dataset.geoFamily === state.family;
            button.classList.toggle('border-blue-700', active);
            button.classList.toggle('bg-blue-700', active);
            button.classList.toggle('text-white', active);
            button.classList.toggle('border-slate-300', !active);
            button.classList.toggle('bg-white', !active);
            button.classList.toggle('text-slate-600', !active);
        });

        sectorControls?.classList.toggle('hidden', state.family !== 'sectors');
        sectorControls?.classList.toggle('grid', state.family === 'sectors');
        interventionControls?.classList.toggle('hidden', state.family !== 'interventions');
    }

    function rebuildSectorOptions() {
        if (!sector || !sectorGroup) return;

        const current = state.selection?.sector || '';
        sector.innerHTML = '<option value="">All categories in selected family</option>';

        (state.controls?.sectors || [])
            .filter((item) => item.group === sectorGroup.value)
            .forEach((item) => {
                const option = document.createElement('option');
                option.value = item.key;
                option.textContent = item.label;
                option.selected = item.key === current;
                sector.appendChild(option);
            });
    }

    function updateControlsFromState() {
        if (sectorGroup && state.selection?.sector_group) {
            sectorGroup.value = state.selection.sector_group;
        }

        rebuildSectorOptions();

        if (sector && state.selection?.sector) {
            sector.value = state.selection.sector;
        }

        if (interventionFocus) {
            interventionFocus.value = state.selection?.intervention_focus || '';
        }
    }

    function renderBreadcrumb() {
        if (!breadcrumb) return;
        breadcrumb.innerHTML = '';

        (state.breadcrumbs || []).forEach((item, index) => {
            const span = document.createElement('span');
            span.textContent = item.label;
            span.className = index === (state.breadcrumbs || []).length - 1
                ? 'text-slate-900'
                : 'text-slate-400';
            breadcrumb.appendChild(span);

            if (index < (state.breadcrumbs || []).length - 1) {
                const separator = document.createElement('span');
                separator.textContent = '›';
                separator.className = 'text-slate-300';
                breadcrumb.appendChild(separator);
            }
        });
    }

    function chartHeight(rowCount) {
        const seriesCount = Math.max(1, (state.series || []).length);
        const rowHeight = seriesCount > 1 ? 48 : 36;

        if (rowCount <= 6) return seriesCount > 1 ? 420 : 360;
        return Math.min(1800, Math.max(460, rowCount * rowHeight + 80));
    }

    function renderChart() {
        const rows = state.rows || [];
        const hasRows = rows.length > 0;

        empty?.classList.toggle('hidden', hasRows);
        if (chartWrap) chartWrap.classList.toggle('hidden', !hasRows);

        if (!canvas || !hasRows) {
            chart?.destroy();
            chart = null;
            return;
        }

        if (chartWrap) {
            chartWrap.style.height = `${chartHeight(rows.length)}px`;
        }

        chart?.destroy();

        const datasets = (state.series || []).map((series, index) => ({
            label: series.label,
            data: rows.map((row) => Number(row[series.key] || 0)),
            borderWidth: 1,
            borderRadius: 4,
            maxBarThickness: 24,
            backgroundColor: index === 0 ? '#063b86' : '#0d9bc0',
            borderColor: index === 0 ? '#063b86' : '#0d9bc0',
        }));

        chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.name),
                datasets,
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    intersect: true,
                },
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        position: 'top',
                        align: 'start',
                    },
                    tooltip: {
                        callbacks: {
                            afterBody: () => state.level === 'municipality'
                                ? ''
                                : 'Click this bar to drill down.',
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                        grid: {
                            color: '#e2e8f0',
                        },
                    },
                    y: {
                        grid: {
                            display: false,
                        },
                    },
                },
                onHover: (event, elements) => {
                    if (event?.native?.target) {
                        event.native.target.style.cursor = elements.length && state.level !== 'municipality'
                            ? 'pointer'
                            : 'default';
                    }
                },
                onClick: (_event, elements) => {
                    if (!elements.length || state.level === 'municipality' || loading) return;

                    const row = rows[elements[0].index];
                    if (!row?.id) return;

                    if (state.level === 'region') {
                        load({ province_id: row.id });
                    } else if (state.level === 'province') {
                        load({
                            province_id: state.selected_province_id,
                            municipality_id: row.id,
                        });
                    }
                },
            },
        });
    }

    function render() {
        setFamilyButtons();
        updateControlsFromState();
        renderBreadcrumb();

        if (title) {
            title.textContent = `${state.family_label} — ${state.scope_label}`;
        }
        if (description) description.textContent = state.family_description || '';
        if (note) note.textContent = state.data_note || '';

        setText('[data-geo-summary-projects]', number(state.summary?.projects));
        setText('[data-geo-summary-acp]', number(state.summary?.through_acp));
        setText('[data-geo-summary-total]', number(state.summary?.beneficiaries));
        setText('[data-geo-summary-female]', number(state.summary?.female));
        setText('[data-geo-summary-areas]', number(state.summary?.areas));

        root.querySelector('[data-project-acp-summary]')?.classList.toggle('hidden', state.family !== 'projects');
        root.querySelector('[data-beneficiary-summary]')?.classList.toggle('hidden', state.family === 'projects');
        root.querySelector('[data-female-summary]')?.classList.toggle('hidden', state.family === 'projects');

        if (backButton) {
            backButton.classList.toggle('hidden', !state.can_go_back);
            backButton.classList.toggle('inline-flex', state.can_go_back);
        }

        renderChart();
    }

    function currentFilters() {
        return {
            sector_group: state.family === 'sectors' ? (sectorGroup?.value || '') : '',
            sector: state.family === 'sectors' ? (sector?.value || '') : '',
            intervention_focus: state.family === 'interventions' ? (interventionFocus?.value || '') : '',
        };
    }

    function currentLocation() {
        if (state.level === 'municipality') {
            return {
                province_id: state.selected_province_id,
                municipality_id: state.selected_municipality_id,
            };
        }

        if (state.level === 'province') {
            return { province_id: state.selected_province_id };
        }

        return {};
    }

    async function load(location = {}) {
        if (!endpoint || loading) return;

        loading = true;
        root.setAttribute('aria-busy', 'true');

        const params = new URLSearchParams({
            family: state.family,
            ...currentFilters(),
        });

        Object.entries(location).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                params.set(key, String(value));
            }
        });

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Dashboard geographic analytics request failed with ${response.status}.`);
            }

            state = await response.json();
            render();
        } catch (error) {
            console.error(error);
            if (note) {
                note.textContent = 'The geographic chart could not be refreshed. Open the full Geographic Mapping report or reload the dashboard.';
            }
        } finally {
            loading = false;
            root.removeAttribute('aria-busy');
        }
    }

    root.querySelectorAll('[data-geo-family]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.geoFamily === state.family || loading) return;
            state.family = button.dataset.geoFamily;
            load({});
        });
    });

    backButton?.addEventListener('click', () => {
        if (state.level === 'municipality') {
            load({ province_id: state.selected_province_id });
            return;
        }

        load({});
    });

    sectorGroup?.addEventListener('change', () => {
        state.selection = {
            ...(state.selection || {}),
            sector_group: sectorGroup.value,
            sector: null,
        };
        rebuildSectorOptions();
        load(currentLocation());
    });

    sector?.addEventListener('change', () => load(currentLocation()));
    interventionFocus?.addEventListener('change', () => load(currentLocation()));

    render();
}
