# Pull Request Template (UI/UX)

**Title:** `feat(theme): improve customer portal UI/UX demo flow`

## Summary

This PR updates the customer-facing UI (`hesk3` theme) to match approved mockups and adds a clickable demo flow. Full **merge checklist and IT handoff** live in [docs/developer-handoff-ux-prototype.md](./developer-handoff-ux-prototype.md).

## Included Changes

- Home page UI refresh (hero, search field, 3 action cards)
- Demo mode via `ui_demo=1`
- Clickable category grid (all cards open demo form)
- Demo form with:
  - placeholders
  - category-specific title
  - “Локація” dropdown options
- Extra demo tile: **«Відділ інформації та маркетингу»** (`demo_cat=info-marketing`)
- Grid fix: **«Інше»** stays in the 2-column layout (no orphan row)
- Theme CSS adjustments for consistent spacing and card layout
- Documentation for developers merging into a university HESK sandbox

## Changed Files

- `index.php`
- `theme/hesk3/customer/index.php`
- `theme/hesk3/customer/create-ticket/category-select.php`
- `theme/hesk3/customer/create-ticket/create-ticket.php`
- `theme/hesk3/customer/css/core_overrides.css`
- `docs/developer-handoff-ux-prototype.md`
- `docs/ui-ux-changes.md`
- `docs/ui-ux-pull-request.md`
- `readme.md`

## Test Plan

1. Open `/index.php`.
2. Click **Надіслати звернення**.
3. Verify every category card is clickable (including **Відділ інформації та маркетингу** and **Інше**).
4. Verify each form opens and shows selected category title.
5. Open **Відділ інформації та маркетингу** and confirm the page title matches.
6. Verify **Інше** sits in the right column of the last row (no empty cell, no off-center strip).
7. Verify placeholders are present.
8. Verify “Локація” dropdown contains all required options.
9. Verify other home actions still route to existing pages.

## Notes

- No DB schema changes.
- No API integrations added.
- This PR is focused on UI/UX only.
