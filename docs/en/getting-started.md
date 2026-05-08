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
php artisan vendor:publish --tag=pulse-config
```

Edit `config/pulse.php`.


## What it adds

`/admin/dashboard/pulse` with widgets:

- Response times (avg, p50, p95, p99) per route
- Slow queries (top N by avg duration)
- Jobs throughput (per minute)
- Top exceptions (count + last seen)

Sampling runs in middleware; aggregates persist hourly via a scheduled
command.

## See also

- [Usage](usage.md)
- [Glossary](https://github.com/dskripchenko/laravel-admin/blob/main/docs/en/glossary.md)
