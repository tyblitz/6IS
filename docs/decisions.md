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

---

## Decision 6: Dynamic Accomplishment Report Consolidation without Summary Tables
- **Context**: Consolidated reports (Monthly, Quarterly, Annual, Custom Period) are needed to aggregate daily office accomplishments and communication activity counts.
- **Decision**: Accomplishment summaries are generated dynamically on-the-fly directly from `tbl_accomplishments` and `tbl_communications` records. No pre-aggregated summary or report cache tables are created in the database.
- **Rationale**: Eliminates synchronization lag, prevents stale data rollups, simplifies database migrations, and guarantees strict soft-deletion exclusion across all views.

---

## Decision 7: Extensible Entity-Attribute-Value (EAV) Model for Equipment Inventory
- **Context**: Different equipment items (e.g., Desktops vs. Printers vs. Mixers) require distinct technical attributes without altering core table schemas.
- **Decision**: Equipment specifications utilize an Extensible EAV schema (`tbl_inventory_attribute_definitions` and `tbl_inventory_equipment_attribute_values`) linked to equipment subtypes.
- **Rationale**: Allows administrators to define dynamic custom technical attributes per subtype without requiring ALTER TABLE database schema migrations for new equipment specifications.

---

## Decision 8: Database-Backed Module Registry & Universal API Gatekeeper (Phase 1)
- **Context**: The platform required runtime configuration to enable or disable business modules without data loss or hardcoded arrays.
- **Decision**: `tbl_modules` serves as the authoritative database registry. Core modules (`dashboard`, `administrator`) have `is_core = 1` and cannot be disabled. Universal middleware `requireModuleActive()` blocks API access with HTTP 403 when a module is inactive.
- **Rationale**: Ensures zero data loss when deactivating modules while guaranteeing that platform foundation modules remain accessible.

---

## Decision 9: Granular RBAC with Authoritative `role_id` and System Role Guards (Phase 2)
- **Context**: Role authorization needed to evolve beyond coarse string checks to granular permissions across all application functions.
- **Decision**: Implemented `tbl_roles`, `tbl_permissions`, and `tbl_role_permissions`. `tbl_users.role_id` is the authoritative source of truth. System roles (`Administrator`, `User`) cannot be renamed, deactivated, or deleted. System permissions (`is_system = 1`) cannot be stripped from Administrator.
- **Rationale**: Prevents accidental administrative lockout while enabling flexible custom roles and fine-grained access control.

---

## Decision 10: Single-Tenant Organization Entity & Scoped Office Directory (Phase 3)
- **Context**: The platform needed enterprise organizational identity without introducing complex multi-tenancy overhead.
- **Decision**: Single-tenant `tbl_organization` entity with child `tbl_offices`. Offices enforce code and name uniqueness scoped to the organization.
- **Rationale**: Satisfies enterprise organizational requirements while keeping architectural simplicity and performance high.

---

## Decision 11: Office Deletion Protection & Cross-Module Dependency Guard (Phase 3)
- **Context**: Deleting an office could orphan historical equipment, accomplishment, communication, or calendar records.
- **Decision**: Office deletion performs comprehensive dependency checks across all 6IS tables. If any records (active or historical) reference the office, deletion is blocked with HTTP 409 Conflict.
- **Rationale**: Strictly preserves operational history and relational database integrity.

---

## Decision 12: Centralized Immutable Audit Trail with Recursive Sanitization & Atomic Transaction Coupling (Phase 4)
- **Context**: Enterprise governance requires tamper-evident audit logs of all administrative mutations and authentication events.
- **Decision**: `tbl_audit_logs` records state snapshots (`old_values`, `new_values`) alongside actor and IP metadata. State mutations and audit log writes are coupled in atomic PDO transactions. If audit writing fails, the mutation rolls back. Recursive sanitization automatically redacts passwords, tokens, secrets, and session IDs.
- **Rationale**: Guarantees zero un-audited state mutations while preventing credential leakage into logs.

---

## Decision 13: Strict Server-Side CORS Allowlist & Header-First CSRF Enforcement (Phase 4)
- **Context**: Mitigating cross-origin abuse and cross-site request forgery without degrading API ergonomics.
- **Decision**: Server-side allowlist for CORS comparing incoming `Origin` without trusting client-supplied headers. CSRF validation requires `X-CSRF-Token` on all mutating HTTP methods (`POST`, `PUT`, `PATCH`, `DELETE`) with constant-time verification.
- **Rationale**: Closes security attack vectors while supporting clean frontend API integration via centralized client helpers.

---

## Decision 14: Platform Invariants: Final Administrator & Active Organization Protection (Phase 4)
- **Context**: Administrative mutations must never leave the system in an unmanageable or orphaned state.
- **Decision**: Server evaluates the resulting state of all user mutations. If a mutation would leave zero active Administrator accounts (via deactivation, soft deletion, or role change), it is rejected with HTTP 400. Administrators cannot self-deactivate. Deactivating the last active organization is similarly rejected.
- **Rationale**: Guarantees continuous platform availability and administrative governance.

