/*
|--------------------------------------------------------------------------
| Declarative charts
|--------------------------------------------------------------------------
| <canvas data-tupad-chart='{"type":"bar","labels":[…],"datasets":[…],
|         "money":true,"horizontal":true,"percent":false}'></canvas>
|
*/

import Chart from 'chart.js/auto';

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const pesoExact = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 2 });

function compactPeso(value) {
    const abs = Math.abs(value);
    if (abs >= 1e9) return `₱${(value / 1e9).toFixed(1)}B`;
    if (abs >= 1e6) return `₱${(value / 1e6).toFixed(1)}M`;
    if (abs >= 1e3) return `₱${(value / 1e3).toFixed(0)}K`;
    return peso.format(value);
}

function buildConfig(spec) {
    const horizontal = Boolean(spec.horizontal);
    const isCircular = spec.type === 'doughnut' || spec.type === 'pie';
    const format = (value) => (spec.money ? pesoExact.format(value) : spec.percent ? `${value}%` : Number(value).toLocaleString('en-PH'));

    const valueAxis = {
        beginAtZero: true,
        stacked: Boolean(spec.stacked),
        grid: { color: '#eef2f7' },
        ticks: {
            color: '#64748b',
            font: { size: 10 },
            callback: (value) => (spec.money ? compactPeso(value) : spec.percent ? `${value}%` : value),
        },
        ...(spec.percent ? { max: 100 } : {}),
    };
    const categoryAxis = {
        stacked: Boolean(spec.stacked),
        grid: { display: false },
        ticks: { color: '#334155', font: { size: 11, weight: '600' } },
    };

    return {
        type: spec.type || 'bar',
        data: {
            labels: spec.labels || [],
            datasets: (spec.datasets || []).map((dataset) => ({
                borderRadius: isCircular ? 0 : 6,
                maxBarThickness: 36,
                borderWidth: isCircular ? 2 : 0,
                borderColor: isCircular ? '#ffffff' : undefined,
                ...dataset,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            cutout: spec.type === 'doughnut' ? '68%' : undefined,
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 500 },
            plugins: {
                legend: {
                    display: spec.legend !== false && (isCircular || (spec.datasets || []).length > 1),
                    position: isCircular ? 'bottom' : 'top',
                    labels: { boxWidth: 10, boxHeight: 10, color: '#475569', font: { size: 11 } },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.raw ?? 0;
                            const name = context.dataset.label || context.label;
                            return `${name}: ${format(value)}`;
                        },
                    },
                },
            },
            scales: isCircular ? {} : (horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis }),
        },
    };
}

export function initializeTupadCharts(root = document) {
    const canvases = Array.from(root.querySelectorAll('canvas[data-tupad-chart]:not([data-chart-ready])'));
    if (canvases.length === 0) return;

    canvases.forEach((canvas) => {
        try {
            const spec = JSON.parse(canvas.dataset.tupadChart || '{}');
            canvas.dataset.chartReady = 'true';
            new Chart(canvas, buildConfig(spec));
        } catch (error) {
            console.error('Chart could not be rendered', error);
        }
    });
}
