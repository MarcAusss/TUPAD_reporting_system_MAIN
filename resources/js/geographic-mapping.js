import L from 'leaflet';
import Chart from 'chart.js/auto';
import 'leaflet/dist/leaflet.css';

const mapState = new WeakMap();

const numberFormatter = new Intl.NumberFormat('en-PH');
const compactFormatter = new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 });
const pesoFormatter = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0,
});

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function chartRows(payload) {
    if (Array.isArray(payload?.areas)) return payload.areas;
    if (payload?.map_level === 'municipality') return payload?.barangays ?? [];
    if (payload?.map_level === 'province') return payload?.municipalities ?? [];
    return payload?.provinces ?? [];
}

function mapRows(payload) {
    return payload?.map_level === 'region'
        ? (payload?.provinces ?? [])
        : (payload?.municipalities ?? []);
}

function colorFor(value, max) {
    if (!value || max <= 0) return '#eff6ff';

    const ratio = value / max;
    if (ratio <= 0.20) return '#dbeafe';
    if (ratio <= 0.40) return '#bfdbfe';
    if (ratio <= 0.60) return '#93c5fd';
    if (ratio <= 0.80) return '#3b82f6';
    return '#063b86';
}

function mapStatsIndex(payload) {
    return new Map(mapRows(payload).map((area) => [String(area.psgc_code), area]));
}

function allocationText(stats) {
    if (stats?.allocation_available === false || stats?.allocation_cents === null) {
        return 'Not geographically split';
    }

    return pesoFormatter.format(Number(stats?.allocation_cents ?? 0) / 100);
}

function metricValueText(value, payload) {
    if (payload?.metric?.key === 'allocation') {
        return pesoFormatter.format(Number(value ?? 0) / 100);
    }

    return numberFormatter.format(Number(value ?? 0));
}

function metricAxisText(value, payload) {
    if (payload?.metric?.key === 'allocation') {
        return `₱${compactFormatter.format(Number(value ?? 0) / 100)}`;
    }

    return compactFormatter.format(Number(value ?? 0));
}

function metricTooltipText(value, payload) {
    const label = String(payload?.metric?.label ?? 'Beneficiaries').toLowerCase();
    return `${metricValueText(value, payload)} ${label}`;
}

function tooltipHtml(stats, featureName, payload) {
    const isRegionView = payload?.map_level === 'region';
    const fallbackName = isRegionView ? 'Province' : 'Municipality / City';
    const name = escapeHtml(stats?.name ?? featureName ?? fallbackName);
    const incomplete = stats && !stats.has_complete_exact_allocation
        ? `<div class="tupad-map-tooltip-warning">Includes ${numberFormatter.format(stats.legacy_unallocated_project_count ?? 0)} legacy unallocated project(s)</div>`
        : '';

    let foot = 'Click to view municipalities and cities';
    if (!isRegionView) {
        const selectedCode = String(payload?.selected_municipality?.psgc_code ?? '');
        const currentCode = String(stats?.psgc_code ?? '');
        foot = selectedCode && selectedCode === currentCode
            ? 'Selected — barangay ranking and labels are shown'
            : 'Click for barangay breakdown';
    }

    return `
        <div class="tupad-map-tooltip-card">
            <div class="tupad-map-tooltip-title">${name}</div>
            <div class="tupad-map-tooltip-grid">
                <span>Beneficiaries</span><strong>${numberFormatter.format(stats?.beneficiaries ?? 0)}</strong>
                <span>Projects</span><strong>${numberFormatter.format(stats?.projects ?? 0)}</strong>
                <span>Ongoing</span><strong>${numberFormatter.format(stats?.ongoing_projects ?? 0)}</strong>
                <span>Completed</span><strong>${numberFormatter.format(stats?.completed_projects ?? 0)}</strong>
                <span>Allocation</span><strong>${escapeHtml(allocationText(stats))}</strong>
            </div>
            <div class="tupad-map-tooltip-metric">Map metric: <strong>${escapeHtml(payload?.metric?.label ?? 'Beneficiaries')}</strong></div>
            ${incomplete}
            <div class="tupad-map-tooltip-foot">${foot}</div>
        </div>`;
}

function isSelectedMunicipality(feature, payload) {
    if (payload?.map_level !== 'municipality') return false;

    return String(feature?.properties?.psgc_code ?? '')
        === String(payload?.selected_municipality?.psgc_code ?? '');
}

