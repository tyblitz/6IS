# Changelog

## v0.1.0

Initial project setup

- Created Ionic Vue project
- Configured Vue Router
- Created Dashboard
- Added Main Layout
- Added Inventory navigation
- Added Communications navigation


## Unreleased

- Created reusable AppLayout.
- Added shared header, footer, sidebar placeholder, and breadcrumb placeholder.
- Refactored Inventory module to use the shared layout.
- Created reusable ModuleSidebar component.
- Added support for dynamic sidebar navigation using TypeScript interfaces.
- Implemented menu rendering using Vue props and `v-for`.
- Added route-based navigation using Vue Router.
- Implemented full CRUD operations (Create, Read details, Update, Soft Delete) for Communications module in PHP REST API (`backend/api/communications/index.php`).
- Added explicit `communication_type` (`Incoming` / `Outgoing`) column to `tbl_communications`.
- Implemented process activity history tracking with controlled logging rules (`Logged` on creation, status change logging, and explicit user process updates).
- Implemented dynamic communication age calculation from the latest `activity_date`.
- Created reusable Vue 3 frontend components: `CommunicationTable.vue`, `CommunicationFormModal.vue`, and `CommunicationDetailModal.vue`.
- Updated Communications Overview, Incoming, Outgoing, and Reports views with full interactive management and search/filtering.
- Populated database with realistic, multi-stage sample data for Incoming and Outgoing communications without destroying existing 6IS tables.





