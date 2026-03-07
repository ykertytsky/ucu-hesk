# HESK Codebase Feature Reference

This document provides a comprehensive description of the HESK codebase for use by AI agents, developers, and contributors. It covers all major functional areas, key files, data flows, and extension points.

---

## Overview

**HESK** (Help Desk Software) is a self-hosted, PHP-based ticket management and customer support system. This repository is a fork of HESK 3.7.5 being developed as part of a Business Analysis course at UCU.

| Property | Value |
|---|---|
| Version | 3.7.5 (13 Feb 2026) |
| Language | PHP (5.6 – 8.5 compatible) |
| Database | MySQL (MyISAM engine) |
| Architecture | PHP monolith, server-side rendered, no REST API |
| Frontend | jQuery 3.5.1, jQuery UI, TinyMCE 7.x |
| Auth | Session-based + optional TOTP MFA |
| Email | SMTP, IMAP, POP3, email piping |

---

## Directory Structure

```
ucu-hesk/
  admin/                  Admin panel (73 PHP files)
  attachments/            Uploaded file attachments
  cache/                  File-based PHP cache
  cron/                   Cron job scripts
  css/                    Front-end CSS
  docs/                   Documentation
    existing/             Markdown versions of original HTML docs
  fonts/                  Web fonts
  img/                    Images
  inc/                    Core library / include files (55 items)
  install/                Installation and upgrade wizard
  js/                     JavaScript libraries
  language/               i18n language packs
  theme/                  Theme assets (hesk3/)
  vendor/                 Composer-managed PHP third-party libraries
  hesk_settings.inc.php   Master configuration file
  index.php               Customer portal home
  submit_ticket.php       Ticket submission form
  ticket.php              View ticket status
  login.php               Staff login
  knowledgebase.php       Public knowledgebase
  my_tickets.php          Customer ticket list
```

---

## Core Configuration

### `hesk_settings.inc.php`

This is the single master configuration file for the entire application. All PHP files include it via `require()`. It defines:

- **Database:** host, name, user, password, table prefix (`hesk_`)
- **URLs:** base URL of the help desk installation
- **Email:** SMTP host/port/TLS, from address, IMAP/POP3 settings, OAuth2 credentials
- **Feature flags:** customer accounts, attachments, knowledgebase, MFA, flood control, etc.
- **Security:** login attempt limits, session timeouts, CAPTCHA settings
- **Debug mode:** `$hesk_settings['debug_mode']` (should be off in production)

Key settings agents may need to modify:

```php
$hesk_settings['db_host']         // Database host
$hesk_settings['db_name']         // Database name
$hesk_settings['db_user']         // Database user
$hesk_settings['db_pass']         // Database password
$hesk_settings['hesk_url']        // Public URL of the help desk
$hesk_settings['noreply_mail']    // From email address
$hesk_settings['smtp_host']       // SMTP server hostname
$hesk_settings['customer_accounts'] // 0=disabled, 1=optional, 2=required
$hesk_settings['debug_mode']      // 0=off, 1=on
```

---

## Core Library (`inc/`)

The `inc/` directory contains the framework of the application. All business logic, utilities, and feature modules live here.

### Key Files

| File | Description |
|---|---|
| `inc/common.inc.php` (121KB) | Core framework: session handling, DB wrapper, request helpers, utilities, output functions |
| `inc/admin_functions.inc.php` (38KB) | Admin-only helpers: ticket operations, user management, permission checks |
| `inc/email_functions.inc.php` (68KB) | All email generation and sending logic (templates, SMTP dispatch, piping) |
| `inc/posting_functions.inc.php` | Ticket creation and reply logic |
| `inc/ticket_list.inc.php` (37KB) | Ticket list rendering with filters, sorting, pagination |
| `inc/customer_accounts.inc.php` | Customer account registration, login, password management |
| `inc/knowledgebase_functions.inc.php` | Knowledgebase query and rendering functions |
| `inc/pipe_functions.inc.php` | Email-to-ticket processing (inbound email parsing) |
| `inc/profile_functions.inc.php` | Staff profile management |
| `inc/mfa_functions.inc.php` | TOTP MFA generation, verification, backup codes |
| `inc/oauth_functions.inc.php` | OAuth2 helpers for Gmail/Microsoft 365 IMAP auth |
| `inc/attachments.inc.php` | File upload/download handling |
| `inc/statuses.inc.php` | Ticket status definitions and helpers |
| `inc/priorities.inc.php` | Ticket priority definitions and helpers |

