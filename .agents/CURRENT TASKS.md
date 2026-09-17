# Current Tasks & Active Focus (6IS)

*Last Updated: 08 Sep 2026*

This document tracks active development goals, in-progress tasks, and immediate next steps. Updated at the start and completion of each task.

---

## 1. Active Focus
- **Current Objective**: EDFS Account Monitoring Refinements Complete (Office Derived from tbl_offices, Unified Username/Account Name, Soft Delete); Ready for Phase 1B: R&M Monitoring (Budget Monitoring).
- **Branch**: `development`
- **Current Base Version**: `0.2.0`
- **Key Milestones Delivered (17 1550H Sep 2026)**:
  1. **EDFS Master De-Duplication**: Merged `List for Creation of EDFS Accounts.docx` (124 accounts) and `List for Creation of Additional EDFS Accounts Sept 2026.docx` (169 accounts) into 286 de-duplicated master records based on user recommendations.
  2. **Office Short Name Integration**: Replaced arbitrary free-text `office_name` input with dynamic `<select>` dropdown populated strictly from `tbl_offices`. Derived short name (`office_code` / `office_name`) automatically stored on create/update.
  3. **Unified Login Credentials**: Unified `account_name` and `username` as identical login credentials across database, backend API, and frontend interface.
  4. **Soft Delete Architecture**: Added `deleted_at DATETIME NULL DEFAULT NULL` with index. Standard users and admins can create, edit, and soft delete records. API defaults to excluding soft-deleted accounts (`deleted_at IS NULL`) while preserving audit trail and data integrity.
  5. **Schema Cleanup**: Removed `source_doc` and `orig_nr` columns from `tbl_edfs_accounts`, backend API, and frontend views.
  6. **100% Quality Gate Passing**: All test suites passing (EDFS 20/20, Modules & Auth 133/133, Vitest 62/62, ESLint 0 errors, Vite production build clean).

---

## 2. In-Progress Items
- [x] Process and de-duplicate EDFS master lists per user specifications (AFPCOC/GSMO removed, OG4 Message Center de-duplicated, MAJ Delim replaced MAJ Talledo).
- [x] Create database migration & seed script `database/migrations/migrate_and_seed_edfs.php`.
- [x] Implement refinement: restrict `office_name` to short name from `tbl_offices` dropdown.
- [x] Implement refinement: unify `account_name` and `username` as the single login credential.
- [x] Implement refinement: add soft delete (`deleted_at`) with user/admin create, edit, and soft delete permissions.
- [x] Remove obsolete `source_doc` and `orig_nr` columns from database, API, and UI.
- [x] Create backend API `backend/api/edfs/index.php` (GET, POST, PUT, DELETE soft delete, search, filters, CSRF, audit).
- [x] Create frontend types, service, sidebar menu, routes, and `EdfsView.vue`.
- [x] Add EDFS card to `DashboardView.vue`.
- [x] Automated test suite `tests/unit/edfs_test.php` (20/20 passed).
- [x] Full regression verification (PHP Unit 20/20 & 133/133, Vitest 62/62, Lint 0 errors, Build clean).
- [ ] Next: Implement Phase 1B — R&M Monitoring (Budget Monitoring Module).

---

## 3. Pending / Upcoming Backlog
- [ ] Commit & push verified JRRS C4ISTAR baseline to `origin/development`.
- [ ] Receive updated military inventory format and templates from user.
- [ ] Implement template-based export engine for Accomplishments (`.docx`) and Inventory (`.xlsx`).
- [ ] Design and scaffold the Performance Monitoring Module (Annual Programs & Office Dashboards).

---

## 4. Checkpoints & Invariants
- **Testing Standard**: All 5 test suites must stay passing on every pass (PHP, Vitest, Lint, Build, Cypress).
- **Security & Integrity**: Preserve server-side CORS allowlist, CSRF validation, session regeneration, final Administrator protection, minimum active organization invariant, and sensitive audit field sanitization.
- **Date/Time Formatting**: Adhere strictly to `AGENTS.md` standard (`DD MMM YYYY`, `HHmmH`).

---

## 4. Checkpoints & Invariants
- **Testing Standard**: All 5 test suites must stay passing on every pass (PHP, Vitest, Lint, Build, Cypress).
- **Security & Integrity**: Preserve server-side CORS allowlist, CSRF validation, session regeneration, final Administrator protection, minimum active organization invariant, and sensitive audit field sanitization.
- **Date/Time Formatting**: Adhere strictly to `AGENTS.md` standard (`DD MMM YYYY`, `HHmmH`).
