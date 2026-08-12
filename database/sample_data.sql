INSERT INTO tbl_offices (office_name, office_code, office_abbv, created_at, updated_at) VALUES 
('', 'OG1', 'OG1', NOW(), NOW()),
('', 'OG2', 'OG2', NOW(), NOW()),
('', 'OG6', 'OG6', NOW(), NOW());

INSERT INTO tbl_accomplishment_categories (category_name, category_code, created_at, updated_at) VALUES 
('Installation of Public Address System (PAS)', 'PAS', NOW(), NOW()),
('Conducted Repair and Maintenance of ICT Equipment', 'ICT Repair', NOW(), NOW()),
('Supervised/Assisted TELCO Personnel', 'TELCO', NOW(), NOW()),
('LED Board Support', 'LED', NOW(), NOW());

INSERT INTO tbl_users (username, full_name, password, role, created_at, updated_at) VALUES 
('admin01', 'John Doe (Administrator)', 'sample_hash', 'admin', NOW(), NOW()),
('asmith', 'Alice Smith', 'sample_hash', 'user', NOW(), NOW()),
('bwayne', 'Bruce Wayne', 'sample_hash', 'user', NOW(), NOW());

INSERT INTO tbl_accomplishments (office_id, category_id, date, description, remarks, created_at, updated_at) VALUES 
(3, 1, CURDATE(), 'Installation and setup of Public Address System for conference hall briefing.', 'PAS tested clear.', NOW(), NOW()),
(3, 2, CURDATE(), 'Conducted repair and maintenance of desktop computer units.', 'Repaired 3 units.', NOW(), NOW()),
(3, 3, '2026-08-01', 'Supervised PLDT TELCO technicians during fiber line restoration.', 'Fiber optic line restored.', NOW(), NOW());