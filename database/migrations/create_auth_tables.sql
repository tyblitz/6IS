-- database/migrations/create_auth_tables.sql
-- Migration for 6IS Authentication Foundation (tbl_users & initial accounts)

CREATE TABLE IF NOT EXISTS tbl_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL,
    role ENUM('Administrator', 'User') NOT NULL DEFAULT 'User',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_by INT NOT NULL DEFAULT 1,
    modified_by INT NOT NULL DEFAULT 1,
    deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure columns exist if tbl_users was created previously
ALTER TABLE tbl_users MODIFY COLUMN role ENUM('Administrator', 'User') NOT NULL DEFAULT 'User';
