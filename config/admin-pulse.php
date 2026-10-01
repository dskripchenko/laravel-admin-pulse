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

    /*
    |--------------------------------------------------------------------------
    | Telemetry dashboard
    |--------------------------------------------------------------------------
    | window_hours  — how far back every widget looks. The tables and the KPI
    |                 tiles read raw samples, so a window longer than
    |                 retention.samples_hours shows only what is still kept.
    | buckets       — the number of points on the charts across the window.
    | top           — the number of rows in the "slowest" / "top" tables.
    | cache_seconds — how long a computed widget payload is reused; 0 turns
    |                 the cache off.
    */

    'dashboard' => [
        'enabled' => true,
        'window_hours' => 24,
        'buckets' => 24,
        'top' => 10,
        'cache_seconds' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Top-bar indicator
    |--------------------------------------------------------------------------
    | The share of 5xx responses among the sampled requests of the last
    | window_minutes. Silent below min_requests samples and below the warning
    | threshold; rates are fractions (0.05 = 5%).
    */

    'indicator' => [
        'enabled' => true,
        'window_minutes' => 15,
        'min_requests' => 20,
        'warning_error_rate' => 0.05,
        'error_error_rate' => 0.20,
    ],
];
