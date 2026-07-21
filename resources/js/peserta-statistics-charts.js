import {
    Chart,
    LineController,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';

Chart.register(
    LineController,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
);

const charts = new WeakMap();
const activeMetric = new WeakMap();

const METRICS = {
    total: {
        label: 'Skor Total',
        dataKey: 'totals',
        passingKey: 'passing_total',
        color: '79, 70, 229',
    },
    twk: {
        label: 'TWK',
        dataKey: 'twk',
        passingKey: 'passing_twk',
        color: '37, 99, 235',
    },
    tiu: {
        label: 'TIU',
        dataKey: 'tiu',
        passingKey: 'passing_tiu',
        color: '124, 58, 237',
    },
    tkp: {
        label: 'TKP',
        dataKey: 'tkp',
        passingKey: 'passing_tkp',
        color: '13, 148, 136',
    },
};

function chartWrappers(root = document) {
    if (root instanceof Element && root.matches('[data-score-trend-chart]')) {
        return [root];
    }

    return [...root.querySelectorAll('[data-score-trend-chart]')];
}

function readChartPayload(wrapper) {
    const script = wrapper.querySelector('[data-score-trend-payload]');

    if (script?.textContent) {
        try {
            return JSON.parse(script.textContent);
        } catch {
            return {};
        }
    }

    try {
        return JSON.parse(wrapper.dataset.chart ?? '{}');
    } catch {
        return {};
    }
}

function destroyChartOnCanvas(canvas) {
    const tracked = charts.get(canvas);
    const registered = typeof Chart.getChart === 'function' ? Chart.getChart(canvas) : null;
    const existing = tracked ?? registered;

    if (existing) {
        existing.destroy();
    }

    charts.delete(canvas);
}

function buildDatasets(payload, metricKey) {
    const metric = METRICS[metricKey] ?? METRICS.total;
    const values = payload[metric.dataKey] ?? [];
    const passing = payload[metric.passingKey] ?? 0;
    const passingLine = payload.labels?.map(() => passing) ?? [];
    const rgb = metric.color;

    return {
        datasets: [
            {
                label: metric.label,
                data: values,
                borderColor: `rgb(${rgb})`,
                backgroundColor: `rgba(${rgb}, 0.1)`,
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 4,
                pointHoverRadius: 7,
                pointBackgroundColor: metricKey === 'total' && payload.passed_flags?.length
                    ? payload.passed_flags.map((passed) => (
                        passed ? 'rgb(16, 185, 129)' : 'rgb(244, 63, 94)'
                    ))
                    : `rgb(${rgb})`,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            },
            {
                label: 'Ambang Lulus',
                data: passingLine,
                borderColor: 'rgba(245, 158, 11, 0.9)',
                borderDash: [6, 4],
                borderWidth: 1.5,
                pointRadius: 0,
                fill: false,
                tension: 0,
            },
        ],
        values,
        passing,
    };
}

function renderScoreTrendChart(wrapper, payload, metricKey = 'total') {
    const canvas = wrapper?.querySelector('canvas');

    if (!canvas || !payload?.labels?.length) {
        return;
    }

    destroyChartOnCanvas(canvas);

    const { datasets, values, passing } = buildDatasets(payload, metricKey);

    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: payload.labels,
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 10 },
                        color: '#64748b',
                    },
                },
                y: {
                    beginAtZero: false,
                    suggestedMin: Math.max(0, Math.min(...values, passing) - 25),
                    suggestedMax: Math.max(...values, passing) + 15,
                    grid: { color: 'rgba(148, 163, 184, 0.2)' },
                    ticks: {
                        font: { size: 11 },
                        color: '#64748b',
                    },
                },
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 16,
                        font: { size: 11, weight: '600' },
                        color: '#475569',
                    },
                },
                tooltip: {
                    callbacks: {
                        afterBody: (items) => {
                            if (metricKey !== 'total') {
                                return '';
                            }

                            const index = items[0]?.dataIndex;

                            if (index === undefined || !payload.passed_flags) {
                                return '';
                            }

                            return payload.passed_flags[index] ? ' ✓ Lulus ambang' : ' ✗ Belum lulus';
                        },
                    },
                },
            },
        },
    });

    charts.set(canvas, chart);
    activeMetric.set(wrapper, metricKey);
}

function setActiveToggle(wrapper, metricKey) {
    wrapper.querySelectorAll('[data-score-metric]').forEach((button) => {
        const isActive = button.dataset.scoreMetric === metricKey;

        button.classList.toggle('bg-primary-600', isActive);
        button.classList.toggle('text-white', isActive);
        button.classList.toggle('shadow-sm', isActive);
        button.classList.toggle('shadow-primary-500/25', isActive);
        button.classList.toggle('bg-slate-100', ! isActive);
        button.classList.toggle('text-slate-600', ! isActive);
        button.classList.toggle('hover:bg-slate-200', ! isActive);
    });
}

function bindMetricToggles(wrapper, payload) {
    const buttons = wrapper.querySelectorAll('[data-score-metric]');

    if (! buttons.length) {
        return;
    }

    buttons.forEach((button) => {
        if (button.dataset.bound === '1') {
            return;
        }

        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const metricKey = button.dataset.scoreMetric ?? 'total';

            setActiveToggle(wrapper, metricKey);
            renderScoreTrendChart(wrapper, payload, metricKey);
        });
    });

    setActiveToggle(wrapper, activeMetric.get(wrapper) ?? 'total');
}

function initStatisticsCharts(root = document) {
    chartWrappers(root).forEach((wrapper) => {
        const payload = readChartPayload(wrapper);
        const metricKey = activeMetric.get(wrapper) ?? 'total';

        bindMetricToggles(wrapper, payload);
        renderScoreTrendChart(wrapper, payload, metricKey);
    });
}

document.addEventListener('DOMContentLoaded', () => initStatisticsCharts());
document.addEventListener('livewire:navigated', () => initStatisticsCharts());

window.initStatisticsCharts = initStatisticsCharts;

export { initStatisticsCharts };
