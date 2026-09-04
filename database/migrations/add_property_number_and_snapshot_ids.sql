-- database/migrations/add_property_number_and_snapshot_ids.sql
-- 6IS Stage 1 Migration: Inventory Property Number & Historical Snapshot Relational IDs
-- Preserves existing data, ensures idempotency, and enforces zero cross-copy between serial_number and property_number.

-- 1. Safely add property_number to tbl_inventory_equipment
SET @col_prop_eq = 0;
SELECT COUNT(*) INTO @col_prop_eq 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_equipment' 
  AND COLUMN_NAME = 'property_number';

SET @sql_add_prop_eq = IF(@col_prop_eq = 0, 
    'ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `property_number` VARCHAR(100) NULL AFTER `serial_number`', 
    'SELECT 1');
PREPARE stmt_prop_eq FROM @sql_add_prop_eq;
EXECUTE stmt_prop_eq;
DEALLOCATE PREPARE stmt_prop_eq;

-- Add index idx_equipment_property_number on tbl_inventory_equipment
SET @idx_prop_eq = 0;
SELECT COUNT(*) INTO @idx_prop_eq 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_equipment' 
  AND INDEX_NAME = 'idx_equipment_property_number';

SET @sql_idx_prop_eq = IF(@idx_prop_eq = 0, 
    'ALTER TABLE `tbl_inventory_equipment` ADD INDEX `idx_equipment_property_number` (`property_number`)', 
    'SELECT 1');
PREPARE stmt_idx_prop_eq FROM @sql_idx_prop_eq;
EXECUTE stmt_idx_prop_eq;
DEALLOCATE PREPARE stmt_idx_prop_eq;

-- 2. Safely add property_number to tbl_inventory_history
SET @col_prop_hist = 0;
SELECT COUNT(*) INTO @col_prop_hist 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND COLUMN_NAME = 'property_number';

SET @sql_add_prop_hist = IF(@col_prop_hist = 0, 
    'ALTER TABLE `tbl_inventory_history` ADD COLUMN `property_number` VARCHAR(100) NULL AFTER `serial_number`', 
    'SELECT 1');
PREPARE stmt_prop_hist FROM @sql_add_prop_hist;
EXECUTE stmt_prop_hist;
DEALLOCATE PREPARE stmt_prop_hist;

-- Add index idx_history_property_number on tbl_inventory_history
SET @idx_prop_hist = 0;
SELECT COUNT(*) INTO @idx_prop_hist 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND INDEX_NAME = 'idx_history_property_number';

SET @sql_idx_prop_hist = IF(@idx_prop_hist = 0, 
    'ALTER TABLE `tbl_inventory_history` ADD INDEX `idx_history_property_number` (`property_number`)', 
    'SELECT 1');
PREPARE stmt_idx_prop_hist FROM @sql_idx_prop_hist;
EXECUTE stmt_idx_prop_hist;
DEALLOCATE PREPARE stmt_idx_prop_hist;

-- 3. Safely add equipment_type_id to tbl_inventory_history
SET @col_type_hist = 0;
SELECT COUNT(*) INTO @col_type_hist 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND COLUMN_NAME = 'equipment_type_id';

SET @sql_add_type_hist = IF(@col_type_hist = 0, 
    'ALTER TABLE `tbl_inventory_history` ADD COLUMN `equipment_type_id` INT(11) NULL AFTER `office_id`', 
    'SELECT 1');
PREPARE stmt_type_hist FROM @sql_add_type_hist;
EXECUTE stmt_type_hist;
DEALLOCATE PREPARE stmt_type_hist;

-- 4. Safely add equipment_subtype_id to tbl_inventory_history
SET @col_subtype_hist = 0;
SELECT COUNT(*) INTO @col_subtype_hist 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND COLUMN_NAME = 'equipment_subtype_id';

SET @sql_add_subtype_hist = IF(@col_subtype_hist = 0, 
    'ALTER TABLE `tbl_inventory_history` ADD COLUMN `equipment_subtype_id` INT(11) NULL AFTER `equipment_type_id`', 
    'SELECT 1');
PREPARE stmt_subtype_hist FROM @sql_add_subtype_hist;
EXECUTE stmt_subtype_hist;
DEALLOCATE PREPARE stmt_subtype_hist;

-- Add index idx_history_subtype on tbl_inventory_history(equipment_subtype_id)
SET @idx_sub_hist = 0;
SELECT COUNT(*) INTO @idx_sub_hist 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND INDEX_NAME = 'idx_history_subtype';

SET @sql_idx_sub_hist = IF(@idx_sub_hist = 0, 
    'ALTER TABLE `tbl_inventory_history` ADD INDEX `idx_history_subtype` (`equipment_subtype_id`)', 
    'SELECT 1');
PREPARE stmt_idx_sub_hist FROM @sql_idx_sub_hist;
EXECUTE stmt_idx_sub_hist;
DEALLOCATE PREPARE stmt_idx_sub_hist;

-- 5. Safely add status_id to tbl_inventory_history
SET @col_status_hist = 0;
SELECT COUNT(*) INTO @col_status_hist 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'tbl_inventory_history' 
  AND COLUMN_NAME = 'status_id';

SET @sql_add_status_hist = IF(@col_status_hist = 0, 
    'ALTER TABLE `tbl_inventory_history` ADD COLUMN `status_id` INT(11) NULL AFTER `equipment_subtype_id`', 
    'SELECT 1');
PREPARE stmt_status_hist FROM @sql_add_status_hist;
EXECUTE stmt_status_hist;
DEALLOCATE PREPARE stmt_status_hist;
