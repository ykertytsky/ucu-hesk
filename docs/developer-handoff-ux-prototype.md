# Developer handoff: UCU customer UX prototype (HESK fork)

**Audience:** University IT engineers merging this fork into an existing HESK installation or sandbox.  
**Course context:** Business Analysis (UCU). This repository extends upstream **HESK 3.7.5** with **customer-facing presentation and UX**, not new server-side ticket APIs.

---

## 1. What problem this solves

University IT runs a self-hosted help desk based on HESK. Stakeholders (students, staff) need a **clearer entry experience** and a **walkthrough of the intended “submit a ticket” journey** aligned with UCU wording and layout.

This fork documents **exactly which files** implement that experience so you can **cherry-pick or diff-merge** into your maintained codebase without guessing.

---

## 2. Two surfaces you must preserve

These URLs are the **contract** with stakeholders for “what we changed”:

| Surface | URL (local example) | Purpose |
|--------|----------------------|---------|
| **Customer home** | `/` → `index.php` | Reworked hero, static search UI, three Ukrainian action cards (submit ticket, view ticket, knowledge base). |
| **Demo ticket flow** | `/index.php?a=add&ui_demo=1` | **UI-only** category grid + form: Ukrainian labels, placeholders, location dropdown; **does not submit** a real ticket. |

**Production ticket submission** (real database, `submit_ticket.php`, staff queues) is **unchanged** for routes **without** `ui_demo=1`. The demo path is an **overlay** for communication and BA documentation.

---

## 3. Prototype vs production (important)

| Aspect | `ui_demo=1` flow | Normal HESK (`ui_demo` absent) |
|--------|------------------|--------------------------------|
| Category list | Hardcoded in PHP (`category-select.php`) | From **Admin → Categories** (`$hesk_settings['categories']`) |
| Form submit | Blocked (`onsubmit="return false;"`) | Full validation + DB insert |
| “Synthetic” feel | Expected: no reliance on specific DB rows for the **grid** | Requires working DB + categories |

So: **lack of a populated database does not block reviewing the demo UI**; it **does** block testing full ticket creation until your sandbox has HESK installed and categories configured.

---

## 4. Routing behaviour

**File:** `index.php` (repository root)

When `a=add` and `ui_demo=1` are present **and** no category has been chosen yet, the app forces the **category selection** template first. That keeps the demo flow aligned with the mockups (pick a tile, then see the form).

---

## 5. Files to merge (single checklist)

Copy or merge these paths from this fork into your HESK tree (same relative paths under the web root):

| Path | Role |
|------|------|
| `index.php` | Demo routing guard for `a=add` + `ui_demo=1`. |
| `theme/hesk3/customer/index.php` | Home page layout and links (including link to `ui_demo=1`). |
| `theme/hesk3/customer/create-ticket/category-select.php` | Demo category grid + `ui_demo` branch; normal HESK branches unchanged. |
| `theme/hesk3/customer/create-ticket/create-ticket.php` | Demo form branch + `$demoCategoryMap` (must include every `demo_cat=` slug used in the grid). |
| `theme/hesk3/customer/css/core_overrides.css` | Spacing, grid, typography for home + category + demo form. |

**Optional (local dev only):**

| Path | Role |
|------|------|
| `docker-compose.yml` | Example Compose stack (`web` + `db`). Requires a PHP/Apache image tagged `ucu-hesk-web:latest` **or** replace `web` with your own image. Not required for merging into production HESK. |

**Documentation (this handoff + changelog):**

- `docs/developer-handoff-ux-prototype.md` (this file)
- `docs/ui-ux-changes.md`
- `readme.md` (index of docs)

---

## 6. Merge procedure (sandbox)

1. **Backup** production files and database before any merge.
2. **Diff** each file in section 5 against your tree; resolve conflicts (especially `index.php` if you already customized it).
3. Deploy to a **sandbox** with a working HESK DB.
4. **Clear PHP/file caches** if your deployment uses `cache/` or opcode cache.
5. **Verify:**
   - `/` — home matches expectations; cards link correctly.
   - `/index.php?a=add&ui_demo=1` — all tiles clickable; form title matches `demo_cat`; submit does not POST to production.
   - `/index.php?a=add&category=<id>` **without** `ui_demo` — still creates real tickets as before.
6. After HESK install/upgrade on any environment, ensure the **`install/`** directory is **removed** when not in use; otherwise the **customer** portal can show HESK’s maintenance-style lockout (core HESK behaviour when `install/` exists).

---

## 7. Extending the demo grid

Demo tiles are defined in `$demoCats` in `category-select.php`. Each tile needs:

- A unique `demo_cat` query value in `href`.
- A matching entry in `$demoCategoryMap` in `create-ticket.php`.

If either is missing, the form title falls back to the default demo category.

**Layout:** The category area uses a **2-column CSS grid**. The last row should contain **two** tiles (or an intentional full-width rule). A full-width “Other” tile (`navlink--other`) with an **odd** number of preceding tiles leaves a visual hole; the current demo keeps “Інше” in the grid as a normal cell so the last row stays balanced.

---

## 8. What we explicitly did *not* change

- No new database tables or migrations for this UX work.
- No changes to `submit_ticket.php` business rules for the **non-demo** path.
- Admin panel PHP is outside this UX slice (unless your fork already diverged elsewhere).

---

## 9. Support contacts

Course / product questions: see **Development Team** in `readme.md`.  
Technical upstream reference: [HESK knowledge base](https://www.hesk.com/knowledgebase/) and `docs/existing/` in this repo.
