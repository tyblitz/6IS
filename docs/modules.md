# 6IS Modules Overview

## Modules Summary

The **6IS (Integrated Information System)** is organized into core platform modules and operational business modules launched from the main Dashboard.

---

## 1. Dashboard (`/home`)
Application launcher containing entry cards for each active operational module. The Dashboard operates without a sidebar and displays real-time module status.

---

## 2. Communications Module (`/communications`)

### Sub-Navigation Routes
- **Overview** (`/communications`): Central module landing page presenting communications sub-module entry cards and summary metrics.
- **Incoming Communications** (`/communications/incoming`): Entry, logging, and routing for incoming documents, disposition forms, and correspondence.
- **Outgoing Communications** (`/communications/outgoing`): Dispatch, logging, and release tracking for outgoing memorandums and letters.
- **Reports** (`/communications/reports`): Turnaround analytics, volume summaries, and processing age monitoring.

### Reference Data Model
- `tbl_communication_categories`, `tbl_communication_purposes`, `tbl_offices`, `tbl_communications`, `tbl_communication_activities`.

---

## 3. Inventory Module (`/inventory`)
Equipment and inventory tracking module.
- **Overview** (`/inventory`): High-level operational readiness and maintenance metrics.
- **Equipment Management** (`/inventory/equipment`): Equipment registry with extensible attribute specifications (EAV model).
- **JRRS Tracking** (`/inventory/jrrs`): Target vs. actual equipment inventory baseline comparisons.
- **Monthly Snapshots**: Frozen historical records stored in `tbl_inventory_history`.

---

## 4. Accomplishments Module (`/accomplishments`)
Productivity and daily accomplishment reporting module. Strictly records completed accomplishments (not pending tasks).
- **Overview** (`/accomplishments`): Summary metric cards and current-day accomplishment preview table.
- **Daily Report** (`/accomplishments/daily`): Daily accomplishment logs with CRUD and native browser print.
- **Monthly Report** (`/accomplishments/monthly`): Dynamically consolidated monthly productivity rollup.
- **Quarterly Report** (`/accomplishments/quarterly`): Consolidated quarterly productivity rollup (Q1–Q4).
- **Annual Report** (`/accomplishments/annual`): Consolidated annual productivity rollup.
- **Custom Period Report** (`/accomplishments/custom`): Dynamic date range consolidation.

---

## 5. Calendar Module (`/calendar`)
Event scheduling, operational activity tracking, and milestone monitoring.
- **Calendar Schedule** (`/calendar`): Interactive schedule grid supporting month, week, day, and list views.
- **Event Types**: Standard activity classifications (`CONF`, `PAS`, `VTC`, `CER`).
- **Reschedule Tracking**: Full audit tracking of rescheduled dates and operational justifications.
- **Accomplishment Linking**: Events can be linked to completed accomplishment records.

---

## 6. Administrator Module (`/administrator`)
Central administrative management, governance, and system security hub. Access restricted by role (`Administrator`) and granular permissions.

### Sub-Navigation Routes & Views
1. **Overview** (`/administrator`): Quick-launch dashboard for administrative functions.
2. **Audit Logs & System Activity** (`/administrator/audit`): Immutable audit trail with date/module/action filters, pagination, and JSON state diff inspection.
3. **Module Management** (`/administrator/modules`): Toggle business module activation states with core module protection.
4. **Role & Permission Management** (`/administrator/roles`): Role creation, permission matrix editor, and system role safeguards.
5. **User Management** (`/administrator/users`): User account CRUD, office assignments, and final Administrator protection.
6. **Organization Profile** (`/administrator/organization`): Enterprise organizational profile and contact management.
7. **Offices Directory** (`/administrator/offices`): Comprehensive office directory with dependency safety checks before deletion.
8. **Inventory Reference Data** (`/administrator/inventory`): Equipment types, subtypes, and attribute definitions.
9. **Communications Reference Data** (`/administrator/communications`): Document categories and processing purposes.
10. **Accomplishments Reference Data** (`/administrator/accomplishments`): Accomplishment categories and settings.
