# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries for releases published before this file existed were reconstructed from
the tagged commit history.

## [Unreleased]

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
