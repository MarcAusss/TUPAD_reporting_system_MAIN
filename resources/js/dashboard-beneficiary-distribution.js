/*
|--------------------------------------------------------------------------
| Dashboard distribution charts (Resource Mapping style): beneficiaries or projects
|--------------------------------------------------------------------------
| Province pies (by district) → municipality bars → barangay bars.
|   click province card   → all municipalities of the province
|   click district slice  → municipalities of that district only
|   click municipality    → barangays (fetched on demand)
*/

import Chart from 'chart.js/auto';

const numberFormat = new Intl.NumberFormat('en-PH');
const format = (value) => numberFormat.format(Number(value || 0));

/** Draws each bar's value above it, like the resource mapping sheets. */
const barValueLabels = {
    id: 'bdBarValueLabels',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;
        const meta = chart.getDatasetMeta(0);
        const data = chart.data.datasets[0]?.data || [];

        ctx.save();
        ctx.font = '600 10px "Instrument Sans", ui-sans-serif, system-ui, sans-serif';
        ctx.fillStyle = '#334155';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';

        meta.data.forEach((bar, index) => {
            ctx.fillText(format(data[index]), bar.x, bar.y - 3);
        });

        ctx.restore();
    },
};

/** Every distribution chart on the page (beneficiaries and/or projects). */
export function initializeDashboardBeneficiaryDistribution() {
    document.querySelectorAll('[data-beneficiary-distribution]').forEach(initializeDistribution);
}

