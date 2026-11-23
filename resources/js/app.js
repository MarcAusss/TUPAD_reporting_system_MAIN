//
import { initializeTupadUi } from './tupad-ui';
import { initializeExecutiveDashboard } from './executive-dashboard';
import { initializeDashboardGeographicAnalytics } from './dashboard-geographic-analytics';
import { initializeDashboardBeneficiaryDistribution } from './dashboard-beneficiary-distribution';
import { initializeGeographicMapping } from './geographic-mapping';
import { initializeProjectWorkspace } from './project-workspace';
import { initializeRealtimeNotifications } from './realtime-notifications';
import { initializeNotificationDropdown } from './notification-dropdown';
import { initializeMoneyInputs } from './money-input';
import { initializeTupadTables } from './tupad-tables';
import { initializeTupadCharts } from './tupad-charts';
import { initializeNavigation } from './navigation';
import { initializeFormWizards } from './form-wizard';
import { initializeChartTooltips } from './chart-tooltips';
import { initializeDashboardFilters } from './dashboard-filters';

document.addEventListener('DOMContentLoaded', () => {
    initializeNavigation();
    initializeTupadUi();
    initializeFormWizards();
    initializeExecutiveDashboard();
    initializeDashboardGeographicAnalytics();
    initializeDashboardBeneficiaryDistribution();
    initializeGeographicMapping();
    initializeProjectWorkspace();
    initializeNotificationDropdown();
    initializeRealtimeNotifications();
    initializeMoneyInputs();
    initializeTupadTables();
    initializeTupadCharts();
    initializeChartTooltips();
    initializeDashboardFilters();
});
