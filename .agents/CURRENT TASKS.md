# Current Tasks & Active Focus (6IS)

*Last Updated: 21 Sep 2026*

This document tracks active development goals, in-progress tasks, and immediate next steps. Updated at the start and completion of each task.

---

## 1. Active Focus
- **Current Objective**: Design System Unification Completed — Ready for Next Functional Backlog Milestone.
- **Branch**: `development`
- **Current Base Version**: `0.2.0`
- **Immediate Task**: Review next priorities: JRRS C4ISTAR baseline push, military reporting format ingestion, and template-based export engine.

---

## 2. Completed Items
- [x] Unify and centralize application-wide design system, typography scale, and color tokens across all 9 modules:
  - Centralized component styling into `forms.css`, `cards.css`, `buttons.css`, and `tables.css` imported via `main.css`.
  - Standardized `.btn-primary` (Royal Blue `#2563EB`, `6px` radius), `.btn-secondary` (White / Slate border), `.btn-danger`, `.btn-sm`, and `.btn-icon` (32x32px).
  - Maintained canonical backward-compatible aliases for all automated test selectors (`.btn-print`, `.btn-export-doc`, `.add-btn`, `.save-btn`, `.action-main-btn`, `.btn-primary-add`, etc.).
  - Standardized inputs, selects, textareas, focus rings (`rgba(37, 99, 235, 0.12)`), search boxes, and error alerts.
  - Standardized cards, table cards, summary cards, and modal dialog containers across modules.
  - Eliminated duplicate ad-hoc scoped styles and `#082f6d` hardcoded button overrides across Inventory, Accomplishments, Communications, Login, Calendar, and Administrator views.
- [x] Documented architectural and anti-divergence decisions:
  - Added **Decision 15** (Unified Design System, Tokenized Component Styles, and Anti-Divergence Standards) in `docs/decisions.md`.
  - Expanded `AGENTS.md` Section 2 with Component CSS specifications, mandatory test selector aliases, and the strict prohibition on local scoped overrides.
- [x] Executed full regression testing across all 5 verification layers (100% Green, 0 failures):
  - PHP Unit Tests: 189 / 189 passed (`modules_and_auth_test.php` 133, `budget_monitoring_test.php` 36, `edfs_test.php` 20).
  - Vitest Unit Tests: 71 / 71 passed across 11 test suites.
  - ESLint: 0 errors, 0 warnings across the entire repository.
  - Production Build: `vue-tsc --noEmit && vite build` passed cleanly.
  - Cypress E2E Suites: 29 / 29 passed across all 6 specs (`accomplishments.cy.ts`, `audit_governance.cy.ts`, `budget.cy.ts`, `organization_offices.cy.ts`, `roles_permissions.cy.ts`, `test.cy.ts`).

---

## 3. Pending / Upcoming Backlog
- [ ] Commit & push verified design system and baseline to `origin/development`.
- [ ] Receive updated military inventory format and templates from user.
- [ ] Implement template-based export engine for Accomplishments (`.docx`) and Inventory (`.xlsx`).
- [ ] Design and scaffold the Performance Monitoring Module (Annual Programs & Office Dashboards).

---

## 4. Checkpoints & Invariants
- **Testing Standard**: All 5 test suites must stay passing on every pass (PHP, Vitest, Lint, Build, Cypress).
- **Design System Invariant**: Centralized tokens in `theme.css` and modular component files (`buttons.css`, `cards.css`, `forms.css`, `tables.css`) must be used. Zero scoped button or input style overrides allowed.
- **Date/Time Formatting**: Adhere strictly to `AGENTS.md` standard (`DD MMM YYYY`, `HHmmH`).
- **Security & Integrity**: Preserve server-side CORS allowlist, CSRF validation, session regeneration, final Administrator protection, minimum active organization invariant, and sensitive audit field sanitization.


