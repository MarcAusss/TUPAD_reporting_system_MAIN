//
import { initializeTupadUi } from './tupad-ui';
import { initializeExecutiveDashboard } from './executive-dashboard';
import { initializeGeographicMapping } from './geographic-mapping';

document.addEventListener('DOMContentLoaded', () => {
    initializeTupadUi();
    initializeExecutiveDashboard();
    initializeGeographicMapping();
});