function initializeDistribution(root) {
    const dataNode = document.getElementById(root.dataset.bdData || 'focalBeneficiaryDistributionData');

    if (!root || !dataNode) {
        return;
    }

    let data;
    try {
        data = JSON.parse(dataNode.textContent || '{}');
    } catch {
        return;
    }

    // Wording: beneficiaries by default, or projects for the project distribution.
    const wording = {
        unit: 'beneficiaries',
        unit_one: 'beneficiary',
        secondary: 'female',
        axis: 'No. of Beneficiaries',
        root: 'Bicol Region',
        ...(data.labels || {}),
    };
    const countLabel = (value) => `${format(value)} ${Number(value) === 1 ? wording.unit_one : wording.unit}`;

    const provinces = new Map((data.provinces || []).map((province) => [String(province.id), province]));
    const endpoint = root.dataset.barangayEndpoint;

    const overview = root.querySelector('[data-bd-overview]');
    const detail = root.querySelector('[data-bd-detail]');
    const breadcrumb = root.querySelector('[data-bd-breadcrumb]');
    const title = root.querySelector('[data-bd-title]');
    const subtitle = root.querySelector('[data-bd-subtitle]');
    const legend = root.querySelector('[data-bd-legend]');
    const hint = root.querySelector('[data-bd-hint]');
    const backButton = root.querySelector('[data-bd-back]');
    const chartWrap = root.querySelector('[data-bd-chart-wrap]');
    const barCanvas = root.querySelector('[data-bd-bar]');
    const loading = root.querySelector('[data-bd-loading]');
    const errorBox = root.querySelector('[data-bd-error]');

    const pies = new Map();
    let barChart = null;
    let requestToken = 0;

    // Navigation stack of views: { level: 'province'|'district'|'municipality', province, district?, municipality? }
    let stack = [];

    function districtColor(province, districtName) {
        return province.districts.find((district) => district.name === districtName)?.color || province.color;
    }

    /* ---------------- Level 1: pies ---------------- */

    function drawPies() {
        root.querySelectorAll('[data-bd-pie]').forEach((canvas) => {
            const province = provinces.get(canvas.dataset.bdPie);
            if (!province) return;

            const hasData = province.total > 0;
            const chart = new Chart(canvas, {
                type: 'pie',
                data: {
                    labels: hasData ? province.districts.map((district) => district.name) : ['No data'],
                    datasets: [{
                        data: hasData ? province.districts.map((district) => district.total) : [1],
                        backgroundColor: hasData ? province.districts.map((district) => district.color) : ['#e2e8f0'],
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: hasData ? 8 : 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    layout: { padding: 8 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: hasData,
                            callbacks: {
                                label(context) {
                                    const district = province.districts[context.dataIndex];
                                    const share = province.total > 0 ? (district.total / province.total) * 100 : 0;
                                    return ` ${district.name}: ${format(district.total)} (${share.toFixed(1)}%) · ${format(district.female)} ${wording.secondary}`;
                                },
                                footer: () => 'Click to open this district',
                            },
                        },
                    },
                    onHover(event, elements) {
                        event.native.target.style.cursor = elements.length && hasData ? 'pointer' : '';
                    },
                },
            });

            pies.set(String(province.id), chart);
        });
    }

    function openFromCard(card, event) {
        const province = provinces.get(card.dataset.bdProvince);
        if (!province) return;

        // A click on a pie slice opens that district; anywhere else opens the province.
        const pie = pies.get(String(province.id));
        if (pie && event && event.target === pie.canvas && province.total > 0) {
            const hit = pie.getElementsAtEventForMode(event, 'nearest', { intersect: true }, false);
            if (hit.length) {
                const district = province.districts[hit[0].index];
                show([{ level: 'province', province }, { level: 'district', province, district: district.name }]);
                return;
            }
        }

        show([{ level: 'province', province }]);
    }

    overview.querySelectorAll('[data-bd-province]').forEach((card) => {
        card.addEventListener('click', (event) => openFromCard(card, event));
        card.addEventListener('keydown', (event) => {
            if (event.target === card && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                openFromCard(card, null);
            }
        });
    });

    overview.querySelectorAll('[data-bd-district]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const province = provinces.get(button.dataset.bdDistrict);
            if (!province) return;
            show([{ level: 'province', province }, { level: 'district', province, district: button.dataset.bdDistrictName }]);
        });
    });

    /* ---------------- Levels 2–3: bars ---------------- */

    function show(nextStack) {
        stack = nextStack;
        render();
    }

    function goBack() {
        stack = stack.slice(0, -1);
        render();
    }

    function renderBreadcrumb() {
        breadcrumb.innerHTML = '';

        const crumbs = [{ label: wording.root, depth: 0 }];
        stack.forEach((view, index) => {
            const label = view.level === 'province'
                ? view.province.name
                : view.level === 'district'
                    ? view.district
                    : view.municipality.name;
            crumbs.push({ label, depth: index + 1 });
        });

        crumbs.forEach((crumb, index) => {
            if (index > 0) {
                const separator = document.createElement('span');
                separator.textContent = '›';
                separator.className = 'text-slate-300';
                breadcrumb.appendChild(separator);
            }

            const isLast = index === crumbs.length - 1;
            const node = document.createElement(isLast ? 'span' : 'button');
            node.textContent = crumb.label;

            if (isLast) {
                node.className = 'text-slate-800';
                node.setAttribute('aria-current', 'page');
            } else {
                node.type = 'button';
                node.className = 'text-blue-700 hover:text-blue-900 hover:underline';
                node.addEventListener('click', () => show(stack.slice(0, crumb.depth)));
            }

            breadcrumb.appendChild(node);
        });
    }

    function renderLegend(province, districtNames) {
        legend.innerHTML = '';

        districtNames.forEach((name) => {
            const item = document.createElement('span');
            item.className = 'inline-flex items-center gap-1.5';

            const swatch = document.createElement('span');
            swatch.className = 'h-2.5 w-2.5 rounded-sm';
            swatch.style.background = districtColor(province, name);

            item.append(swatch, document.createTextNode(name));
            legend.appendChild(item);
        });
    }

    function drawBars({ labels, values, colors, female, axisTitle, onClick }) {
        barChart?.destroy();

        // Wide municipalities / barangays lists scroll sideways instead of squashing.
        chartWrap.style.minWidth = `${Math.max(560, labels.length * 34)}px`;

        barChart = new Chart(barCanvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: wording.unit,
                    data: values,
                    backgroundColor: colors,
                    borderRadius: 4,
                    maxBarThickness: 48,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 18 } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#334155', font: { size: 10, weight: '600' }, autoSkip: false, maxRotation: 70, minRotation: labels.length > 12 ? 60 : 0 },
                        title: { display: true, text: axisTitle, color: '#64748b', font: { size: 11, weight: '700' } },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#eef2f7' },
                        ticks: { color: '#64748b', font: { size: 10 }, precision: 0 },
                        title: { display: true, text: wording.axis, color: '#64748b', font: { size: 11, weight: '700' } },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => ` ${countLabel(context.parsed.y)}`,
                            afterLabel: (context) => (female ? ` ${format(female[context.dataIndex])} ${wording.secondary}` : ''),
                            footer: () => (onClick ? 'Click to see barangays' : ''),
                        },
                    },
                },
                onHover(event, elements) {
                    event.native.target.style.cursor = onClick && elements.length ? 'pointer' : '';
                },
                onClick: onClick
                    ? (event, elements) => {
                        if (elements.length) onClick(elements[0].index);
                    }
                    : undefined,
            },
            plugins: [barValueLabels],
        });
    }

    function renderMunicipalities(view) {
        const { province } = view;
        const municipalities = province.municipalities.filter(
            (municipality) => view.level !== 'district' || municipality.district === view.district,
        );
        const total = municipalities.reduce((sum, municipality) => sum + municipality.total, 0);
        const districtNames = [...new Set(municipalities.map((municipality) => municipality.district))];

        title.textContent = view.level === 'district' ? `${province.name} — ${view.district}` : province.name;
        title.style.color = view.level === 'district' ? districtColor(province, view.district) : province.color;
        subtitle.textContent = `${countLabel(total)} · ${municipalities.length} municipalit${municipalities.length === 1 ? 'y' : 'ies'}`;
        hint.textContent = 'Click a municipality bar to see its barangays.';
        renderLegend(province, districtNames);

        drawBars({
            labels: municipalities.map((municipality) => municipality.name),
            values: municipalities.map((municipality) => municipality.total),
            female: municipalities.map((municipality) => municipality.female),
            colors: municipalities.map((municipality) => districtColor(province, municipality.district)),
            axisTitle: `Municipality (${municipalities.length})`,
            onClick: (index) => show([...stack, { level: 'municipality', province, municipality: municipalities[index] }]),
        });
    }

    async function renderBarangays(view) {
        const { province, municipality } = view;
        const color = districtColor(province, municipality.district);
        const token = ++requestToken;

        title.textContent = municipality.name;
        title.style.color = color;
        subtitle.textContent = `${countLabel(municipality.total)} · ${municipality.district}`;
        hint.textContent = '';
        renderLegend(province, [municipality.district]);

        barChart?.destroy();
        barChart = null;
        chartWrap.classList.add('hidden');
        errorBox.classList.add('hidden');
        loading.classList.remove('hidden');

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('municipality_id', municipality.id);

            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();

            if (token !== requestToken) return; // user navigated away meanwhile

            const rows = payload.rows || [];
            subtitle.textContent = `${countLabel(payload.total)} · ${municipality.district} · ${rows.length} barangay${rows.length === 1 ? '' : 's'}`;

            loading.classList.add('hidden');
            chartWrap.classList.remove('hidden');

            drawBars({
                labels: rows.map((row) => row.name),
                values: rows.map((row) => row.total),
                female: rows.map((row) => row.female),
                colors: rows.map(() => color),
                axisTitle: `Barangay (${rows.length})`,
                onClick: null,
            });
        } catch {
            if (token !== requestToken) return;
            loading.classList.add('hidden');
            errorBox.classList.remove('hidden');
        }
    }

    function render() {
        renderBreadcrumb();

        if (stack.length === 0) {
            requestToken++;
            barChart?.destroy();
            barChart = null;
            detail.classList.add('hidden');
            overview.classList.remove('hidden');
            return;
        }

        overview.classList.add('hidden');
        detail.classList.remove('hidden');
        chartWrap.classList.remove('hidden');
        loading.classList.add('hidden');
        errorBox.classList.add('hidden');

        const view = stack[stack.length - 1];
        backButton.textContent = stack.length === 1 ? (provinces.size === 1 ? '← Overview' : '← All provinces') : '← Back';

        if (view.level === 'municipality') {
            renderBarangays(view);
        } else {
            requestToken++;
            renderMunicipalities(view);
        }

        if (root.getBoundingClientRect().top < 0) {
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    backButton.addEventListener('click', goBack);

    drawPies();
}
