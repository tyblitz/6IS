-- Database migration for 6IS Communications Module
-- Reuses authoritative reference table: tbl_offices
-- Creates reference tables: tbl_communication_categories, tbl_communication_purposes
-- Creates module table: tbl_communications
-- Creates activity tracking table: tbl_communication_activities

-- 1. Create Reference Table: tbl_communication_categories
CREATE TABLE IF NOT EXISTS tbl_communication_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Initial Categories (Idempotent)
INSERT INTO tbl_communication_categories (id, name, code, is_active, created_at, updated_at, created_by, modified_by) VALUES
(1, 'Disposition Form', 'DF', 1, NOW(), NOW(), 1, 1),
(2, 'Summary Disposition Form', 'SDF', 1, NOW(), NOW(), 1, 1),
(3, 'Subject to Letter', 'STL', 1, NOW(), NOW(), 1, 1),
(4, 'Memorandum', 'Memo', 1, NOW(), NOW(), 1, 1),
(5, 'Standard Operating Procedure', 'SOP', 1, NOW(), NOW(), 1, 1),
(6, 'Others', NULL, 1, NOW(), NOW(), 1, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), code=VALUES(code);

-- 2. Create Reference Table: tbl_communication_purposes
CREATE TABLE IF NOT EXISTS tbl_communication_purposes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Initial Purposes (Idempotent)
INSERT INTO tbl_communication_purposes (id, name, is_active, created_at, updated_at, created_by, modified_by) VALUES
(1, 'Access Pass', 1, NOW(), NOW(), 1, 1),
(2, 'PAS Request', 1, NOW(), NOW(), 1, 1),
(3, 'R&M ICT Fund Request', 1, NOW(), NOW(), 1, 1),
(4, 'Others', 1, NOW(), NOW(), 1, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 3. Create Module Table: tbl_communications
CREATE TABLE IF NOT EXISTS tbl_communications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    communication_type ENUM('Incoming', 'Outgoing') NOT NULL DEFAULT 'Incoming',
    office_id INT NOT NULL,
    category_id INT NOT NULL,
    purpose_id INT NOT NULL,
    subject VARCHAR(255) NULL,
    communication_date DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    FOREIGN KEY (office_id) REFERENCES tbl_offices(id),
    FOREIGN KEY (category_id) REFERENCES tbl_communication_categories(id),
    FOREIGN KEY (purpose_id) REFERENCES tbl_communication_purposes(id),
    INDEX idx_communication_type (communication_type),
    INDEX idx_office_id (office_id),
    INDEX idx_category_id (category_id),
    INDEX idx_purpose_id (purpose_id),
    INDEX idx_status (status),
    INDEX idx_communication_date (communication_date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Activity Table: tbl_communication_activities
CREATE TABLE IF NOT EXISTS tbl_communication_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    communication_id INT NOT NULL,
    activity_type VARCHAR(100) NOT NULL,
    activity_date DATETIME NOT NULL,
    remarks TEXT NULL,
    created_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    FOREIGN KEY (communication_id) REFERENCES tbl_communications(id),
    INDEX idx_communication_id (communication_id),
    INDEX idx_activity_date (activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Sample Communications (Idempotent with fixed IDs)
INSERT INTO tbl_communications 
(id, communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by, modified_by) 
VALUES
(1, 'Incoming', 1, 1, 2, 'Request for IT Infrastructure Audit & Security Patching', '2026-08-01', 'In Progress', NOW(), NOW(), 1, 1),
(2, 'Incoming', 2, 3, 1, 'Application for Server Room Access Pass for Q3', '2026-08-05', 'Completed', NOW(), NOW(), 1, 1),
(3, 'Outgoing', 1, 4, 3, 'Memo on Quarterly Hardware Procurement & Maintenance Budget', '2026-08-08', 'Pending', NOW(), NOW(), 1, 1),
(4, 'Outgoing', 3, 5, 4, 'Guidelines on Information Systems Security & Password Management', '2026-08-10', 'Released', NOW(), NOW(), 1, 1)
ON DUPLICATE KEY UPDATE 
subject=VALUES(subject),
communication_type=VALUES(communication_type),
office_id=VALUES(office_id),
category_id=VALUES(category_id),
purpose_id=VALUES(purpose_id),
communication_date=VALUES(communication_date),
status=VALUES(status);

-- Seed Sample Communication Activities (Idempotent)
INSERT INTO tbl_communication_activities
(id, communication_id, activity_type, activity_date, remarks, created_at, created_by)
VALUES
(1, 1, 'Logged', '2026-08-01 09:00:00', 'Communication received and logged into system.', NOW(), 1),
(2, 1, 'Status changed to In Progress', '2026-08-02 10:30:00', 'Assigned to Systems Administrator for audit review.', NOW(), 1),
(3, 2, 'Logged', '2026-08-05 08:30:00', 'Access pass application received.', NOW(), 1),
(4, 2, 'Approved', '2026-08-06 14:00:00', 'Pass approved by ICT Director.', NOW(), 1),
(5, 2, 'Status changed to Completed', '2026-08-07 11:15:00', 'Physical access badge issued to personnel.', NOW(), 1),
(6, 3, 'Logged', '2026-08-08 13:45:00', 'Outgoing memorandum drafted and dispatched to Finance.', NOW(), 1),
(7, 4, 'Logged', '2026-08-10 10:00:00', 'Drafted SOP guidelines document.', NOW(), 1),
(8, 4, 'Status changed to Released', '2026-08-11 16:20:00', 'Circular distributed to all unit heads.', NOW(), 1)
ON DUPLICATE KEY UPDATE 
activity_type=VALUES(activity_type),
remarks=VALUES(remarks);
