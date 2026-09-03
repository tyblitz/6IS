# Changelog

## Version 0.1.4 (v0.1.4) - September 3, 2026

### Phase 4: Core Governance, Security & Audit
- **Comprehensive Audit Trail System**:
  - Introduced `tbl_audit_logs` storing immutable event records (`id`, `user_id`, `action`, `module_key`, `entity_type`, `entity_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`).
  - Added centralized backend `auditLog()` helper with automatic actor, IP, user-agent derivation, and recursive denylist sanitization (passwords, hashes, tokens, keys, secrets).
  - Enforced atomic database transactions coordinating state mutations with audit logging; audit write failure rolls back the state mutation.
  - Implemented read-only REST API endpoint (`/backend/api/core/audit/index.php`) enforcing `audit.view`, pagination, and multi-factor server-side SQL filters.
  - Built immutable frontend inspection view (`AdminAuditView.vue` at `/administrator/audit`) with date/time formatting (`DD HHmmH MMM YYYY`), semantic action badges, and detailed JSON state inspection.
- **Session & Cookie Security Hardening**:
  - Implemented session fixation protection with immediate `session_regenerate_id(true)` upon successful authentication.
  - Enforced `HttpOnly`, `SameSite=Lax`, and HTTPS-aware `Secure` session cookie flags.
- **Cross-Origin Resource Sharing (CORS) Hardening**:
  - Created centralized server-side allowlist (`backend/config/cors.php`, `backend/helpers/cors.php`) preventing arbitrary origin reflection and rejecting client-controlled header injections.
- **Cross-Site Request Forgery (CSRF) Protection**:
  - Implemented cryptographic token generation and validation (`backend/helpers/csrf.php`).
  - Required `X-CSRF-Token` header verification using `hash_equals()` on all mutating endpoints (`POST`, `PUT`, `PATCH`, `DELETE`).
  - Added lightweight frontend wrapper (`frontend/src/utils/api.ts`) automatically injecting active CSRF tokens on mutating requests.
- **System Governance & Invariant Guards**:
  - Added `is_system` flag (`DEFAULT 0`) to `tbl_permissions`, seeding official permissions with `is_system = 1` and protecting them against runtime tampering.
  - Protected system roles (`Administrator`, `User`) against rename, deactivation, and deletion.
  - Enforced final Administrator invariant preventing any mutation (deactivation, soft deletion, or role change) that leaves zero active Administrator users.
  - Enforced organization minimum active count invariant (`ACTIVE ORGANIZATIONS >= 1`).
- **Comprehensive Test Suite & Validation**:
  - Added automated PHP test suites (Suites 17 through 25) verifying audit trail logging, recursive sanitization, transactional rollback, session security, CORS allowlisting, CSRF rejection, and governance invariants.
  - Added Vitest unit test suite (`tests/unit/auditService.spec.ts`).

### Phase 3: Organization & Office Management
- **Single Organization Architecture**: Introduced `tbl_organization` to represent the single deployment organization (`6th Infantry Division`, `6ID`) with profile attributes, headquarters address, and contact details.
- **Offices Directory**: Introduced `tbl_offices` relational hierarchy with unique office codes per organization (`uq_org_office_code`), preserving backward-compatible aliases (`office_name`, `office_code`, `office_abbv`).
- **User-to-Office Association**: Added `tbl_users.office_id` foreign key constraint (`ON DELETE SET NULL`), single primary office association, and null-safe user handling.
- **Office Session Payload**: Extended `/backend/api/auth/index.php` to include `office_id`, `office_name`, and `office_code` on authentication check and login.
- **Deletion Dependency Protection**: Enforced zero-dependency deletion guards on offices with assigned users or historical activity across operational tables (`tbl_accomplishments`, `tbl_communications`, `tbl_inventory_equipment`).
- **Phase 3 Core Permissions**: Added 7 new system permissions (`organization.view`, `organization.configure`, `offices.view`, `offices.create`, `offices.edit`, `offices.delete`, `offices.configure`).
- **Administrator UI**:
  - Built `AdminOrganizationView.vue` at `/administrator/organization` with profile details and modal configuration.
  - Built `AdminOfficesView.vue` at `/administrator/offices` with search, status filters, user counts, and CRUD modal.
  - Updated `AdminUsersView.vue` with Office column, Office search filter, and active office selector.
  - Added launcher cards to `AdministratorView.vue`.
