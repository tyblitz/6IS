-- Migration: Add audit history tables, event type reference table, and accomplishment linking foreign key
-- Database: db_ict_system

-- 1. Reference Table for Calendar Event Types
CREATE TABLE IF NOT EXISTS `tbl_calendar_event_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type_name` VARCHAR(100) NOT NULL,
  `type_code` VARCHAR(20) NOT NULL,
  `color` VARCHAR(20) NULL DEFAULT '#2563EB',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  UNIQUE KEY `uk_type_code` (`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed initial event types: PAS, CONF, VTC
INSERT INTO `tbl_calendar_event_types` (`id`, `type_name`, `type_code`, `color`, `created_at`, `updated_at`)
VALUES 
  (1, 'Public Address System', 'PAS', '#16A34A', NOW(), NOW()),
  (2, 'Conference', 'CONF', '#2563EB', NOW(), NOW()),
  (3, 'Video Teleconference', 'VTC', '#9333EA', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
  `type_name` = VALUES(`type_name`),
  `color` = VALUES(`color`);

-- 2. Rescheduling Audit History Table
CREATE TABLE IF NOT EXISTS `tbl_calendar_event_reschedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `calendar_event_id` INT NOT NULL,
  `previous_start_datetime` DATETIME NULL,
  `previous_end_datetime` DATETIME NULL,
  `new_start_datetime` DATETIME NOT NULL,
  `new_end_datetime` DATETIME NOT NULL,
  `reason` TEXT NULL,
  `changed_by` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`calendar_event_id`) REFERENCES `tbl_calendar_events` (`id`) ON DELETE CASCADE,
  INDEX `idx_reschedule_event` (`calendar_event_id`),
  INDEX `idx_reschedule_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Status Transition History Table
CREATE TABLE IF NOT EXISTS `tbl_calendar_event_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `calendar_event_id` INT NOT NULL,
  `previous_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `reason` TEXT NULL,
  `changed_by` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`calendar_event_id`) REFERENCES `tbl_calendar_events` (`id`) ON DELETE CASCADE,
  INDEX `idx_status_history_event` (`calendar_event_id`),
  INDEX `idx_status_history_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
