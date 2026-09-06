<?php

return [
    'document' => [
        'version' => env('TUPAD_REPORT_VERSION', '1.0'),
        'revision' => env('TUPAD_REPORT_REVISION', '0'),
        'classification' => env(
            'TUPAD_REPORT_CLASSIFICATION',
            'Internal Government Report'
        ),
        'reference_prefix' => env(
            'TUPAD_REPORT_REFERENCE_PREFIX',
            'DOLE-RO5-TUPAD'
        ),
        'office' => env(
            'TUPAD_REPORT_OFFICE',
            'Department of Labor and Employment - Regional Office V'
        ),
        'timezone' => env('TUPAD_REPORT_TIMEZONE', 'Asia/Manila'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Official Report Signatories
    |--------------------------------------------------------------------------
    |
    | Names are intentionally blank by default so the application never invents
    | an approving official. Configure the current authorized signatories in the
    | deployment environment. Blank names render as signature lines.
    |
    */
    'signatories' => [
        'prepared_by' => [
            'label' => 'Prepared by',
            'name' => env('TUPAD_REPORT_PREPARED_BY_NAME', ''),
            'position' => env('TUPAD_REPORT_PREPARED_BY_POSITION', ''),
            'office' => env('TUPAD_REPORT_PREPARED_BY_OFFICE', ''),
        ],
        'reviewed_by' => [
            'label' => 'Reviewed by',
            'name' => env('TUPAD_REPORT_REVIEWED_BY_NAME', ''),
            'position' => env('TUPAD_REPORT_REVIEWED_BY_POSITION', ''),
            'office' => env('TUPAD_REPORT_REVIEWED_BY_OFFICE', ''),
        ],
        'approved_by' => [
            'label' => 'Approved by',
            'name' => env('TUPAD_REPORT_APPROVED_BY_NAME', ''),
            'position' => env('TUPAD_REPORT_APPROVED_BY_POSITION', ''),
            'office' => env('TUPAD_REPORT_APPROVED_BY_OFFICE', ''),
        ],
    ],
];
