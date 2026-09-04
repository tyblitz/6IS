# 6IS System Architecture

## Architecture Overview

The **6IS (Integrated Information System)** follows an extensible, modular client-server architecture:

```text
Vue 3 Views / Components (Ionic Vue + TypeScript)
       ↓
Vue Router (Route-based Module Resolution, RBAC & Breadcrumbs)
       ↓
TypeScript Services (frontend/src/services/<module>Service.ts)
       ↓
Centralized API Client (frontend/src/utils/api.ts — Automatic CSRF & Session Credentials)
       ↓
PHP REST API Endpoints (backend/api/<module>/index.php)
       ↓
Security & Governance Middleware (CORS Allowlist, CSRF Verification, RBAC, Module Gatekeeper, Audit)
       ↓
MySQL Database (db_ict_system)
```

---

## Layout Architecture

1. **Dashboard (`/home`)**:
   - Application launcher view presenting registered, active module cards.
   - Operates without a sidebar.

2. **Module Layout (`MainLayout.vue`)**:
   - `AppHeader`: Top branding, active office badge, user session display, and logout.
   - `AppBreadcrumb`: Route path hierarchy navigation.
   - `ModuleSidebar`: Dynamic sidebar navigation rendered from `route.meta.module`, filtered by active module state and user permissions.
   - `AppContent`: View slot containing active page content.
   - `AppFooter`: Footer metadata, version display (`v0.2.0`), and copyright.

---

## Core Platform & Business Modules

### Core Platform Modules (Non-disableable, `is_core = 1`)
- **Dashboard (`dashboard`)**: Route `/home`. Central platform entry point.
- **Administrator (`administrator`)**: Route `/administrator`. Administrative management, system security, and governance.

### Business Modules (Extensible, Configurable `is_core = 0`)
- **Communications (`communications`)**: Route `/communications`. Document tracking, incoming/outgoing logs, and turnaround analytics.
- **Inventory (`inventory`)**: Route `/inventory`. Equipment registry, JRRS target comparisons, and frozen monthly history.
- **Accomplishments (`accomplishments`)**: Route `/accomplishments`. Daily accomplishment logs, productivity reports, and dynamic consolidation.
- **Calendar (`calendar`)**: Route `/calendar`. Event management, scheduling, conflict monitoring, and accomplishment linking.

---

## Backend API Endpoints & Core Infrastructure

### Core Administration APIs
- **Authentication**: `backend/api/auth/index.php` (Session login, fixation regeneration, logout, session bootstrap, CSRF token issuance).
- **Audit Logs**: `backend/api/core/audit/index.php` (Read-only immutable audit trail inspection, filtering, pagination).
- **Module Registry**: `backend/api/core/modules/index.php` (Module listing, activation toggles, core protection).
- **Roles & Permissions**: `backend/api/core/roles/index.php` & `backend/api/core/permissions/index.php` (RBAC matrix, system role guards, permission assignments).
- **Organization Profile**: `backend/api/core/organization/index.php` (Enterprise profile management, minimum active invariant).
- **Offices Directory**: `backend/api/core/offices/index.php` (Offices CRUD, dependency guards, user counts).
- **User Management**: `backend/api/users/index.php` (User accounts, office assignments, role synchronization, final Administrator protection).

### Business Module APIs
- **Accomplishments REST API**: `backend/api/accomplishments/index.php`
- **Communications REST API**: `backend/api/communications/index.php`
- **Inventory REST API**: `backend/api/inventory/index.php`
- **Calendar REST API**: `backend/api/calendar/index.php`

All endpoints return a standardized JSON envelope:
```json
{
  "success": true,
  "message": "Status description",
  "data": { ... },
  "errors": null
}
```

---

## Security & Governance Architecture

1. **Strict Server-Side CORS Hardening**:
   - Server-defined allowlist in `backend/config/cors.php` and `backend/helpers/cors.php`.
   - Incoming `Origin` strictly compared against server allowlist. Non-matching origins receive no reflection.
   - Preflight `OPTIONS` requests from unauthorized origins return `HTTP 403 Forbidden`.
   - Client headers (e.g. `HTTP_APP_ORIGIN`) are prohibited from expanding the allowlist.

2. **Header-First CSRF Token Defense**:
   - 256-bit cryptographically secure token generated on login and session bootstrap (`backend/helpers/csrf.php`).
   - Constant-time verification via `hash_equals()`.
   - All mutating requests (`POST`, `PUT`, `PATCH`, `DELETE`) require a valid `X-CSRF-Token` header. Safe methods (`GET`, `OPTIONS`, `HEAD`) are exempt.
   - Native frontend client (`frontend/src/utils/api.ts`) automatically injects the active token.

3. **Session Fixation & Cookie Hardening**:
   - `session_regenerate_id(true)` executed immediately upon successful authentication.
   - Cookie flags: `HttpOnly = true`, `SameSite = Lax`, and HTTPS-aware `Secure`.

4. **Centralized Audit Logging & Atomic Transaction Coupling**:
   - Centralized helper `auditLog()` in `backend/helpers/audit.php` automatically records user ID, client IP, User-Agent, and timestamps.
   - **Recursive Sanitization**: Case-insensitive denylist recursively scrubs passwords, tokens, secrets, session IDs, and API keys into `[REDACTED]`.
   - **Transactional Atomicity**: State mutations and audit writes are coupled in atomic database transactions. If audit logging fails, the transaction rolls back.

5. **Platform Governance Invariants**:
   - **Final Administrator Guard**: Rejects deactivations, deletions, or role changes that would leave zero active Administrator accounts.
   - **Self-Deactivation Guard**: Administrator accounts cannot deactivate their own active accounts.
   - **System Role & Permission Protection**: System roles (`Administrator`, `User`) and 40 official system permissions (`is_system = 1`) cannot be deleted, renamed, or stripped.
   - **Minimum Active Organization Guard**: Rejects deactivating the sole active organization.

---

## Database Architecture

- **Database**: MySQL (`db_ict_system`)
- **Naming Convention**: `tbl_` prefix for database tables.
- **Audit Fields**: Every table contains `created_at`, `updated_at`, `created_by`, `modified_by`, and `deleted_at` (for soft deletes).
- **Reference Data Pattern**: Reference tables use `is_active` (TINYINT) to control form selection availability without breaking historical relational integrity.
- **Relational Integrity**: Foreign keys enforce `RESTRICT` on critical operational dependencies (preventing deletion of referenced offices, categories, or parent entities).
