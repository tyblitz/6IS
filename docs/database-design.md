# Database Design

## General Rules

Every table includes:

- `created_at`
- `updated_at`
- `created_by`
- `modified_by`
- `deleted_at` (soft deletion timestamp)

---

## Authoritative Reference Tables

### `tbl_offices`
Authoritative office reference table shared across 6IS modules (Accomplishments, Communications, etc.).
- `id` (INT, AUTO_INCREMENT, PK)
- `office_name` (VARCHAR(100), NOT NULL)
- `office_code` (VARCHAR(20), NOT NULL)
- `office_abbv` (VARCHAR(20), NULL)
- `office_category` (ENUM('Staff', 'Special Staff', 'Group', 'Others'), DEFAULT 'Others')
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

---

## Communications Module Tables

### `tbl_communication_categories`
Reference table for communication category types.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(150), NOT NULL)
- `code` (VARCHAR(50), NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

Initial Records:
- Disposition Form (`DF`)
- Summary Disposition Form (`SDF`)
- Subject to Letter (`STL`)
- Memorandum (`Memo`)
- Standard Operating Procedure (`SOP`)
- Others (`NULL`)

### `tbl_communication_purposes`
Reference table for communication processing purposes.
- `id` (INT, AUTO_INCREMENT, PK)
- `name` (VARCHAR(150), NOT NULL)
- `is_active` (TINYINT(1), NOT NULL, DEFAULT 1)

Initial Records:
- Access Pass
- PAS Request
- R&M ICT Fund Request
- Others

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

> **Note**: Communication age is **not** stored as a static field in `tbl_communications`. Age is computed dynamically from the latest `activity_date` in `tbl_communication_activities`.

### `tbl_communication_activities`
Process tracking and turnaround history log for communications.
- `id` (INT, AUTO_INCREMENT, PK)
- `communication_id` (INT, FK -> `tbl_communications.id`)
- `activity_type` (VARCHAR(100), NOT NULL)
- `activity_date` (DATETIME, NOT NULL)
- `remarks` (TEXT, NULL)
- `created_at` (DATETIME, NOT NULL)
- `created_by` (INT, NOT NULL)