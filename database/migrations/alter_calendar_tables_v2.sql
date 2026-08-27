-- Database migration: Enhance tbl_calendar_events for 6IS Operational Activity Calendar

ALTER TABLE `tbl_calendar_events`
  ADD COLUMN `start_datetime` DATETIME NULL AFTER `event_time`,
  ADD COLUMN `end_datetime` DATETIME NULL AFTER `start_datetime`,
  ADD COLUMN `all_day` TINYINT(1) NOT NULL DEFAULT 0 AFTER `end_datetime`,
  ADD COLUMN `location` VARCHAR(255) NULL AFTER `all_day`,
  ADD COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'Scheduled' AFTER `priority`;

-- Populate start_datetime and end_datetime for existing rows
UPDATE `tbl_calendar_events`
SET 
  `start_datetime` = CONCAT(`event_date`, ' ', COALESCE(`event_time`, '09:00:00')),
  `end_datetime` = DATE_ADD(CONCAT(`event_date`, ' ', COALESCE(`event_time`, '09:00:00')), INTERVAL 1 HOUR)
WHERE `start_datetime` IS NULL;

-- Add performance index
ALTER TABLE `tbl_calendar_events`
  ADD INDEX `idx_start_datetime` (`start_datetime`),
  ADD INDEX `idx_end_datetime` (`end_datetime`),
  ADD INDEX `idx_status` (`status`);
