# Changelog

All notable changes to this project are documented here. The project uses
[Semantic Versioning](https://semver.org/). Every version that changes the
database schema adds `sql/updates/{mysql,postgresql}/<version>.sql`.

## [0.1.2] - 2026-11-24

Fixes found by installing the package on real Joomla sites (Joomla 5.0.0, 5.2, 5.4
and 6.2; MariaDB and PostgreSQL). **0.1.0 and 0.1.1 could not save calendars or
store synced events; update to this version.**

### Fixed
- Calendars could not be saved: the calendar ID field used Joomla's `regex` rule
  with the wrong attribute (and that rule only exists since Joomla 5.1). It now
  uses the extension's own rule.
- Synced events could not be stored: a bound query variable was reused after the
  database driver had changed its type, which fails under `strict_types`.
- Switching **Allow in feeds** off, or changing a calendar's access level or
  state, did not clear cached feeds (the wrong cache folder was cleaned), so
  a calendar could stay in a feed for up to 15 minutes.
- "Download .ics" links, and all links with SEF switched off, were HTML-escaped
  twice and did not work.
- Calendar create/edit/delete were not recorded in the User Actions Log: the
  component is now added to the log's "Events To Log" on install and when
  updating from 0.1.0/0.1.1, and removed on uninstall.
- A cached public feed could be poisoned through a forged `Host` header; the
  host is now part of the cache key.
- PHP warnings from OpenSSL when a file that is not a certificate or key was
  validated.
- The Options page title was an untranslated language key.

### Changed
- "Test connection" and "Sync now" show the HTTP status, Microsoft's error code
  (e.g. `AADSTS700016`) and the request-id when authentication fails.
- Feed rate limiting uses Joomla's proxy-aware client address (the "Behind Load
  Balancer" setting).
- The personal feed cache key includes the user's access levels, so group
  changes apply at once.
- Clearer wording for the personal link ("fingerprint (hash)", not "encrypted").

### Added
- Integration test (`tests/Integration/`) that installs Joomla and the package
  and tests them on MySQL and PostgreSQL, including upgrade and uninstall; run
  in CI on Joomla 5.0.0, 5.4.11 and 6.2.1.
- Unit tests for certificate/key validation.

## [0.1.1] - 2026-11-24

Published as a normal (latest) release; 0.1.0 was published as a pre-release. The extension code is unchanged from 0.1.0.

### Changed
- Release workflow: releases are published as normal releases marked as latest, not as pre-releases.
- CI: PHPStan also runs against Joomla 5.4.11, the latest 5.x release (alongside 5.2.6 and 6.2.1).

## [0.1.0] - 2026-11-24

First public release. Not yet tested on a live Joomla site.

### Added
- `pkg_m365calendar` installing, in order: `lib_m365graph`, `com_m365calendar`,
  `plg_task_m365calendarsync`; requirement checks (Joomla 5+, PHP 8.1+, openssl),
  task plugin enabled on install, full data removal on uninstall, update server.
- `lib_m365graph`: certificate client assertion (RS256, `x5t#S256`), token request,
  Graph client with paging, bounded `Retry-After` handling and error mapping.
- `com_m365calendar`:
  - Connection options (restricted to `core.admin`) with file path validation.
  - Calendars (CRUD, access level, colour, "Allow in feeds", batch access).
  - Read-only Events view with sync status, "Sync now" and "Test connection".
  - Personal feed links view with revoke.
  - SyncService with a database lock, per-calendar status and logging.
  - One "Calendar" menu item type with list and month display, preset and
    visitor filters, event detail pages, SEF router, JSON-LD.
  - Subscription feeds (public or personal), single-event `.ics` downloads and
    Outlook / Google "Add to my calendar" links.
- `plg_task_m365calendarsync`: "Sync Microsoft 365 calendars" routine (`m365calendar.sync`).
- GitHub Actions: PHPUnit on PHP 8.1–8.5, PHPStan (level 6) against Joomla 5.2.6 and 6.2.1, PHP_CodeSniffer, and a package build.
- Release workflow: pushing a tag `v<version>` builds the package and publishes it as a GitHub release.
- Joomla 6 support for the task plugin's constructor (Joomla 5 still gets the dispatcher it requires).

[0.1.2]: https://github.com/aboarken/joomla-m365calendar/releases/tag/v0.1.2
[0.1.1]: https://github.com/aboarken/joomla-m365calendar/releases/tag/v0.1.1
[0.1.0]: https://github.com/aboarken/joomla-m365calendar/releases/tag/v0.1.0
