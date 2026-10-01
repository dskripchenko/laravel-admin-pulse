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

The sample is written in `terminate()`, after the response has been sent.

## What it adds

- **Telemetry samples** resource in the admin (group "System"): a read-only
  list of raw samples (`admin_pulse_samples`) with columns kind, key, label,
  duration, status code and time, and filters by kind, key (route /
  fingerprint) and period.
- Permission `admin.system.pulse.view`.
- Console commands `admin:pulse:aggregate` (per-route p50/p95/p99 into
  `admin_pulse_aggregates`) and `admin:pulse:rotate` (retention cleanup).

The package does not ship dashboard widgets or charts; aggregates are stored
for your own use.

## See also

- [Usage](usage.md)
- [Glossary](https://github.com/dskripchenko/laravel-admin/blob/main/docs/en/glossary.md)
