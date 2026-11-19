<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Laboratory Information System (LIS) Global Settings
    |--------------------------------------------------------------------------
    */

    'enabled_globally' => env('LIS_ENABLED', true),

    'branches' => [
        1 => [
            'enabled' => true,
            'name' => 'Main Hospital Lab',
        ],
    ],

    'tat' => [
        'routine_hours' => 4,
        'stat_hours' => 1,
    ],

    'outsource' => [
        'storage_disk' => 'public',
    ],
];
