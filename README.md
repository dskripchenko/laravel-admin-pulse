# dskripchenko/laravel-admin-pulse

> 🌐 **English** · [Русский](README.ru.md) · [Deutsch](README.de.md) · [中文](README.zh.md)

Lightweight telemetry: response-times, slow-queries, jobs throughput, top exceptions. Own implementation without laravel/pulse.

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
php artisan vendor:publish --tag=pulse-config
```

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Usage](docs/en/usage.md)

## License

[MIT](LICENSE) © Denis Skripchenko