function styleForFeature(feature, payload) {
    const rows = mapRows(payload);
    const byCode = mapStatsIndex(payload);
    const max = Math.max(0, ...rows.map((area) => Number(area.value ?? 0)));
    const stats = byCode.get(String(feature?.properties?.psgc_code ?? ''));
    const selected = isSelectedMunicipality(feature, payload);

    return {
        color: selected ? '#062f6d' : '#ffffff',
        weight: selected ? 3.5 : 1.5,
        opacity: 1,
        fillColor: colorFor(Number(stats?.value ?? 0), max),
        fillOpacity: selected ? 1 : 0.92,
    };
}

function clearLabels(state) {
    state.labelLoadToken += 1;
    state.labels.forEach((label) => state.map.removeLayer(label));
    state.labels = [];
}

function clearBoundary(state) {
    clearLabels(state);

    if (state.geoJsonLayer) {
        state.map.removeLayer(state.geoJsonLayer);
        state.geoJsonLayer = null;
    }
}

function boundaryUrl(root, payload) {
    return payload?.boundary?.url || root.dataset.geojsonUrl || '';
}

function labelBoundaryUrl(payload) {
    return payload?.label_boundary?.ready
        ? (payload?.label_boundary?.url ?? '')
        : '';
}

function updateExistingLayer(state, payload) {
    if (!state.geoJsonLayer) return;

    const byCode = mapStatsIndex(payload);

    state.geoJsonLayer.eachLayer((layer) => {
        const code = String(layer.feature?.properties?.psgc_code ?? '');
        const stats = byCode.get(code);

        layer.setStyle(styleForFeature(layer.feature, payload));
        layer.unbindTooltip();
        layer.bindTooltip(
            tooltipHtml(stats, layer.feature?.properties?.name, payload),
            {
                sticky: true,
                direction: 'top',
                className: 'tupad-map-stat-tooltip',
                opacity: 1,
            },
        );
    });
}

function addBoundaryLabels(state, payload) {
    if (!state.geoJsonLayer) return;

    const isRegionView = payload?.map_level === 'region';
    const selectedCode = String(payload?.selected_municipality?.psgc_code ?? '');

    state.geoJsonLayer.eachLayer((layer) => {
        const name = layer.feature?.properties?.name;
        const code = String(layer.feature?.properties?.psgc_code ?? '');
        if (!name || !layer.getBounds) return;

        const isSelected = !isRegionView && selectedCode !== '' && code === selectedCode;
        const label = L.tooltip({
            permanent: true,
            direction: 'center',
            className: isRegionView
                ? 'tupad-province-label'
                : `tupad-municipality-label${isSelected ? ' tupad-municipality-label-selected' : ''}`,
            opacity: 1,
            interactive: false,
        })
            .setLatLng(layer.getBounds().getCenter())
            .setContent(escapeHtml(name))
            .addTo(state.map);

        state.labels.push(label);
    });
}