### Third-Party Libraries in `inc/`

| Dir | Library | Purpose |
|---|---|---|
| `inc/htmlpurifier/` | HTMLPurifier | User input sanitization (XSS prevention) |
| `inc/tiny_mce/` | TinyMCE 7.x | Rich text editor for ticket replies |
| `inc/recaptcha/` | Google reCAPTCHA | Spam prevention on forms |
| `inc/html2text/` | html2text | Convert HTML email bodies to plain text |
| `inc/mail/` | Mail handling | IMAP/POP3 mail parsing |
| `inc/zip/` | Zip utilities | Attachment zip handling |
| `inc/tecnick/` | TCPDF/Barcode | Barcode generation (C128, QR) for printed tickets |
| `inc/timer/` | Timer | Ticket time tracking |

---

## Ticket System

### Ticket Lifecycle

```
Submitted (New)
    │
    ├── Staff replies       → Status: "Staff Replied"
    ├── Customer replies    → Status: "Customer Replied"
    ├── Staff resolves      → Status: "Resolved"
    └── Custom statuses     → Configurable additional states
```

### Ticket Fields

Every ticket stores:
- **Core:** tracking ID, subject, message, category, priority, status
- **Assignment:** owner (staff), collaborators
- **Dates:** created, last updated, due date, resolved, closed at
- **People:** customer name, customer email, customer IP
- **Custom fields:** `custom1` through `custom100` (flat EAV antipattern on the tickets table)
- **Metadata:** time worked, reply count, attachments, linked tickets, notes

### Key Admin Ticket Files

| File | Description |
|---|---|
| `admin/admin_ticket.php` (185KB) | Main admin ticket view — the largest file in the codebase |
| `admin/assign_owner.php` | Assign/unassign ticket owner |
| `admin/change_priority.php` | Change ticket priority |
| `admin/change_status.php` | Change ticket status |
| `admin/delete_ticket.php` | Delete ticket |
| `admin/archive.php` | Archive/unarchive ticket |
| `admin/merge_tickets.php` | Merge two tickets |
| `admin/link_tickets.php` | Link related tickets |
| `admin/lock.php` | Lock/unlock ticket |
| `admin/print_ticket.php` | Printable ticket view with barcode |
| `admin/export.php` | Export tickets to CSV/XML |

### Customer-Facing Ticket Files

| File | Description |
|---|---|
| `submit_ticket.php` | Ticket submission form |
| `ticket.php` | View ticket by tracking ID |
| `reply_ticket.php` | Customer reply to a ticket |
| `change_status.php` | Customer marks ticket as resolved |
| `my_tickets.php` | List of all tickets for a logged-in customer |

### Ticket ID System

HESK supports two ID modes:
- **Tracking ID:** random alphanumeric (default), used by customers
- **Sequential ID:** numeric auto-increment, used in admin view

---

## User & Permission System

### User Types

| Type | Description |
|---|---|
| Administrator | Full access to all settings and tickets |
| Staff | Configurable access via permissions or permission groups |
| Customer | Can submit tickets, view own tickets (accounts optional/required/disabled) |

### Permission Groups (`admin/manage_permission_groups.php`)

Since 3.7.0, staff permissions can be managed via groups. Each group defines:
- Which admin features are accessible
- Which ticket categories can be viewed/edited
- Reply, resolve, delete, assign, lock permissions

