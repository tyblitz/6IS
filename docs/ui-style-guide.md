# 6IS UI Style Guide & Visual Standards

## 1. Color Palette

| Purpose | Token Variable | Hex Value | Semantic Usage |
|:---|:---|:---|:---|
| Primary Dark | `--color-primary-dark` | `#172554` | Midnight Navy for page titles (`h1`/`h2`), active headers |
| Primary Brand | `--color-primary` | `#1E3A8A` | Executive Navy for primary controls, drawer heads |
| Primary Accent | `--color-primary-light` | `#2563EB` | Royal Blue for primary action buttons, active tab indicators |
| Sidebar Background | `--color-sidebar` | `#0F2D5C` | Deep Executive Navy for navigation sidebar |
| Canvas Background | `--color-background` | `#F4F6F9` | Cool neutral background canvas |
| Surface Background | `--color-surface` | `#FFFFFF` | Pure white for cards, modals, table surfaces |
| Surface Hover | `--color-surface-hover` | `#F8FAFC` | Even table row zebra striping, subtle hover state |
| Border | `--color-border` | `#D6DCE5` / `#CBD5E1` | Standard slate divider and input border |

---

## 2. Typography Scale

* **Font Family**: `'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`
* **Scale & Hierarchy**:
  * **Page Title (`h2`)**: `1.35rem – 1.5rem` (`22–24px`), bold `700`, color `var(--color-primary-dark)`.
  * **Section / Card Header (`h3`)**: `1.1rem – 1.2rem` (`18–20px`), semi-bold `600`.
  * **Body & Form Inputs**: `0.875rem` (`14px`), regular `400`, line-height `1.5`.
  * **Table Column Headers**: `0.75rem` (`12px`), bold `700`, uppercase, with letter-spacing `0.05em`.
  * **Badges & Meta Labels**: `0.68rem – 0.75rem` (`11–12px`), bold `700` or `800`.

---

## 3. Executive UI Assessment & Professional Polish Recommendations

Following an executive design review of the system (evaluated against operational military dashboards and fund release matrix views), the following recommendations are established to elevate the interface to a state-of-the-art enterprise standard:

### A. Badge Text Wrapping & Military Acronym Integrity
* **Problem**: Badges in table cells (such as `AFP K-9` under the Office column) wrap awkwardly into two lines (`AFP K-` on line 1, `9` on line 2) when column space narrows.
* **Standard**:
  * All status badges, office acronyms, and category tags must declare `white-space: nowrap;`.
  * The `OFFICE` table column must have a minimum width (e.g. `min-width: 110px`) so compound military abbreviations never wrap across lines.

### B. 12-Month Matrix Grid Alignment
* **Problem**: In 12-month schedule grids (`JAN` – `DEC`), variable column widths cause release badges (`1`) and neutral dashes (`—`) to drift out of vertical alignment with their headers.
* **Standard**:
  * Month columns must have a fixed uniform width (e.g. `30px – 34px` each).
  * Column headers and table cell values must both be centered (`text-align: center; justify-content: center;`).
  * Creates a crisp, Excel/ledger-style matrix grid where planned releases line up with the month headers.

### C. Right-Edge Table Clipping & Horizontal Balance
* **Problem**: On standard widescreen viewports, wide data tables can be clipped on the right (e.g. showing `RE...` for Remaining Balance or cutting off action buttons).
* **Standard**:
  * Balance padding across description and month columns so standard 12-column matrices fit comfortably within 1280px/1440px desktop viewports without clipping critical financial totals or action buttons.
  * Ensure the table container features a subtle horizontal scroll indicator or sticky columns for actions when viewed on smaller viewports.

### D. Header Title & Badge Baseline Alignment
* **Problem**: Sub-badges placed alongside page titles (such as the `MOOE` badge next to `Budget & R&M Monitoring`) can sit visibly high relative to the font baseline.
* **Standard**:
  * Wrap title and badge in a flex container: `display: inline-flex; align-items: center; gap: 10px;`.
  * Badges must vertically center with the font cap-height or baseline of the `h2` title.

### E. Action Toolbar Icon Consistency
* **Problem**: In toolbar button groups, mixing heavy filled icons (e.g. thick circle-plus) with thin outline icons (e.g. outline calendar, outline download) creates visual imbalance.
* **Standard**:
  * Use consistent outline/stroke icons across all buttons in a toolbar group (`ion-icon` size `18px`).
  * Maintain consistent 8px gap between icon and label, and identical button vertical height across primary and secondary controls.

### F. Breadcrumb Header Vertical Proportion
* **Problem**: A tall breadcrumb bar creates stacked horizontal strips under the top app bar, consuming valuable vertical screen real estate.
* **Standard**:
  * Keep breadcrumb padding compact (e.g. `padding: 8px 24px; font-size: 0.8125rem;`) to maximize vertical space dedicated to data grids and KPI metrics.

### G. Navigation Sidebar Balance
* **Problem**: When viewing isolated sub-modules with only 1 navigation item, the sidebar displays a tall expanse of empty dark navy space.
* **Standard**:
  * Display the module category title / breadcrumb context or group items under structured category headers.
  * Support a collapsed mini-sidebar mode or subtle border divider so the sidebar feels intentional regardless of the number of items.