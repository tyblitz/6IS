-- database/migrations/add_role_id_to_users.sql
-- Add role_id foreign key relationship to tbl_users and migrate existing users safely (Phase 2)

-- 1. Safely add role_id column if not already present
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND COLUMN_NAME = 'role_id';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `tbl_users` ADD COLUMN `role_id` INT NULL AFTER `password`, ADD INDEX `idx_user_role_id` (`role_id`)', 
    'SELECT "Column role_id already exists in tbl_users"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Safely add foreign key constraint if not exists
SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_users' AND CONSTRAINT_NAME = 'fk_users_role_id';

SET @sql_fk = IF(@fk_exists = 0,
    'ALTER TABLE `tbl_users` ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `tbl_roles`(`id`) ON DELETE SET NULL',
    'SELECT "Constraint fk_users_role_id already exists"');
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- 3. Safely Map Existing Users to System Roles based on their current role value
UPDATE `tbl_users` u
JOIN `tbl_roles` r ON (
    (u.role = 'Administrator' AND r.name = 'Administrator') OR
    (u.role = 'User' AND r.name = 'User')
)
SET u.role_id = r.id
WHERE u.role_id IS NULL;

-- 4. Ensure any remaining users with NULL role_id get the baseline User role
UPDATE `tbl_users` u
JOIN `tbl_roles` r ON r.name = 'User'
SET u.role_id = r.id, u.role = 'User'
WHERE u.role_id IS NULL;