Permission groups are stored in `hesk_permission_groups` and linked to users via `hesk_users.permission_group`.

### Staff Management Files

| File | Description |
|---|---|
| `admin/manage_users.php` | List/create/edit staff |
| `admin/manage_permission_groups.php` | Manage permission group definitions |
| `admin/manage_mfa.php` | Manage MFA for staff |
| `admin/profile.php` | Staff profile settings |

### Customer Account Files

| File | Description |
|---|---|
| `register.php` | Customer registration |
| `verify_registration.php` | Email verification |
| `login.php` | Staff and customer login |
| `reset_password.php` | Password reset |
| `profile.php` | Customer profile |
| `admin/manage_customers.php` | Admin: list/edit/delete customers |
| `admin/import_customers.php` | Bulk import customers |

---

## Email Integration

HESK supports four email delivery modes:

### Outbound Email (Notifications)

All outbound email is handled by `inc/email_functions.inc.php` using **PHPMailer** (`vendor/phpmailer/`).

- **SMTP** — standard TLS/SSL SMTP with optional OAuth2 (Gmail, Microsoft 365)
- Templates are configurable per event type from `admin/email_templates.php`
- Email events: new ticket, new reply, ticket assigned, status changed, overdue, etc.

### Inbound Email (Email-to-Ticket)

Three modes for converting incoming email into tickets:

| Mode | Description | Key File |
|---|---|---|
| IMAP polling | Cron fetches emails from IMAP server every ~5 minutes | `cron/` + `inc/pipe_functions.inc.php` |
| POP3 polling | Same as IMAP but via POP3 | `cron/` + `inc/pipe_functions.inc.php` |
| Email piping | Email is piped directly into a PHP script | `inc/pipe_functions.inc.php` |

IMAP supports OAuth2 authentication for Gmail and Microsoft 365.

### Email Templates

Located in `admin/email_templates.php`. Templates support variables like `%%SUBJECT%%`, `%%TRACK_ID%%`, `%%NAME%%`, etc.

---

## Knowledgebase

The knowledgebase is a public FAQ/article system.

### Features
- Hierarchical categories (categories and subcategories)
- Public and private articles
- WYSIWYG article editing (TinyMCE)
- Full-text search
- Article ratings (helpful / not helpful)
- Article suggestion on ticket submission form

### Key Files

| File | Description |
|---|---|
| `knowledgebase.php` | Public KB browsing |
| `admin/manage_knowledgebase.php` | Admin KB management |
| `inc/knowledgebase_functions.inc.php` | KB query functions |

---

## Custom Fields

HESK supports up to **100 custom fields** (`custom1` – `custom100`) stored as flat columns on the tickets table.

### Field Types
- Text input
- Textarea
- Radio buttons
- Dropdown select
- Checkbox
- Date picker
- Email
- Hidden (pre-filled value)

### Management
Custom fields are configured in `admin/custom_fields.php`. Each field has:
- Label, placeholder, required flag
- Where it appears (customer form, admin form, email)
- Field type and options (for dropdowns/radios)

---

## Custom Statuses and Priorities

Since 3.6.0:
- **Custom statuses** — `admin/custom_statuses.php` — define additional ticket states beyond New/Open/Resolved
- **Custom priorities** — `admin/custom_priorities.php` — define priorities beyond Low/Medium/High/Critical

---

## Theme and Frontend

### Theme System

The `theme/hesk3/` directory contains the active theme. The admin panel has a color customizer (`admin/admin_settings_theme.php`) that generates CSS variable overrides.

### Frontend Libraries (`js/`)

| Library | Purpose |
|---|---|
| jQuery 3.5.1 | DOM manipulation |
| jQuery UI | Datepicker, sortable |
| Selectize | Enhanced dropdowns |
| Dropzone | Drag-and-drop file uploads |
| TinyMCE 7.x | Rich text editor (also in `inc/tiny_mce/`) |
| Prism.js | Code syntax highlighting in KB articles |

