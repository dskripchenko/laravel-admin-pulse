# dskripchenko/laravel-admin-pulse

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · **Deutsch** · [中文](../zh/README.md)

Leichtgewichtige Telemetrie für das Admin-Panel: Die Middleware `pulse` speichert Request-Zeiten als Samples in der Datenbank (Datenbankabfragen, Jobs und Exceptions werden über den Service `Sampler` erfasst), und das Admin-Panel erhält ein Dashboard **Telemetry**, einen Fehlerquoten-Indikator in der oberen Leiste und eine Liste **Telemetry samples**. Konsolenbefehle aggregieren die Samples zu Perzentilen pro Zeitfenster und löschen alte Daten. Eigene Implementierung ohne `laravel/pulse`.

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

Die Aggregation in den Scheduler eintragen — die Diagramme des Dashboards basieren darauf:

```php
Schedule::command('admin:pulse:aggregate')->everyFiveMinutes();
Schedule::command('admin:pulse:rotate')->daily();
```

## Telemetry-Dashboard

`/admin/dashboard/telemetry`, Menügruppe „System“, für Benutzer mit der Berechtigung `admin.system.pulse.view`. Aufgebaut aus den eingebauten Widget-Typen des Kerns, Zeitfenster: die letzten 24 Stunden:

- **Kennzahlen** — geschätzte Anzahl Requests, 5xx-Fehlerquote, p95-Antwortzeit, Exceptions.
- **Requests pro Minute** — Requests und 5xx-Fehler (Liniendiagramm).
- **Antwortzeit** — p50 und p95 (Liniendiagramm).
- **Langsamste Routen** — Route, Samples, Durchschnitt, p95, Maximum, 5xx.
- **Langsamste Abfragen** — SQL-Fingerprint, Samples, Durchschnitt, Maximum.
- **Häufigste Exceptions** — Exception, Samples, zuletzt gesehen.
- **Jobs** — verarbeitet und fehlgeschlagen pro Stunde (gestapelte Balken).

Die Diagramme lesen die Aggregate, die Tabellen und Kennzahlen die rohen Samples. Der Indikator in der oberen Leiste wird zur Warnung oder zum Fehler, wenn der Anteil der 5xx-Antworten der letzten 15 Minuten die konfigurierten Schwellen überschreitet. Die Widget-Klassen lassen sich auch auf eigenen Dashboards platzieren.

## Dokumentation

- [Erste Schritte](../../docs/en/getting-started.md) (en)
- [Verwendung](../../docs/en/usage.md) (en)

## Voraussetzungen

PHP 8.2+, Laravel 11–13, `dskripchenko/laravel-admin` ^1.33.

## Lizenz

[MIT](../../LICENSE) © Denis Skripchenko
