# Microsoft 365 Calendar for Joomla (`pkg_m365calendar`)

Shows Microsoft 365 (Exchange Online) calendar events on a Joomla 5 or 6 site through **one menu item type, "Calendar"**, with list and month views, fixed filters per menu item, visitor filters, event pages, calendar subscription feeds and "Add to my calendar" links.

Events are copied **read-only** from Microsoft Graph into local tables by a scheduled task (or the "Sync now" button). Site pages never call Microsoft 365: all listing and filtering is done with SQL on the local tables.

| Extension | Type | What it does |
|---|---|---|
| `lib_m365graph` | Library (`M365Graph\`) | Entra certificate client assertion, token request, Graph HTTP client (paging, `Retry-After`, error mapping). No settings of its own. |
| `com_m365calendar` | Component (`Joomla\Component\M365calendar`) | Connection options, calendars, events, feed links, the `SyncService`, the "Calendar" menu item, router, feeds. |
| `plg_task_m365calendarsync` | Task plugin | The "Sync Microsoft 365 calendars" task routine (`m365calendar.sync`). It only calls the component's `SyncService`. |

---

## Contents

1. [Requirements](#requirements)
2. [Installing](#installing)
3. [Microsoft Entra setup (separate app registration)](#microsoft-entra-setup-separate-app-registration)
4. [Certificate](#certificate)
5. [Limit access to specific mailboxes (Exchange Online RBAC for Applications)](#limit-access-to-specific-mailboxes-exchange-online-rbac-for-applications)
6. [Configure the component](#configure-the-component)
7. [Scheduled sync](#scheduled-sync)
8. [Calendar menu items](#calendar-menu-items)
9. [Calendar subscription feeds](#calendar-subscription-feeds)
10. [Bootstrap and jQuery](#bootstrap-and-jquery)
11. [Template overrides](#template-overrides)
12. [Security notes](#security-notes)
13. [Privacy](#privacy)
14. [Troubleshooting](#troubleshooting)
15. [Development](#development)
16. [Implementation notes](#implementation-notes)

---

## Requirements

- Joomla 5.0 or later, or Joomla 6 (no Backward Compatibility plugin needed)
- PHP 8.1 or later with the `openssl` extension (checked by the installer)
- MySQL 8 / MariaDB 10.4+ (utf8mb4) or PostgreSQL 12+
- Outgoing HTTPS from the server to `login.microsoftonline.com` and `graph.microsoft.com` (Joomla's proxy settings in Global Configuration are used)
- A Microsoft 365 tenant where you can create an app registration and run Exchange Online PowerShell

No third-party Composer packages are shipped.

## Installing

1. Build the package (see [Development](#development)) or download `pkg_m365calendar-x.y.z.zip` from the releases.
2. **System → Install → Extensions**, upload the zip.

The package installs the library, the component and the task plugin, in that order, and enables the task plugin. Updates are offered through Joomla's update system (update server: `updates/pkg_m365calendar.xml`).

**Uninstalling** the package (System → Manage → Extensions → *Microsoft 365 Calendar* package → Uninstall) removes all three extensions, all `#__m365calendar_*` tables (calendars, events, sync status, feed tokens), the extension's scheduled tasks and its action-log registration. The child extensions cannot be uninstalled on their own.

## Microsoft Entra setup (separate app registration)

> **This extension needs its OWN Entra app registration and its own certificate.** Do not reuse an app registration or certificate of any other extension, script or service. That way you can see, scope, rotate and revoke this extension's access on its own.

1. Entra admin center → **Identity → Applications → App registrations → New registration**.
   - Name: e.g. `Joomla – Microsoft 365 Calendar (www.example.org)`
   - Supported account types: *Accounts in this organizational directory only*
   - Redirect URI: none (app-only, client credentials flow)
2. Note the **Application (client) ID** and **Directory (tenant) ID** (Overview page).
3. **Certificates & secrets → Certificates → Upload certificate**: upload the `.cer` file from [Certificate](#certificate). Do **not** create a client secret; the extension does not support secrets.
4. **API permissions:** the extension needs only **`Calendars.Read`** (read-only), and it should be granted **only for specific mailboxes** through Exchange Online RBAC (next section), not tenant-wide.
   **Remove any tenant-wide Microsoft Graph `Calendars.*` application permission** (and its admin consent) from this app registration and from **Enterprise applications → (this app) → Permissions**. A tenant-wide `Calendars.Read` consent gives access to *every* mailbox and is added to the RBAC scope, not limited by it.
5. Open **Enterprise applications → (this app)** and note its **Object ID**. This is the service principal's Object ID, which is different from the app registration's Object ID; RBAC needs this one.

## Certificate

`bin/generate-cert.sh` is part of the source repository only and is never installed on the site. It creates a 3072-bit RSA key and a self-signed certificate with `openssl`:

```bash
bin/generate-cert.sh /secure/place/m365calendar-cert 730
```

It writes:

| File | Use |
|---|---|
| `m365calendar.key` | Private key (PEM, mode 600). Store it **outside the Joomla folder**, readable only by the web server user. |
| `m365calendar.pem` | Certificate (PEM). Put its path in *Certificate path*. |
| `m365calendar.cer` | Certificate (DER). Upload it to Entra. |

and prints the SHA-256 thumbprint (plus SHA-1 as shown in Entra, and the `x5t#S256` value used in the JWT header). Copy the key and certificate to the server, for example:

```bash
sudo install -d -m 750 -o root -g www-data /etc/joomla-m365calendar
```

```bash
sudo install -m 640 -o root -g www-data m365calendar.key m365calendar.pem /etc/joomla-m365calendar/
```

Only the **file paths** are stored in Joomla. The extension refuses files that do not exist, cannot be read, or are **inside `JPATH_ROOT`**. Renew the certificate before it expires: upload the new `.cer`, change the paths, then delete the old certificate in Entra.

## Limit access to specific mailboxes (Exchange Online RBAC for Applications)

Run in Exchange Online PowerShell (`Install-Module ExchangeOnlineManagement`) as an Exchange administrator. Replace the placeholders:

- `<AppId>`: Application (client) ID
- `<ObjectId>`: **Object ID of the Enterprise Application** (service principal), see step 5 above

```powershell
Connect-ExchangeOnline
```

1. Register the service principal in Exchange Online:

```powershell
New-ServicePrincipal -AppId <AppId> -ObjectId <ObjectId> -DisplayName "Joomla M365 Calendar"
```

2. Create a management scope with the mailboxes the site may read. Either list them:

```powershell
New-ManagementScope -Name "Joomla M365 Calendar mailboxes" -RecipientRestrictionFilter "PrimarySmtpAddress -eq 'board@contoso.com' -or PrimarySmtpAddress -eq 'training@contoso.com'"
```

or use a mail-enabled security group (members can then be managed without PowerShell):

```powershell
New-ManagementScope -Name "Joomla M365 Calendar mailboxes" -RecipientRestrictionFilter "MemberOfGroup -eq 'CN=Joomla Calendars,OU=contoso.onmicrosoft.com,OU=Microsoft Exchange Hosted Organizations,DC=EURPR01A001,DC=PROD,DC=OUTLOOK,DC=COM'"
```

(Get the group's `DistinguishedName` with `Get-Group "Joomla Calendars" | Select DistinguishedName`.)

3. Assign the read-only calendar role, limited to that scope:

```powershell
New-ManagementRoleAssignment -App <ObjectId> -Role "Application Calendars.Read" -CustomResourceScope "Joomla M365 Calendar mailboxes"
```

4. Check the result for a mailbox inside and one outside the scope:

```powershell
Test-ServicePrincipalAuthorization -Identity <AppId> -Resource board@contoso.com
```

```powershell
Test-ServicePrincipalAuthorization -Identity <AppId> -Resource ceo@contoso.com
```

`InScope` must be `True` for the first and `False` for the second. Changes can take a while (up to about two hours) to apply. **Remember to remove any tenant-wide `Calendars.*` consent in Entra** (see the Entra section), otherwise the scope does not restrict anything.

## Configure the component

**Components → Microsoft 365 Calendar → Options**

| Tab | Settings |
|---|---|
| **Connection** (only `core.admin`) | Tenant ID (GUID or domain), Client ID (GUID), certificate path, private key path. Validated on save: GUID/domain format, files exist, are readable, are outside the web root, contain a certificate / an RSA key ≥ 2048 bits, and the key matches the certificate. Users without `core.admin` see a notice instead of the fields and cannot change the values. |
| **Sync** | Days back (default 30), days ahead (default 365), time zone sent to Graph (`Prefer: outlook.timezone`), lock expiry. |
| **Display** | Events per page, date format, first day of the week, show location / categories / calendar name. |
| **Frontend Assets** | Bootstrap and jQuery, see [below](#bootstrap-and-jquery). |
| **Permissions** | `core.admin`, `core.options`, `core.manage`, `core.create`, `core.edit`, `core.edit.state`, `core.delete` and **`m365calendar.sync`** (Sync now / Test connection). |

**Calendars** (Components → Microsoft 365 Calendar → Calendars): title, mailbox (validated e-mail address), calendar ID (optional; empty = the mailbox's default calendar), colour, **Allow in feeds** (off by default), published state, access level. Each calendar is synced separately. Batch can change the access level. Create/edit/delete/publish actions and Options changes are recorded in **Users → User Actions Log**.

**Events**: read-only list of synced events (search, calendar, category, upcoming/past, all-day/timed filters) with the last sync time and status per calendar, and the **Sync now** and **Test connection** buttons (POST with CSRF token, `m365calendar.sync` permission, logged in the User Actions Log).

**Feed Links**: users who created a personal subscription link (no token data is shown). Select users and click **Revoke** to invalidate their links.

## Scheduled sync

1. **System → Scheduled Tasks → New → "Sync Microsoft 365 calendars"**. Choose an interval, e.g. every 15 or 30 minutes.
2. Run the scheduler from the server instead of the "lazy scheduler" (which runs tasks during page visits):
   - **Server cron (recommended)**, as the web server user:

     ```bash
     * * * * * cd /var/www/joomla && php cli/joomla.php scheduler:run >/dev/null 2>&1
     ```

     (`scheduler:run` runs tasks that are due; `php cli/joomla.php scheduler:run --id=<task id>` runs one task now.)
   - **Web cron**: System → Scheduled Tasks → Options → enable *Web Cron*, copy the URL with its key and call it from an external cron service.
   - Then disable the **Lazy Scheduler** in System → Scheduled Tasks → Options.

How a sync works:

- A lock row (row `calendar_id = 0` in `#__m365calendar_sync`, with expiry) makes sure only one sync runs at a time, whether started by the scheduler or by an admin.
- For each published calendar: `GET /v1.0/users/{mailbox}/calendarView` (default calendar) or `/users/{mailbox}/calendars/{id}/calendarView`, with `startDateTime`/`endDateTime` from the sync window, `$select=id,subject,start,end,isAllDay,location,categories,sensitivity,showAs,isCancelled,webLink,lastModifiedDateTime`, `Prefer: outlook.timezone="…"`, following `@odata.nextLink`. Mailbox and calendar ID are URL-encoded.
- Body, attendees and organizer are never requested or stored. Events with a sensitivity other than `normal` and cancelled events are skipped.
- Dates are stored in UTC (all-day events as dates) and shown in the visitor's time zone (or the site's).
- Rows are upserted by Graph event ID + calendar ID; events that are no longer in the window are deleted.
- HTTP 429/503/504: `Retry-After` is honoured (max. 60 s per wait, 3 retries). On failure, the existing data of that calendar is kept, the status is written to `#__m365calendar_sync`, and the error (status code and request-id only) is logged to `administrator/logs/com_m365calendar.php` (categories `com_m365calendar.sync` and `com_m365calendar.feed`).
- The access token only lives in memory during the run. It is never stored, logged or sent to the browser.

## Calendar menu items

Create menu items of type **Microsoft 365 Calendar → Calendar**, as many as needed (e.g. *All Events*, *Board Meetings*, *Training*). All settings are in the **Options** tab, in four groups:

- **Display**: list or month (with a list/month toggle on the page), events per page, sort order, event detail page on/off, link to Outlook on the web, structured data (JSON-LD, on by default).
- **Preset filters** (fixed for the menu item; visitors cannot change them): calendars, categories, location contains, keyword in subject, upcoming/past/all, next/last N days or fixed dates, all-day/timed.
- **Visitor filters**: show the filter bar and each filter (keyword, date range, calendar, category, location, all-day/timed, upcoming/past, sorting). Filters are sticky per menu item. The calendar and category lists only contain values allowed by the preset.
- **Calendar subscription**: feed (No / Everyone / Logged-in users only), "Add to my calendar" button, feed window (default 180 days ahead, 30 days back).

Preset and visitor filters are always combined with AND on the server. A visitor can narrow the result but never widen it; a request for a calendar or category outside the preset is ignored. Calendar access levels are always respected.

Event pages have no menu item type: they open from the Calendar page and use its menu item (SEF URL `/{menu-alias}/{event-id}`), so modules, breadcrumbs and URLs stay correct. The month view is a server-rendered table with previous/next links.

## Calendar subscription feeds

Each Calendar menu item can offer a feed at **`/{menu-alias}/feed.ics`** (also offered as `webcal://…`). It contains the events of that menu item, with its **preset filters** (never visitor filters), inside the feed window, and **only from calendars with "Allow in feeds" switched on**.

| Mode | Link | Which calendars |
|---|---|---|
| **Everyone (public link)** | Same link for everybody, no token | Only calendars whose access level is one of the **guest** view levels (normally *Public*), even if the menu item includes others. The menu item shows a warning when this mode is selected. |
| **Logged-in users only (personal link)** | `…/feed.ics?token=…`, one per user | Only calendars the user's view levels allow. Guests do not see the Subscribe button. |

Personal links:

- The token is 32 random bytes, generated when the user clicks **Create my personal link**. It is shown **once** (copy button included) and only its SHA-256 hash is stored in `#__m365calendar_feed_tokens`. Lookups use the hash and `hash_equals()`.
- **Reset link** (POST with CSRF token) replaces the token; the old link stops working at once. One token per user covers all Calendar pages.
- The token works **only** for feeds: it never logs anyone in. Feeds answer 404 for unknown tokens, blocked or deleted users, and when subscriptions are off.
- Admins revoke links in **Feed Links**. Tokens of deleted users are removed at the next sync (and immediately when such a token is used).

Feeds follow RFC 5545 (escaping, control characters removed, folding at 75 octets, UTC times or `VALUE=DATE`, stable `UID` = hash of the Graph event ID + site domain, `DTSTAMP`, `LAST-MODIFIED`, `X-WR-CALNAME`, `REFRESH-INTERVAL`/`X-PUBLISHED-TTL` = `PT1H`) and contain only subject, start, end, location, categories and the event page URL.

They are cached for 15 minutes with Joomla's Cache API (public feeds per menu item, personal feeds per user and menu item; the cache is cleared after each sync), send `ETag`/`Last-Modified` and answer conditional requests with 304, are rate-limited per IP address and per token, and send `X-Robots-Tag: noindex, nofollow`. Feed URLs and tokens are never logged; feed errors log only the menu item ID and user ID.

**How often calendar apps refresh subscriptions** (`REFRESH-INTERVAL` is only a hint):

| App | Typical refresh |
|---|---|
| Outlook on the web / new Outlook / Outlook.com | every few hours (roughly 3–6 h; can be longer) |
| Outlook for Windows (classic, "Internet calendars") | follows the publisher's hint where supported, otherwise on its send/receive schedule |
| Google Calendar ("From URL") | every 12–24 hours; cannot be refreshed manually |
| Apple Calendar (macOS/iOS) | configurable per subscription (every 5 min to weekly) |

For changes that must show up immediately, use the "Add to my calendar" button or the site itself.

**Add to my calendar** (event page and list items): *Outlook* (Outlook on the web deeplink), *Google Calendar* (template link) and *Download .ics* (single event, for Outlook desktop and Apple Calendar). Links are built on the server with URL-encoded values; external links open with `target="_blank" rel="noopener noreferrer"`.

## Bootstrap and jQuery

The extension never bundles, copies or loads Bootstrap or jQuery from a CDN. Its markup uses standard Bootstrap 5 classes (`card`, `form-control`, `form-select`, `btn`, `badge`, `pagination`, `table`, `visually-hidden`, grid) so it takes the template's look; its own CSS is small (month grid, calendar colours) and scoped under `.m365calendar`.

Options → Frontend Assets:

- **Bootstrap**: *Use template* (default, load nothing), *Joomla core* (`$wa->useStyle('bootstrap.css')`; no Bootstrap JavaScript is needed because menus use native `<details>`), *None*.
- **jQuery**: *Use template* (default) or *Joomla core* (loads Joomla's `jquery` + `jquery-noconflict` assets, only useful for template overrides that need jQuery). The extension's own script is vanilla JavaScript.

All assets are registered in `media/com_m365calendar/joomla.asset.json` (`m365calendar.site`, `m365calendar.subscribe`) and loaded only on this component's pages through the Web Asset Manager. Scripts are ES modules with `defer`; there are no inline scripts or event handlers (configuration via `addScriptOptions()`, strings via `Text::script()`), so the pages work with a strict Content Security Policy. Calendar colours and JSON-LD are added through the Web Asset Manager as inline style / `application/ld+json` blocks, which Joomla's CSP plugin can give a nonce.

## Template overrides

Copy files to your template and edit them there:

| Original | Override |
|---|---|
| `components/com_m365calendar/tmpl/calendar/default.php` (+ `default_list.php`, `default_month.php`) | `templates/YOUR_TEMPLATE/html/com_m365calendar/calendar/…` |
| `components/com_m365calendar/tmpl/event/default.php` | `templates/YOUR_TEMPLATE/html/com_m365calendar/event/default.php` |
| `components/com_m365calendar/layouts/m365calendar/event_card.php` | `templates/YOUR_TEMPLATE/html/layouts/com_m365calendar/m365calendar/event_card.php` |
| `…/layouts/m365calendar/filter_bar.php`, `add_to_calendar.php`, `subscribe.php` | `templates/YOUR_TEMPLATE/html/layouts/com_m365calendar/m365calendar/…` |

(Or use System → Site Templates → *your template* → Create Overrides.) Keep `$this->escape()` on every value: event data is untrusted, anyone can send an invitation to a synced mailbox.

## Security notes

- App-only access with a **certificate**; client secrets are not supported. Only `Calendars.Read`, limited to listed mailboxes with RBAC for Applications. Use a dedicated app registration and certificate.
- The private key must be outside the web root (enforced) and readable only by the web server user. Only paths are stored.
- Access tokens live only in memory during a sync; they are never stored, logged or sent to the browser. Graph `@odata.nextLink` URLs are only followed on `graph.microsoft.com`.
- All event text is treated as untrusted: control characters are stripped on import, all output is escaped, `webLink` must be `https://`, JSON-LD is encoded with `JSON_HEX_*` flags, feeds use RFC 5545 escaping.
- Every state-changing request checks the CSRF token and ACL; all input goes through typed input filters; all SQL uses the query builder with bound parameters.
- Personal feed tokens: random 256-bit, hashed (SHA-256), constant-time comparison, rate-limited, shown once, never grant a login.
- Confidential calendars: leave **Allow in feeds** off (the default). Public feeds additionally only include guest-visible calendars.
- No system plugins and no global event listeners; the task plugin only listens to the scheduler's events.

## Privacy

Only these event fields are stored: subject, start, end, all-day flag, location name, categories, free/busy status, the Outlook web link and the last-modified time, plus the Graph event ID. Event bodies, attendees and organizers are never requested or stored; private, personal and confidential events are skipped. Feed tokens store a user ID, a hash and two dates; IP addresses for rate limiting are only kept as hashed cache keys for a few minutes. **Uninstalling the package removes all of this data.** Mention the synced calendars and the subscription feature in your site's privacy policy.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Authentication failed" | Wrong tenant/client ID, certificate not uploaded to *this* app registration, or certificate expired. Check the `AADSTS` code in the Events view message. |
| "Access denied" (HTTP 403) for a mailbox | Mailbox not in the management scope, role assignment missing, or changes not applied yet. Run `Test-ServicePrincipalAuthorization`. |
| Access to mailboxes outside the scope | A tenant-wide `Calendars.*` consent still exists in Entra. Remove it. |
| "Mailbox or calendar not found" (404) | Typo in the mailbox, or the calendar ID belongs to another mailbox. Leave the calendar ID empty for the default calendar. |
| "File is inside the Joomla folder" | Move the key and certificate outside `JPATH_ROOT`. |
| "Private key cannot be parsed" | The key must be an unencrypted PEM RSA key (`-----BEGIN PRIVATE KEY-----`). Convert with `openssl pkey -in old.key -out new.key`. |
| "Another sync is running" | Wait; a crashed sync's lock expires after the configured lock time. |
| Sync never runs | The task plugin must be enabled, the scheduled task published, and cron / web cron set up (the lazy scheduler needs visitors). |
| Events missing | Outside the sync window, not `normal` sensitivity, cancelled, calendar unpublished, access level, or the menu item's preset filters. |
| Times are shifted | Check the user's and the site's time zone (Global Configuration → Server). |
| Feed returns 404 | Subscription off for the menu item, token reset or revoked, user blocked, or the menu item's access level excludes the user/guests. |
| Feed is empty | No calendar has *Allow in feeds* on, or (public link) none of them has a guest access level. |
| `/feed.ics` gives 404 with SEF off | Use the link shown in the Subscribe panel; it works with and without URL rewriting. |
| Logs | `administrator/logs/com_m365calendar.php` and System → Scheduled Tasks (task log). |

## Development

```text
bin/generate-cert.sh          key pair for Entra (not installed)
build/build.php               builds dist/pkg_m365calendar-<version>.zip
src/pkg/                      package manifest, install script, language
src/lib_m365graph/            library
src/com_m365calendar/         component (administrator/, site/, media/)
src/plg_task_m365calendarsync task plugin
tests/                        PHPUnit tests
updates/                      update server XML
```

Dev tools (not shipped): `composer install`, then

```bash
vendor/bin/phpunit
```

```bash
vendor/bin/phpcs
```

```bash
JOOMLA_ROOT=/path/to/joomla-5 vendor/bin/phpstan analyse
```

GitHub Actions (`.github/workflows/tests.yml`) runs PHPUnit on PHP 8.1–8.5, PHPStan against a Joomla 5 release, and the coding standard check on every push to `main` and every pull request, then builds the package and uploads the zip as a workflow artifact.

Build the installable package:

```bash
php build/build.php
```

The build lints all PHP/XML/INI/JSON files, stamps the version into the manifests, creates the minified CSS/JS, zips the three extensions and wraps them in `dist/pkg_m365calendar-<version>.zip`, then prints its SHA-256 and SHA-384 (put the SHA-384 into `updates/pkg_m365calendar.xml`). `php build/build.php --version=1.0.1` overrides the version.

Versioning follows SemVer; every release adds an entry to `CHANGELOG.md` and, when the schema changes, `sql/updates/mysql/<version>.sql` and `sql/updates/postgresql/<version>.sql`.

## Implementation notes

Where the requirements and Joomla's conventions met, these choices were made:

- **Deleted users' feed tokens**: the requirements forbid system plugins and global event listeners, so there is no `onUserAfterDelete` listener. Instead, a feed request with a deleted user's token returns 404 and deletes the token immediately, and every sync removes tokens whose user no longer exists. Uninstall drops the table.
- **Showing the personal link**: since only the hash is stored, the link cannot be shown again later. It is shown once right after it is created or reset (kept in the session only until then).
- **"Connection" tab restricted to `core.admin`**: Joomla's Options screen has no per-tab permission, so the fields use a custom field (shows a notice instead of the input) and a custom form filter (keeps the stored value) for users without `core.admin`.
- **User Action Logs**: calendar create/edit/delete/publish and Options changes are logged by the core *Action Log – Joomla* plugin (the component registers itself in `#__action_logs_extensions` / `#__action_log_config`); manual sync, connection test and token revocation are logged through `ActionlogModel`.
- **Feed URL**: `/{menu-alias}/feed.ics` is produced by a router rule; with "Add suffix to URLs" on, Joomla adds the `.ics` suffix itself.
- **Microsoft Graph throttling**: 504 is treated like 429/503.
