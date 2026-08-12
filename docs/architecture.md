# 6IS System Architecture

## Architecture Overview

The system follows a modular, decoupled client-server architecture:

```text
Vue 3 Views / Components (Ionic Vue + TypeScript)
       ↓
Vue Router (Route-based Module Resolution & Breadcrumbs)
       ↓
TypeScript Services (frontend/src/services/<module>Service.ts)
       ↓
PHP REST API Endpoints (backend/api/<module>/index.php)
       ↓
MySQL Database (db_ict_system)
```

---

## Layout Architecture

1. **Dashboard (`/home`)**:
   - Application launcher view.
   - Operates without a sidebar.

2. **Module Layout (`MainLayout.vue`)**:
   - `AppHeader`: Top branding and user session display.
   - `AppBreadcrumb`: Route path hierarchy navigation.
   - `ModuleSidebar`: Dynamic sidebar navigation rendered from `route.meta.module`.
   - `AppContent`: View slot containing active page content.
   - `AppFooter`: Footer metadata and copyright display.

---

## Backend API Endpoints

- **Accomplishments REST API**: `backend/api/accomplishments/index.php`
- **Communications REST API**: `backend/api/communications/index.php`

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

## Database Architecture

- **Database**: MySQL (`db_ict_system`)
- **Naming Convention**: `tbl_` prefix for database tables.
- **Audit Fields**: Every table contains `created_at`, `updated_at`, `created_by`, `modified_by`, and `deleted_at` (for soft deletes).
- **Reference Data Pattern**: Reference tables use `is_active` (TINYINT) to control form selection availability without breaking historical relational integrity.
