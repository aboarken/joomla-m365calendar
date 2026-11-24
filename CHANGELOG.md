# Changelog

All notable changes to this project are documented here. The project uses
[Semantic Versioning](https://semver.org/). Every version that changes the
database schema adds `sql/updates/{mysql,postgresql}/<version>.sql`.

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

[0.1.1]: https://github.com/aboarken/joomla-m365calendar/releases/tag/v0.1.1
[0.1.0]: https://github.com/aboarken/joomla-m365calendar/releases/tag/v0.1.0
