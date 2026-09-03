-- database/migrations/create_organization_and_offices_tables.sql
-- 6IS Phase 3: Organization & Office Management Architecture

-- 1. Create Core Organization Table
CREATE TABLE IF NOT EXISTS `tbl_organization` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `short_name` VARCHAR(50) NULL,
  `description` TEXT NULL,
  `address` TEXT NULL,
  `contact_number` VARCHAR(50) NULL,
  `email` VARCHAR(100) NULL,
  `logo_path` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_org_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Single Organization (ID = 1) if not already present
INSERT INTO `tbl_organization` (`id`, `name`, `short_name`, `description`, `address`, `contact_number`, `email`, `is_active`)
VALUES (1, '6th Infantry Division', '6ID', 'Primary command organization for 6IS deployment', 'Camp Siongco, Awang, Datu Odin Sinsuat, Maguindanao', '+63 (64) 431-0123', 'contact@6id.mil.ph', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `short_name` = VALUES(`short_name`);

-- 2. Adapt tbl_offices for Phase 3 Core Architecture
-- 2a. Add organization_id
SET @col_org_id = 0;
SELECT COUNT(*) INTO @col_org_id FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'organization_id';
SET @sql_org_id = IF(@col_org_id = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `organization_id` INT NOT NULL DEFAULT 1 AFTER `id`', 'SELECT 1');
PREPARE stmt_org_id FROM @sql_org_id; EXECUTE stmt_org_id; DEALLOCATE PREPARE stmt_org_id;

-- 2b. Add name
SET @col_name = 0;
SELECT COUNT(*) INTO @col_name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'name';
SET @sql_name = IF(@col_name = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `name` VARCHAR(150) NULL AFTER `organization_id`', 'SELECT 1');
PREPARE stmt_name FROM @sql_name; EXECUTE stmt_name; DEALLOCATE PREPARE stmt_name;

-- 2c. Add code
SET @col_code = 0;
SELECT COUNT(*) INTO @col_code FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'code';
SET @sql_code = IF(@col_code = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `code` VARCHAR(50) NULL AFTER `name`', 'SELECT 1');
PREPARE stmt_code FROM @sql_code; EXECUTE stmt_code; DEALLOCATE PREPARE stmt_code;

-- 2d. Add description
SET @col_desc = 0;
SELECT COUNT(*) INTO @col_desc FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'description';
SET @sql_desc = IF(@col_desc = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `description` TEXT NULL AFTER `code`', 'SELECT 1');
PREPARE stmt_desc FROM @sql_desc; EXECUTE stmt_desc; DEALLOCATE PREPARE stmt_desc;

-- 2e. Add address
SET @col_addr = 0;
SELECT COUNT(*) INTO @col_addr FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'address';
SET @sql_addr = IF(@col_addr = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `address` TEXT NULL AFTER `description`', 'SELECT 1');
PREPARE stmt_addr FROM @sql_addr; EXECUTE stmt_addr; DEALLOCATE PREPARE stmt_addr;

-- 2f. Add contact_number
SET @col_contact = 0;
SELECT COUNT(*) INTO @col_contact FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'contact_number';
SET @sql_contact = IF(@col_contact = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `contact_number` VARCHAR(50) NULL AFTER `address`', 'SELECT 1');
PREPARE stmt_contact FROM @sql_contact; EXECUTE stmt_contact; DEALLOCATE PREPARE stmt_contact;

-- 2g. Add email
SET @col_email = 0;
SELECT COUNT(*) INTO @col_email FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND COLUMN_NAME = 'email';
SET @sql_email = IF(@col_email = 0, 'ALTER TABLE `tbl_offices` ADD COLUMN `email` VARCHAR(100) NULL AFTER `contact_number`', 'SELECT 1');
PREPARE stmt_email FROM @sql_email; EXECUTE stmt_email; DEALLOCATE PREPARE stmt_email;

-- 2h. Synchronize name, code, and organization_id from existing office_name and office_code
UPDATE `tbl_offices`
SET `name` = CASE 
    WHEN `name` IS NOT NULL AND `name` != '' THEN `name`
    WHEN `office_name` IS NOT NULL AND `office_name` != '' THEN `office_name`
    ELSE COALESCE(`office_code`, CONCAT('Office-', `id`))
  END,
  `code` = CASE
    WHEN `code` IS NOT NULL AND `code` != '' THEN `code`
    ELSE COALESCE(`office_code`, CONCAT('OFF-', `id`))
  END,
  `organization_id` = 1
WHERE `organization_id` = 0 OR `organization_id` IS NULL OR `code` IS NULL OR `name` IS NULL;

-- 2i. Foreign key fk_offices_organization
SET @fk_org = 0;
SELECT COUNT(*) INTO @fk_org FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND CONSTRAINT_NAME = 'fk_offices_organization';
SET @sql_fk_org = IF(@fk_org = 0, 'ALTER TABLE `tbl_offices` ADD CONSTRAINT `fk_offices_organization` FOREIGN KEY (`organization_id`) REFERENCES `tbl_organization`(`id`)', 'SELECT 1');
PREPARE stmt_fk_org FROM @sql_fk_org; EXECUTE stmt_fk_org; DEALLOCATE PREPARE stmt_fk_org;

-- 2j. Unique key uq_org_office_code
SET @uq_code = 0;
SELECT COUNT(*) INTO @uq_code FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_offices' AND INDEX_NAME = 'uq_org_office_code';
SET @sql_uq_code = IF(@uq_code = 0, 'ALTER TABLE `tbl_offices` ADD UNIQUE KEY `uq_org_office_code` (`organization_id`, `code`)', 'SELECT 1');
PREPARE stmt_uq_code FROM @sql_uq_code; EXECUTE stmt_uq_code; DEALLOCATE PREPARE stmt_uq_code;

-- 3. Adapt tbl_users for User-to-Office Association
-- 3a. Add office_id
SET @col_user_off = 0;
SELECT COUNT(*) INTO @col_user_off FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND COLUMN_NAME = 'office_id';
SET @sql_user_off = IF(@col_user_off = 0, 'ALTER TABLE `tbl_users` ADD COLUMN `office_id` INT NULL AFTER `role_id`', 'SELECT 1');
PREPARE stmt_user_off FROM @sql_user_off; EXECUTE stmt_user_off; DEALLOCATE PREPARE stmt_user_off;

-- 3b. Foreign key fk_users_office
SET @fk_user_off = 0;
SELECT COUNT(*) INTO @fk_user_off FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND CONSTRAINT_NAME = 'fk_users_office';
SET @sql_fk_user_off = IF(@fk_user_off = 0, 'ALTER TABLE `tbl_users` ADD CONSTRAINT `fk_users_office` FOREIGN KEY (`office_id`) REFERENCES `tbl_offices`(`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt_fk_user_off FROM @sql_fk_user_off; EXECUTE stmt_fk_user_off; DEALLOCATE PREPARE stmt_fk_user_off;

-- 3c. Index on office_id
SET @idx_user_off = 0;
SELECT COUNT(*) INTO @idx_user_off FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND INDEX_NAME = 'idx_user_office';
SET @sql_idx_user_off = IF(@idx_user_off = 0, 'ALTER TABLE `tbl_users` ADD INDEX `idx_user_office` (`office_id`)', 'SELECT 1');
PREPARE stmt_idx_user_off FROM @sql_idx_user_off; EXECUTE stmt_idx_user_off; DEALLOCATE PREPARE stmt_idx_user_off;
