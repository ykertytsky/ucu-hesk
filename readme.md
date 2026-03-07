# UCU HESK — Help Desk Software Fork

A fork of [HESK](https://www.hesk.com) (v3.7.5) — a PHP-based help desk software for managing customer support tickets. This fork is developed as part of the **Business Analysis Course at Ukrainian Catholic University (UCU)**.

The goal is to make HESK more accessible and easier to use, with planned improvements and new features targeting UCU's institutional support workflows.

---

## Features

- Ticket submission via web form, email (IMAP/POP3/piping), or admin panel
- Ticket management: assignment, priorities, custom statuses, due dates, merging, linking
- Knowledgebase (FAQ) with categories, full-text search, and article ratings
- Staff management with role-based permission groups and optional MFA
- Customer accounts (optional or required)
- Multi-channel email integration with SMTP, IMAP, and OAuth2 support (Gmail, Microsoft 365)
- 100 configurable custom fields per ticket
- GDPR-compliant ticket anonymization
- Spam protection: CAPTCHA, reCAPTCHA, IP/email banning, flood control
- Theme customization with color picker
- CSV/XML ticket exports and reporting

---

## Getting Started

### Requirements

- PHP 5.6 – 8.5
- MySQL 5.x or later
- A web server (Apache, Nginx, etc.)
- An SMTP server or Gmail/Microsoft 365 account for email

### Installation

1. **Clone or download** the repository and upload files to your web server's public directory:
   ```
   /public_html/helpdesk/
   ```

2. **Create a MySQL database** and note the database name, user, and password. You will need these during setup.

3. **Open the install wizard** in your browser:
   ```
   https://your-domain.com/helpdesk/install
   ```

4. Click **INSTALL HESK** and complete the 4-step wizard:
   - Step 1: Accept the license agreement
   - Step 2: Server requirements check
   - Step 3: Enter your MySQL credentials
   - Step 4: Create database tables

5. **Delete the `install/` directory** from your server before proceeding.

6. **Open the admin panel** to configure your help desk:
   ```
   https://your-domain.com/helpdesk/admin
   ```

7. Complete the initial setup:
   - **Profile** — set your name, email, and password
   - **Settings** — configure the site URL, title, and email settings
   - **Categories** — create support departments/categories
   - **Team** — add staff accounts
   - **Knowledgebase** — create FAQ categories and articles
   - **Canned responses** — add pre-written reply templates

8. The customer-facing portal is available at:
   ```
   https://your-domain.com/helpdesk
   ```

### Local Development

For local development using a standard LAMP/LEMP stack:

```bash
# Example with PHP built-in server (for quick testing only)
php -S localhost:8000

# Then open: http://localhost:8000/install
```

> For a full local environment, use tools like MAMP, XAMPP, Laravel Valet, or Docker with a PHP + MySQL stack.

### Configuration

All configuration lives in `hesk_settings.inc.php`. Key values to set:

```php
$hesk_settings['db_host']    = 'localhost';
$hesk_settings['db_name']    = 'your_database';
$hesk_settings['db_user']    = 'your_user';
$hesk_settings['db_pass']    = 'your_password';
$hesk_settings['hesk_url']   = 'https://your-domain.com/helpdesk';
$hesk_settings['noreply_mail'] = 'support@your-domain.com';
```

> Do not upload `hesk_settings.inc.php` when upgrading — it contains your configuration and database credentials.

### Upgrading

1. **Backup** your existing database and files
2. Upload new HESK files **except**: `hesk_settings.inc.php`, `head.txt`, `header.txt`, `footer.txt`
3. Open the install wizard and click **UPDATE HESK**
4. Delete the `install/` directory after completion

---

## Documentation

| Document | Description |
|---|---|
| [docs/existing/index.md](./docs/existing/index.md) | Documentation home |
| [docs/existing/quick-guide.md](./docs/existing/quick-guide.md) | Quick install guide for experienced webmasters |
| [docs/existing/step-by-step-guide.md](./docs/existing/step-by-step-guide.md) | Detailed install guide |
| [docs/existing/changelog.md](./docs/existing/changelog.md) | Version history |
| [docs/existing/license.md](./docs/existing/license.md) | HESK End User License Agreement |
| [docs/codebase-features.md](./docs/codebase-features.md) | Comprehensive codebase feature reference for developers and agents |

---

## Project Context

This project is developed as part of the **Business Analysis Course at UCU**. The strategic context, identified pain points, and planned improvements for UCU's institutional use case are documented in `hesk-ucu-strategic-brief.html`.

**Key areas of focus:**
- Enabling Google OAuth SSO for UCU accounts
- Keyword-based auto-routing for incoming email tickets
- Improved onboarding and form UX for students
- Analytics integration (Metabase)
- AI-assisted ticket classification and reply suggestions

---

## Development Team

- Yarema Kertytsky

---

## Credits

HESK is developed and maintained by [HESK.COM](https://www.hesk.com) (Klemen Stirn). This repository is a fork. We do not take credit for the original codebase.

© HESK.COM 2005–2026. HESK is a registered trademark of Klemen Stirn.
See [docs/existing/license.md](./docs/existing/license.md) for the full license agreement.
