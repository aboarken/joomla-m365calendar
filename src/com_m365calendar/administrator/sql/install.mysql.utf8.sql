--
-- com_m365calendar install (MySQL / MariaDB)
--

CREATE TABLE IF NOT EXISTS `#__m365calendar_calendars` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `mailbox` varchar(320) NOT NULL DEFAULT '',
  `graph_calendar_id` varchar(512) NOT NULL DEFAULT '',
  `color` varchar(7) NOT NULL DEFAULT '#0d6efd',
  `allow_feeds` tinyint NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_published_access` (`published`, `access`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__m365calendar_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `calendar_id` int unsigned NOT NULL,
  `graph_id` varchar(1024) NOT NULL DEFAULT '',
  `uid_hash` char(64) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  `start_utc` datetime NOT NULL,
  `end_utc` datetime NOT NULL,
  `all_day` tinyint NOT NULL DEFAULT 0,
  `show_as` varchar(32) NOT NULL DEFAULT '',
  `categories` text NOT NULL,
  `web_link` varchar(2048) NOT NULL DEFAULT '',
  `last_modified_utc` datetime NULL DEFAULT NULL,
  `synced_utc` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_calendar_uid` (`calendar_id`, `uid_hash`),
  KEY `idx_calendar_id` (`calendar_id`),
  KEY `idx_start_utc` (`start_utc`),
  KEY `idx_end_utc` (`end_utc`),
  KEY `idx_subject` (`subject`(191)),
  KEY `idx_location` (`location`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__m365calendar_event_categories` (
  `event_id` int unsigned NOT NULL,
  `category` varchar(191) NOT NULL,
  PRIMARY KEY (`event_id`, `category`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__m365calendar_sync` (
  `calendar_id` int unsigned NOT NULL,
  `last_run` datetime NULL DEFAULT NULL,
  `last_success` datetime NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT '',
  `message` varchar(1024) NOT NULL DEFAULT '',
  `event_count` int unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `lock_owner` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`calendar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Row 0 is the global sync lock.
INSERT IGNORE INTO `#__m365calendar_sync` (`calendar_id`, `status`, `message`) VALUES (0, '', '');

CREATE TABLE IF NOT EXISTS `#__m365calendar_feed_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `created` datetime NOT NULL,
  `last_used` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  UNIQUE KEY `idx_token_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- Let the core "Action Log - Joomla" plugin record calendar create/edit/delete/publish.
INSERT INTO `#__action_logs_extensions` (`extension`)
SELECT 'com_m365calendar' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `#__action_logs_extensions` WHERE `extension` = 'com_m365calendar');

INSERT INTO `#__action_log_config` (`type_title`, `type_alias`, `id_holder`, `title_holder`, `table_name`, `text_prefix`)
SELECT 'calendar', 'com_m365calendar.calendar', 'id', 'title', '#__m365calendar_calendars', 'COM_M365CALENDAR_ACTIONLOG'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `#__action_log_config` WHERE `type_alias` = 'com_m365calendar.calendar');
