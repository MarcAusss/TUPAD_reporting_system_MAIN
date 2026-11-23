/*
|--------------------------------------------------------------------------
| Cursor tooltips for CSS-drawn charts (dashboard donuts and bars)
|--------------------------------------------------------------------------
| Bars / any element:
|   <div data-chart-tip="Total Paid" data-chart-tip-value="₱812,445,230.00"
|        data-chart-tip-color="#16864b">
|
| Donuts drawn with a conic-gradient (slices start at 12 o'clock, clockwise):
|   <div data-chart-donut='[{"label":"Approved","value":2,"display":"2","percent":3.3,"color":"#0284c7"}]'
|        data-chart-donut-hole="0.64">      inner radius as a share of the outer one
|
| The tooltip follows the cursor and stays inside the viewport. Charts keep
| their aria-label, so screen readers get the same data without hovering.
*/

let tooltip = null;

function ensureTooltip() {
    if (tooltip) return tooltip;

    tooltip = document.createElement('div');
    tooltip.className = 'tupad-chart-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    tooltip.innerHTML = '<span class="tupad-chart-tooltip-swatch"></span><span class="tupad-chart-tooltip-body"><span class="tupad-chart-tooltip-label"></span><span class="tupad-chart-tooltip-value"></span></span>';
    document.body.append(tooltip);

    return tooltip;
}

function show(event, { label, value, color }) {
    const tip = ensureTooltip();
    tip.querySelector('.tupad-chart-tooltip-label').textContent = label;
    tip.querySelector('.tupad-chart-tooltip-value').textContent = value ?? '';
    const swatch = tip.querySelector('.tupad-chart-tooltip-swatch');
    swatch.style.background = color || 'transparent';
    swatch.hidden = !color;
    tip.hidden = false;
    move(event);
}

function move(event) {
    if (!tooltip || tooltip.hidden) return;

    const gap = 14;
    const { innerWidth, innerHeight } = window;
    const width = tooltip.offsetWidth;
    const height = tooltip.offsetHeight;

    let left = event.clientX + gap;
    let top = event.clientY + gap;

    if (left + width > innerWidth - 8) left = event.clientX - width - gap;
    if (top + height > innerHeight - 8) top = event.clientY - height - gap;

    tooltip.style.transform = `translate(${Math.max(8, left)}px, ${Math.max(8, top)}px)`;
}

function hide() {
    if (tooltip) tooltip.hidden = true;
}

function sliceAt(element, slices, event) {
    const rect = element.getBoundingClientRect();
    const x = event.clientX - (rect.left + rect.width / 2);
    const y = event.clientY - (rect.top + rect.height / 2);
    const radius = Math.min(rect.width, rect.height) / 2;
    const distance = Math.hypot(x, y);
    const hole = Number(element.dataset.chartDonutHole || 0);

    if (distance > radius || distance < radius * hole) return null;

    // Angle clockwise from 12 o'clock, in percent of a full turn.
    const angle = (Math.atan2(x, -y) * 180 / Math.PI + 360) % 360;
    const position = angle / 360 * 100;

    const total = slices.reduce((sum, slice) => sum + Number(slice.value || 0), 0);
    if (total <= 0) return null;

    let start = 0;
    for (const slice of slices) {
        const end = start + Number(slice.value || 0) / total * 100;
        if (position >= start && position < end) return slice;
        start = end;
    }

    return slices[slices.length - 1] ?? null;
}

function describeSlice(slice) {
    const percent = slice.percent !== undefined ? ` · ${Number(slice.percent).toFixed(1)}%` : '';
    return { label: slice.label, value: `${slice.display ?? slice.value}${percent}`, color: slice.color };
}

export function initializeChartTooltips() {
    document.querySelectorAll('[data-chart-donut]').forEach((donut) => {
        let slices = [];
        try {
            slices = JSON.parse(donut.dataset.chartDonut || '[]').filter((slice) => Number(slice.value) > 0);
        } catch {
            return;
        }
        if (slices.length === 0) return;

        donut.style.cursor = 'crosshair';
        donut.addEventListener('pointermove', (event) => {
            const slice = sliceAt(donut, slices, event);
            if (slice) {
                show(event, describeSlice(slice));
            } else {
                hide();
            }
        });
        donut.addEventListener('pointerleave', hide);
    });

    document.querySelectorAll('[data-chart-tip]').forEach((element) => {
        const data = () => ({
            label: element.dataset.chartTip,
            value: element.dataset.chartTipValue,
            color: element.dataset.chartTipColor,
        });

        element.addEventListener('pointerenter', (event) => show(event, data()));
        element.addEventListener('pointermove', move);
        element.addEventListener('pointerleave', hide);
    });

    window.addEventListener('scroll', hide, { passive: true });
}
