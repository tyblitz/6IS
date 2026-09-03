-- database/migrations/add_is_system_to_permissions.sql
-- Migration: Add is_system to tbl_permissions and Seed audit.view Permission (Phase 4)

-- 1. Add is_system column with DEFAULT 0 (if not exists)
-- Safe for execution in standard MySQL
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_permissions' 
  AND COLUMN_NAME = 'is_system';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `tbl_permissions` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Mark ONLY official application-defined/seeded permissions as is_system = 1
UPDATE `tbl_permissions`
SET `is_system` = 1
WHERE (`module_key` = 'inventory' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'))
   OR (`module_key` = 'communications' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'))
   OR (`module_key` = 'calendar' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'))
   OR (`module_key` = 'accomplishments' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'))
   OR (`module_key` = 'users' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'))
   OR (`module_key` = 'roles' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'))
   OR (`module_key` = 'modules' AND `permission_key` IN ('view', 'configure'))
   OR (`module_key` = 'organization' AND `permission_key` IN ('view', 'configure'))
   OR (`module_key` = 'offices' AND `permission_key` IN ('view', 'create', 'edit', 'delete', 'configure'));

-- 3. Seed audit.view official system permission
INSERT INTO `tbl_permissions` (`module_key`, `permission_key`, `name`, `description`, `is_active`, `is_system`, `created_at`, `updated_at`)
VALUES ('audit', 'view', 'View Audit Logs', 'View system security and operational audit trails', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `is_system` = 1,
    `updated_at` = NOW();

-- 4. Grant Administrator System Role audit.view Permission
INSERT INTO `tbl_role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `tbl_roles` r
JOIN `tbl_permissions` p ON (p.module_key = 'audit' AND p.permission_key = 'view')
WHERE r.name = 'Administrator'
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);
