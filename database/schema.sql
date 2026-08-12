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

CREATE TABLE IF NOT EXISTS tbl_accomplishment_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
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
    FOREIGN KEY (category_id) REFERENCES tbl_accomplishment_categories(id),
    FOREIGN KEY (assigned_employee_id) REFERENCES tbl_users(id),
    INDEX idx_office_id (office_id),
    INDEX idx_category_id (category_id),
    INDEX idx_assigned_employee_id (assigned_employee_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_date_started (date_started),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;