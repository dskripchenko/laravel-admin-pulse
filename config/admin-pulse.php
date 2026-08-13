<?php

declare(strict_types=1);

return [
    'enabled' => env('ADMIN_PULSE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Sample rates per kind (0..1)
    |--------------------------------------------------------------------------
    | rate=1.0 writes everything; 0.1 is a 10% sample; 0.0 is off.
    */

    'sample_rate' => [
        'request' => 0.1,
        'query' => 0.1,
        'job' => 1.0,
        'exception' => 1.0,
        'cache' => 0.05,
    ],

    /*
    |--------------------------------------------------------------------------
    | The routes to skip
    |--------------------------------------------------------------------------
    | request->is($pattern) — the paths the middleware does not sample.
    | By default: pulse itself plus the health endpoints.
    */

    'ignore_routes' => [
        'api/admin/system-pulse-samples/*',
        '_debugbar/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | The TTL for the cleanup
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'samples_hours' => 24,
        'aggregates_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL fingerprint
    |--------------------------------------------------------------------------
    */

    'fingerprint' => [
        'sql_strip_values' => true,
    ],
];
