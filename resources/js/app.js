//
import { initializeTupadUi } from './tupad-ui';
import { initializeExecutiveDashboard } from './executive-dashboard';
import { initializeGeographicMapping } from './geographic-mapping';
import { initializeProjectWorkspace } from './project-workspace';

document.addEventListener('DOMContentLoaded', () => {
    initializeTupadUi();
    initializeExecutiveDashboard();
    initializeGeographicMapping();
    initializeProjectWorkspace();
});