### CSS (`css/`)

- `app.css` / `app.min.css` — main application stylesheet
- Supplementary: `dropzone.css`, `prism.css`, `selectize.css`

---

## Security Features

| Feature | Implementation |
|---|---|
| Input sanitization | HTMLPurifier (`inc/htmlpurifier/`) |
| SQL injection prevention | Parameterized queries via custom DB wrapper in `inc/common.inc.php` |
| XSS prevention | HTMLPurifier + output escaping in templates |
| Session security | Session-based auth, SameSite cookies, optional SSL-only |
| Login brute-force protection | Configurable attempt limit + timed lockout |
| MFA (2FA) | TOTP via `inc/mfa_functions.inc.php` — staff and/or customers |
| CAPTCHA | Image CAPTCHA, Google reCAPTCHA, or custom security question |
| Flood control | Rate limiting on ticket submission |
| Banned emails/IPs | `admin/banned_emails.php`, `admin/banned_ips.php` |
| Muted emails | Accept tickets but suppress all notifications |
| GDPR anonymization | Ticket anonymization removes all PII |
| Privilege escalation guard | "Elevator" prompt for sensitive admin operations |
| X-Frame-Options | Set in headers to prevent clickjacking |

---

## Cron Jobs (`cron/`)

HESK uses cron jobs for background tasks:

| Script | Purpose |
|---|---|
| `cron/email_overdue_tickets.php` | Sends email alerts for tickets past due date |
| IMAP/POP3 polling | Triggered by cron at configurable interval (default: 5 min) |

---

## Admin Panel Structure (`admin/`)

The admin panel is a traditional multi-page PHP application. Every admin page:
1. Includes `inc/common.inc.php` and `inc/admin_functions.inc.php`
2. Verifies admin session
3. Performs its own DB queries
4. Renders HTML output inline

### Settings Pages

| File | Description |
|---|---|
| `admin/admin_settings_general.php` | General help desk settings |
| `admin/admin_settings_email.php` | Email/SMTP/IMAP settings |
| `admin/admin_settings_helpdesk.php` | Ticket behavior settings |
| `admin/admin_settings_kb.php` | Knowledgebase settings |
| `admin/admin_settings_theme.php` | Theme and color customizer |
| `admin/admin_settings_misc.php` | Miscellaneous settings |

### Utility Pages

| File | Description |
|---|---|
| `admin/service_messages.php` | Banner messages / maintenance mode |
| `admin/canned.php` | Canned (template) responses |
| `admin/reports.php` | Reporting dashboard |
| `admin/oauth_providers.php` | Google/Microsoft OAuth2 setup |
| `admin/banned_emails.php` | Manage banned email addresses |
| `admin/banned_ips.php` | Manage banned IP addresses |
| `admin/muted_emails.php` | Manage muted email addresses |

### Cloud-Only Stubs

These pages exist but point to paid HESK Cloud features:

| File | Feature |
|---|---|
| `admin/module_statistics.php` | Advanced statistics |
| `admin/module_escalate.php` | Ticket escalation |
| `admin/module_satisfaction.php` | Customer satisfaction surveys |
| `admin/module_recurring_tickets.php` | Recurring ticket automation |

---

## Database Schema Notes

HESK uses **MyISAM** MySQL tables (no foreign key constraints, no transactions). Data integrity is enforced entirely in PHP code.

### Key Tables (prefixed with `hesk_`)

