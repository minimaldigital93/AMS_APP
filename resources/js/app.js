import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Chart.js is bundled locally (not loaded from a CDN) so dashboards render
// reliably offline / behind firewalls. Exposed globally as `window.Chart`
// for the inline chart scripts on the dashboard pages.
import Chart from 'chart.js/auto';

window.Chart = Chart;
