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