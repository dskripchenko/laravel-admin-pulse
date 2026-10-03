# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries for releases published before this file existed were reconstructed from
the tagged commit history.

## [1.6.1] — 2026-10-03

### Added

- `singularLabel()` on the pack's resources ("telemetry sample", with Russian source
  strings and English translations), so a laravel-admin core that supports
  it titles their pages "Create telemetry sample" instead of gluing the plural
  label. An older core ignores the method; the core constraint is unchanged.

## [1.6.0] — 2026-10-02

### Fixed
- The middleware recorded no requests at all. It kept the start time on its
  instance and wrote the sample in `terminate()`, but the kernel calls
  `terminate()` on a fresh instance, which found no start time and returned;
  and a middleware group run inside another pipeline — the admin API runs
  `web` inside laravel-api's version pipeline — never gets `terminate()`
  called. The sample is now written from an application `terminating`
  callback registered in `handle()`.
- Every admin API request had the same key, `POST api/{version}/{controller}/{action}`,
  so the slowest-routes table had one row for the whole API. The values of the
  parameters listed in the new `key_parameters` option (default `version`,
  `controller`, `action`) are filled into the key: `POST api/admin/orders/search`.
  Other parameters stay templates.
- The `DB::listen` recipe in the usage guide recorded its own INSERT into
  `admin_pulse_samples` as a query sample, which at a rate of 1.0 recursed
  without end; it now skips the pack's tables. The Russian usage guide had a
  stray English copy of its last sections.

### Added
- `admin:pulse:aggregate --hours=N` aggregates every full window of the last
  N hours, catching up the windows missed while the scheduler was down and
  samples imported after the fact. Repeating it is safe: a window aggregated
  again replaces its rows.
- `Sampler::record()` takes an optional `sampledAt` for samples recorded after
  the fact (imports, backfills, seeders); it defaults to now.

## [1.5.2] — 2026-10-02

### Fixed
- The telemetry samples table had headers made from the column names
  ("Duration ms", "Status code", "Sampled at") and English kind captions
  ("Request", "Query"…), English in a Russian panel. Every column now has a
  label and the kinds are source strings, translated per request; the kind
  badges show the same captions as the filter.

## [1.5.1] — 2026-10-02

### Fixed
- The permission group was registered as `__('Системные')`, translated once at
  boot: the role matrix showed it in the boot locale whatever the request's
  language, and apart from the "Системные" group of the other packs when those
  register the source string. The group and its label are now passed as source
  strings, which core translates per request. The telemetry menu entry takes
  its group from `PulseSampleResource::$group`, so the dashboard and the
  samples list always share one sidebar group.
- A test still expected a dashboard the user may not open to be in the
  manifest with no widgets; core leaves it out altogether.

## [1.5.0] — 2026-10-02

### Added
- **Telemetry dashboard** (`/admin/dashboard/telemetry`, slug `telemetry`) with a menu entry in the "System" group, built from the core's widget types over the last 24 hours: KPI tiles (estimated requests, 5xx error rate, p95 response time, exceptions), requests and 5xx errors per minute and p50/p95 response time (multi-series line charts), slowest routes (samples, avg, exact p95, max, 5xx), slowest query fingerprints, top exceptions (samples, last seen) and processed/failed jobs (stacked bars).
- The widget classes (`Dskripchenko\LaravelAdminPulse\Widgets\*`) can be placed on other dashboards; each checks `admin.system.pulse.view` itself and sends empty data without it.
- Top-bar status indicator: warning or error when the share of 5xx responses among the requests sampled in the last 15 minutes crosses the configured thresholds; silent below a minimum number of samples and for users without the permission.
- `Telemetry` service: the read side for the dashboard — bucketed time series from the aggregates, grouped queries over samples, exact percentiles computed by the database, results cached for `dashboard.cache_seconds`.
- Aggregator buckets `requests` (all requests of a window: count, errors, p50/p95/p99, min/max/avg) and `jobs` (count, failed); `route.percentiles` rows also carry `errors`.
- Config sections `dashboard` (`enabled`, `window_hours`, `buckets`, `top`, `cache_seconds`) and `indicator` (`enabled`, `window_minutes`, `min_requests`, `warning_error_rate`, `error_error_rate`).
- Documentation of the dashboard and of the sample conventions (query, job and exception keys); Russian getting-started and usage pages.

### Changed
- Requires `dskripchenko/laravel-admin` `^1.33` (multi-series line and stacked bar charts).
- Aggregating a window that was already aggregated replaces its rows instead of adding duplicates.

### Fixed
- The aggregation window is half-open, `[from, to)`, as documented: a sample exactly on the boundary was counted in two adjacent windows.
- The usage docs described a `top_slow_route` bucket that was never written.

## [1.4.0] — 2026-10-01

### Changed
- The plugin version is now read from Composer metadata instead of a hardcoded value.
- Requires `dskripchenko/laravel-admin` `^1.30`.
- User-facing strings (resource label, filter labels, permission group and label) go through `__()`; an English translation ships in `resources/lang/en.json`.

### Fixed
- Documentation referenced a non-existent `pulse-config` publish tag; the tag is `admin-pulse-config`.
- Documentation described dashboard widgets that the package does not provide; it now describes the samples list and the console commands that exist today.
- Usage docs referenced wrong command names and config keys.

### Added
- Weekly scheduled CI run.

## [v1.3.0] - 2026-07-20

### Changed
- Supported versions moved to the canonical matrix: PHP 8.2-8.5 with Laravel 11, 12 and 13.

### Added
- GitHub Actions pipeline covering the whole support matrix.
- Documentation in German, Russian and Chinese alongside the English default.

## [v1.2.0] - 2026-05-01

### Changed
- Version aligned with the admin core release line. No functional changes.

## [v1.0.0] - 2026-05-01

### Added
- First standalone release, extracted from the laravel-admin monorepo.
- Packagist metadata: description, keywords, authors and support links.
