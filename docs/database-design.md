# Database Design

## General Rules

Every table in the `db_ict_system` database adheres to standard audit fields:

- `created_at` (DATETIME, NOT NULL)
- `updated_at` (DATETIME, NOT NULL)
- `created_by` (INT, DEFAULT 1)
- `modified_by` (INT, DEFAULT 1)
- `deleted_at` (DATETIME, NULL - soft deletion timestamp)

All soft-deleted records (`deleted_at IS NOT NULL`) are strictly excluded from normal queries and reporting logic. Reference tables utilize `is_active TINYINT(1)` to deactivate selectable options without breaking historical relational records.

---

## 1. Core Platform & Security Tables

### `tbl_modules`
Centralized authoritative registry of all platform and business modules.
- `id` (INT, AUTO_INCREMENT, PK)
- `module_key` (VARCHAR(50), NOT NULL, UNIQUE) — e.g. `dashboard`, `administrator`, `inventory`, `communications`, `accomplishments`, `calendar`
- `name` (VARCHAR(100), NOT NULL)
- `description` (TEXT, NULL)
- `icon` (VARCHAR(50), NOT NULL)
- `route` (VARCHAR(100), NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)
- `is_core` (TINYINT(1), NOT NULL, DEFAULT 0) — Core modules cannot be disabled
- `display_order` (INT, NOT NULL, DEFAULT 0)
- `version` (VARCHAR(20), NOT NULL, DEFAULT '1.0.0')

### `tbl_roles`
Authoritative role registry.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(50), NOT NULL, UNIQUE) — e.g. `Administrator`, `User`
- `description` (TEXT, NULL)
- `is_system` (TINYINT(1), NOT NULL, DEFAULT 0) — System roles cannot be renamed, deactivated, or deleted
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

### `tbl_permissions`
Granular application-wide permission registry.
- `id` (INT, AUTO_INCREMENT, PK)
- `module_key` (VARCHAR(50), NOT NULL)
- `permission_key` (VARCHAR(50), NOT NULL)
- `name` (VARCHAR(100), NOT NULL)
- `description` (TEXT, NULL)
- `is_system` (TINYINT(1), NOT NULL, DEFAULT 0) — 40 official seeded permissions protected with `is_system = 1`
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)
- *Unique Constraint*: `(module_key, permission_key)`

### `tbl_role_permissions`
Role-to-permission join table.
- `id` (INT, AUTO_INCREMENT, PK)
- `role_id` (INT, NOT NULL, FK to `tbl_roles(id)` ON DELETE CASCADE)
- `permission_id` (INT, NOT NULL, FK to `tbl_permissions(id)` ON DELETE CASCADE)
- *Unique Constraint*: `(role_id, permission_id)`

### `tbl_audit_logs`
Centralized, immutable system audit trail with JSON state snapshots and actor metadata.
- `id` (BIGINT, AUTO_INCREMENT, PK)
- `user_id` (INT, NULL, FK to `tbl_users(id)` ON DELETE SET NULL)
- `action` (VARCHAR(50), NOT NULL) — e.g. `LOGIN`, `LOGIN_FAILED`, `LOGOUT`, `CREATE`, `UPDATE`, `DELETE`, `ACTIVATE`, `DEACTIVATE`, `ASSIGN`
- `module_key` (VARCHAR(50), NOT NULL)
- `entity_type` (VARCHAR(50), NULL)
- `entity_id` (VARCHAR(100), NULL)
- `description` (TEXT, NOT NULL)
- `old_values` (JSON, NULL) — Previous record state snapshot (sanitized)
- `new_values` (JSON, NULL) — Resulting record state snapshot (sanitized)
- `ip_address` (VARCHAR(45), NULL)
- `user_agent` (VARCHAR(255), NULL)
- `created_at` (DATETIME, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
- *Indexes*: `(module_key, created_at)`, `(action, created_at)`, `(user_id, created_at)`

---

## 2. Organization & User Domain Tables

### `tbl_organization`
Single-tenant top-level enterprise organizational profile.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(150), NOT NULL)
- `code` (VARCHAR(50), NOT NULL)
- `description` (TEXT, NULL)
- `logo_url` (VARCHAR(255), NULL)
- `contact_email` (VARCHAR(150), NULL)
- `contact_phone` (VARCHAR(50), NULL)
- `address` (TEXT, NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1) — Minimum active count invariant enforced (cannot deactivate last active organization)

