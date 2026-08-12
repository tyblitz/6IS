# Architectural Decisions

## Decision 1: Separate Reference Tables for Communication Categories and Purposes
- **Context**: Communication records require classification by category (e.g., Disposition Form, Memo) and processing purpose (e.g., Access Pass, PAS Request).
- **Decision**: Communication Categories (`tbl_communication_categories`) and Communication Purposes (`tbl_communication_purposes`) are separate reference tables. `tbl_communications` references both through dedicated foreign keys (`category_id` and `purpose_id`).
- **Rationale**: Categories define document classification while purposes define processing intent. Separating them prevents domain data pollution and ensures flexibility for future form design.

---

## Decision 2: Dynamic Communication Age Calculation from Event Date
- **Context**: Turnaround time and age monitoring are required for processing communications.
- **Decision**: Communication age is calculated dynamically from the latest `activity_date` (event date) in `tbl_communication_activities` relative to the current date, rather than stored as a static column in `tbl_communications`.
- **Rationale**: Storing static age fields causes data staleness. Computing age on-the-fly relative to activity event timestamps guarantees real-time accuracy.

---

## Decision 3: Explicit Communication Type (`communication_type`)
- **Context**: Incoming and Outgoing communications require distinct routing and filtering.
- **Decision**: `tbl_communications` stores an explicit `communication_type ENUM('Incoming', 'Outgoing')` column rather than inferring direction from category, purpose, office, or activity.
- **Rationale**: Direct explicit typing ensures clean filtering across module views without fragile heuristics.

---

## Decision 4: Controlled Activity History Creation & Soft Deletion Preservation
- **Context**: Processing history must record meaningful milestones without logging minor field edits.
- **Decision**: Process activity log entries are automatically created upon record creation (`Logged`) and status changes (`Status changed to ...`), or manually appended by users. Ordinary field edits do not generate activity logs. When a communication is soft-deleted (`deleted_at = NOW()`), its activity history rows remain preserved intact.
- **Rationale**: Keeps process activity timelines meaningful while ensuring full audit trail retention for historical data.

---

## Decision 5: Reuse of Single Authoritative Office Reference Table
- **Context**: Multiple modules (Accomplishments, Communications) require office organizational data.
- **Decision**: The system maintains a single authoritative office reference table (`tbl_offices`) rather than creating duplicate per-module office tables.
- **Rationale**: Reusing `tbl_offices` preserves data integrity and prevents domain duplication across 6IS modules.