- **Test Coverage**: Added PHP CLI unit test suites (Suites 14, 15, 16), Vitest unit specs (`organizationService.spec.ts`, `officeService.spec.ts`), and Cypress E2E specs (`organization_offices.cy.ts`).

## Version 1.2.0 (v1.2.0) - September 2, 2026

### Phase 2: Core Roles & Permissions (RBAC)
- **Centralized Core RBAC**: Established `tbl_roles`, `tbl_permissions`, and `tbl_role_permissions` schema.
- **System Role Protection**: Guaranteed persistence and unmodifiable status for system roles (`Administrator`, `User`).
- **Granular Permissions Catalog**: 32 system permissions cataloging capabilities across Inventory, Communications, Calendar, Accomplishments, Users, Roles, and Modules.
- **Independent Configure Permission**: Decoupled `*.configure` capabilities from operational CRUD operations.
- **Fail-Closed Authorization**: Server-side middleware (`requirePermission()`) and reactive frontend composable (`usePermissions()`) ensuring zero security bypasses.
- **Interactive Role Matrix**: Built `AdminRolesView.vue` at `/administrator/roles` with interactive permission matrix modal.

## Version 0.1.1 (v0.1.1) - August 28, 2026

### Inventory Module & System Fixes
- **Relational Mapping Fix**: Corrected equipment type, subtype, and status column updates in `tbl_inventory_equipment` to preserve subpage filtering for Communications items (e.g., Public Address Systems).
- **Backend API Structure Alignment**: Refactored `backend/api/inventory/index.php` `view=equipment` and `view=jrrs` to return standardized `{ period, period_label, is_current, items: [...] }` objects.
- **Single Equipment Detail API**: Implemented single item lookup (`view=equipment&id=X`) with dynamic custom attribute values.
- **Defensive Frontend Normalization**: Updated `inventoryService.ts` and inventory views (`EquipmentView.vue`, `JRRS.vue`) with data parsing fallbacks.
- **Full Backend API Coverage**: Implemented missing POST endpoints for equipment CRUD, target updates, historical snapshots, equipment types, subtypes, statuses, and custom attribute definitions.

## Version 0.1 (v0.1.0) - Initial Release

### Core Features & Architecture
- **Unified Layout & Navigation**: Built reusable `MainLayout`, `AppHeader`, `AppBreadcrumb`, and `ModuleSidebar` components with active route highlighting for subpages and dynamic breadcrumb hierarchies.
- **Role-Based Security & Auth**: Configured PHP session security with role checks (`Administrator`, `User`) and secure password hashing.

### Inventory Module
- **Extensible Equipment Architecture**: Created equipment types (`ICT`, `Communications`), subtypes, status lookups, and flexible attribute definition value mappings.
- **Dynamic Subpages**: Added dedicated ICT and Communications subpages for both Inventory and Joint Repair & Replacement System (JRRS).
- **Client & Admin Views**: Created dedicated read-only and edit view pages (`AdminInventoryView.vue`) for item management.

### Communications Module
- **Incoming & Outgoing Tracking**: Full CRUD management for communications with category codes, office origin assignments, dates, status tracking, and dynamic age calculations.
- **Dedicated Read-Only & Edit Views**: Route-based detail pages (`CommunicationDetailView.vue`, `CommunicationEditView.vue`) formatted with `(Category Abbv) - (Subject)` headers and single-row detail summary cards.
- **Multiple Image Attachments**: Added multi-file image upload, database attachment table (`tbl_communication_attachments`), and interactive full-screen Lightbox Gallery.

### Accomplishment Module
- **Operational Activity Tracking**: Added today, annual, and custom period accomplishment logging with priority and status breakdown metrics.

### Automated Setup & Installation
- **1-Click Master Installer**: Created self-elevating `Setup_Everything_1Click.bat`, `Install_6IS.bat`, and `database/setup_db.php` script for automatic XAMPP deployment, database migration execution, and initial account seeding.
- **Inno Setup Script**: Configured Windows setup compiler script (`installer/6IS_Setup_Script.iss`) for output `6IS_Setup_v0.1.exe`.





