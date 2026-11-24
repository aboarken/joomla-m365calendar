--
-- com_m365calendar install (PostgreSQL)
--

CREATE TABLE IF NOT EXISTS "#__m365calendar_calendars" (
  "id" serial NOT NULL,
  "title" varchar(255) DEFAULT '' NOT NULL,
  "mailbox" varchar(320) DEFAULT '' NOT NULL,
  "graph_calendar_id" varchar(512) DEFAULT '' NOT NULL,
  "color" varchar(7) DEFAULT '#0d6efd' NOT NULL,
  "allow_feeds" smallint DEFAULT 0 NOT NULL,
  "published" smallint DEFAULT 1 NOT NULL,
  "access" bigint DEFAULT 1 NOT NULL,
  "ordering" bigint DEFAULT 0 NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "created_by" bigint DEFAULT 0 NOT NULL,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" bigint DEFAULT 0 NOT NULL,
  "checked_out" integer,
  "checked_out_time" timestamp without time zone,
  PRIMARY KEY ("id")
);
CREATE INDEX IF NOT EXISTS "#__m365calendar_calendars_idx_published_access" ON "#__m365calendar_calendars" ("published", "access");
CREATE INDEX IF NOT EXISTS "#__m365calendar_calendars_idx_checkout" ON "#__m365calendar_calendars" ("checked_out");

CREATE TABLE IF NOT EXISTS "#__m365calendar_events" (
  "id" serial NOT NULL,
  "calendar_id" bigint NOT NULL,
  "graph_id" varchar(1024) DEFAULT '' NOT NULL,
  "uid_hash" char(64) NOT NULL,
  "subject" varchar(255) DEFAULT '' NOT NULL,
  "location" varchar(255) DEFAULT '' NOT NULL,
  "start_utc" timestamp without time zone NOT NULL,
  "end_utc" timestamp without time zone NOT NULL,
  "all_day" smallint DEFAULT 0 NOT NULL,
  "show_as" varchar(32) DEFAULT '' NOT NULL,
  "categories" text NOT NULL,
  "web_link" varchar(2048) DEFAULT '' NOT NULL,
  "last_modified_utc" timestamp without time zone,
  "synced_utc" timestamp without time zone NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__m365calendar_events_idx_calendar_uid" UNIQUE ("calendar_id", "uid_hash")
);
CREATE INDEX IF NOT EXISTS "#__m365calendar_events_idx_calendar_id" ON "#__m365calendar_events" ("calendar_id");
CREATE INDEX IF NOT EXISTS "#__m365calendar_events_idx_start_utc" ON "#__m365calendar_events" ("start_utc");
CREATE INDEX IF NOT EXISTS "#__m365calendar_events_idx_end_utc" ON "#__m365calendar_events" ("end_utc");
CREATE INDEX IF NOT EXISTS "#__m365calendar_events_idx_subject" ON "#__m365calendar_events" ("subject");
CREATE INDEX IF NOT EXISTS "#__m365calendar_events_idx_location" ON "#__m365calendar_events" ("location");

CREATE TABLE IF NOT EXISTS "#__m365calendar_event_categories" (
  "event_id" bigint NOT NULL,
  "category" varchar(191) NOT NULL,
  PRIMARY KEY ("event_id", "category")
);
CREATE INDEX IF NOT EXISTS "#__m365calendar_event_categories_idx_category" ON "#__m365calendar_event_categories" ("category");

CREATE TABLE IF NOT EXISTS "#__m365calendar_sync" (
  "calendar_id" bigint NOT NULL,
  "last_run" timestamp without time zone,
  "last_success" timestamp without time zone,
  "status" varchar(20) DEFAULT '' NOT NULL,
  "message" varchar(1024) DEFAULT '' NOT NULL,
  "event_count" bigint DEFAULT 0 NOT NULL,
  "locked_until" timestamp without time zone,
  "lock_owner" varchar(64) DEFAULT '' NOT NULL,
  PRIMARY KEY ("calendar_id")
);

-- Row 0 is the global sync lock.
INSERT INTO "#__m365calendar_sync" ("calendar_id", "status", "message") VALUES (0, '', '') ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS "#__m365calendar_feed_tokens" (
  "id" serial NOT NULL,
  "user_id" bigint NOT NULL,
  "token_hash" char(64) NOT NULL,
  "created" timestamp without time zone NOT NULL,
  "last_used" timestamp without time zone,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__m365calendar_feed_tokens_idx_user_id" UNIQUE ("user_id"),
  CONSTRAINT "#__m365calendar_feed_tokens_idx_token_hash" UNIQUE ("token_hash")
);

-- Let the core "Action Log - Joomla" plugin record calendar create/edit/delete/publish.
INSERT INTO "#__action_logs_extensions" ("extension")
SELECT 'com_m365calendar'
WHERE NOT EXISTS (SELECT 1 FROM "#__action_logs_extensions" WHERE "extension" = 'com_m365calendar');

INSERT INTO "#__action_log_config" ("type_title", "type_alias", "id_holder", "title_holder", "table_name", "text_prefix")
SELECT 'calendar', 'com_m365calendar.calendar', 'id', 'title', '#__m365calendar_calendars', 'COM_M365CALENDAR_ACTIONLOG'
WHERE NOT EXISTS (SELECT 1 FROM "#__action_log_config" WHERE "type_alias" = 'com_m365calendar.calendar');
