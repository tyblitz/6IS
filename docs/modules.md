# 6IS Modules Overview

## Modules Summary

The 6IS (Integrated Information System) consists of several core application modules launched from the main Dashboard.

---

## 1. Dashboard (`/home`)
Application launcher containing entry cards for each operational module. The Dashboard operates without a sidebar.

---

## 2. Communications Module (`/communications`)

### Sub-Navigation Routes
- **Overview** (`/communications`): Central module landing page presenting communications sub-module entry cards.
- **Incoming Communications** (`/communications/incoming`): Entry and tracking for incoming documents, disposition forms, and official correspondence.
- **Outgoing Communications** (`/communications/outgoing`): Release, dispatch, and logging for outgoing memorandums and letters.
- **Reports** (`/communications/reports`): Statistical reports, turnaround analytics, and processing logs.

### Reference Data Model
- **Communication Categories** (`tbl_communication_categories`): Disposition Form (`DF`), Summary Disposition Form (`SDF`), Subject to Letter (`STL`), Memorandum (`Memo`), Standard Operating Procedure (`SOP`), Others (`NULL`).
- **Communication Purposes** (`tbl_communication_purposes`): Access Pass, PAS Request, R&M ICT Fund Request, Others.
- **Authoritative Office Reference** (`tbl_offices`): Shared office reference data across 6IS modules.
- **Communications Table** (`tbl_communications`): Core communications records referencing office, category, and purpose IDs.
- **Communication Activities** (`tbl_communication_activities`): Log of activity events for turnaround monitoring and age calculation.

---

## 3. Inventory Module (`/inventory`)
Equipment and inventory tracking module.
- Equipment Management (`/inventory/equipment`)
- JRRS Tracking (`/inventory/jrrs`)

---

## 4. Accomplishments Module (`/accomplishments`)
Productivity and daily accomplishment reporting module.
- Daily Report (`/accomplishments/daily`)
- Monthly Report (`/accomplishments/monthly`)
- Quarterly Report (`/accomplishments/quarterly`)
- Annual Report (`/accomplishments/annual`)
- Custom Period Report (`/accomplishments/custom`)
