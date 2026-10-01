# dskripchenko/laravel-admin-pulse

> 🌐 [English](../../README.md) · **Русский** · [Deutsch](../de/README.md) · [中文](../zh/README.md)

Лёгкая телеметрия запросов для `dskripchenko/laravel-admin`: middleware `pulse` пишет время запросов в базу сэмплами, а в админке появляется список **«Сэмплы телеметрии»** с фильтрами по типу, ключу и периоду. Консольные команды сворачивают сэмплы в перцентили по маршрутам и удаляют устаревшие данные. Своя реализация без `laravel/pulse`.

Пакет-компаньон для [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).

## Установка

```bash
composer require dskripchenko/laravel-admin-pulse
php artisan migrate
```

Плагин регистрируется автоматически через Laravel package discovery. Публикация конфига:

```bash
php artisan vendor:publish --tag=admin-pulse-config
```

## Документация

- [Быстрый старт](../../docs/en/getting-started.md) (en)
- [Использование](../../docs/en/usage.md) (en)

## Лицензия

[MIT](../../LICENSE) © Denis Skripchenko
