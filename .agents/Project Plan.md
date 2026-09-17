# 6IS Master Project Plan & Architectural Alignment

*Created: 17 Sep 2026*  
*Maintained in: `.agents/Project Plan.md`*

This document serves as the single source of truth for the **6th Infantry Division Information System (6IS)** architecture, module definitions, operational priorities, and phased implementation milestones.

---

## 1. Project Overview & Architectural Decisions

### 1.1 Organizational Context
- **Organization**: 6th Infantry Division (6IS), supporting Headquarters (e.g., Office of the Assistant Chief of Staff for C4S / G6), subordinate brigades, battalions, and specialized staff offices.
- **Multi-Office Tenancy (Option C — Module-Specific Boundaries)**:
  - Every user account is strictly assigned to one primary Office (`tbl_offices`).
  - **Isolated Modules**: Specific data is scoped strictly to the user's assigned office (e.g., *Budget Monitoring*, *Workstation Accounts*).
  - **Collaborative Modules**: Data is shared across the organization with office tagging and filtering (e.g., *Calendar of Activities*, *Communications*, *Inventory* with division-wide G6 readiness rollups).
  - **Executive Oversight**: Organization Administrators and Command leadership maintain cross-office visibility.

### 1.2 Deployment Model
- **Current Phase**: Independent on-premises installations (e.g., per unit/installation on local server/XAMPP). System Admins activate or deactivate modules per installation via the Module Registry (`tbl_modules`).
- **Future Phase (Phase 4)**: Seamless migration to a centralized multi-office server without schema re-architecting.

### 1.3 Template-Driven Document Output
- Official military reporting requires that exports duplicate exact uploaded military templates (`.xlsx`, `.docx`) and insert live system data into predefined tables, bookmarks, and cells (e.g., `Monthly Accomplishment Report.docx`, `Monthly Inventory Report.xlsx`, `JRRS Monthly Report.xls`).

### 1.4 Decoupled Architecture
- Modules remain independent and decoupled initially:
  - *Accomplishments* do not automatically score *Performance Monitoring*.
  - *Workstation Accounts* remain an independent credential/terminal ledger without requiring a foreign key link to *Inventory Equipment* for now.

---

## 2. Updated Phasing & Priorities Matrix

```
[Phase 1A: EDFS Accounts]  ──►  [Phase 1B: R&M / Budget]  ──►  [Phase 1C: Inventory Finalization]
                                                                        │
[Phase 1E: Performance]    ◄──  [Phase 1D: Accomplishments DOCX]  ◄─────┘
         │
         ▼
[Phase 2: Communications & Calendar]  ──►  [Phase 3: Workstation Accounts]  ──►  [Phase 4: Centralized Server]
```

| Phase | Priority Level | Module(s) | Primary Deliverable |
| :--- | :---: | :--- | :--- |
| **Phase 1A** | **IMMEDIATE** | **EDFS Account Monitoring** | Fast, clean multi-office ledger (*Name, Designation, Office, Username, Remarks*). |
| **Phase 1B** | **IMMEDIATE** | **Budget Monitoring (R&M Focus)** | Monthly APB/APP ledger + Dedicated subpage monitoring office requests for R&M ICT & R&M Comms. |
| **Phase 1C** | **HIGH** | **Inventory Module** | Ingest updated military inventory format + Exact-template Excel export for JRRS and Monthly Inventory. |
| **Phase 1D** | **HIGH** | **Accomplishments Module** | Exact-template DOCX export for Monthly/Quarterly Accomplishment reports. |
| **Phase 1E** | **HIGH** | **Performance Monitoring** | Annual Office Programs connected to higher office directives, milestones, and office dashboards. |
| **Phase 2** | **MEDIUM** | **Communications & Calendar** | Radio net logbook, signal dispatch records, facility/VTC scheduling, and military activity calendar. |
| **Phase 3** | **MEDIUM** | **Workstation Accounts** | Local PC credential registry (*Office, Username, Password, Admin, Password, IP*) with encrypted storage & masked UI. |
| **Phase 4** | **ENTERPRISE** | **Centralized Server** | Central multi-tenant database migration and cross-office division rollup. |

---

## 3. Detailed Module Specifications

### Module 1: Core Infrastructure & Authentication
- **Status**: **COMPLETE & HARDENED**
- **Highlights**: PHP session management with regeneration, CSRF tokens on mutations, CORS security, and user-to-office resolution.

### Module 2: Administration & Governance
- **Status**: **COMPLETE & HARDENED**
- **Highlights**: Module registry (`tbl_modules`) with activation toggles, granular RBAC (14 protected core permissions), Organization and Office administration, and recursively sanitized audit logging.

### Module 3: EDFS Account Monitoring *(Priority 1A)*
- **Status**: **READY FOR SCAFFOLDING**
- **Operational Scope**: Tracking Electronic Document Filing System accounts across offices.
- **Data Model (`tbl_edfs_accounts`)**:
  - `id`: INT (PK, Auto-Increment)
  - `personnel_name`: VARCHAR(255) — Full Name and Rank
  - `designation`: VARCHAR(255) — Position (e.g., *Action Officer, Chief Clerk, Encoder*)
  - `office_id`: INT (FK to `tbl_offices`) — Assigned office
  - `account_username`: VARCHAR(100) — EDFS account ID/username
  - `status`: ENUM(`Active`, `Inactive`, `For Renewal`) — Default `Active`
  - `remarks`: TEXT — Notes, authority, endorsement references
  - `created_at` / `updated_at`: TIMESTAMP
