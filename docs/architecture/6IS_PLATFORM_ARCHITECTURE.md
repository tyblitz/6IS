# 6IS Platform Architecture & Modular Evolution Roadmap

## 1. Executive Summary & Current State (Phase 0 + Phase 1)

The **6IS** platform is evolving from a collection of loosely-coupled monolithic modules into an extensible, configurable enterprise platform. 

This document defines the architectural boundaries established during **Phase 0 (Core Stabilization)** and **Phase 1 (Database-Backed Module Registry)**, and outlines the step-by-step roadmap toward a full modular architecture.

```
+-----------------------------------------------------------------------------------+
|                                   6IS CORE                                        |
|  +---------------------+  +----------------------+  +--------------------------+  |
|  | Authentication &    |  | Database-Backed      |  | System Administration &  |  |
|  | Session Security    |  | Module Registry      |  | Route Gatekeeper Guards  |  |
|  +---------------------+  +----------------------+  +--------------------------+  |
+-----------------------------------------------------------------------------------+
                                         │
        ┌────────────────────────────────┼────────────────────────────────┐
        ▼                                ▼                                ▼
+───────────────────+          +───────────────────+          +───────────────────+
| INVENTORY MODULE  |          |   COMMUNICATIONS  |          |  CALENDAR MODULE  |
| Equipment & JRRS  |          | Log & Dispatch    |          | Schedule Hub      |
+───────────────────+          +───────────────────+          +───────────────────+
        │                                │                                │
        ▼                                ▼                                ▼
+───────────────────+          +───────────────────+          +───────────────────+
|  ACCOMPLISHMENTS  |          | PERFORMANCE (NEW) |          |  FINANCES (NEW)   |
| Daily & Metrics   |          | Operations KPI    |          | Operational Cost  |
+───────────────────+          +───────────────────+          +───────────────────+
```

---

## 2. Core vs. Module Architectural Principles

### A. What 6IS Core Owns
6IS Core is the foundational platform runtime responsible for:
1. **Identity & Session Security (`backend/helpers/auth.php`)**: Authoritative session validation (`requireAuth()`), role authorization (`requireRole()`), and strict refusal of fallback privileges.
2. **Authoritative Module Registry (`tbl_modules`)**: The centralized database source of truth defining registered modules, versions, routing, display order, core flags, and activation status.
3. **Gatekeeper Enforcement (`backend/helpers/modules.php`)**: Universal API middleware (`requireModuleActive()`) preventing unauthorized invocation of disabled business logic.
4. **Shell & Navigation Orchestration (`MainLayout.vue`, `router/index.ts`, `useModules.ts`)**: Global responsive shell, navigation guard preventing direct URL access to disabled modules, and dynamic sidebar menu filtering.
5. **Administrator Management Hub (`/administrator/modules`)**: Administrative configuration interfaces, system audit trails, and core platform safeguards.

### B. What Business Modules Own
Business modules (Inventory, Communications, Calendar, Accomplishments) encapsulate specific operational domains:
- Their own database schemas, tables, and transactional logic.
- Domain-specific views, forms, and business calculations (e.g. readiness rates, dispatch workflows, activity rescheduling).
- **Critical Invariant**: Modules **MUST DEPEND ON CORE**, never on each other directly. If a module requires data from another module, it communicates via defined core service contracts or interfaces.

---

## 3. Module Lifecycle States

The 6IS Module Registry manages the following lifecycle states:

| Lifecycle State | Description | In Database | In Navigation | In API | Data Preserved? |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **Core (Protected)** | Essential platform foundation (`dashboard`, `administrator`). Cannot be disabled. | `is_core = 1`<br>`is_active = 1` | Always visible | Always accessible | Yes |
| **Active** | Fully operational business module (`inventory`, `calendar`, etc.). | `is_core = 0`<br>`is_active = 1` | Visible | Allowed | Yes |
| **Disabled (Inactive)** | Temporarily suspended module. | `is_core = 0`<br>`is_active = 0` | Hidden | Blocked (HTTP 403) | **100% Intact (Zero Data Loss)** |
| **Uninstalled / Planned** | Registered in catalog but not yet released or active (`performance`, `finances`). | `is_core = 0`<br>`is_active = 0`<br>`route = NULL` | Hidden | Blocked | Schema ready |

### The Data Safety Guarantee
Deactivating or disabling a module **NEVER** executes `DROP TABLE`, `DELETE`, or alters underlying domain tables. Toggling activation only changes the gatekeeper flag in `tbl_modules`. Re-activating a module instantly restores access with all historical records and configurations unchanged.

