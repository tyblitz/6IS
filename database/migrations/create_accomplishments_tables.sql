-- Database migration for 6IS Accomplishment Module
-- Create reference tables: tbl_offices, tbl_categories, tbl_users
-- Create module table: tbl_accomplishments

CREATE TABLE IF NOT EXISTS tbl_offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_name VARCHAR(100) NOT NULL,
    office_code VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
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

CREATE TABLE IF NOT EXISTS tbl_accomplishments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    office_id INT NOT NULL,
    category_id INT NOT NULL,
    assigned_employee_id INT NOT NULL,
    date_started DATE NOT NULL,
    date_completed DATE NULL,
    status ENUM('Pending', 'Ongoing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    priority ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    remarks TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    FOREIGN KEY (office_id) REFERENCES tbl_offices(id),
    FOREIGN KEY (category_id) REFERENCES tbl_categories(id),
    FOREIGN KEY (assigned_employee_id) REFERENCES tbl_users(id),
    INDEX idx_office_id (office_id),
    INDEX idx_category_id (category_id),
    INDEX idx_assigned_employee_id (assigned_employee_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_date_started (date_started),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Seed Data
INSERT INTO tbl_offices (office_name, office_code, created_at, updated_at) VALUES 
('Information & Communications Technology', 'ICT', NOW(), NOW()),
('Management Information Systems', 'MIS', NOW(), NOW()),
('Administrative & Finance', 'ADMIN', NOW(), NOW());

INSERT INTO tbl_categories (category_name, created_at, updated_at) VALUES 
('Infrastructure & Hardware', NOW(), NOW()),
('Software & System Development', NOW(), NOW()),
('Network & Cyber Security', NOW(), NOW()),
('Technical Support & Maintenance', NOW(), NOW());

INSERT INTO tbl_users (username, full_name, password, role, created_at, updated_at) VALUES 
('admin01', 'John Doe (Administrator)', 'sample_hash', 'admin', NOW(), NOW()),
('asmith', 'Alice Smith', 'sample_hash', 'user', NOW(), NOW()),
('bwayne', 'Bruce Wayne', 'sample_hash', 'user', NOW(), NOW());

INSERT INTO tbl_accomplishments (title, description, office_id, category_id, assigned_employee_id, date_started, date_completed, status, priority, remarks, created_at, updated_at) VALUES 
('Server Infrastructure Upgrade', 'Upgraded core application servers and storage array', 1, 1, 1, CURDATE(), CURDATE(), 'Completed', 'High', 'Deployment completed smoothly without downtime', NOW(), NOW()),
('Network Security Vulnerability Audit', 'Audit external firewall rules and internal subnet policies', 2, 3, 2, CURDATE(), NULL, 'Ongoing', 'Critical', 'Assessment in progress', NOW(), NOW()),
('Annual Database Optimization', 'Re-index tables and clear legacy log entries', 1, 2, 3, '2026-08-01', NULL, 'Pending', 'Medium', 'Scheduled for execution', NOW(), NOW());
