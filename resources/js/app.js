import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

import './attachments';

Alpine.start();

// Chart.js is bundled locally (not loaded from a CDN) so dashboards render
// reliably offline / behind firewalls — but it is ~2/3 of the JS bundle and
// only the dashboard/finance pages chart, so it's split into a lazy chunk.
// Chart pages call window.ensureChart() (from a DOMContentLoaded handler, so
// this module has already run) and get a memoized promise that resolves once
// `window.Chart` is available.
window.ensureChart = function () {
    if (!window.__chartReady) {
        window.__chartReady = import('chart.js/auto').then(function (m) {
            window.Chart = m.default;
            return m.default;
        });
    }

    return window.__chartReady;
};
