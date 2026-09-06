<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard Aging Attention Thresholds
    |--------------------------------------------------------------------------
    |
    | These values are operational dashboard indicators only. They do not
    | represent a statutory processing deadline and never trigger workflow
    | transitions. They simply help users prioritize older pending records.
    |
    */
    'attention_after_days' => (int) env('TUPAD_DASHBOARD_ATTENTION_DAYS', 7),
    'critical_after_days' => (int) env('TUPAD_DASHBOARD_CRITICAL_DAYS', 14),
];