async function getBarangayLabelGeoJson(state, payload) {
    const url = labelBoundaryUrl(payload);
    if (!url) return null;

    if (state.labelBoundaryUrl === url && state.labelGeoJson) {
        return state.labelGeoJson;
    }

    const response = await fetch(url, {
        headers: { Accept: 'application/geo+json, application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`Barangay label request returned HTTP ${response.status}.`);

    const geoJson = await response.json();
    state.labelBoundaryUrl = url;
    state.labelGeoJson = geoJson;

    return geoJson;
}

async function addBarangayLabels(root, state, payload) {
    if (payload?.map_level !== 'municipality' || !payload?.label_boundary?.ready) return;

    const loadToken = state.labelLoadToken;

    try {
        const geoJson = await getBarangayLabelGeoJson(state, payload);
        if (!geoJson || loadToken !== state.labelLoadToken) return;

        for (const feature of geoJson.features ?? []) {
            if (feature?.geometry?.type !== 'Point') continue;

            const coordinates = feature.geometry.coordinates ?? [];
            const lng = Number(coordinates[0]);
            const lat = Number(coordinates[1]);
            const name = feature?.properties?.name;
            if (!Number.isFinite(lat) || !Number.isFinite(lng) || !name) continue;

            const label = L.tooltip({
                permanent: true,
                direction: 'center',
                className: 'tupad-barangay-label',
                opacity: 1,
                interactive: false,
            })
                .setLatLng([lat, lng])
                .setContent(escapeHtml(name))
                .addTo(state.map);

            state.labels.push(label);
        }
    } catch (error) {
        const message = root.querySelector('[data-map-error]');
        if (message) {
            message.textContent = `Municipality map loaded, but barangay labels could not be loaded. ${error.message}`;
            message.classList.remove('hidden');
        }
    }
}

function syncLabelVisibility(state, payload) {
    const zoom = state.map.getZoom();

    state.labels.forEach((label) => {
        const element = label.getElement?.();
        if (!element) return;

        const isBarangay = element.classList.contains('tupad-barangay-label');
        const isMunicipality = element.classList.contains('tupad-municipality-label');
        const isSelectedMunicipality = element.classList.contains('tupad-municipality-label-selected');

        const shouldHide = (isBarangay && zoom < 10)
            || (isMunicipality && !isSelectedMunicipality && zoom < 7);

        element.classList.toggle('tupad-map-label-hidden', shouldHide);
    });
}

async function refreshLabels(root, state, payload) {
    clearLabels(state);
    addBoundaryLabels(state, payload);
    await addBarangayLabels(root, state, payload);
    syncLabelVisibility(state, payload);
}

async function loadBoundary(root, state, payload, { animate = true } = {}) {
    const url = boundaryUrl(root, payload);
    if (!url) throw new Error('No geographic boundary URL was provided.');

    const loadToken = ++state.loadToken;
    const errorBox = root.querySelector('[data-map-error]');
    errorBox?.classList.add('hidden');

    const response = await fetch(url, {
        headers: { Accept: 'application/geo+json, application/json' },
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`GeoJSON request returned HTTP ${response.status}.`);

    const geoJson = await response.json();
    if (loadToken !== state.loadToken) return;

    clearBoundary(state);
    state.payload = payload;
    state.boundaryUrl = url;

    state.geoJsonLayer = L.geoJSON(geoJson, {
        style: (feature) => styleForFeature(feature, state.payload),
        onEachFeature: (feature, layer) => {
            const code = String(feature?.properties?.psgc_code ?? '');
            const stats = mapStatsIndex(state.payload).get(code);

            layer.bindTooltip(tooltipHtml(stats, feature?.properties?.name, state.payload), {
                sticky: true,
                direction: 'top',
                className: 'tupad-map-stat-tooltip',
                opacity: 1,
            });

            layer.on({
                mouseover: () => {
                    layer.setStyle({ color: '#063b86', weight: 2.8, fillOpacity: 1 });
                    if (layer.bringToFront) layer.bringToFront();
                },
                mouseout: () => {
                    layer.setStyle(styleForFeature(feature, state.payload));
                },
                click: () => {
                    const currentStats = mapStatsIndex(state.payload).get(code);
                    if (!currentStats?.id) return;

                    if (layer.getBounds) {
                        state.map.fitBounds(layer.getBounds(), {
                            padding: [28, 28],
                            animate: true,
                            duration: 0.45,
                        });
                    }

                    if (state.payload?.map_level !== 'region') {
                        root.dispatchEvent(new CustomEvent('tupad-map-select-municipality', {
                            bubbles: true,
                            detail: { municipalityId: Number(currentStats.id) },
                        }));
                        return;
                    }

                    root.dispatchEvent(new CustomEvent('tupad-map-select-province', {
                        bubbles: true,
                        detail: { provinceId: Number(currentStats.id) },
                    }));
                },
            });
        },
    }).addTo(state.map);

    state.currentBounds = state.geoJsonLayer.getBounds();
    if (state.currentBounds.isValid()) {
        state.map.fitBounds(state.currentBounds, {
            padding: [18, 18],
            animate,
            duration: animate ? 0.45 : undefined,
        });
    }

    await refreshLabels(root, state, payload);
}

async function applyMapPayload(root, state, payload) {
    state.payload = payload;
    const nextBoundaryUrl = boundaryUrl(root, payload);

    if (!state.geoJsonLayer || state.boundaryUrl !== nextBoundaryUrl) {
        try {
            await loadBoundary(root, state, payload, { animate: true });
        } catch (error) {
            const message = root.querySelector('[data-map-error]');
            if (message) {
                message.textContent = `Unable to load the selected Bicol administrative boundaries. ${error.message}`;
                message.classList.remove('hidden');
            }
        }
    } else {
        const errorBox = root.querySelector('[data-map-error]');
        errorBox?.classList.add('hidden');
        updateExistingLayer(state, payload);
        await refreshLabels(root, state, payload);
    }

    updateChart(root, state, payload);
}

function updateChart(root, state, payload) {
    const shell = root.closest('[data-mapping-phase="2"]');
    const canvas = shell?.querySelector('[data-map-chart]');
    if (!canvas) return;

    const rows = [...chartRows(payload)]
        .sort((a, b) => Number(b.value ?? 0) - Number(a.value ?? 0) || String(a.name).localeCompare(String(b.name)));

    const labels = rows.map((row) => row.name);
    const values = rows.map((row) => Number(row.value ?? 0));

    if (canvas.parentElement) {
        canvas.parentElement.style.height = `${Math.max(360, (rows.length * 27) + 70)}px`;
    }

    if (!state.chart) {
        state.chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: payload?.metric?.label ?? 'Beneficiaries',
                    data: values,
                    backgroundColor: '#063b86',
                    borderRadius: 3,
                    borderSkipped: false,
                    barThickness: 18,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 220 },
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            label: (context) => metricTooltipText(context.raw ?? 0, state.payload),
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7' },
                        border: { display: false },
                        ticks: {
                            color: '#718096',
                            font: { size: 9 },
                            callback: (value) => metricAxisText(value, state.payload),
                        },
                    },
                    y: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: '#334155',
                            font: { size: 10, weight: 600 },
                        },
                    },
                },
            },
        });
        return;
    }

    state.chart.data.labels = labels;
    state.chart.data.datasets[0].data = values;
    state.chart.data.datasets[0].label = payload?.metric?.label ?? 'Beneficiaries';
    state.chart.update();
}

