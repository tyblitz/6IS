# Database Design

## General Rules

Every table in the `db_ict_system` database adheres to standard audit fields:

- `created_at` (DATETIME, NOT NULL)
- `updated_at` (DATETIME, NOT NULL)
- `created_by` (INT, DEFAULT 1)
- `modified_by` (INT, DEFAULT 1)
- `deleted_at` (DATETIME, NULL - soft deletion timestamp)

All soft-deleted records (`deleted_at IS NOT NULL`) are strictly excluded from normal queries and reporting logic.

---

## Authoritative Reference & Security Tables

### `tbl_offices`
Authoritative office reference table shared across all 6IS modules (Accomplishments, Communications, Inventory).
- `id` (INT, AUTO_INCREMENT, PK)
- `office_name` (VARCHAR(100), NULL)
- `office_code` (VARCHAR(20), NULL)
- `office_abbv` (VARCHAR(50), NOT NULL)
- `office_category` (ENUM('Staff', 'Special Staff', 'Group', 'Others'), DEFAULT 'Others')
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

### `tbl_users`
User authentication and role-based access management table.
- `id` (INT, AUTO_INCREMENT, PK)
- `username` (VARCHAR(100), NOT NULL, UNIQUE)
- `full_name` (VARCHAR(150), NOT NULL)
- `password` (VARCHAR(255), NOT NULL, BCRYPT hashed)
- `role` (VARCHAR(20), NOT NULL, DEFAULT 'user') — Options: `Administrator`, `User`
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

---

## Communications Module Tables

