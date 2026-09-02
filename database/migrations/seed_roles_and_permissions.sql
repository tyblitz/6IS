-- database/migrations/seed_roles_and_permissions.sql
-- Seed Initial System Roles, Official Permissions Registry, and Default Role Assignments (Phase 2)

-- 1. Seed System Roles (Idempotent: preserve existing if already present)
INSERT INTO `tbl_roles` (`name`, `description`, `is_system`, `is_active`, `created_at`, `updated_at`)
VALUES 
    ('Administrator', 'Full system access, administration, and platform configuration privileges', 1, 1, NOW(), NOW()),
    ('User', 'Standard system user with operational view and baseline privileges', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `is_system` = 1,
    `updated_at` = NOW();

-- 2. Seed Official Permissions Registry (Idempotent: ON DUPLICATE KEY UPDATE)
INSERT INTO `tbl_permissions` (`module_key`, `permission_key`, `name`, `description`, `is_active`, `created_at`, `updated_at`)
VALUES
    -- Dashboard
    ('dashboard', 'view', 'View Dashboard', 'View executive dashboard and operational KPI metrics', 1, NOW(), NOW()),

    -- Inventory
    ('inventory', 'view', 'View Inventory', 'View ICT equipment records and JRRS target metrics', 1, NOW(), NOW()),
    ('inventory', 'create', 'Create Equipment', 'Register new ICT equipment records into inventory', 1, NOW(), NOW()),
    ('inventory', 'edit', 'Edit Equipment', 'Update equipment details, specifications, and service status', 1, NOW(), NOW()),
    ('inventory', 'delete', 'Delete Equipment', 'Archive or remove equipment records from inventory', 1, NOW(), NOW()),
    ('inventory', 'configure', 'Configure Inventory', 'Manage JRRS targets, equipment types, subtypes, and statuses', 1, NOW(), NOW()),

    -- Communications
    ('communications', 'view', 'View Communications', 'View incoming and outgoing communications records', 1, NOW(), NOW()),
    ('communications', 'create', 'Create Communication', 'Register new incoming and outgoing communication entries', 1, NOW(), NOW()),
    ('communications', 'edit', 'Edit Communication', 'Update communication details, attachments, and statuses', 1, NOW(), NOW()),
    ('communications', 'delete', 'Delete Communication', 'Archive or remove communication records', 1, NOW(), NOW()),
    ('communications', 'configure', 'Configure Communications', 'Manage communication categories, purposes, and reference data', 1, NOW(), NOW()),

    -- Calendar
    ('calendar', 'view', 'View Calendar', 'View scheduled events, activities, and calendar views', 1, NOW(), NOW()),
    ('calendar', 'create', 'Create Event', 'Schedule new calendar events and command activities', 1, NOW(), NOW()),
    ('calendar', 'edit', 'Edit Event', 'Update event details, reschedule dates, and transition status', 1, NOW(), NOW()),
    ('calendar', 'delete', 'Delete Event', 'Cancel or archive scheduled calendar events', 1, NOW(), NOW()),
    ('calendar', 'configure', 'Configure Calendar', 'Manage calendar event types, categories, and references', 1, NOW(), NOW()),

    -- Accomplishments
    ('accomplishments', 'view', 'View Accomplishments', 'View accomplishment records and periodic summary reports', 1, NOW(), NOW()),
    ('accomplishments', 'create', 'Create Accomplishment', 'Record new operational accomplishment reports', 1, NOW(), NOW()),
    ('accomplishments', 'edit', 'Edit Accomplishment', 'Update accomplishment records, descriptions, and remarks', 1, NOW(), NOW()),
    ('accomplishments', 'delete', 'Delete Accomplishment', 'Archive or delete accomplishment records', 1, NOW(), NOW()),
    ('accomplishments', 'configure', 'Configure Accomplishments', 'Manage accomplishment categories and templates', 1, NOW(), NOW()),

    -- Users (Core)
    ('users', 'view', 'View Users', 'View system user accounts, profiles, and active status', 1, NOW(), NOW()),
    ('users', 'create', 'Create User', 'Register new user accounts in the platform', 1, NOW(), NOW()),
    ('users', 'edit', 'Edit User', 'Update user account details, credentials, and active state', 1, NOW(), NOW()),
    ('users', 'delete', 'Delete User', 'Deactivate or soft-delete user accounts', 1, NOW(), NOW()),
    ('users', 'configure', 'Configure User Roles', 'Assign and reassign roles to user accounts', 1, NOW(), NOW()),

    -- Roles & Permissions (Core)
    ('roles', 'view', 'View Roles', 'View system roles and permission matrices', 1, NOW(), NOW()),
    ('roles', 'create', 'Create Role', 'Create new custom platform access roles', 1, NOW(), NOW()),
    ('roles', 'edit', 'Edit Role', 'Update custom role details and active status', 1, NOW(), NOW()),
    ('roles', 'delete', 'Delete Role', 'Delete custom, unassigned access roles', 1, NOW(), NOW()),
    ('roles', 'configure', 'Configure Permissions', 'Assign and modify permission matrices for roles', 1, NOW(), NOW()),

    -- Modules (Core Registry)
    ('modules', 'view', 'View Modules', 'View registered platform modules and status', 1, NOW(), NOW()),
    ('modules', 'configure', 'Configure Modules', 'Activate or deactivate platform business modules', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `is_active` = 1,
    `updated_at` = NOW();

-- 3. Grant Administrator System Role ALL Permissions (Idempotent)
INSERT INTO `tbl_role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `tbl_roles` r
CROSS JOIN `tbl_permissions` p
WHERE r.name = 'Administrator'
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);

-- 4. Grant User System Role Baseline Operational View Permissions (Idempotent)
INSERT INTO `tbl_role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `tbl_roles` r
JOIN `tbl_permissions` p ON (
    (p.module_key = 'dashboard' AND p.permission_key = 'view') OR
    (p.module_key = 'inventory' AND p.permission_key = 'view') OR
    (p.module_key = 'communications' AND p.permission_key = 'view') OR
    (p.module_key = 'calendar' AND p.permission_key = 'view') OR
    (p.module_key = 'accomplishments' AND p.permission_key = 'view')
)
WHERE r.name = 'User'
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);
