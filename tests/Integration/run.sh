#!/usr/bin/env bash
#
# Integration test: installs Joomla into a scratch directory, installs the
# built package with Joomla's own installer, runs tests/Integration/smoke.php
# inside that site, checks pages and feeds over HTTP, then uninstalls the
# package and checks that everything is removed.
#
#   DB_TYPE=mysqli|pgsql DB_HOST=127.0.0.1 DB_USER=joomla DB_PASS=joomla DB_NAME=joomla_it \
#   JOOMLA_VERSION=5.4.11 tests/Integration/run.sh
#
# Optional: JOOMLA_ZIP (local Joomla full package instead of downloading),
# WORKDIR (default: a new temp dir), PORT (default 8089), PACKAGE_ZIP,
# UPGRADE_FROM_ZIP (an older package zip to install first, to test upgrading).
#
# The database must be empty; it is filled with a disposable Joomla site.
#
# Copyright (C) 2026 M365 Calendar contributors
# License GNU General Public License version 2 or later; see LICENSE.txt

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
JOOMLA_VERSION="${JOOMLA_VERSION:-5.4.11}"
DB_TYPE="${DB_TYPE:-mysqli}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-joomla}"
DB_PASS="${DB_PASS:-joomla}"
DB_NAME="${DB_NAME:-joomla_it}"
PORT="${PORT:-8089}"
WORKDIR="${WORKDIR:-$(mktemp -d)}"
SITE="$WORKDIR/site"
BASE="http://127.0.0.1:$PORT"
SERVER_PID=""

