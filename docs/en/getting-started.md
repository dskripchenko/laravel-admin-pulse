---
title: Getting Started
locale: en
status: stable
---

# Getting Started

`dskripchenko/laravel-admin-pulse` is a sister-pack of `dskripchenko/laravel-admin`.
Install once — it auto-registers and surfaces in your admin.

## Install

```bash
composer require dskripchenko/laravel-admin-pulse
php artisan migrate
```

## Configure

```bash
php artisan vendor:publish --tag=admin-pulse-config
```

Edit `config/admin-pulse.php` (sample rates per kind, ignored routes,
retention, SQL fingerprinting).

## Wire the middleware

The package registers a `pulse` middleware alias. Add it to the routes you
want to measure:

```php
Route::middleware('pulse')->group(function () {
    // ...
});
```

The sample is written after the response has been sent. Requests are keyed
by method and route, with laravel-api's endpoint parameters filled in — see
[Usage → Request samples](usage.md#request-samples).

## What it adds

- **Telemetry** dashboard (`/admin/dashboard/telemetry`, menu group "System"):
  KPI tiles, request and job charts, and tables of the slowest routes, the
  slowest queries and the most frequent exceptions over the last 24 hours.
  See [Usage → Telemetry dashboard](usage.md#telemetry-dashboard).
- A top-bar indicator that appears when the share of 5xx responses of the
  last 15 minutes crosses a threshold.
- **Telemetry samples** resource (group "System"): a read-only list of raw
  samples (`admin_pulse_samples`) with columns kind, key, label, duration,
  status code and time, and filters by kind, key (route / fingerprint) and
  period.
- Permission `admin.system.pulse.view` — it gates the dashboard, every widget,
  the indicator and the samples list.
- Console commands `admin:pulse:aggregate` (per-window aggregates into
  `admin_pulse_aggregates`, which the charts are drawn from) and
  `admin:pulse:rotate` (retention cleanup).

Schedule `admin:pulse:aggregate` every five minutes — without it the charts
stay empty (the tables and tiles read raw samples and work regardless).

## See also

- [Usage](usage.md)
- [Glossary](https://github.com/dskripchenko/laravel-admin/blob/main/docs/en/glossary.md)
