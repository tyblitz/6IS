# 6IS UI Style Guide

## Color Palette

| Purpose | Variable | Value |
|----------|----------|-------|
| Primary | --color-primary | #1E3A8A |
| Sidebar | --color-sidebar | #0F2D5C |
| Background | --color-background | #F4F6F9 |
| Surface | --color-surface | #FFFFFF |
| Border | --color-border | #D6DCE5 |

---

## Typography

Font Family

Inter

---

## Design Tokens

The application uses CSS custom properties defined in `theme.css`.

All new components should reference these variables instead of hardcoded values.

Examples:

- `var(--color-primary)`
- `var(--space-md)`
- `var(--radius-md)`