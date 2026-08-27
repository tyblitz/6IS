-- Migration: Refine tbl_calendar_events schema by dropping unused fields and adding office_id FK
-- Database: db_ict_system

-- 1. Ensure office_id column exists and drop legacy location column if present
ALTER TABLE `tbl_calendar_events` 
  DROP COLUMN IF EXISTS `description`,
  DROP COLUMN IF EXISTS `event_type`,
  DROP COLUMN IF EXISTS `priority`;

-- Add office_id if not present
SET @dbname = DATABASE();
SET @tablename = "tbl_calendar_events";
SET @columnname = "office_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `tbl_calendar_events` ADD COLUMN `office_id` INT NULL AFTER `all_day`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Drop legacy location column if exists
SET @columnname2 = "location";
SET @preparedStatement2 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname2
  ) > 0,
  "ALTER TABLE `tbl_calendar_events` DROP COLUMN `location`",
  "SELECT 1"
));
PREPARE dropIfExists FROM @preparedStatement2;
EXECUTE dropIfExists;
DEALLOCATE PREPARE dropIfExists;

-- Update null office_id to default office ID 1
UPDATE `tbl_calendar_events` SET `office_id` = 1 WHERE `office_id` IS NULL;
