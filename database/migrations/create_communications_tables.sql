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

-- Seed Sample Communications (10 Incoming & 10 Outgoing)
INSERT INTO tbl_communications 
(id, communication_type, office_id, category_id, purpose_id, subject, communication_date, status, created_at, updated_at, created_by, modified_by) 
VALUES
-- Incoming (10 Records)
(1, 'Incoming', 1, 1, 2, 'Request for IT Infrastructure Audit & Security Patching', CURDATE(), 'In Progress', NOW(), NOW(), 1, 1),
(2, 'Incoming', 2, 3, 1, 'Application for Server Room Access Pass for Q3', '2026-08-05', 'Completed', NOW(), NOW(), 1, 1),
(3, 'Incoming', 3, 2, 3, 'Endorsement for Additional Network Switches Procurement', CURDATE(), 'Pending', NOW(), NOW(), 1, 1),
(4, 'Incoming', 1, 4, 4, 'Inquiry on Fiber Optic Backbone Link Cable Upgrade', '2026-08-02', 'In Progress', NOW(), NOW(), 1, 1),
(5, 'Incoming', 2, 1, 2, 'Request for Database Access Credentials Provisioning', '2026-08-11', 'In Progress', NOW(), NOW(), 1, 1),
(6, 'Incoming', 3, 5, 4, 'Request for Cyber Hygiene Workshop Schedule Confirmation', '2026-08-09', 'Completed', NOW(), NOW(), 1, 1),
(7, 'Incoming', 1, 3, 1, 'Temporary Datacenter Entry Request for External Technicians', CURDATE(), 'Pending', NOW(), NOW(), 1, 1),
(8, 'Incoming', 2, 2, 3, 'Funding Allocation Clarification for Annual Cloud Server License', '2026-08-07', 'In Progress', NOW(), NOW(), 1, 1),
(9, 'Incoming', 3, 4, 4, 'Transmittal of Annual Hardware Equipment Inventory Report', '2026-08-04', 'Completed', NOW(), NOW(), 1, 1),
(10, 'Incoming', 1, 6, 2, 'User Acceptance Testing Feedback for 6IS Portal Release', '2026-08-03', 'Completed', NOW(), NOW(), 1, 1),

-- Outgoing (10 Records)
(11, 'Outgoing', 1, 4, 3, 'Memo on Quarterly Hardware Procurement & Maintenance Budget', CURDATE(), 'Pending', NOW(), NOW(), 1, 1),
(12, 'Outgoing', 3, 5, 4, 'Guidelines on Information Systems Security & Password Management', '2026-08-10', 'Released', NOW(), NOW(), 1, 1),
(13, 'Outgoing', 2, 1, 2, 'Dispatch of Final Migration Plan for Enterprise ERP System', CURDATE(), 'In Progress', NOW(), NOW(), 1, 1),
(14, 'Outgoing', 1, 3, 1, 'Issuance of Revoked Access Badges Advisory to All Units', '2026-08-06', 'Released', NOW(), NOW(), 1, 1),
(15, 'Outgoing', 3, 2, 3, 'Transmittal of ICT Equipment Depreciation Assessment', '2026-08-08', 'Pending', NOW(), NOW(), 1, 1),
(16, 'Outgoing', 2, 4, 4, 'Advisory on Scheduled Server Room Electrical Maintenance Power Down', '2026-08-11', 'Released', NOW(), NOW(), 1, 1),
(17, 'Outgoing', 1, 5, 4, 'Standard Protocol for Data Backup and Disaster Recovery Tests', '2026-08-01', 'Completed', NOW(), NOW(), 1, 1),
(18, 'Outgoing', 3, 1, 2, 'Transmittal of Personnel Security Clearance Documents', CURDATE(), 'In Progress', NOW(), NOW(), 1, 1),
(19, 'Outgoing', 2, 3, 1, 'Notice of Deactivated VPN Accounts for Inactive Offsite Staff', '2026-08-05', 'Released', NOW(), NOW(), 1, 1),
(20, 'Outgoing', 1, 4, 3, 'Submission of Repair Cost Estimates for Core Switch Units', '2026-08-03', 'Completed', NOW(), NOW(), 1, 1)
ON DUPLICATE KEY UPDATE 
subject=VALUES(subject),
communication_type=VALUES(communication_type),
office_id=VALUES(office_id),
category_id=VALUES(category_id),
purpose_id=VALUES(purpose_id),
communication_date=VALUES(communication_date),
status=VALUES(status);

-- Seed Sample Communication Activities
INSERT INTO tbl_communication_activities
(id, communication_id, activity_type, activity_date, remarks, created_at, created_by)
VALUES
(1, 1, 'Logged', '2026-08-12 09:00:00', 'Communication received and logged into system.', NOW(), 1),
(2, 1, 'Status changed to In Progress', '2026-08-12 10:30:00', 'Assigned to Systems Administrator for audit review.', NOW(), 1),
(3, 2, 'Logged', '2026-08-05 08:30:00', 'Access pass application received.', NOW(), 1),
(4, 2, 'Approved', '2026-08-06 14:00:00', 'Pass approved by ICT Director.', NOW(), 1),
(5, 2, 'Status changed to Completed', '2026-08-07 11:15:00', 'Physical access badge issued to personnel.', NOW(), 1),
(6, 3, 'Logged', '2026-08-12 08:45:00', 'Endorsement letter received and logged.', NOW(), 1),
(7, 4, 'Logged', '2026-08-02 11:00:00', 'Inquiry logged into tracking database.', NOW(), 1),
(8, 5, 'Logged', '2026-08-11 09:15:00', 'Database credential request received.', NOW(), 1),
(9, 6, 'Logged', '2026-08-09 13:20:00', 'Workshop schedule proposal logged.', NOW(), 1),
(10, 7, 'Logged', '2026-08-12 11:10:00', 'Temporary datacenter access request received.', NOW(), 1),
(11, 11, 'Logged', '2026-08-12 13:45:00', 'Outgoing memorandum drafted and dispatched to Finance.', NOW(), 1),
(12, 12, 'Logged', '2026-08-10 10:00:00', 'Drafted SOP guidelines document.', NOW(), 1),
(13, 12, 'Status changed to Released', '2026-08-11 16:20:00', 'Circular distributed to all unit heads.', NOW(), 1),
(14, 13, 'Logged', '2026-08-12 14:00:00', 'ERP migration plan dispatched to stakeholders.', NOW(), 1),
(15, 14, 'Logged', '2026-08-06 15:30:00', 'Revocation advisory dispatched.', NOW(), 1),
(16, 15, 'Logged', '2026-08-08 09:00:00', 'Depreciation report submitted for audit.', NOW(), 1),
(17, 16, 'Logged', '2026-08-11 10:45:00', 'Power down notice issued to all divisions.', NOW(), 1),
(18, 17, 'Logged', '2026-08-01 16:00:00', 'Disaster recovery procedure published.', NOW(), 1),
(19, 18, 'Logged', '2026-08-12 15:30:00', 'Personnel clearance documents forwarded to Admin.', NOW(), 1),
(20, 19, 'Logged', '2026-08-05 11:20:00', 'VPN account revocation notice dispatched.', NOW(), 1),
(21, 20, 'Logged', '2026-08-03 14:15:00', 'Repair estimates forwarded to Procurement.', NOW(), 1)
ON DUPLICATE KEY UPDATE 
activity_type=VALUES(activity_type),
remarks=VALUES(remarks);
