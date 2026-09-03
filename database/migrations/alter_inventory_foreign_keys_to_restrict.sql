-- database/migrations/alter_inventory_foreign_keys_to_restrict.sql
-- 6IS Phase 3 Hardening: Protect Historical & Current Inventory on Office Deletion (ON DELETE RESTRICT)

-- 1. Safely alter tbl_inventory_history foreign key from CASCADE to RESTRICT
SET @fk_hist = NULL;
SELECT CONSTRAINT_NAME INTO @fk_hist 
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND REFERENCED_TABLE_NAME = 'tbl_offices'
LIMIT 1;

SET @sql_drop_hist = IF(@fk_hist IS NOT NULL, CONCAT('ALTER TABLE `tbl_inventory_history` DROP FOREIGN KEY `', @fk_hist, '`'), 'SELECT 1');
PREPARE stmt_drop_hist FROM @sql_drop_hist;
EXECUTE stmt_drop_hist;
DEALLOCATE PREPARE stmt_drop_hist;

SET @fk_hist_exists = 0;
SELECT COUNT(*) INTO @fk_hist_exists
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME = 'fk_inventory_history_office';

SET @sql_add_hist = IF(@fk_hist_exists = 0, 'ALTER TABLE `tbl_inventory_history` ADD CONSTRAINT `fk_inventory_history_office` FOREIGN KEY (`office_id`) REFERENCES `tbl_offices`(`id`) ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt_add_hist FROM @sql_add_hist;
EXECUTE stmt_add_hist;
DEALLOCATE PREPARE stmt_add_hist;

-- 2. Safely alter tbl_inventory_equipment foreign key from CASCADE to RESTRICT
SET @fk_eq = NULL;
SELECT CONSTRAINT_NAME INTO @fk_eq 
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_equipment' 
  AND REFERENCED_TABLE_NAME = 'tbl_offices'
LIMIT 1;

SET @sql_drop_eq = IF(@fk_eq IS NOT NULL, CONCAT('ALTER TABLE `tbl_inventory_equipment` DROP FOREIGN KEY `', @fk_eq, '`'), 'SELECT 1');
PREPARE stmt_drop_eq FROM @sql_drop_eq;
EXECUTE stmt_drop_eq;
DEALLOCATE PREPARE stmt_drop_eq;

SET @fk_eq_exists = 0;
SELECT COUNT(*) INTO @fk_eq_exists
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME = 'fk_inventory_equipment_office';

SET @sql_add_eq = IF(@fk_eq_exists = 0, 'ALTER TABLE `tbl_inventory_equipment` ADD CONSTRAINT `fk_inventory_equipment_office` FOREIGN KEY (`office_id`) REFERENCES `tbl_offices`(`id`) ON DELETE RESTRICT', 'SELECT 1');
PREPARE stmt_add_eq FROM @sql_add_eq;
EXECUTE stmt_add_eq;
DEALLOCATE PREPARE stmt_add_eq;
