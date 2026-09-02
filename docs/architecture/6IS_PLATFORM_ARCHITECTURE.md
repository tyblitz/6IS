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

## 6. Evolution Roadmap (Future Phases)

```
Phase 0 & 1 (COMPLETED)
Core Stabilization + Database Module Registry
   │
   ▼
Phase 2 (Planned)
Granular Permissions & Dynamic Role Engine
   │
   ▼
Phase 3 (Planned)
Multi-Tenant Organization & Office Hierarchy
   │
   ▼
Phase 4 (Planned)
Declarative Module Manifests (module.json)
   │
   ▼
Phase 5 (Planned)
Modular App Store & Guided Setup Wizard
```

### Phase 2: Granular Permissions & Dynamic Role Engine
- Transition from rigid static roles (`Administrator`, `User`) to permission matrix (`inventory.view`, `inventory.edit`, `calendar.schedule`, etc.).
- Allow system administrators to create custom roles and bind them to specific active modules.

### Phase 3: Multi-Tenant Organization & Office Hierarchy
- Contextualize module activations by organizational branch or unit (e.g. Unit A enables Inventory, Unit B enables Communications).
- Data isolation driven by tenant identifier while sharing the core platform codebase.

### Phase 4: Declarative Module Manifests (`module.json`)
- Each business module self-defines:
  - Metadata, version, and dependencies.
  - Navigation entries and icon identifiers.
  - Database schema migrations and seed scripts.
- Core discovers and registers modules automatically without requiring manual schema updates.

### Phase 5: Modular App Store & Guided Setup Wizard
- Interactive graphical installer allowing administrators to enable/disable module packs on initial system setup.
- Versioned upgrade engine running module-specific database migrations safely.
