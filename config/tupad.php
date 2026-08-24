<?php

return [
    // FY2025 PER ADL (Current) uses 4,356 as the target-grant divisor.
    // Keep this configurable because the reference amount can change by year/rate.
    'target_cost_per_beneficiary' => (float) env('TUPAD_TARGET_COST_PER_BENEFICIARY', 4356),

    'provinces' => [
        'Albay',
        'Camarines Norte',
        'Camarines Sur',
        'Catanduanes',
        'Masbate',
        'Sorsogon',
    ],
];
