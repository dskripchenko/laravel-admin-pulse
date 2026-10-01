# dskripchenko/laravel-admin-pulse

> 🌐 **English** · [Русский](docs/ru/README.md) · [Deutsch](docs/de/README.md) · [中文](docs/zh/README.md)

Lightweight telemetry for the admin panel: a `pulse` middleware samples
request timings into the database (queries, jobs and exceptions are recorded
through the `Sampler` service), and the admin gets a **Telemetry** dashboard,
a top-bar error-rate indicator and a **Telemetry samples** list. Console
commands aggregate samples into per-window percentiles and rotate old data.
Own implementation, no dependency on `laravel/pulse`.

A sister-pack for [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-pulse)](https://packagist.org/packages/dskripchenko/laravel-admin-pulse)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-pulse)](LICENSE)

## Install

```bash
composer require dskripchenko/laravel-admin-pulse
php artisan migrate
```

The plugin auto-registers via Laravel package discovery. To publish the
config:

```bash
php artisan vendor:publish --tag=admin-pulse-config
```

Schedule the aggregation — the dashboard charts are drawn from it:

```php
Schedule::command('admin:pulse:aggregate')->everyFiveMinutes();
Schedule::command('admin:pulse:rotate')->daily();
```

## Telemetry dashboard

`/admin/dashboard/telemetry`, in the "System" menu group, for users with the
`admin.system.pulse.view` permission. Built from the core's widget types, over
the last 24 hours:

- **KPI tiles** — estimated requests, 5xx error rate, p95 response time,
  exceptions.
- **Requests per minute** — requests and 5xx errors (line chart).
- **Response time** — p50 and p95 (line chart).
- **Slowest routes** — route, samples, avg, p95, max, 5xx.
- **Slowest queries** — SQL fingerprint, samples, avg, max.
- **Top exceptions** — exception, samples, last seen.
- **Jobs** — processed and failed per hour (stacked bars).

The top-bar indicator turns into a warning or an error when the share of 5xx
responses of the last 15 minutes crosses the configured thresholds. The widget
classes can also be placed on your own dashboards. Details in
[Usage](docs/en/usage.md).

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Usage](docs/en/usage.md)

## Requirements

PHP 8.2+, Laravel 11–13, `dskripchenko/laravel-admin` ^1.33.

## License

[MIT](LICENSE) © Denis Skripchenko
