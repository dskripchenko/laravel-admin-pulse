# dskripchenko/laravel-admin-pulse

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · **Deutsch** · [中文](../zh/README.md)

Leichtgewichtige Request-Telemetrie für das Admin-Panel: Die Middleware `pulse` speichert Request-Zeiten als Samples in der Datenbank, und das Admin-Panel erhält eine Liste **Telemetry samples** mit Filtern nach Typ, Schlüssel und Zeitraum. Konsolenbefehle aggregieren die Samples zu Perzentilen pro Route und löschen alte Daten. Eigene Implementierung ohne `laravel/pulse`.

Ein Sister-Pack für [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).

## Installation

```bash
composer require dskripchenko/laravel-admin-pulse
php artisan migrate
```

Das Plugin registriert sich automatisch über Laravel Package Discovery. Konfiguration veröffentlichen:

```bash
php artisan vendor:publish --tag=admin-pulse-config
```

## Dokumentation

- [Erste Schritte](../../docs/en/getting-started.md) (en)
- [Verwendung](../../docs/en/usage.md) (en)

## Lizenz

[MIT](../../LICENSE) © Denis Skripchenko