---

## 4. Phase 0 Implementation Detail (Core Security Stabilization)

1. **Elimination of Privilege Escalation Fallback**:
   - Previous implementation had an unauthenticated fallback: `$_SESSION['user_id'] = 1; $_SESSION['role'] = 'Administrator';`.
   - **Remediation**: Completely removed fallback. Unauthenticated requests strictly return HTTP 401 JSON:
     ```json
     {
       "success": false,
       "message": "Unauthorized. Authentication required."
     }
     ```
2. **Session Preservation**:
   - Legitimate login sessions via `/backend/api/auth/index.php?action=login` assign `$_SESSION['user_id']`, `$_SESSION['role']`, and `$_SESSION['username']`.
   - `requireRole($requiredRole)` strictly verifies roles and returns HTTP 403 JSON on mismatch.
3. **Frontend Redirection**:
   - `router.beforeEach` intercepts unauthenticated transitions to protected routes and redirects to `/login?redirect=...`.

---

## 5. Phase 1 Implementation Detail (Database-Backed Module Registry)

1. **Database Schema (`tbl_modules`)**:
   ```sql
   CREATE TABLE IF NOT EXISTS tbl_modules (
       id INT AUTO_INCREMENT PRIMARY KEY,
       module_key VARCHAR(100) NOT NULL UNIQUE,
       name VARCHAR(150) NOT NULL,
       description TEXT NULL,
       icon VARCHAR(100) NULL,
       route VARCHAR(255) NULL,
       is_core TINYINT(1) NOT NULL DEFAULT 0,
       is_active TINYINT(1) NOT NULL DEFAULT 1,
       sort_order INT NOT NULL DEFAULT 0,
       version VARCHAR(30) NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
2. **Idempotent Migration Seed (`database/migrations/create_modules_table.sql`)**:
   - Seeds 8 official modules: `dashboard` (core), `inventory`, `communications`, `calendar`, `accomplishments`, `performance` (inactive), `finances` (inactive), and `administrator` (core).
   - `ON DUPLICATE KEY UPDATE` explicitly omits `is_active`, ensuring administrative toggles are preserved across repeat migrations.
3. **Backend Middleware (`backend/helpers/modules.php`)**:
   - `isModuleActive(string $moduleKey, ?PDO $pdo = null): bool`
   - `requireModuleActive(string $moduleKey, ?PDO $pdo = null): void` (terminates with HTTP 403 on disabled module).
   - Enforced in all entry points (`inventory/index.php`, `communications/index.php`, `accomplishments/index.php`, `calendar/index.php`).
   - Order enforced: `requireAuth()` **BEFORE** `requireModuleActive()` to protect module status disclosure.
4. **Core Modules API (`backend/api/core/modules/index.php`)**:
   - `GET`: Authenticated users fetch registered modules ordered by `sort_order`.
   - `PATCH`: Administrator-only toggle with server-side validation rejecting any attempt to deactivate `is_core = 1` modules (HTTP 400).
5. **Frontend State & Navigation**:
   - Types: `SystemModule` interface in `frontend/src/types/module.ts`.
   - Service: `frontend/src/services/moduleService.ts` using native `fetch()` and `credentials: 'include'`.
   - Composable: `frontend/src/composables/useModules.ts` managing reactive module registry cache.
   - Router Guard: `router.beforeEach` blocks disabled module routes and redirects to `/home`.
   - Dynamic Navigation: `ModuleSidebar.vue` filters menu links; `DashboardView.vue` conditionally hides launcher cards.
   - Admin UI: `AdminModulesView.vue` at `/administrator/modules` with lock badges, summary counters, and confirmation dialogs.

---

## 6. Phase 2 Implementation Detail (Core Roles & Permissions / RBAC)

1. **Core Ownership of Authorization**:
   - Roles and permissions are owned strictly by 6IS Core, not distributed across ad-hoc business modules.
   - Backend is the authoritative security boundary. Frontend checks are exclusively for UI/UX visibility.

2. **Relational Database Schema**:
   - `tbl_roles`: System and custom roles (`name`, `description`, `is_system`, `is_active`).
   - `tbl_permissions`: Granular capabilities catalog (`module_key`, `permission_key`, `name`, `code`, `is_active`).
   - `tbl_role_permissions`: Relational join table mapping assigned permissions to roles (`role_id`, `permission_id`).
   - `tbl_users.role_id`: Relational foreign key constraint to `tbl_roles(id)`. Legacy `role` string column is kept synchronized for backwards compatibility.

3. **System Roles & Safety Guardrails**:
   - `Administrator` and `User` are permanent system roles (`is_system = 1`).
   - System roles cannot be deleted, renamed, or deactivated.
   - Administrator cannot be locked out; `roles.configure` is protected from being revoked.
   - Custom roles assigned to active users cannot be deleted until users are reassigned.

4. **Independent `configure` Permission Rule**:
   - The `configure` permission governs reference tables, metadata categories, and system settings independently.
   - Granting `configure` (e.g. `inventory.configure`) does **not** grant `view`, `create`, `edit`, or `delete`.

5. **Module Activation & Permissions Invariant**:
   - Module activation and permissions are separate concepts:
     - `requireModuleActive($moduleKey)` answers: *Is this functionality enabled on the system?*
     - `requirePermission($moduleKey, $permKey)` answers: *Is this user allowed to perform this operation?*
   - Both must succeed. If a module is inactive, even an Administrator with all permissions receives HTTP 403:
     ```json
     {
       "success": false,
       "message": "Module 'inventory' is currently disabled on this system."
     }
     ```

6. **Core Permission Helper (`backend/helpers/permissions.php`)**:
   - `getUserPermissions(int $userId, ?PDO $pdo): array`
   - `hasPermission(string $moduleKey, string $permissionKey, ?PDO $pdo): bool`
   - `requirePermission(string $moduleKey, string $permissionKey, ?PDO $pdo): void`

7. **Production APIs**:
   - `backend/api/core/roles/index.php`: Full CRUD, system protection, transactional permission assignment (`?action=permissions`).
   - `backend/api/core/permissions/index.php`: System catalog of permissions grouped by module with active module indicators.
   - `backend/api/auth/index.php`: Returns authenticated user with `role_id` and effective `permissions` array.
   - `backend/api/users/index.php`: Resolves and synchronizes `role_id` and `role` string on create/update.
   - Business module APIs (`inventory`, `communications`, `calendar`, `accomplishments`, `modules`) enforced with granular `requirePermission()`.

8. **Frontend RBAC Architecture**:
   - Types (`frontend/src/types/permission.ts`): `Role`, `Permission`, `GroupedModulePermissions`.
   - Service (`frontend/src/services/roleService.ts`): Native `fetch()` client for roles and permissions.
   - Composable (`frontend/src/composables/usePermissions.ts`): Reactive helper (`hasPermission`, `can`, `isPermitted`, `isAdmin`) with **fail-closed** behavior for unauthenticated/unresolved state.
   - Router Navigation Guard (`router.beforeEach`): Enforces `to.meta.permission` and redirects unauthorized users to `/home`. Note: `/home` remains accessible without business permission requirements.
   - Administrator UI:
     - `AdminRolesView.vue` at `/administrator/roles`: Role list, KPI pills, create/edit modal, and interactive permission matrix grouped by module with inactive module badges.
     - `AdministratorView.vue`: Launcher card linking to `/administrator/roles`.
     - `AdminUsersView.vue`: Dynamic role dropdown bound to `role_id`.

---

## 7. Phase 3 Implementation Detail (Organization & Office Management)

Phase 3 establishes the centralized organizational backbone of 6IS, making the platform organization-aware and office-aware while strictly adhering to core platform principles.

```
6IS CORE
├── Authentication
├── Organization (tbl_organization)
├── Offices (tbl_offices)
├── Users (tbl_users.office_id)
├── Roles (tbl_roles)
├── Permissions (tbl_permissions)
├── Module Registry (tbl_modules)
└── Audit & System Administration
```

1. **Single-Organization Deployment Architecture (`tbl_organization`)**:
   - 6IS operates on a single organization per deployment model (e.g. `6th Infantry Division`, `6ID`).
   - Generic multi-company isolation, tenant switching, accounting multi-currency, and tenant isolation overhead are deliberately omitted.
   - Database schema: `tbl_organization` (`id`, `name`, `short_name`, `description`, `address`, `contact_number`, `email`, `logo_path`, `is_active`, `created_at`, `updated_at`).
   - Default primary record (ID = 1) is automatically seeded and preserved.

2. **Organizational Offices Directory (`tbl_offices`)**:
   - Relational child of `tbl_organization` (`organization_id` foreign key).
   - Code uniqueness enforced within the organization: `UNIQUE KEY uq_org_office_code (organization_id, code)`.
   - Backward compatibility: Preserves existing operational unit aliases (`office_name`, `office_code`, `office_abbv`) so that legacy queries in operational modules continue to function without breakage.
   - Deactivation preference: Offices can be deactivated (`is_active = 0`) to retire units while preserving all historical references.
   - Deletion protection guard: Physical deletion of an office is strictly rejected (HTTP 400) if user accounts are assigned or if historical business module records reference the office (`tbl_accomplishments`, `tbl_communications`, `tbl_inventory_equipment`).

3. **User-to-Office Association (`tbl_users.office_id`)**:
   - Each user account has an optional primary office assignment (`office_id INT NULL`).
   - Relational integrity: Foreign key constraint `fk_users_office` with `ON DELETE SET NULL`.
   - Null-tolerant: Users without an assigned office (e.g. external auditors, unassigned personnel) remain fully operational with zero authentication or permission impediments.
   - User session integration: `/backend/api/auth/index.php` returns `office_id`, `office_name`, and `office_code` on login and session retrieval.

4. **Phase 3 Core Permissions Catalog**:
   - Seeded in `tbl_permissions` and governed by Core RBAC:
     - `organization.view`: View organization identity and profile (Admin, User baseline).
     - `organization.configure`: Edit and configure organization profile (Admin only).
     - `offices.view`: View organizational offices directory (Admin, User baseline).
     - `offices.create`: Register a new organizational office (Admin only).
     - `offices.edit`: Update office metadata and toggle active status (Admin only).
     - `offices.delete`: Delete an office with zero dependencies (Admin only).
     - `offices.configure`: Manage office settings and categories (Admin only).

5. **Scope Boundary — Business Modules Not Yet Office-Scoped**:
   - Phase 3 establishes the organizational foundations in Core.
   - **Business module data filtering (e.g. filtering inventory equipment, communications, or accomplishments by office) is NOT implemented in Phase 3**.
   - Business modules will consume `tbl_offices` and user office assignments in subsequent phases.

6. **Production APIs**:
   - `backend/api/core/organization/index.php`:
     - `GET`: Returns organization details (requires `organization.view`).
     - `PATCH` / `POST`: Updates organization identity with input validation (requires `organization.configure`).
   - `backend/api/core/offices/index.php`:
     - `GET`: Returns offices list with assigned `user_count`, search filter, and active-only flag (requires `offices.view`).
     - `POST`: Creates new office with code/name uniqueness enforcement (requires `offices.create`).
     - `PATCH`: Updates office details and toggles active state (requires `offices.edit`).
     - `DELETE`: Enforces zero-dependency safety check before deleting office (requires `offices.delete`).
   - `backend/api/users/index.php`:
     - `GET`: Returns users with `office_id`, `office_name`, `office_code`.
     - `POST` (`create`, `update`): Validates and associates `office_id` (requires active office).

7. **Frontend Architecture**:
   - Types: `frontend/src/types/organization.ts`, `frontend/src/types/office.ts`, and updated `frontend/src/types/user.ts` & `frontend/src/types/auth.ts`.
   - Services: `organizationService.ts` and `officeService.ts`.
   - Administrator UI Views:
     - `AdminOrganizationView.vue` at `/administrator/organization`: Enterprise profile card, contact details, headquarters location, and edit modal.
     - `AdminOfficesView.vue` at `/administrator/offices`: Offices directory table, user count badges, search filter, status filter, create/edit modal, and safe deletion confirmation.
     - `AdminUsersView.vue`: Office column, office filter, and office selector in user creation/edit modal.
     - `AdministratorView.vue`: Organization Profile and Offices Management launcher cards.

---

## 8. Evolution Roadmap (Future Phases)

```
Phase 0, 1, 2 & 3 (COMPLETED)
Core Stabilization + Module Registry + Core RBAC + Organization & Offices
   │
   ▼
Phase 4 (Planned)
Declarative Module Manifests (module.json)
   │
   ▼
Phase 5 (Planned)
Modular App Store & Guided Setup Wizard
```

### Phase 4: Declarative Module Manifests (`module.json`)
- Each business module self-defines:
  - Metadata, version, and dependencies.
  - Navigation entries and icon identifiers.
  - Database schema migrations and seed scripts.
- Core discovers and registers modules automatically without requiring manual schema updates.

### Phase 5: Modular App Store & Guided Setup Wizard
- Interactive graphical installer allowing administrators to enable/disable module packs on initial system setup.
- Versioned upgrade engine running module-specific database migrations safely.
