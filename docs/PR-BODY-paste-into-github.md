**Merge guide for IT:** [docs/developer-handoff-ux-prototype.md](docs/developer-handoff-ux-prototype.md)

---

## Summary

This PR updates the customer-facing UI (`hesk3` theme) to match approved mockups and adds a clickable demo flow. The handoff document above lists every file to port into a university HESK sandbox and explains **prototype vs production** (`ui_demo=1`).

## Included changes

- Home page UI refresh (hero, search field, 3 action cards)
- Demo mode via `ui_demo=1`
- Clickable category grid (all cards open demo form)
- Demo form with placeholders, category-specific title, “Локація” dropdown options
- Extra demo tile: **«Відділ інформації та маркетингу»** (`demo_cat=info-marketing`)
- Grid fix: **«Інше»** stays in the 2-column layout (no orphan row)
- Theme CSS adjustments for consistent spacing and card layout
- Documentation: IT handoff, readme index, `docker-compose.yml` for optional local stack

## Files in this branch (UX slice)

- `index.php`
- `theme/hesk3/customer/index.php`
- `theme/hesk3/customer/create-ticket/category-select.php`
- `theme/hesk3/customer/create-ticket/create-ticket.php`
- `theme/hesk3/customer/css/core_overrides.css`
- `docs/developer-handoff-ux-prototype.md`
- `docs/ui-ux-changes.md`
- `docs/ui-ux-pull-request.md`
- `readme.md`
- `docker-compose.yml`

## Test plan

1. Open `/index.php`.
2. Click **Надіслати звернення**.
3. Verify every category card is clickable (including **Відділ інформації та маркетингу** and **Інше**).
4. Verify each form opens and shows the selected category title.
5. Open **Відділ інформації та маркетингу** and confirm the page title matches.
6. Verify **Інше** sits in the right column of the last row (no empty cell, no off-center strip).
7. Verify placeholders are present.
8. Verify “Локація” dropdown contains all required options.
9. Verify other home actions still route to existing pages.

## Notes

- No DB schema changes for this UX work.
- No API integrations added.
- `docker-compose.yml` expects a local image `ucu-hesk-web:latest` or replace the `web` service with your PHP image.