| Table | Description |
|---|---|
| `hesk_tickets` | All tickets; includes `custom1`–`custom100` columns for EAV custom fields |
| `hesk_replies` | Ticket replies (staff and customer) |
| `hesk_notes` | Internal ticket notes (staff-only) |
| `hesk_users` | Staff accounts |
| `hesk_customers` | Customer accounts (when enabled) |
| `hesk_categories` | Ticket categories/departments |
| `hesk_permission_groups` | Staff permission group definitions |
| `hesk_kb_cat` | Knowledgebase categories |
| `hesk_kb_articles` | Knowledgebase articles |
| `hesk_kb_attachments` | Files attached to KB articles |
| `hesk_attachments` | Files attached to tickets/replies |
| `hesk_ticket_to_customer` | Maps tickets to customer accounts |
| `hesk_linked_tickets` | Ticket linking relationships |
| `hesk_statuses` | Custom ticket statuses |
| `hesk_priorities` | Custom ticket priorities |
| `hesk_canned` | Canned responses |
| `hesk_service_messages` | Banner/service messages |
| `hesk_banned_emails` | Banned email addresses |
| `hesk_banned_ips` | Banned IP addresses |
| `hesk_muted_emails` | Muted email addresses |
| `hesk_log` | Ticket history/audit log |

---

## Vendor Libraries (`vendor/`)

Managed by Composer.

| Library | Purpose |
|---|---|
| `phpmailer/phpmailer` | Email sending |
| `paragonie/random_compat` | CSPRNG compatibility for PHP < 7 |
| `matthiasmullie/minify` | CSS/JS minification |

---

## Known Architectural Limitations

Agents working on this codebase should be aware of these structural constraints:

1. **MyISAM tables** — no foreign keys, no transactions; all relational integrity is PHP-level
2. **EAV antipattern** — `custom1`–`custom100` are flat columns on `hesk_tickets`, making multi-field queries difficult
3. **No plugin architecture** — every extension is a direct file modification; there are no hooks or events
4. **No REST API** — all communication is form-based server-side rendering; no JSON endpoints for external integration
5. **IMAP sync lag** — inbound email is polled on a cron schedule (~5-minute delay by default)
6. **No queue system** — email sending is synchronous within the request/response cycle
7. **Procedural architecture** — most code is procedural PHP; no MVC framework or dependency injection
8. **Single config file** — all configuration in one flat `hesk_settings.inc.php` PHP array

---

## Extension Points for UCU-Specific Development

Based on the strategic brief (`hesk-ucu-strategic-brief.html`), planned extension areas include:

### Quick Wins (Low effort, high impact)
- Enable customer accounts (`$hesk_settings['customer_accounts'] = 1`)
- Keyword-based auto-category routing in IMAP cron loop (`inc/pipe_functions.inc.php`)
- Fix form UX: update service banner and category descriptions
- Create KB articles for common UCU support requests
- Escalation cron for overdue ticket alerts

### Planned Integrations
- **Google OAuth SSO** — integrate `inc/oauth_functions.inc.php` for customer login via Google
- **Structured intake forms** — add category-specific custom fields for different request types
- **AI email classifier sidecar** — a separate process that reads incoming email queue and sets category before HESK processes it
- **Metabase analytics** — connect Metabase directly to the MySQL database for dashboards
- **AI reply suggestions** — a widget in `admin/admin_ticket.php` calling an LLM API for draft reply generation

---

## File Navigation Quick Reference

| Task | Key File(s) |
|---|---|
| Change ticket status | `change_status.php`, `admin/change_status.php` |
| Submit a new ticket | `submit_ticket.php` |
| Process inbound email | `inc/pipe_functions.inc.php` |
| Send email notification | `inc/email_functions.inc.php` |
| Authenticate a user | `login.php`, `inc/common.inc.php` |
| Configure the app | `hesk_settings.inc.php` |
| Manage staff permissions | `admin/manage_permission_groups.php`, `admin/manage_users.php` |
| Manage knowledgebase | `admin/manage_knowledgebase.php` |
| Add/edit custom fields | `admin/custom_fields.php` |
| Run DB migrations | `install/` (update wizard) |
| Send overdue alerts | `cron/email_overdue_tickets.php` |
