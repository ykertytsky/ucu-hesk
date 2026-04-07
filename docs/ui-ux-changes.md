# UI/UX Changes Summary

Created: 2026-04-07

## Goal

Implement a clickable UI prototype for the customer portal that matches stakeholder mockups, without changing database schema or backend business logic.

## Scope

- Updated customer-facing UI in the `hesk3` theme.
- Added a demo mode via `ui_demo=1` for presentation flow.
- Kept core HESK ticket logic intact for normal routes.

## What Was Changed

### 1) Home page (`theme/hesk3/customer/index.php`)
- Reworked header area and hero section to match the provided design.
- Added static search input UI.
- Displayed 3 action cards:
  - Надіслати звернення
  - Переглянути існуюче звернення
  - База знань
- “Надіслати звернення” opens demo flow: `index.php?a=add&ui_demo=1`.

### 2) Demo category page (`theme/hesk3/customer/create-ticket/category-select.php`)
- Added `ui_demo` mode.
- Rendered fixed category grid with Ukrainian labels/descriptions.
- Made **all category cards clickable**.
- Each card opens a form page in demo mode with category context.

### 3) Demo form page (`theme/hesk3/customer/create-ticket/create-ticket.php`)
- Added `ui_demo` branch for UI-only form rendering.
- Added placeholders for all fields with neutral examples.
- Updated dropdown “Локація” with the full requested option list.
- Form remains non-destructive in demo mode (`onsubmit="return false;"`).
- Shows selected category title based on clicked card (`demo_cat`).

### 4) Routing (`index.php`)
- For `ui_demo=1` without selected category, force category-selection step first.

### 5) Styling (`theme/hesk3/customer/css/core_overrides.css`)
- Tuned spacing, card size, typography, and grid layout for the three demo screens.

## Files Changed

- `index.php`
- `theme/hesk3/customer/index.php`
- `theme/hesk3/customer/create-ticket/category-select.php`
- `theme/hesk3/customer/create-ticket/create-ticket.php`
- `theme/hesk3/customer/css/core_overrides.css`
- `docs/ui-ux-changes.md`
- `docs/ui-ux-pull-request.md`

## Behaviour Notes

- `index.php?a=add&ui_demo=1` = clickable demo prototype flow.
- Normal HESK pages (`ticket.php`, `knowledgebase.php`, etc.) remain accessible.
- No new DB tables, migrations, or API integrations were introduced.

## Quick Test Checklist

1. Open `/index.php`.
2. Click **Надіслати звернення**.
3. Verify all category cards are clickable.
4. Open several categories and verify form title changes by category.
5. Verify placeholders and “Локація” dropdown options are visible.
6. Verify other two home cards open existing pages.
