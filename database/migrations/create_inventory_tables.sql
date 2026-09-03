-- Database migration for 6IS Inventory Module Foundation
-- Tables: tbl_inventory_equipment, tbl_inventory_jrrs, tbl_inventory_history

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `tbl_inventory_history`;
DROP TABLE IF EXISTS `tbl_inventory_equipment`;
DROP TABLE IF EXISTS `tbl_inventory_jrrs`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Current Equipment Registry Table
CREATE TABLE `tbl_inventory_equipment` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `office_id` INT NOT NULL,
    `equipment_type` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `serial_number` VARCHAR(100) NULL,
    `date_acquired` DATE NULL,
    `status` ENUM('Serviceable', 'For Repair', 'For Turn-In / Unserviceable') NOT NULL DEFAULT 'Serviceable',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL,
    FOREIGN KEY (`office_id`) REFERENCES `tbl_offices`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Approved Table of Equipment Target (JRRS)
CREATE TABLE `tbl_inventory_jrrs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `equipment_type` VARCHAR(100) NOT NULL UNIQUE,
    `target_quantity` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `created_by` INT NOT NULL DEFAULT 1,
    `modified_by` INT NOT NULL DEFAULT 1,
    `deleted_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Immutable Historical Monthly Inventory Snapshots Table
CREATE TABLE `tbl_inventory_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `year_month` VARCHAR(7) NOT NULL,
    `equipment_id` INT NULL,
    `office_id` INT NOT NULL,
    `equipment_type` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `serial_number` VARCHAR(100) NULL,
    `date_acquired` DATE NULL,
    `status` ENUM('Serviceable', 'For Repair', 'For Turn-In / Unserviceable') NOT NULL,
    `snapshot_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    FOREIGN KEY (`office_id`) REFERENCES `tbl_offices`(`id`) ON DELETE RESTRICT,
    INDEX `idx_history_year_month` (`year_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================
-- Seed Initial Reference & Sample Data
-- ==========================================

-- Seed JRRS Approved Target Quantities
INSERT INTO `tbl_inventory_jrrs` (`id`, `equipment_type`, `target_quantity`, `created_at`, `updated_at`) VALUES
(1, 'Desktop Computer', 25, NOW(), NOW()),
(2, 'Laptop', 15, NOW(), NOW()),
(3, 'Printer', 10, NOW(), NOW()),
(4, 'Public Address System', 5, NOW(), NOW()),
(5, 'Network Switch', 8, NOW(), NOW())
ON DUPLICATE KEY UPDATE `target_quantity` = VALUES(`target_quantity`);

-- Seed Current Equipment Registry (tbl_inventory_equipment)
INSERT INTO `tbl_inventory_equipment` (`id`, `office_id`, `equipment_type`, `description`, `serial_number`, `date_acquired`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Desktop Computer', 'HP EliteDesk 800 G5 i7 16GB', 'SN-HP800-001', '2024-03-15', 'Serviceable', NOW(), NOW()),
(2, 1, 'Desktop Computer', 'Dell OptiPlex 7080 i5 8GB', 'SN-DELL70-002', '2024-05-20', 'Serviceable', NOW(), NOW()),
(3, 1, 'Laptop', 'Lenovo ThinkPad T14 i7 16GB', 'SN-TP14-003', '2025-01-10', 'Serviceable', NOW(), NOW()),
(4, 1, 'Printer', 'HP LaserJet Pro M404dn', 'SN-HPM404-004', '2024-08-12', 'Serviceable', NOW(), NOW()),

(5, 5, 'Public Address System', 'Yamaha StagePas 600BT PA System', 'SN-YAM600-005', '2023-11-05', 'Serviceable', NOW(), NOW()),
(6, 5, 'Public Address System', 'Bose S1 Pro Portable PA', 'SN-BOSE-006', '2024-02-14', 'Serviceable', NOW(), NOW()),
(7, 5, 'Desktop Computer', 'Dell OptiPlex 5090 i5', 'SN-DELL50-007', '2024-06-18', 'For Repair', NOW(), NOW()),
(8, 5, 'Printer', 'Epson EcoTank L3250 All-in-One', 'SN-EPSON-008', '2025-02-01', 'Serviceable', NOW(), NOW()),
(9, 5, 'Network Switch', 'Cisco Catalyst 2960 24-Port', 'SN-CISCO-009', '2023-09-25', 'Serviceable', NOW(), NOW()),

(10, 9, 'Desktop Computer', 'HP ProDesk 400 G7 i5', 'SN-HP400-010', '2024-04-11', 'Serviceable', NOW(), NOW()),
(11, 9, 'Laptop', 'Dell Latitude 5420 i5', 'SN-DELL54-011', '2024-10-05', 'Serviceable', NOW(), NOW()),
(12, 9, 'Laptop', 'Asus ExpertBook B1 i5', 'SN-ASUS-012', '2025-03-20', 'For Repair', NOW(), NOW()),
(13, 9, 'Printer', 'Canon ImageCLASS MF244dw', 'SN-CANON-013', '2023-12-19', 'For Turn-In / Unserviceable', NOW(), NOW()),

(14, 3, 'Desktop Computer', 'Lenovo ThinkCentre M70q i5', 'SN-LENM70-014', '2024-07-22', 'Serviceable', NOW(), NOW()),
(15, 3, 'Laptop', 'HP ProBook 450 G8 i7', 'SN-HP450-015', '2024-11-30', 'Serviceable', NOW(), NOW()),
(16, 3, 'Network Switch', 'Ubiquiti UniFi 24 PoE Switch', 'SN-UBI24-016', '2024-01-15', 'Serviceable', NOW(), NOW()),

(17, 7, 'Desktop Computer', 'Dell OptiPlex 3080 i3', 'SN-DELL30-017', '2023-05-10', 'For Turn-In / Unserviceable', NOW(), NOW()),
(18, 7, 'Laptop', 'Lenovo ThinkPad E14 i5', 'SN-TPE14-018', '2024-09-08', 'Serviceable', NOW(), NOW()),
(19, 7, 'Printer', 'Brother HL-L2375DW Laser', 'SN-BRO-019', '2024-04-03', 'Serviceable', NOW(), NOW()),
(20, 7, 'Public Address System', 'Mipro MA-708 Portable PA System', 'SN-MIPRO-020', '2024-12-01', 'Serviceable', NOW(), NOW())
ON DUPLICATE KEY UPDATE `equipment_type` = VALUES(`equipment_type`);

-- Seed Frozen Immutable Historical Snapshots for June 2026 (2026-06)
INSERT INTO `tbl_inventory_history` (`year_month`, `equipment_id`, `office_id`, `equipment_type`, `description`, `serial_number`, `date_acquired`, `status`, `snapshot_date`, `created_at`, `updated_at`) VALUES
('2026-06', 1, 1, 'Desktop Computer', 'HP EliteDesk 800 G5 i7 16GB', 'SN-HP800-001', '2024-03-15', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 2, 1, 'Desktop Computer', 'Dell OptiPlex 7080 i5 8GB', 'SN-DELL70-002', '2024-05-20', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 3, 1, 'Laptop', 'Lenovo ThinkPad T14 i7 16GB', 'SN-TP14-003', '2025-01-10', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 5, 5, 'Public Address System', 'Yamaha StagePas 600BT PA System', 'SN-YAM600-005', '2023-11-05', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 7, 5, 'Desktop Computer', 'Dell OptiPlex 5090 i5', 'SN-DELL50-007', '2024-06-18', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 10, 9, 'Desktop Computer', 'HP ProDesk 400 G7 i5', 'SN-HP400-010', '2024-04-11', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 11, 9, 'Laptop', 'Dell Latitude 5420 i5', 'SN-DELL54-011', '2024-10-05', 'For Repair', '2026-06-30', NOW(), NOW()),
('2026-06', 14, 3, 'Desktop Computer', 'Lenovo ThinkCentre M70q i5', 'SN-LENM70-014', '2024-07-22', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 16, 3, 'Network Switch', 'Ubiquiti UniFi 24 PoE Switch', 'SN-UBI24-016', '2024-01-15', 'Serviceable', '2026-06-30', NOW(), NOW()),
('2026-06', 17, 7, 'Desktop Computer', 'Dell OptiPlex 3080 i3', 'SN-DELL30-017', '2023-05-10', 'For Repair', '2026-06-30', NOW(), NOW());

-- Seed Frozen Immutable Historical Snapshots for July 2026 (2026-07)
INSERT INTO `tbl_inventory_history` (`year_month`, `equipment_id`, `office_id`, `equipment_type`, `description`, `serial_number`, `date_acquired`, `status`, `snapshot_date`, `created_at`, `updated_at`) VALUES
('2026-07', 1, 1, 'Desktop Computer', 'HP EliteDesk 800 G5 i7 16GB', 'SN-HP800-001', '2024-03-15', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 2, 1, 'Desktop Computer', 'Dell OptiPlex 7080 i5 8GB', 'SN-DELL70-002', '2024-05-20', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 3, 1, 'Laptop', 'Lenovo ThinkPad T14 i7 16GB', 'SN-TP14-003', '2025-01-10', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 4, 1, 'Printer', 'HP LaserJet Pro M404dn', 'SN-HPM404-004', '2024-08-12', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 5, 5, 'Public Address System', 'Yamaha StagePas 600BT PA System', 'SN-YAM600-005', '2023-11-05', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 7, 5, 'Desktop Computer', 'Dell OptiPlex 5090 i5', 'SN-DELL50-007', '2024-06-18', 'For Repair', '2026-07-31', NOW(), NOW()),
('2026-07', 8, 5, 'Printer', 'Epson EcoTank L3250 All-in-One', 'SN-EPSON-008', '2025-02-01', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 10, 9, 'Desktop Computer', 'HP ProDesk 400 G7 i5', 'SN-HP400-010', '2024-04-11', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 11, 9, 'Laptop', 'Dell Latitude 5420 i5', 'SN-DELL54-011', '2024-10-05', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 13, 9, 'Printer', 'Canon ImageCLASS MF244dw', 'SN-CANON-013', '2023-12-19', 'For Repair', '2026-07-31', NOW(), NOW()),
('2026-07', 14, 3, 'Desktop Computer', 'Lenovo ThinkCentre M70q i5', 'SN-LENM70-014', '2024-07-22', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 15, 3, 'Laptop', 'HP ProBook 450 G8 i7', 'SN-HP450-015', '2024-11-30', 'Serviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 17, 7, 'Desktop Computer', 'Dell OptiPlex 3080 i3', 'SN-DELL30-017', '2023-05-10', 'For Turn-In / Unserviceable', '2026-07-31', NOW(), NOW()),
('2026-07', 19, 7, 'Printer', 'Brother HL-L2375DW Laser', 'SN-BRO-019', '2024-04-03', 'Serviceable', '2026-07-31', NOW(), NOW());