### `tbl_offices`
Authoritative office directory scoped to the organization.
- `id` (INT, AUTO_INCREMENT, PK)
- `organization_id` (INT, NOT NULL, DEFAULT 1, FK to `tbl_organization(id)`)
- `office_name` (VARCHAR(100), NOT NULL)
- `office_code` (VARCHAR(20), NOT NULL)
- `office_abbv` (VARCHAR(50), NOT NULL)
- `office_category` (ENUM('Staff', 'Special Staff', 'Group', 'Others'), DEFAULT 'Others')
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)
- *Unique Constraints*: `(organization_id, office_code)`, `(organization_id, office_name)`
- *Foreign Key Restraints*: Deletion is blocked if referenced by users, equipment, history, accomplishments, communications, or calendar events.

### `tbl_users`
User authentication and role-based access management table.
- `id` (INT, AUTO_INCREMENT, PK)
- `username` (VARCHAR(100), NOT NULL, UNIQUE)
- `full_name` (VARCHAR(150), NOT NULL)
- `password` (VARCHAR(255), NOT NULL, BCRYPT hashed)
- `role_id` (INT, NOT NULL, DEFAULT 2, FK to `tbl_roles(id)`) — Authoritative role binding
- `role` (VARCHAR(20), NOT NULL, DEFAULT 'User') — Backward-compatible legacy synchronization column
- `office_id` (INT, NULL, FK to `tbl_offices(id)` ON DELETE SET NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

---

## 3. Business Domain Tables

### Calendar Module
- **`tbl_calendar_event_types`**: Event classifications (`CONF`, `PAS`, `VTC`, etc.).
- **`tbl_calendar_events`**: Scheduled activities (`title`, `start_datetime`, `end_datetime`, `office_id`, `event_type_id`, `status`).
- **`tbl_calendar_event_reschedules`**: Audit history of rescheduled event dates and reasons.
- **`tbl_calendar_event_status_history`**: State transitions (`Scheduled`, `In Progress`, `Completed`, `Cancelled`).

### Communications Module
- **`tbl_communication_categories`**: Document classifications (`DF`, `SDF`, `STL`, `Memo`, `SOP`, `Others`).
- **`tbl_communication_purposes`**: Processing reasons (`Access Pass`, `PAS Request`, `R&M ICT Fund Request`, etc.).
- **`tbl_communications`**: Core communication records with explicit `communication_type` (`Incoming`, `Outgoing`), office references, and tracking dates.
- **`tbl_communication_activities`**: Turnaround event logs and processing history.

### Accomplishments Module
- **`tbl_accomplishments`**: Completed daily accomplishment records (`office_id`, `date`, `description`, `remarks`, `calendar_event_id`).
- Consolidated dynamically on-the-fly without pre-aggregated summary tables.

### Inventory Module
- **`tbl_inventory_equipment_types`** & **`tbl_inventory_equipment_subtypes`**: Equipment taxonomic hierarchy.
- **`tbl_inventory_equipment_statuses`**: Status indicators (`Serviceable`, `Unserviceable`, `Disposed`).
- **`tbl_inventory_equipment`**: Physical equipment inventory (`subtype_id`, `status_id`, `office_id`, `quantity`, `serial_number`, `model`).
- **`tbl_inventory_attribute_definitions`** & **`tbl_inventory_equipment_attribute_values`**: Extensible EAV model for custom technical attributes.
- **`tbl_inventory_jrrs`**: Approved equipment quota baseline targets.
- **`tbl_inventory_history`**: Immutable monthly snapshots of equipment inventory.