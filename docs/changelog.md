# Changelog

## Version 0.1 (v0.1.0) - Initial Release

### Core Features & Architecture
- **Unified Layout & Navigation**: Built reusable `MainLayout`, `AppHeader`, `AppBreadcrumb`, and `ModuleSidebar` components with active route highlighting for subpages and dynamic breadcrumb hierarchies.
- **Role-Based Security & Auth**: Configured PHP session security with role checks (`Administrator`, `User`) and secure password hashing.

### Inventory Module
- **Extensible Equipment Architecture**: Created equipment types (`ICT`, `Communications`), subtypes, status lookups, and flexible attribute definition value mappings.
- **Dynamic Subpages**: Added dedicated ICT and Communications subpages for both Inventory and Joint Repair & Replacement System (JRRS).
- **Client & Admin Views**: Created dedicated read-only and edit view pages (`AdminInventoryView.vue`) for item management.

### Communications Module
- **Incoming & Outgoing Tracking**: Full CRUD management for communications with category codes, office origin assignments, dates, status tracking, and dynamic age calculations.
- **Dedicated Read-Only & Edit Views**: Route-based detail pages (`CommunicationDetailView.vue`, `CommunicationEditView.vue`) formatted with `(Category Abbv) - (Subject)` headers and single-row detail summary cards.
- **Multiple Image Attachments**: Added multi-file image upload, database attachment table (`tbl_communication_attachments`), and interactive full-screen Lightbox Gallery.

### Accomplishment Module
- **Operational Activity Tracking**: Added today, annual, and custom period accomplishment logging with priority and status breakdown metrics.

### Automated Setup & Installation
- **1-Click Master Installer**: Created self-elevating `Setup_Everything_1Click.bat`, `Install_6IS.bat`, and `database/setup_db.php` script for automatic XAMPP deployment, database migration execution, and initial account seeding.
- **Inno Setup Script**: Configured Windows setup compiler script (`installer/6IS_Setup_Script.iss`) for output `6IS_Setup_v0.1.exe`.





