# Changelog

All notable changes to this project are documented here. The project uses
[Semantic Versioning](https://semver.org/). Every version that changes the
database schema adds `sql/updates/{mysql,postgresql}/<version>.sql`.

## [Unreleased]

### Added
- GitHub Actions workflow: PHPUnit on PHP 8.1–8.5, PHP_CodeSniffer, package build artifact.
- PHPStan (level 6) in CI, analysed against the Joomla 5.2.6 release.

### Fixed
- The connection settings form filter implemented a non-existent interface (`Joomla\CMS\Form\Filter\FormFilterInterface` instead of `Joomla\CMS\Form\FormFilterInterface`), which would cause a fatal error when the class is loaded (saving the component Options). Found by PHPStan.
- The action log helper checks that com_actionlogs provides an MVC factory and an ActionlogModel before using them.

## [1.0.0] - 2026-11-24

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
