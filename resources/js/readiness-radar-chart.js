import {
    Chart,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
    RadarController,
} from 'chart.js';

Chart.register(
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
    RadarController,
);

const charts = new WeakMap();

function pillarValues(stats) {
    const pillars = stats?.pillars ?? {};

    return {
        labels: ['TWK', 'TIU', 'TKP'],
        values: [
            pillars.twk?.percentage ?? 0,
            pillars.tiu?.percentage ?? 0,
            pillars.tkp?.percentage ?? 0,
        ],
    };
}

function renderChart(canvas, stats) {
    if (!canvas) {
        return;
    }

    const existing = charts.get(canvas);

    if (existing) {
        existing.destroy();
    }

    const { labels, values } = pillarValues(stats);

    const chart = new Chart(canvas, {
        type: 'radar',
        data: {
            labels,
            datasets: [{
                label: 'Kesiapan (%)',
                data: values,
                backgroundColor: 'rgba(79, 70, 229, 0.18)',
                borderColor: 'rgba(79, 70, 229, 0.9)',
                pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20,
                        backdropColor: 'transparent',
                        font: { size: 10 },
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.35)' },
                    angleLines: { color: 'rgba(148, 163, 184, 0.35)' },
                    pointLabels: {
                        font: { size: 11, weight: '600' },
                        color: '#334155',
                    },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => ` ${context.formattedValue}%`,
                    },
                },
            },
        },
    });

    charts.set(canvas, chart);
}

function initReadinessCharts(root = document) {
    root.querySelectorAll('[data-readiness-chart]').forEach((wrapper) => {
        const canvas = wrapper.querySelector('canvas');
        const stats = JSON.parse(wrapper.dataset.stats || '{}');
        renderChart(canvas, stats);
    });
}

document.addEventListener('DOMContentLoaded', () => initReadinessCharts());
document.addEventListener('livewire:navigated', () => initReadinessCharts());

document.addEventListener('livewire:init', () => {
    Livewire.on('readiness-chart-updated', ({ stats }) => {
        const wrapper = document.querySelector('[data-readiness-chart]');

        if (!wrapper) {
            return;
        }

        wrapper.dataset.stats = JSON.stringify(stats ?? {});
        const canvas = wrapper.querySelector('canvas');
        renderChart(canvas, stats ?? {});
    });

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => initReadinessCharts());
        });
    });
});

export { initReadinessCharts };
