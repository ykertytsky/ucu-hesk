# Pull Request Template (UI/UX)

**Title:** `feat(theme): improve customer portal UI/UX demo flow`

## Summary

This PR updates the customer-facing UI (`hesk3` theme) to match approved mockups and adds a clickable demo flow.

## Included Changes

- Home page UI refresh (hero, search field, 3 action cards)
- Demo mode via `ui_demo=1`
- Clickable category grid (all cards open demo form)
- Demo form with:
  - placeholders
  - category-specific title
  - “Локація” dropdown options
- Theme CSS adjustments for consistent spacing and card layout
- Technical notes documentation

## Changed Files

- `index.php`
- `theme/hesk3/customer/index.php`
- `theme/hesk3/customer/create-ticket/category-select.php`
- `theme/hesk3/customer/create-ticket/create-ticket.php`
- `theme/hesk3/customer/css/core_overrides.css`
- `docs/ui-ux-changes.md`
- `docs/ui-ux-pull-request.md`

## Test Plan

1. Open `/index.php`.
2. Click **Надіслати звернення**.
3. Verify every category card is clickable.
4. Verify each form opens and shows selected category title.
5. Verify placeholders are present.
6. Verify “Локація” dropdown contains all required options.
7. Verify other home actions still route to existing pages.

## Notes

- No DB schema changes.
- No API integrations added.
- This PR is focused on UI/UX only.
