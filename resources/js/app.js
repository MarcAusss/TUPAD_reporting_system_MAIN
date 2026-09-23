//
import { initializeTupadUi } from './tupad-ui';
import { initializeExecutiveDashboard } from './executive-dashboard';
import { initializeDashboardGeographicAnalytics } from './dashboard-geographic-analytics';
import { initializeGeographicMapping } from './geographic-mapping';
import { initializeProjectWorkspace } from './project-workspace';
import { initializeRealtimeNotifications } from './realtime-notifications';
import { initializeMoneyInputs } from './money-input';

document.addEventListener('DOMContentLoaded', () => {
    initializeTupadUi();
    initializeExecutiveDashboard();
    initializeDashboardGeographicAnalytics();
    initializeGeographicMapping();
    initializeProjectWorkspace();
    initializeRealtimeNotifications();
    initializeMoneyInputs();
});
