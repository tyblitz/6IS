-- Database Migration: Extensible Equipment Architecture for 6IS Inventory Module
-- Migration: alter_inventory_to_extensible_equipment.sql

SET FOREIGN_KEY_CHECKS = 0;

-- 0. Alter tbl_inventory_equipment Table Columns
ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `equipment_type_id` INT NULL AFTER `office_id`;
ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `equipment_subtype_id` INT NULL AFTER `equipment_type_id`;
ALTER TABLE `tbl_inventory_equipment` ADD COLUMN `status_id` INT NULL AFTER `equipment_subtype_id`;

UPDATE `tbl_inventory_equipment` SET `equipment_type_id` = 1 WHERE `equipment_type_id` IS NULL;
UPDATE `tbl_inventory_equipment` SET `equipment_subtype_id` = 1 WHERE `equipment_subtype_id` IS NULL;
UPDATE `tbl_inventory_equipment` SET `status_id` = 1 WHERE `status_id` IS NULL;

-- 1. Create Equipment Types Table
CREATE TABLE IF NOT EXISTS `tbl_inventory_equipment_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Equipment Subtypes Table
CREATE TABLE IF NOT EXISTS `tbl_inventory_equipment_subtypes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_type_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL,
    FOREIGN KEY (`equipment_type_id`) REFERENCES `tbl_inventory_equipment_types`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_type_subtype_code` (`equipment_type_id`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Equipment Statuses Table
CREATE TABLE IF NOT EXISTS `tbl_inventory_equipment_statuses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Equipment-Specific Attribute Definitions Table
CREATE TABLE IF NOT EXISTS `tbl_inventory_attribute_definitions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_subtype_id` INT NOT NULL,
    `attribute_name` VARCHAR(100) NOT NULL,
    `attribute_code` VARCHAR(50) NOT NULL,
    `data_type` ENUM('text', 'number', 'decimal', 'date', 'boolean', 'select') NOT NULL DEFAULT 'text',
    `is_required` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL,
    FOREIGN KEY (`equipment_subtype_id`) REFERENCES `tbl_inventory_equipment_subtypes`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_subtype_attr_code` (`equipment_subtype_id`, `attribute_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create Equipment Attribute Values Table
CREATE TABLE IF NOT EXISTS `tbl_inventory_equipment_attribute_values` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_id` INT NOT NULL,
    `attribute_definition_id` INT NOT NULL,
    `value_text` TEXT NULL,
    `value_number` INT NULL,
    `value_decimal` DECIMAL(12, 2) NULL,
    `value_date` DATE NULL,
    `value_boolean` TINYINT(1) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    FOREIGN KEY (`equipment_id`) REFERENCES `tbl_inventory_equipment`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`attribute_definition_id`) REFERENCES `tbl_inventory_attribute_definitions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_equipment_attribute` (`equipment_id`, `attribute_definition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Seed Initial Reference Data
-- ==========================================

-- Seed Equipment Types
INSERT INTO `tbl_inventory_equipment_types` (`id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ICT', 'ICT', 1, NOW(), NOW()),
(2, 'Communications', 'COMM', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = VALUES(`is_active`);

-- Seed Equipment Subtypes
INSERT INTO `tbl_inventory_equipment_subtypes` (`id`, `equipment_type_id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES
-- ICT Subtypes
(1, 1, 'Desktop', 'DESKTOP', 1, NOW(), NOW()),
(2, 1, 'Printer', 'PRINTER', 1, NOW(), NOW()),
(3, 1, 'AVR', 'AVR', 1, NOW(), NOW()),
(4, 1, 'Projector', 'PROJECTOR', 1, NOW(), NOW()),
(5, 1, 'LED TV', 'LED_TV', 1, NOW(), NOW()),
(6, 1, 'Laptop', 'LAPTOP', 1, NOW(), NOW()),
(7, 1, 'Network Switch', 'NETWORK_SWITCH', 1, NOW(), NOW()),
-- Communications Subtypes
(8, 2, 'Mixer', 'MIXER', 1, NOW(), NOW()),
(9, 2, 'Microphone', 'MICROPHONE', 1, NOW(), NOW()),
(10, 2, 'Speaker', 'SPEAKER', 1, NOW(), NOW()),
(11, 2, 'Public Address System', 'PAS', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `equipment_type_id` = VALUES(`equipment_type_id`), `is_active` = VALUES(`is_active`);

-- Seed Equipment Statuses
INSERT INTO `tbl_inventory_equipment_statuses` (`id`, `name`, `code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Serviceable', 'SERVICEABLE', 1, NOW(), NOW()),
(2, 'For Repair', 'FOR_REPAIR', 1, NOW(), NOW()),
(3, 'For Turn-in', 'FOR_TURN_IN', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `is_active` = VALUES(`is_active`);

-- Seed Attribute Definitions
-- Desktop (Subtype 1)
INSERT INTO `tbl_inventory_attribute_definitions` (`id`, `equipment_subtype_id`, `attribute_name`, `attribute_code`, `data_type`, `is_required`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Processor', 'processor', 'text', 1, 1, 1, NOW(), NOW()),
(2, 1, 'RAM', 'ram', 'text', 1, 2, 1, NOW(), NOW()),
(3, 1, 'Storage', 'storage', 'text', 1, 3, 1, NOW(), NOW()),
(4, 1, 'Operating System', 'operating_system', 'text', 0, 4, 1, NOW(), NOW()),

-- Printer (Subtype 2)
(5, 2, 'Technology', 'technology', 'text', 1, 1, 1, NOW(), NOW()),
(6, 2, 'Color Capability', 'color_capability', 'text', 0, 2, 1, NOW(), NOW()),
(7, 2, 'Network Capable', 'network_capable', 'boolean', 0, 3, 1, NOW(), NOW()),

-- Projector (Subtype 4)
(8, 4, 'Resolution', 'resolution', 'text', 0, 1, 1, NOW(), NOW()),
(9, 4, 'Lumens', 'lumens', 'number', 0, 2, 1, NOW(), NOW()),

-- Mixer (Subtype 8)
(10, 8, 'Channels', 'channels', 'number', 0, 1, 1, NOW(), NOW()),
(11, 8, 'Power Rating', 'power_rating', 'text', 0, 2, 1, NOW(), NOW()),

-- Speaker (Subtype 10)
(12, 10, 'Power Rating', 'power_rating', 'text', 0, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `attribute_name` = VALUES(`attribute_name`), `data_type` = VALUES(`data_type`), `is_required` = VALUES(`is_required`);

SET FOREIGN_KEY_CHECKS = 1;
