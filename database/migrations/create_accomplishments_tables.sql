-- Database migration for 6IS Accomplishment Module
-- Create reference tables: tbl_offices, tbl_users
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

-- Re-create tbl_accomplishments adhering strictly to V1 business rules
DROP TABLE IF EXISTS tbl_accomplishments;

CREATE TABLE tbl_accomplishments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_id INT NOT NULL,
    date DATE NOT NULL,
    description TEXT NOT NULL,
    remarks TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL,
    FOREIGN KEY (office_id) REFERENCES tbl_offices(id),
    INDEX idx_office_id (office_id),
    INDEX idx_date (date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Sample Data
INSERT INTO tbl_offices (office_name, office_code, created_at, updated_at) VALUES 
('Information & Communications Technology', 'ICT', NOW(), NOW()),
('Management Information Systems', 'MIS', NOW(), NOW()),
('Administrative & Finance', 'ADMIN', NOW(), NOW())
ON DUPLICATE KEY UPDATE office_name=VALUES(office_name);

INSERT INTO tbl_users (username, full_name, password, role, created_at, updated_at) VALUES 
('admin01', 'John Doe (Administrator)', 'sample_hash', 'admin', NOW(), NOW()),
('asmith', 'Alice Smith', 'sample_hash', 'user', NOW(), NOW()),
('bwayne', 'Bruce Wayne', 'sample_hash', 'user', NOW(), NOW())
ON DUPLICATE KEY UPDATE username=VALUES(username);

INSERT INTO tbl_accomplishments (office_id, date, description, remarks, created_at, updated_at) VALUES 
(1, CURDATE(), 'Completed annual server rack cable management and hardware diagnostics.', 'All systems online and operating at optimal temperature.', NOW(), NOW()),
(2, CURDATE(), 'Conducted quarterly cybersecurity vulnerability audit and patch installation.', 'No critical vulnerabilities detected.', NOW(), NOW()),
(1, '2026-08-01', 'Configured remote backup storage replication for financial records database.', 'Replication verified successfully.', NOW(), NOW());
