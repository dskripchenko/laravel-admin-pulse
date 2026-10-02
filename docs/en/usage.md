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

`admin:pulse:aggregate --minutes=5` aggregates the last full window of samples
into `admin_pulse_aggregates`:

| Bucket | Key | Metrics |
|---|---|---|
| `route.percentiles` | the route (`GET api/orders`) | `count`, `errors`, `p50`, `p95`, `p99`, `min`, `max`, `avg` |
| `requests` | `*` | the same metrics over every request of the window |
| `jobs` | `*` | `count`, `failed` |

A request is an error when its status code is 5xx. Aggregating the same window
again replaces its rows, so a retried run does not double the numbers.

`--hours=N` catches up: it aggregates every full window of the last N hours,
not only the last one. Run it after the scheduler has been down, or after
importing samples:

```bash
php artisan admin:pulse:aggregate --hours=24
```

`admin:pulse:rotate` deletes samples and aggregates older than the configured
retention.

## Telemetry dashboard

The plugin registers a `DashboardScreen` with the slug `telemetry`
(`/admin/dashboard/telemetry`) and a menu entry in the "System" group. Every
widget looks at the same window — the last `dashboard.window_hours` (24 by
default). The dashboard's period selector does not change it: samples are kept
for hours, not for the days the selector offers.

| Widget | Type | Source |
|---|---|---|
| Requests in 24 h | stat | samples: the sampled count divided by the request sample rate |
| 5xx error rate | stat | samples; coloured by the indicator thresholds |
| p95 response time | stat | samples; exact percentile over the window |
| Exceptions | stat | samples |
| Requests per minute | line chart: requests, 5xx errors | `requests` aggregates, scaled by the sample rate |
| Response time, ms | line chart: p50, p95 | `requests` aggregates |
| Slowest routes | table: route, samples, avg, p95, max, 5xx | samples |
| Slowest queries | table: query, samples, avg, max | samples |
| Top exceptions | table: exception, samples, last seen | samples |
| Jobs | stacked bar chart: processed, failed | `jobs` aggregates |

How the numbers are computed:

- The charts split the window into `dashboard.buckets` equal buckets (24
  one-hour buckets by default). p50/p95 of a bucket are the count-weighted
  means of the five-minute windows inside it — exact within a window, an
  approximation across windows. A bucket without requests is a gap in the
  latency lines, not a zero.
- The tables run one grouped query each over the window. The p95 of a route
  is exact: it is read by the database at the percentile's rank (the same
  linear interpolation as the aggregator), one small query per listed route.
- Payloads are cached for `dashboard.cache_seconds` (30 by default).

The widget classes live in `Dskripchenko\LaravelAdminPulse\Widgets` and can be
placed on a dashboard of your own:

```php
use Dskripchenko\LaravelAdminPulse\Widgets\ErrorRateWidget;
use Dskripchenko\LaravelAdminPulse\Widgets\ResponseTimeWidget;

public function widgets(): array
{
    return [
        ErrorRateWidget::make(),
        ResponseTimeWidget::make()->size(12),
    ];
}
```

Each one checks `admin.system.pulse.view` itself and sends empty data to a
user without it.

## Top-bar indicator

`PulseStatusIndicator` reports the share of 5xx responses among the sampled
requests of the last `indicator.window_minutes`. Below `indicator.min_requests`
samples, or below `indicator.warning_error_rate`, it is `ok` and the panel
shows nothing; from `warning_error_rate` it is a warning, from
`error_error_rate` an error. A click opens the telemetry dashboard. Users
without `admin.system.pulse.view` never see it.

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

'dashboard' => [
    'enabled' => true,       // register the dashboard and its menu entry
    'window_hours' => 24,
    'buckets' => 24,
    'top' => 10,             // rows per table
    'cache_seconds' => 30,
],

'indicator' => [
    'enabled' => true,
    'window_minutes' => 15,
    'min_requests' => 20,
    'warning_error_rate' => 0.05,
    'error_error_rate' => 0.20,
],
```

The tables and tiles read raw samples, so a `window_hours` longer than
`retention.samples_hours` only shows what is still kept.

## Request samples

The `pulse` middleware keys a request by its method and route
(`GET api/products/{product}`). The values of the parameters listed in
`key_parameters` are filled in, so laravel-api's generic route
`api/{version}/{controller}/{action}` gives one key per endpoint
(`POST api/admin/orders/search`) rather than one for the whole API; any other
parameter stays a template. The sample is written after the response has been
sent, from an application `terminating` callback, so it works for a group run
inside another pipeline too (the admin API).

```php
'key_parameters' => ['version', 'controller', 'action'],
```

## Recording other kinds

The middleware records `request` samples only. Queries, jobs, exceptions and
cache samples are recorded by the host through the `Sampler` service. The
dashboard expects these keys:

| Kind | `key` | `label` | `status_code` |
|---|---|---|---|
| `query` | `Sampler::fingerprintSql($sql)` | — | — |
| `job` | the job class | — | `500` for a failed job, anything below for a processed one |
| `exception` | `Sampler::fingerprintException($e)` | `ClassName: message` (shown in the table) | — |

```php
use Dskripchenko\LaravelAdminPulse\Services\Sampler;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// A service provider's boot()
$sampler = app(Sampler::class);

DB::listen(function (QueryExecuted $query) use ($sampler): void {
    // Recording a sample is a query too: skip the pack's own tables, or every
    // sample records another one (without end at a rate of 1.0).
    if (str_contains($query->sql, 'admin_pulse_')) {
        return;
    }
    if ($sampler->shouldSample('query')) {
        $sampler->record('query', mb_substr($sampler->fingerprintSql($query->sql), 0, 255), (int) $query->time);
    }
});

Event::listen(JobProcessed::class, function (JobProcessed $event) use ($sampler): void {
    if ($sampler->shouldSample('job')) {
        $sampler->record('job', $event->job->resolveName(), 0, statusCode: 200);
    }
});

Event::listen(JobFailed::class, function (JobFailed $event) use ($sampler): void {
    if ($sampler->shouldSample('job')) {
        $sampler->record('job', $event->job->resolveName(), 0, statusCode: 500);
    }
});

// bootstrap/app.php → withExceptions()
$exceptions->report(function (Throwable $e): void {
    $sampler = app(Sampler::class);
    if ($sampler->shouldSample('exception')) {
        $sampler->record(
            'exception',
            $sampler->fingerprintException($e),
            0,
            label: mb_substr(get_class($e).': '.$e->getMessage(), 0, 255),
        );
    }
});
```

`record()` takes an optional `sampledAt` (any `DateTimeInterface`, now by
default) for samples recorded after the fact — an import, a backfill, a
seeder. Aggregate them afterwards with `admin:pulse:aggregate --hours=N`.
