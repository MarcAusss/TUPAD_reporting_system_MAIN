<?php

return [
    'backup' => [
        'directory' => env('TUPAD_BACKUP_DIRECTORY', 'storage/app/backups/database'),
        'retention_days' => (int) env('TUPAD_BACKUP_RETENTION_DAYS', 14),
    ],

    'health' => [
        'minimum_free_mb' => (int) env('TUPAD_HEALTH_MIN_FREE_MB', 1024),
        'report_path' => env('TUPAD_HEALTH_REPORT_PATH', 'storage/app/health/latest.json'),
    ],

    'initial_admin' => [
        'name' => env('TUPAD_INITIAL_ADMIN_NAME'),
        'username' => env('TUPAD_INITIAL_ADMIN_USERNAME'),
        'email' => env('TUPAD_INITIAL_ADMIN_EMAIL'),
        'position' => env('TUPAD_INITIAL_ADMIN_POSITION', 'System Administrator'),
    ],
];
