--
-- com_m365calendar uninstall (MySQL / MariaDB): removes all data of the extension.
--

DROP TABLE IF EXISTS `#__m365calendar_event_categories`;
DROP TABLE IF EXISTS `#__m365calendar_events`;
DROP TABLE IF EXISTS `#__m365calendar_sync`;
DROP TABLE IF EXISTS `#__m365calendar_feed_tokens`;
DROP TABLE IF EXISTS `#__m365calendar_calendars`;

DELETE FROM `#__action_logs_extensions` WHERE `extension` = 'com_m365calendar';
DELETE FROM `#__action_log_config` WHERE `type_alias` = 'com_m365calendar.calendar';
