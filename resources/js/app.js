//
import { initializeTupadUi } from './tupad-ui';
import { initializeExecutiveDashboard } from './executive-dashboard';
import { initializeDashboardGeographicAnalytics } from './dashboard-geographic-analytics';
import { initializeGeographicMapping } from './geographic-mapping';
import { initializeProjectWorkspace } from './project-workspace';
import { initializeRealtimeNotifications } from './realtime-notifications';
import { initializeNotificationDropdown } from './notification-dropdown';
import { initializeMoneyInputs } from './money-input';
import { initializeTupadTables } from './tupad-tables';
import { initializeTupadCharts } from './tupad-charts';
import { initializeNavigation } from './navigation';

document.addEventListener('DOMContentLoaded', () => {
    initializeNavigation();
    initializeTupadUi();
    initializeExecutiveDashboard();
    initializeDashboardGeographicAnalytics();
    initializeGeographicMapping();
    initializeProjectWorkspace();
    initializeNotificationDropdown();
    initializeRealtimeNotifications();
    initializeMoneyInputs();
    initializeTupadTables();
    initializeTupadCharts();
});