### `tbl_communication_categories`
Reference lookup table for document classifications.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(150), NOT NULL)
- `code` (VARCHAR(50), NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

*Initial Categories*: Disposition Form (`DF`), Summary Disposition Form (`SDF`), Subject to Letter (`STL`), Memorandum (`Memo`), Standard Operating Procedure (`SOP`), Others (`NULL`).

### `tbl_communication_purposes`
Reference lookup table for processing intents/reasons.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(150), NOT NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

*Initial Purposes*: Access Pass, PAS Request, R&M ICT Fund Request, Others.

### `tbl_communications`
Core communications tracking table.
- `id` (INT, AUTO_INCREMENT, PK)
- `communication_type` (ENUM('Incoming', 'Outgoing'), NOT NULL, DEFAULT 'Incoming')
- `office_id` (INT, FK -> `tbl_offices.id`)
- `category_id` (INT, FK -> `tbl_communication_categories.id`)
- `purpose_id` (INT, FK -> `tbl_communication_purposes.id`)
- `subject` (VARCHAR(255), NULL)
- `communication_date` (DATE, NULL)
- `status` (VARCHAR(50), DEFAULT 'Pending')
- `image_url` (VARCHAR(500), NULL)

> **Note**: Communication age is **not** stored as a static field in `tbl_communications`. Age is computed dynamically from the latest `activity_date` in `tbl_communication_activities` relative to current timestamp.

### `tbl_communication_activities`
Process activity timeline and turnaround history log.
- `id` (INT, AUTO_INCREMENT, PK)
- `communication_id` (INT, FK -> `tbl_communications.id`)
- `activity_type` (VARCHAR(100), NOT NULL)
- `activity_date` (DATETIME, NOT NULL)
- `remarks` (TEXT, NULL)

### `tbl_communication_attachments`
Multi-image upload file attachment tracking table.
- `id` (INT, AUTO_INCREMENT, PK)
- `communication_id` (INT, FK -> `tbl_communications.id`)
- `image_url` (VARCHAR(500), NOT NULL)
- `created_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP)

---

## Inventory Module Extensible EAV Tables

The Inventory module uses an Extensible Entity-Attribute-Value (EAV) database architecture to support dynamic specifications across diverse equipment types.

### `tbl_inventory_equipment_types`
Primary equipment domain categories.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(100), NOT NULL) — e.g. `ICT`, `Communications`
- `code` (VARCHAR(50), NOT NULL, UNIQUE) — e.g. `ICT`, `COMM`
- `is_active` (TINYINT(1), DEFAULT 1)

### `tbl_inventory_equipment_subtypes`
Specific equipment classification types.
- `id` (INT, AUTO_INCREMENT, PK)
- `equipment_type_id` (INT, FK -> `tbl_inventory_equipment_types.id`)
- `name` (VARCHAR(100), NOT NULL) — e.g. `Desktop`, `Printer`, `Mixer`, `Speaker`
- `code` (VARCHAR(50), NOT NULL)
- `is_active` (TINYINT(1), DEFAULT 1)

### `tbl_inventory_equipment_statuses`
Operational equipment status reference table.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(100), NOT NULL) — `Serviceable`, `For Repair`, `For Turn-in`
- `code` (VARCHAR(50), NOT NULL, UNIQUE)

### `tbl_inventory_attribute_definitions`
Subtype-specific attribute definitions (Meta schema for key-value fields).
- `id` (INT, AUTO_INCREMENT, PK)
- `equipment_subtype_id` (INT, FK -> `tbl_inventory_equipment_subtypes.id`)
- `attribute_name` (VARCHAR(100), NOT NULL) — e.g. `Processor`, `RAM`, `Storage`
- `attribute_code` (VARCHAR(50), NOT NULL)
- `data_type` (ENUM('text', 'number', 'decimal', 'date', 'boolean', 'select'), DEFAULT 'text')
- `is_required` (TINYINT(1), DEFAULT 0)
- `sort_order` (INT, DEFAULT 0)

### `tbl_inventory_equipment`
Core equipment asset master table.
- `id` (INT, AUTO_INCREMENT, PK)
- `office_id` (INT, FK -> `tbl_offices.id`)
- `equipment_type_id` (INT, FK -> `tbl_inventory_equipment_types.id`)
- `equipment_subtype_id` (INT, FK -> `tbl_inventory_equipment_subtypes.id`)
- `status_id` (INT, FK -> `tbl_inventory_equipment_statuses.id`)
- `serial_number` (VARCHAR(100), NULL)
- `model` (VARCHAR(100), NULL)
- `property_number` (VARCHAR(100), NULL)
- `remarks` (TEXT, NULL)

### `tbl_inventory_equipment_attribute_values`
EAV storage table binding dynamic attribute values to individual equipment items.
- `id` (INT, AUTO_INCREMENT, PK)
- `equipment_id` (INT, FK -> `tbl_inventory_equipment.id`)
- `attribute_definition_id` (INT, FK -> `tbl_inventory_attribute_definitions.id`)
- `value_text` (TEXT, NULL)
- `value_number` (INT, NULL)
- `value_decimal` (DECIMAL(12,2), NULL)
- `value_date` (DATE, NULL)
- `value_boolean` (TINYINT(1), NULL)

### `tbl_inventory_history`
Equipment audit and movement history log.
- `id` (INT, AUTO_INCREMENT, PK)
- `equipment_id` (INT, FK -> `tbl_inventory_equipment.id`)
- `office_id` (INT, FK -> `tbl_offices.id`)
- `equipment_type_id` (INT, NULL)
- `equipment_subtype_id` (INT, NULL)
- `status_id` (INT, NULL)
- `action_type` (VARCHAR(50), NOT NULL) — `CREATED`, `UPDATED`, `STATUS_CHANGED`, `DELETED`
- `attributes_json` (JSON, NULL)

### `tbl_inventory_jrrs`
Joint Repair & Replacement System (JRRS) readiness target tracking table.
- `id` (INT, AUTO_INCREMENT, PK)
- `equipment_subtype_id` (INT, FK -> `tbl_inventory_equipment_subtypes.id`)
- `target_quantity` (INT, NOT NULL, DEFAULT 0)
- `equipment_type` (VARCHAR(100), NULL)

---

## Accomplishments Module Tables

### `tbl_accomplishment_categories`
Reference lookup table for operational accomplishment categories.
- `id` (INT, AUTO_INCREMENT, PK)
- `category_name` (VARCHAR(150), NOT NULL)
- `category_code` (VARCHAR(50), NULL)

### `tbl_accomplishments`
Operational daily accomplishment logging table.
- `id` (INT, AUTO_INCREMENT, PK)
- `office_id` (INT, FK -> `tbl_offices.id`)
- `category_id` (INT, FK -> `tbl_accomplishment_categories.id`)
- `date` (DATE, NOT NULL)
- `description` (TEXT, NOT NULL)
- `remarks` (TEXT, NULL)

> **Note**: Consolidations (Monthly, Quarterly, Annual, Custom Period) are computed **dynamically on-the-fly** from `tbl_accomplishments` records. No pre-aggregated summary tables are stored.

---

## Calendar Module Tables

### `tbl_calendar_events`
Organization calendar and activity scheduling table.
- `id` (INT, AUTO_INCREMENT, PK)
- `title` (VARCHAR(255), NOT NULL)
- `description` (TEXT, NULL)
- `start_date` (DATETIME, NOT NULL)
- `end_date` (DATETIME, NOT NULL)
- `location` (VARCHAR(255), NULL)
- `office_id` (INT, NULL, FK -> `tbl_offices.id`)
- `color_code` (VARCHAR(20), NULL, DEFAULT '#3880ff')