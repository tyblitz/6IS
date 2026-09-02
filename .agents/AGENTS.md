# Workspace Customization Rules for 6IS Project

## 1. Date and Time Formatting Specification

All date and time displays throughout the entire project (all existing modules and any new modules created in the future) MUST strictly follow these formatting rules:

1. **Date Only Format**: `DD MMM YYYY`
   - *Example*: `27 Aug 2026`

2. **Time Only Format**: Military time with trailing 'H' (`HHmmH`)
   - *Example*: `1400H`, `0830H`

3. **Date and Time Combined Format**: `DD HHmmH MMM YYYY`
   - *Example*: `27 1400H Aug 2026`

4. **Date, Time, and Day of Week Format**: `DD HHmmH MMM YYYY dddd`
   - *Example*: `27 1400H Aug 2026 Friday`

### Scope & Implementation Requirements
- **Project-Wide Application**: Applies to all views, tables, cards, headers, calendars, reports, and detail pages across Inventory, Communications, Accomplishments, Calendar, Dashboard, and Administrator modules.
- **Centralized Helper Functions**: Frontend date formatting must be driven by helper functions in `frontend/src/utils/dateUtils.ts` (and backend PHP helpers in `backend/helpers/`) to allow future integration with user settings.

---

## 2. Design System, Typography, and Color Palette Specification

All user interface elements across all modules in 6IS MUST strictly adhere to the following design system rules, typography scale, and color tokens:

### A. Typography
- **Primary Font Family**: `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`
  - Applied globally via `frontend/src/assets/styles/typography.css`.
  - Generic browser default fonts (`Arial`, `Times New Roman`, etc.) are strictly prohibited.
- **Font Weight Scale**:
  - `400 (Regular)`: Body copy, input values, table cell contents, long descriptions.
  - `500 (Medium)`: Subheadings, secondary buttons, interactive table text.
  - `600 (Semi-Bold)`: Card titles, section headers, badges, table column headers.
  - `700 (Bold)`: Page titles (`h1`/`h2`), KPI metrics, primary button labels, modal titles.
  - `800 (Extra-Bold)`: Category acronyms (`CONF`, `PAS`, `VTC`), military time badges.
- **Scale & Hierarchy**:
  - **Page Header (`h2`)**: `1.35rem - 1.5rem` (`22-24px`), bold `700`, color `var(--color-primary-dark)`.
  - **Section / Card Header (`h3`)**: `1.1rem - 1.2rem` (`18-20px`), semi-bold `600`.
  - **Body & Form Inputs**: `0.875rem` (`14px`), regular `400`, line-height `1.5`.
  - **Table Column Headers**: `0.75rem` (`12px`), bold `700`, uppercase, with letter-spacing `0.05em`.
  - **Badges & Meta Labels**: `0.68rem - 0.75rem` (`11-12px`), bold `700` or `800`.

### B. Color Palette & Design Tokens
All styling must reference centralized CSS variables defined in `frontend/src/assets/styles/theme.css`:

1. **Brand & Navigation**:
   - `--color-primary-dark` (`#172554`): Deep Midnight Navy for primary page titles, active headers, and drawer tops.
   - `--color-primary` (`#1E3A8A`): Navy Blue for standard brand elements, headers, and primary controls.
   - `--color-primary-light` (`#2563EB`): Royal Blue accent for primary buttons, active nav tabs, and focus rings.
   - `--color-sidebar` (`#0F2D5C`): Deep Executive Navy for the sidebar background (Hover: `#184785`, Active: `#2563EB`).

2. **Surfaces, Canvas & Borders**:
   - `--color-background` (`#F4F6F9`): Cool executive canvas background.
   - `--color-surface` (`#FFFFFF`): Pure white for cards, modals, and table containers.
   - `--color-surface-hover` (`#F8FAFC`): Subtle hover state and even table row zebra striping.
   - `--color-border` (`#CBD5E1` / `#D6DCE5`): Standard slate border for dividers, tables, and inputs. Never use stark black (`#000000`) or raw dark grays for borders.

3. **Text Colors**:
   - `--color-text` (`#0F172A` / `#1F2937`): Slate charcoal for high-contrast, readable body copy and titles.
   - `--color-text-secondary` (`#64748B` / `#6B7280`): Muted slate for timestamps, subtitles, and descriptions.
   - `--color-text-light` (`#FFFFFF`): Inverted text for primary buttons, badges, and dark headers.

4. **Semantic Status & Severity Tokens**:
   All status badges, pills, and indicators must use paired light pastel backgrounds with dark high-contrast text (minimum WCAG AA compliance):
   - **Success / Accomplished / Available / Active**:
     - Text & Border: `#16A34A` / `#15803D`
     - Background: `#F0FDF4` (Pill fill: `#DCFCE7`)
   - **Warning / In Progress / Pending / Medium Priority**:
     - Text & Border: `#D97706` / `#B45309`
     - Background: `#FFFBEB` (Pill fill: `#FEF3C7`)
   - **Danger / Overdue / Unserviceable / High Priority**:
     - Text & Border: `#DC2626` / `#B91C1C`
     - Background: `#FEF2F2` (Pill fill: `#FEE2E2`)
   - **Info / Scheduled / Low Priority**:
     - Text & Border: `#2563EB` / `#1D4ED8`
     - Background: `#EFF6FF` (Pill fill: `#DBEAFE`)

5. **Operational Activity Categories**:
   Standardized across Calendar, Accomplishments, and Communications:
   - **Conference / Meeting (`CONF`)**: Blue (`#EFF6FF` bg, `#2563EB` border/text)
   - **Physical Activities / Sports (`PAS`)**: Green (`#F0FDF4` bg, `#16A34A` border/text)
   - **Video Teleconference (`VTC`)**: Purple (`#FAF5FF` bg, `#9333EA` border/text)
   - **Ceremony / Protocol (`CER`)**: Amber (`#FFFBEB` bg, `#D97706` border/text)

### C. Component & Layout Conventions
1. **Border Radius**:
   - Controls, buttons, input fields, and status pills: `6px` (`--radius-sm`).
   - Cards, tables, toolbars, and content panels: `10px` (`--radius-md`).
   - Modals and floating dialogs: `16px` (`--radius-lg`).
2. **Button Hierarchy**:
   - **Primary Action**: Filled Royal Blue (`#2563EB`) with white text and `6px` radius (`.btn-primary`).
   - **Secondary Action**: White surface with `#CBD5E1` border and `#334155` text (`.btn-secondary`).
   - **Danger Action**: Soft red background (`#FEF2F2`) with `#DC2626` border/text (`.btn-danger`).
3. **Strict Prohibition on Ad-Hoc Styling**:
   - Raw color names (`red`, `green`, `blue`) and arbitrary hex codes (`#333`, `#222`, `#ccc`) are strictly forbidden. Always use design system variables or defined palette tokens.

