<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | License Storage File
    |--------------------------------------------------------------------------
    | The path where the active license file (.lic) is stored.
    */
    'license_file' => storage_path('license/license.lic'),

    /*
    |--------------------------------------------------------------------------
    | RSA Public Key Path
    |--------------------------------------------------------------------------
    | Path to the public key used to verify license signatures.
    */
    'public_key_path' => storage_path('license/license_public.key'),

    /*
    |--------------------------------------------------------------------------
    | Anti-Clock Rollback State File
    |--------------------------------------------------------------------------
    | Encrypted timestamp record to detect system clock rollbacks.
    */
    'state_file' => storage_path('license/.sys_state'),

    /*
    |--------------------------------------------------------------------------
    | Renewal Warning Threshold (Days)
    |--------------------------------------------------------------------------
    | Show warning notification in UI when this many days remain.
    */
    'warning_days' => 15,

    /*
    |--------------------------------------------------------------------------
    | Grace Period (Days)
    |--------------------------------------------------------------------------
    | Extra days allowed after official expiry date before complete lockout.
    | Set to 0 for immediate cutoff.
    */
    'grace_period_days' => 0,

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    | Routes and URL prefixes allowed even when the system license is expired.
    */
    'excluded_routes' => [
        'license.activate',
        'license.submit',
        'license.upload',
        'license.machine-id',
        'up',
    ],
];
