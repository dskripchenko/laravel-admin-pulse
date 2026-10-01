---
title: Usage
locale: en
status: stable
---

# Usage

## Scheduling

```php
// routes/console.php (or your scheduler)
Schedule::command('admin:pulse:aggregate')->everyFiveMinutes();
Schedule::command('admin:pulse:rotate')->daily();
```

`admin:pulse:aggregate --minutes=5` aggregates the last full window of
`request` samples into per-route percentiles (`route.percentiles`) and the
top 10 slowest routes by p95 (`top_slow_route`).

`admin:pulse:rotate` deletes samples and aggregates older than the configured
retention.

## Configuration

```php
// config/admin-pulse.php
'enabled' => env('ADMIN_PULSE_ENABLED', true),

'sample_rate' => [
    'request' => 0.1,   // 10%
    'query' => 0.1,
    'job' => 1.0,
    'exception' => 1.0,
    'cache' => 0.05,
],

'retention' => [
    'samples_hours' => 24,
    'aggregates_days' => 7,
],
```

## Recording other kinds

The middleware records `request` samples only. Queries, jobs, exceptions and
cache samples are recorded by the host through the `Sampler` service:

```php
use Dskripchenko\LaravelAdminPulse\Services\Sampler;

$sampler = app(Sampler::class);

if ($sampler->shouldSample('job')) {
    $sampler->record(kind: 'job', key: $jobClass, durationMs: $ms);
}
```

`Sampler::fingerprintSql()` and `Sampler::fingerprintException()` build stable
keys for queries and exceptions.
