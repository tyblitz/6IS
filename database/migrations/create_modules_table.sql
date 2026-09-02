-- database/migrations/create_modules_table.sql
-- Module Registry Table & Official Modules Seeding for 6IS

CREATE TABLE IF NOT EXISTS `tbl_modules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module_key` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `icon` VARCHAR(100) NULL,
    `route` VARCHAR(255) NULL,
    `is_core` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `version` VARCHAR(30) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent module seeding: do NOT overwrite `is_active` on duplicate key update!
-- This strictly preserves administrator-configured `is_active` state on repeated runs.
INSERT INTO `tbl_modules` (`module_key`, `name`, `description`, `icon`, `route`, `is_core`, `is_active`, `sort_order`, `version`)
VALUES
    ('dashboard', 'Dashboard', 'Operational schedule overview, KPI metrics, and module launcher', 'homeOutline', '/home', 1, 1, 1, '0.1.0'),
    ('inventory', 'Inventory', 'Equipment registry, extensible attributes, status tracking, and JRRS readiness', 'cubeOutline', '/inventory', 0, 1, 10, '0.1.0'),
    ('communications', 'Communications', 'Incoming and outgoing communications log, document attachments, and reporting', 'chatbubbleEllipsesOutline', '/communications', 0, 1, 20, '0.1.0'),
    ('calendar', 'Calendar', 'Operational schedule, monthly grid, day timeline, and activity management', 'calendarOutline', '/calendar', 0, 1, 30, '0.1.0'),
    ('accomplishments', 'Accomplishments', 'Daily office accomplishment recording and consolidated productivity reports', 'clipboardOutline', '/accomplishments', 0, 1, 40, '0.1.0'),
    ('performance', 'Performance', 'Operational performance metrics, targets, and evaluation', 'speedometerOutline', NULL, 0, 0, 50, NULL),
    ('finances', 'Finances', 'Financial disbursements, budgeting, and fund tracking', 'cashOutline', NULL, 0, 0, 60, NULL),
    ('administrator', 'Administrator', 'System administration, user access management, and module activation registry', 'settingsOutline', '/administrator', 1, 1, 99, '0.1.0')
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `icon` = VALUES(`icon`),
    `route` = VALUES(`route`),
    `is_core` = VALUES(`is_core`),
    `sort_order` = VALUES(`sort_order`),
    `version` = VALUES(`version`),
    `updated_at` = NOW();