- **Permissions**: `edfs.view`, `edfs.create`, `edfs.edit`, `edfs.delete`
- **UI Components**: Search, Office & Status filters, Add/Edit modal, responsive data table.

### Module 4: Budget Monitoring & R&M Monitoring *(Priority 1B)*
- **Status**: **READY FOR SCAFFOLDING**
- **Operational Scope**: Office monthly procurement budgeting and specialized tracking of R&M program requests.
- **Sub-Component A — Office Budget Ledger (APB / APP)**:
  - Monthly budget allocation per office (Fiscal Year & Month selector).
  - Line item breakdown (e.g., *R&M ICT Equipment: ₱4,000*, *Seminar: ₱2,000*).
  - Metrics: **Allotted**, **Requested / Obligated**, **Remaining Balance**.
  - Scoping: Office users manage their own budget; Command/Admin views division aggregate.
- **Sub-Component B — Specific Programs Monitoring Subpage (R&M Focus)**:
  - Focus Programs:
    1. *R&M of ICT Equipment*
    2. *R&M of Communications Equipment*
    *(Extensible for additional specialized programs)*
  - Office Request Status Matrix: Displays all division offices and their current request stage:
    - 🔴 `Not Requested` (Flags offices needing follow-up)
    - 🟡 `Requested / Pending`
    - 🟢 `Funds Released / Obligated`
    - ⚪ `Completed / Liquidated`

### Module 5: Inventory Module *(Priority 1C)*
- **Status**: **FOUNDATION COMPLETE (Pending New Format & Template Export)**
- **Highlights**:
  - 43 C4ISTAR line items and 840 TOE target seeded into `tbl_inventory_jrrs`.
  - Subtype rollup junction `tbl_inventory_jrrs_subtypes` verified (19/19 tests).
  - Dynamic G6 Readiness calculation engine in `G6ReadinessService.php` (54/54 tests).
- **Upcoming Work**:
  - Ingest new military inventory format once received.
  - Build template-based Excel export engine (`Monthly Inventory Report.xlsx`, `JRRS Monthly Report.xls`).

### Module 6: Accomplishments Module *(Priority 1D)*
- **Status**: **FOUNDATION COMPLETE (Pending DOCX Template Export)**
- **Highlights**: Standard military categories (`CONF`, `PAS`, `VTC`, `CER`), strict military date/time formatting, office attribution.
- **Upcoming Work**: Build exact-template DOCX export engine injecting live records into `Monthly Accomplishment Report of OG6 June 2026.docx`.

### Module 7: Performance Monitoring Module *(Priority 1E)*
- **Status**: **CONCEPT DEFINED**
- **Operational Scope**: Simplified annual office programs connected to higher headquarters directives.
- **Data Model**: Program registry, target office, fiscal year, milestones, deliverables, target vs. actual status, and quarterly performance ratings.

### Module 8: Communications Module *(Phase 2)*
- **Operational Scope**: Radio net logbook, signal comms dispatch, frequency registry, and station status tracking across units.

### Module 9: Calendar of Activities Module *(Phase 2)*
- **Operational Scope**: Organization-wide activity calendar, conference room and VTC facility conflict management, and military time display.

### Module 10: Workstation Accounts Monitoring *(Phase 3)*
- **Operational Scope**: Interim terminal and local machine credential ledger before full Active Directory integration.
- **Data Model**: `office_id`, `workstation_name` / `hostname`, `ip_address`, `standard_username`, `standard_password` (encrypted), `admin_username`, `admin_password` (encrypted), `remarks`.
- **Security**: Frontend masking (`••••••••`), audited click-to-reveal, RBAC protection.

---

## 4. Engineering Standards & Quality Invariants

1. **5-Tier Quality Gate**:
   - PHP Unit Tests: 100% passing.
   - Vitest: 100% passing (currently 62/62).
   - ESLint: 0 errors, 0 warnings.
   - Production Build (`vue-tsc && vite build`): Must pass without errors.
   - Cypress E2E: Must pass all specs.
2. **Date & Time Standard (`AGENTS.md`)**:
   - Date Only: `DD MMM YYYY` (e.g., `17 Sep 2026`)
   - Time Only: `HHmmH` (e.g., `1400H`)
   - Combined: `DD HHmmH MMM YYYY` (e.g., `17 1400H Sep 2026`)
3. **Design System**:
   - Typography: Inter font family only.
   - Centralized Palette: `--color-primary` (`#1E3A8A`), `--color-primary-dark` (`#172554`), `--color-primary-light` (`#2563EB`), `--color-background` (`#F4F6F9`), `--color-surface` (`#FFFFFF`).
   - No ad-hoc arbitrary hex codes or browser defaults.
4. **Security & Session Continuity**:
   - All mutations protected by CSRF and permission middleware.
   - Session continuity files (`CURRENT TASKS.md` and `PREVIOUS.md`) maintained on every pass.