async function initializeRoot(root) {
    if (mapState.has(root)) return;

    const canvas = root.querySelector('[data-map-canvas]');
    const payloadScript = root.closest('[data-mapping-phase="2"]')?.querySelector('[data-tupad-map-payload]');
    if (!canvas || !payloadScript) return;

    let payload;
    try {
        payload = JSON.parse(payloadScript.textContent || '{}');
    } catch {
        payload = { map_level: 'region', areas: [], provinces: [], municipalities: [], barangays: [] };
    }

    const map = L.map(canvas, {
        zoomControl: true,
        attributionControl: true,
        minZoom: 5,
        maxZoom: 14,
        preferCanvas: true,
    });
    map.attributionControl.setPrefix(false);
    map.attributionControl.addAttribution('PSGC/NAMRIA-derived administrative boundaries');

    const state = {
        map,
        geoJsonLayer: null,
        currentBounds: null,
        labels: [],
        chart: null,
        payload,
        boundaryUrl: null,
        loadToken: 0,
        labelLoadToken: 0,
        labelBoundaryUrl: null,
        labelGeoJson: null,
    };
    mapState.set(root, state);

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-map-home]') && state.currentBounds?.isValid()) {
            state.map.fitBounds(state.currentBounds, { padding: [18, 18], animate: true, duration: 0.4 });
        }
    });

    map.on('zoomend', () => syncLabelVisibility(state, state.payload));

    try {
        await loadBoundary(root, state, payload, { animate: false });
    } catch (error) {
        const message = root.querySelector('[data-map-error]');
        if (message) {
            message.textContent = `Unable to load the Bicol administrative boundaries. ${error.message}`;
            message.classList.remove('hidden');
        }
    }

    updateChart(root, state, payload);
    setTimeout(() => map.invalidateSize(false), 0);
}

export function initializeGeographicMapping() {
    document.querySelectorAll('[data-tupad-region-map]').forEach((root) => initializeRoot(root));

    if (!window.__tupadMapUpdateListenerRegistered) {
        window.addEventListener('tupad-map-data-updated', (event) => {
            const payload = event.detail?.payload ?? event.detail;
            document.querySelectorAll('[data-tupad-region-map]').forEach(async (root) => {
                if (!mapState.has(root)) await initializeRoot(root);
                const state = mapState.get(root);
                if (state) await applyMapPayload(root, state, payload);
            });
        });
        window.__tupadMapUpdateListenerRegistered = true;
    }
}