cleanup() { [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null || true; }
trap cleanup EXIT

fail() { echo "FAILED: $*" >&2; [ -f "$WORKDIR/php.log" ] && tail -20 "$WORKDIR/php.log" >&2; exit 1; }
ok() { echo "  ok  $*"; }

# --- Package ---------------------------------------------------------------
if [ -z "${PACKAGE_ZIP:-}" ]; then
  php "$ROOT/build/build.php" > /dev/null
  VERSION="$(php -r 'echo (string) simplexml_load_file($argv[1])->version;' "$ROOT/src/pkg/pkg_m365calendar.xml")"
  PACKAGE_ZIP="$ROOT/dist/pkg_m365calendar-$VERSION.zip"
fi
# Joomla unpacks next to the zip, so install from a copy in the work dir.
cp "$PACKAGE_ZIP" "$WORKDIR/pkg_m365calendar.zip"

# --- Joomla ----------------------------------------------------------------
if [ -z "${JOOMLA_ZIP:-}" ]; then
  JOOMLA_ZIP="$WORKDIR/joomla.zip"
  curl -fsSL -o "$JOOMLA_ZIP" "https://github.com/joomla/joomla-cms/releases/download/$JOOMLA_VERSION/Joomla_$JOOMLA_VERSION-Stable-Full_Package.zip"
fi

mkdir -p "$SITE"
unzip -q -o "$JOOMLA_ZIP" -d "$SITE"
cd "$SITE"

echo "Joomla $JOOMLA_VERSION on $DB_TYPE"
php installation/joomla.php install --site-name="M365 IT" --admin-user="Admin" --admin-username=admin \
  --admin-password='Integr4tion!Test-pw' --admin-email=admin@example.org \
  --db-type="$DB_TYPE" --db-host="$DB_HOST" --db-user="$DB_USER" --db-pass="$DB_PASS" --db-name="$DB_NAME" \
  --db-prefix=it_ --db-encryption=0 --public-folder="" -n > "$WORKDIR/install.log" 2>&1 || { cat "$WORKDIR/install.log"; fail "Joomla installation"; }
ok "Joomla installed"

# PHP's built-in server needs an explicit site URL; show all PHP errors.
sed -i "s#public \$live_site = '';#public \$live_site = '$BASE';#; s/public \$error_reporting = '[a-z]*'/public \$error_reporting = 'maximum'/" configuration.php
CLI=(php cli/joomla.php "--live-site=$BASE/")

# Optional: install an older release first, so the next step is a real upgrade.
if [ -n "${UPGRADE_FROM_ZIP:-}" ]; then
  cp "$UPGRADE_FROM_ZIP" "$WORKDIR/pkg_old.zip"
  "${CLI[@]}" extension:install --path="$WORKDIR/pkg_old.zip" > "$WORKDIR/ext-old.log" 2>&1 || { cat "$WORKDIR/ext-old.log"; fail "installation of the older package"; }
  grep -q "installed successfully" "$WORKDIR/ext-old.log" || { cat "$WORKDIR/ext-old.log"; fail "installation of the older package"; }
  ok "older package installed first ($(basename "$UPGRADE_FROM_ZIP"))"
fi

"${CLI[@]}" extension:install --path="$WORKDIR/pkg_m365calendar.zip" > "$WORKDIR/ext-install.log" 2>&1 || { cat "$WORKDIR/ext-install.log"; fail "package installation"; }
grep -q "installed successfully" "$WORKDIR/ext-install.log" || { cat "$WORKDIR/ext-install.log"; fail "package installation"; }
ok "package installed by Joomla's installer"

# Installing the same package again is the update path.
"${CLI[@]}" extension:install --path="$WORKDIR/pkg_m365calendar.zip" > "$WORKDIR/ext-update.log" 2>&1 || { cat "$WORKDIR/ext-update.log"; fail "package update"; }
grep -q "installed successfully" "$WORKDIR/ext-update.log" || { cat "$WORKDIR/ext-update.log"; fail "package update"; }
ok "package installed again (update path)"

# --- In-site smoke test ----------------------------------------------------
php "$ROOT/tests/Integration/smoke.php" "$SITE"

# --- HTTP checks -----------------------------------------------------------
php -d display_errors=1 -d error_log="$WORKDIR/php.log" -S "127.0.0.1:$PORT" -t "$SITE" > "$WORKDIR/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 50); do curl -fs -o /dev/null "$BASE/index.php" && break; sleep 0.2; done

echo "HTTP"
get() { curl -s -o "$WORKDIR/body" -D "$WORKDIR/headers" -w '%{http_code}' "$@"; }
expect() { local want="$1" what="$2"; shift 2; local got; got="$(get "$@")"; [ "$got" = "$want" ] || fail "$what: expected HTTP $want, got $got"; ok "$what ($got)"; }
has() { grep -qaF -- "$1" "$WORKDIR/body" || fail "$2"; ok "$2"; }
hasnt() { if grep -qaF -- "$1" "$WORKDIR/body"; then fail "$2"; fi; ok "$2"; }
noerrors() { if grep -qE '(Fatal error|Warning|Notice|Deprecated)</b>:|Uncaught|An error has occurred' "$WORKDIR/body"; then fail "PHP error on $1"; fi; }

expect 200 "calendar page" "$BASE/index.php/smoke-events"
noerrors "calendar page"
has "Board meeting; budget, Q4 (moved)" "list shows a synced event"
has "&lt;script&gt;alert(1)&lt;/script&gt;" "hostile subject is escaped"
hasnt "<script>alert(1)</script>" "hostile subject is not output raw"
hasnt "Staff only event" "guest does not see the Registered calendar"
has 'rel="canonical"' "canonical link"
has 'application/ld+json' "structured data"
has 'media/com_m365calendar/css/site' "component stylesheet loaded through the asset manager"
hasnt '&amp;amp;' "URLs are escaped exactly once"

EVENT_URL="$(grep -oE 'href="[^"]*smoke-events/[0-9]+"' "$WORKDIR/body" | head -1 | cut -d'"' -f2)"
[ -n "$EVENT_URL" ] || fail "no event link on the calendar page"
expect 200 "event page" "$BASE$EVENT_URL"
noerrors "event page"
expect 404 "unknown event" "$BASE/index.php/smoke-events/99999999"

expect 200 "month view" "$BASE/index.php/smoke-events?mode=month"
noerrors "month view"
has "m365calendar-month-grid" "month grid rendered"
expect 200 "search filter" "$BASE/index.php/smoke-events?mode=list&filter_search=budget"
has "Board meeting; budget" "filter finds the event"
hasnt "Holiday" "filter excludes the others"

expect 200 "public feed" "$BASE/index.php/smoke-events/feed.ics"
grep -qi '^content-type: text/calendar; charset=utf-8' "$WORKDIR/headers" || fail "feed content type"
grep -qi '^x-robots-tag: noindex' "$WORKDIR/headers" || fail "feed robots header"
has "BEGIN:VCALENDAR" "feed is an iCalendar file"
has 'SUMMARY:Board meeting\; budget\, Q4 (moved)' "feed text is escaped per RFC 5545"
hasnt "Staff only" "public feed leaves out non-Public calendars"
php -r '$b = file_get_contents($argv[1]); foreach (explode("\r\n", $b) as $l) { if (strlen($l) > 75) { exit(1); } } exit(str_contains(str_replace("\r\n", "", $b), "\n") ? 1 : 0);' "$WORKDIR/body" || fail "feed lines must be CRLF and at most 75 octets"
ok "feed lines are CRLF and at most 75 octets"
ETAG="$(grep -i '^etag:' "$WORKDIR/headers" | cut -d' ' -f2 | tr -d '\r')"
expect 304 "conditional feed request" -H "If-None-Match: $ETAG" "$BASE/index.php/smoke-events/feed.ics"

expect 404 "personal feed without a token" "$BASE/index.php/smoke-personal/feed.ics"
expect 404 "personal feed with an unknown token" "$BASE/index.php/smoke-personal/feed.ics?token=$(printf 'a%.0s' $(seq 1 64))"
ICS_ID="${EVENT_URL##*/}"
expect 200 "single-event .ics" "$BASE/index.php/smoke-events?task=feed.event&format=ics&id=$ICS_ID"
has "BEGIN:VEVENT" "single event exported"
expect 303 "creating a personal link needs a CSRF token" -X POST "$BASE/index.php/smoke-personal?task=feed.token"

expect 200 "administrator login page" "$BASE/administrator/index.php?option=com_m365calendar"
hasnt "Sync now" "backend is not reachable without logging in"

if [ -s "$WORKDIR/php.log" ]; then cat "$WORKDIR/php.log"; fail "PHP errors were logged"; fi
ok "no PHP errors logged"

# --- Scheduler -------------------------------------------------------------
"${CLI[@]}" scheduler:list > "$WORKDIR/scheduler.log" 2>&1 || { cat "$WORKDIR/scheduler.log"; fail "scheduler:list"; }
ok "scheduler CLI runs with the task plugin enabled"

# --- Uninstall -------------------------------------------------------------
kill "$SERVER_PID" 2>/dev/null || true; SERVER_PID=""
PKG_ID="$("${CLI[@]}" extension:list 2>/dev/null | grep 'PKG_M365CALENDAR' | awk '{print $2}')"
[ -n "$PKG_ID" ] || fail "package not listed"
# Answer the confirmation prompt: Joomla 5.0's CLI rejects --no-interaction here.
printf 'yes\n' | "${CLI[@]}" extension:remove "$PKG_ID" > "$WORKDIR/ext-remove.log" 2>&1 || { cat "$WORKDIR/ext-remove.log"; fail "package removal"; }
if "${CLI[@]}" extension:list 2>/dev/null | grep -qi 'm365'; then fail "extensions left after uninstall"; fi
ok "all three extensions removed"
php "$ROOT/tests/Integration/uninstalled.php" "$SITE"

echo
echo "Integration test passed: Joomla $JOOMLA_VERSION, $DB_TYPE."
