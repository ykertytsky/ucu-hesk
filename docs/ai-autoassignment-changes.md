# AI Auto-Assignment Change Notes

Created: 2026-03-31

## Purpose

This document explains the AI auto-assignment work added on the `feat-ai-autoassignment` branch, which files were changed, and why each change was needed.

The goal of this feature is to let HESK classify incoming tickets with an OpenAI model, suggest category and priority, and optionally pick an owner from the set of staff members who are actually eligible to handle that ticket.

## High-Level Behavior

- Add a dedicated AI settings screen in the admin panel
- Let AI classify tickets submitted by customers
- Let AI classify tickets submitted by staff
- Let AI classify tickets created through email piping
- Optionally let AI choose an owner after category selection
- Add lightweight logging for AI routing requests and failures

## File Changes

### `inc/ai_assignment.inc.php`

This is the core implementation file for the feature.

Why it was added:
- Centralize all AI routing logic in one place instead of scattering it across ticket entry points
- Keep prompt construction, OpenAI request execution, validation, and logging reusable
- Support both field assignment and owner assignment with shared helpers

What it does:
- Builds the category and priority payload sent to the model
- Validates AI responses before applying them
- Applies confidence thresholds before changing ticket fields
- Builds the list of candidate owners for AI owner assignment
- Reads and writes AI log data
- Reads category and user description maps used to enrich prompts

### `admin/module_ai.php`

This file adds a dedicated AI module page in the admin panel.

Why it was added:
- Keep AI settings separate from the already crowded help desk settings page
- Give admins a place to configure prompts indirectly through category and user descriptions
- Make AI logs visible without editing files manually

What it does:
- Renders the AI settings form
- Shows model, timeout, confidence, and override settings
- Lets admins maintain category descriptions and user descriptions
- Shows recent AI log entries and supports log download

### `admin/admin_settings_save.php`

This file was extended so the new AI settings can be persisted correctly.

Why it changed:
- The new admin module needed its own `section=AI` save flow
- The feature introduces structured JSON settings for category and user descriptions
- Saves from `module_ai.php` must redirect back to the AI page instead of a standard settings page

What changed:
- Added `AI` to the list of supported settings sections
- Added AI-specific request parsing and validation
- Added serialization for AI settings into `hesk_settings.inc.php`
- Added `hesk_prepareAiDescriptionMapForStorage()` to normalize description maps before saving

### `submit_ticket.php`

This file was updated to use AI routing on customer-submitted tickets.

Why it changed:
- Customer ticket submission is one of the main entry points where category and priority should be auto-suggested

What changed:
- Includes `inc/ai_assignment.inc.php`
- Runs AI classification before category validation
- Keeps the existing validation flow intact if AI is disabled or uncertain

### `admin/admin_submit_ticket.php`

This file was updated to use AI routing when staff create tickets from the admin side.

Why it changed:
- Staff-submitted tickets should benefit from the same routing behavior as public submissions
- Category suggestions must respect the categories that the current staff member is allowed to submit into

What changed:
- Includes `inc/ai_assignment.inc.php`
- Builds an allowed category list for restricted users
- Runs AI classification before final category validation

### `inc/pipe_functions.inc.php`

This file was updated to use AI routing for email-created tickets.

Why it changed:
- Email piping is a major intake channel, so AI classification must work there too
- Owner assignment should be available for piped tickets when enabled

What changed:
- Runs AI field classification for incoming email tickets
- Optionally runs AI owner assignment after category selection
- Falls back to normal auto-assignment if AI owner assignment is disabled or not confident enough

### `inc/common.inc.php`

This file was updated to expose the pool of eligible assignees in a reusable way.

Why it changed:
- AI owner selection needs the same eligibility rules as built-in auto-assignment
- The original implementation returned only the first matching user, which is not enough for model-based owner choice

What changed:
- Refactored classic auto-assignment to use a reusable helper
- Added `hesk_getAutoAssignEligibleUsers()`
- Preserved the original permission-group and category eligibility logic

### `inc/show_admin_nav.inc.php`

This file was updated to expose the new admin module in navigation.

Why it changed:
- The AI settings page needs a stable entry point in the admin UI

What changed:
- Added `module_ai.php` to the Modules menu

### `language/en/text.php`

This file was updated with the text used by the new module.

Why it changed:
- The AI module needs user-facing labels, descriptions, and log messages

What changed:
- Added labels for AI settings
- Added descriptive text for category and user description fields
- Added log-related copy for the AI admin page

### `css/app.css`

This file includes the visible styling updates for the AI module page.

Why it changed:
- The AI settings page introduces long labels and multi-line textareas that do not fit well with existing defaults

What changed:
- Added layout rules for AI behavior checkboxes
- Added spacing and sizing rules for category/user description fields

Note:
- The diff is very large because the stylesheet formatting changed along with the targeted AI styles
- The meaningful functional CSS change is small and limited to `.module_ai`, `.ai-behavior`, and `.ai-description-list` selectors

### `css/app.min.css`

This file changed to keep the minified stylesheet in sync with `css/app.css`.

Why it changed:
- HESK ships both the readable and minified CSS assets

### `hesk_settings.inc.php`

This file was extended with AI configuration keys.

Why it changed:
- The application needs persistent settings for the AI module
- The admin save flow writes into this file

What changed:
- Added defaults for AI enablement, model, timeout, confidence thresholds, owner routing flags, logging flags, and description maps

Important:
- This file is environment-specific in real deployments
- Secrets, URLs, and local credentials must not be committed from a live or local install

## Why the Feature Uses Multiple Entry Points

AI routing was intentionally integrated into all ticket creation paths:

- `submit_ticket.php` for customer submissions
- `admin/admin_submit_ticket.php` for staff-created tickets
- `inc/pipe_functions.inc.php` for email-created tickets

Without this, AI behavior would only work for part of the system and produce inconsistent routing depending on how a ticket entered HESK.

## Why a Separate Admin Module Was Chosen

The initial implementation started inside existing help desk settings, but the dedicated `admin/module_ai.php` page is easier to maintain because:

- AI settings are conceptually a module, not a generic help desk toggle
- The page needs richer UI than a normal settings block
- It needs its own log viewer and prompt-context fields

## Deployment Notes

- Do not commit real API keys or local install credentials
- Do not commit cache or generated log files
- Do not rely on local install state for PR validation
- Keep AI defaults safe so the feature is disabled until explicitly configured

## Local-Only Runtime Notes

Some local fixes were intentionally kept out of the PR:

- local `hesk_settings.inc.php` values needed to keep the dev instance installed and runnable
- local handling around the `install/` directory so the app can run without deleting installer files during development

Those adjustments are environment-specific and should not be treated as feature code.
