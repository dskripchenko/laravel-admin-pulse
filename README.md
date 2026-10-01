# dskripchenko/laravel-admin-pulse

> 🌐 **English** · [Русский](docs/ru/README.md) · [Deutsch](docs/de/README.md) · [中文](docs/zh/README.md)

Lightweight request telemetry for the admin panel: a `pulse` middleware samples
request timings into the database, and the admin gets a **Telemetry samples**
list with filters by kind, key and period. Console commands aggregate samples
into per-route percentiles and rotate old data. Own implementation, no
dependency on `laravel/pulse`.

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

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Usage](docs/en/usage.md)

## License

[MIT](LICENSE) © Denis Skripchenko
