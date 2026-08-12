-- Database migration for 6IS Accomplishment Module
-- Create reference tables: tbl_offices, tbl_users, tbl_accomplishment_categories
-- Create module table: tbl_accomplishments

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS tbl_categories;
DROP TABLE IF EXISTS tbl_accomplishments;
DROP TABLE IF EXISTS tbl_accomplishment_categories;
DROP TABLE IF EXISTS tbl_offices;

CREATE TABLE tbl_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_name VARCHAR(100) NULL DEFAULT '',
    office_code VARCHAR(20) NULL DEFAULT '',
    office_abbv VARCHAR(50) NOT NULL,
    office_category ENUM('Staff', 'Special Staff', 'Group', 'Others') NOT NULL DEFAULT 'Others',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tbl_accomplishment_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(150) NOT NULL,
    category_code VARCHAR(50) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-create tbl_accomplishments adhering strictly to V1 business rules
CREATE TABLE tbl_accomplishments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    category_id INT NOT NULL DEFAULT 1,
    date DATE NOT NULL,
    description TEXT NOT NULL,
    remarks TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    FOREIGN KEY (office_id) REFERENCES tbl_offices(id),
    FOREIGN KEY (category_id) REFERENCES tbl_accomplishment_categories(id),
    INDEX idx_office_id (office_id),
    INDEX idx_category_id (category_id),
    INDEX idx_date (date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Accomplishment Categories
INSERT INTO tbl_accomplishment_categories (id, category_name, category_code, created_at, updated_at) VALUES 
(1, 'Installation of Public Address System (PAS)', 'PAS', NOW(), NOW()),
(2, 'Conducted Repair and Maintenance of ICT Equipment', 'ICT Repair', NOW(), NOW()),
(3, 'Supervised/Assisted TELCO Personnel', 'TELCO', NOW(), NOW()),
(4, 'LED Board Support', 'LED', NOW(), NOW());

-- Seed Offices with blank office_name and exact office_abbv list
INSERT INTO tbl_offices (id, office_name, office_code, office_abbv, created_at, updated_at) VALUES 
(1, '', 'OG1', 'OG1', NOW(), NOW()),
(2, '', 'OG2', 'OG2', NOW(), NOW()),
(3, '', 'OG3', 'OG3', NOW(), NOW()),
(4, '', 'OG4', 'OG4', NOW(), NOW()),
(5, '', 'OG6', 'OG6', NOW(), NOW()),
(6, '', 'OG7', 'OG7', NOW(), NOW()),
(7, '', 'OG8', 'OG8', NOW(), NOW()),
(8, '', 'OG10', 'OG10', NOW(), NOW()),
(9, '', 'ESG', 'ESG', NOW(), NOW()),
(10, '', 'ASPG', 'ASPG', NOW(), NOW()),
(11, '', 'CFMG', 'CFMG', NOW(), NOW()),
(12, '', 'CSMG', 'CSMG', NOW(), NOW()),
(13, '', 'HPMG', 'HPMG', NOW(), NOW()),
(14, '', 'SDO', 'SDO', NOW(), NOW()),
(15, '', 'CMC', 'CMC', NOW(), NOW()),
(16, '', 'ONAF', 'ONAF', NOW(), NOW());

-- Seed Sample Users
INSERT INTO tbl_users (id, username, full_name, password, role, created_at, updated_at) VALUES 
(1, 'admin01', 'John Doe (Administrator)', 'sample_hash', 'admin', NOW(), NOW()),
(2, 'asmith', 'Alice Smith', 'sample_hash', 'user', NOW(), NOW()),
(3, 'bwayne', 'Bruce Wayne', 'sample_hash', 'user', NOW(), NOW())
ON DUPLICATE KEY UPDATE username=VALUES(username);

-- Seed 20 Sample Accomplishments across different categories, offices, and dates
INSERT INTO tbl_accomplishments (id, office_id, category_id, date, description, remarks, created_at, updated_at) VALUES 
(1, 5, 1, CURDATE(), 'Installation and setup of Public Address System for conference hall briefing.', 'PAS tested clear with zero audio distortion.', NOW(), NOW()),
(2, 5, 2, CURDATE(), 'Conducted repair and maintenance of desktop computer units and printer power supplies.', 'Repaired 3 units and replaced power supplies.', NOW(), NOW()),
(3, 9, 3, CURDATE(), 'Supervised PLDT TELCO technicians during fiber line restoration inside camp.', 'Fiber optic line restored successfully.', NOW(), NOW()),
(4, 5, 4, '2026-08-11', 'Provided LED board technical support for parade ground ceremony display.', 'LED display active throughout ceremony.', NOW(), NOW()),
(5, 5, 1, '2026-08-10', 'Setup Public Address System (PAS) for unit anniversary social activity.', 'PAS operated smoothly without issues.', NOW(), NOW()),
(6, 9, 2, '2026-08-09', 'Conducted preventative maintenance on network switch hardware and server racks.', 'All rack fans operational.', NOW(), NOW()),
(7, 5, 3, '2026-08-08', 'Assisted Globe TELCO team in relocating telephone cabling for G6 office expansion.', 'Lines reconnected and verified.', NOW(), NOW()),
(8, 5, 4, '2026-08-07', 'Configured LED board display input settings for video presentation.', 'Resolution calibrated to 1080p.', NOW(), NOW()),
(9, 9, 1, '2026-08-06', 'Deployed wireless PAS audio set for outdoor battalion formation.', 'Audio coverage reached all attendees.', NOW(), NOW()),
(10, 5, 2, '2026-08-05', 'Troubleshot corrupted OS drive and reformatted administrative workstation.', 'Workstation restored to active duty.', NOW(), NOW()),
(11, 5, 3, '2026-08-04', 'Supervised TELCO personnel installing dedicated internet circuit in Datacenter.', 'Speed test verified 500Mbps symmetrical link.', NOW(), NOW()),
(12, 9, 4, '2026-08-03', 'Operated LED board controls during joint command briefing session.', 'Slide transitions executed on schedule.', NOW(), NOW()),
(13, 5, 1, '2026-08-02', 'Mounted permanent PAS horn speakers in main assembly area.', 'Speaker mounting secured.', NOW(), NOW()),
(14, 5, 2, '2026-08-01', 'Replaced failing UPS batteries and serviced uninterruptible power supply units.', 'Backup runtime extended to 45 mins.', NOW(), NOW()),
(15, 9, 3, '2026-07-28', 'Coordinated with TELCO engineers regarding underground cable maintenance.', 'No service interruption reported.', NOW(), NOW()),
(16, 5, 4, '2026-07-25', 'Checked LED board power supply modules and replaced faulty LED module tiles.', 'Display tiles fully functional.', NOW(), NOW()),
(17, 5, 1, '2026-07-20', 'Supported commander conference with dual microphone PAS system.', 'Audio levels balanced.', NOW(), NOW()),
(18, 9, 2, '2026-07-15', 'Diagnosed motherboard failure on office workstation and replaced damaged RAM sticks.', 'Hardware benchmark passed.', NOW(), NOW()),
(19, 5, 3, '2026-06-30', 'Supervised contractor installing outdoor fiber terminal box for TELCO feed.', 'Box sealed against weather.', NOW(), NOW()),
(20, 5, 4, '2026-06-15', 'Configured LED display controller software for scheduled video broadcasts.', 'Broadcast schedule initialized.', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
