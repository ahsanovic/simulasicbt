/**
 * Chart.js (~60 KB gzipped) is only needed on pages that show a chart
 * (statistik, AI readiness). It used to ship with every page, including the
 * exam login and the exam room; now it is downloaded the first time a chart
 * element is on the page.
 */
const CHART_SELECTOR = '[data-readiness-chart], [data-score-trend-chart]';

let chartModules = null;

function loadCharts() {
    chartModules ??= Promise.all([
        import('./readiness-radar-chart.js'),
        import('./peserta-statistics-charts.js'),
    ]);

    return chartModules;
}

function initReadinessCharts() {
    if (document.querySelector('[data-readiness-chart]')) {
        loadCharts().then(([readiness]) => readiness.initReadinessCharts());
    }
}

function initStatisticsCharts(root = document) {
    const selector = '[data-score-trend-chart]';

    if ((root instanceof Element && root.matches(selector)) || root.querySelector(selector)) {
        loadCharts().then(([, statistics]) => statistics.initStatisticsCharts(root));
    }
}

function initCharts() {
    if (document.querySelector(CHART_SELECTOR)) {
        initReadinessCharts();
        initStatisticsCharts();
    }
}

document.addEventListener('DOMContentLoaded', initCharts);
document.addEventListener('livewire:navigated', initCharts);

document.addEventListener('livewire:init', () => {
    Livewire.on('readiness-chart-updated', ({ stats }) => {
        if (document.querySelector('[data-readiness-chart]')) {
            loadCharts().then(([readiness]) => readiness.updateReadinessChart(stats));
        }
    });

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => initReadinessCharts());
            requestAnimationFrame(() => initStatisticsCharts());
        });
    });
});

// Used by the statistik page (x-init) when the trend chart is re-rendered.
window.initStatisticsCharts = initStatisticsCharts;
