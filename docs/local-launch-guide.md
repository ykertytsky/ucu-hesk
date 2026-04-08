# How to run UCU HESK locally

You need to **run** the project only if you want to:
- See the form and customer portal in the browser
- Test changes (e.g. form UX, categories, custom fields)
- Use the install wizard and admin panel

You **do not** need to run it if you only:
- Read or edit code, docs, or prototypes
- Plan enhancements or write specs
- Work on static HTML/CSS/JS prototypes in `docs/prototypes/`

---

## What this repository is for

This repo is the **full HESK help desk application**: PHP backend, MySQL database, customer-facing portal (submit tickets, view ticket, knowledge base), and admin panel (manage tickets, staff, categories, settings). You run it to get a working help desk you can use and customize (e.g. for UCU’s form UX, categories, OAuth).

---

## Quick launch (minimal setup)

### 1. Prerequisites

- **PHP** 7.4+ (8.x recommended), with extensions: `pdo_mysql`, `mbstring`, `gd`, `json`, `session`, `xml`, `zip`
- **MySQL** 5.x or 8.x (or MariaDB) running locally

### 2. Create the database

In MySQL (command line or GUI like phpMyAdmin / TablePlus):

```sql
CREATE DATABASE hesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hesk'@'localhost' IDENTIFIED BY 'hesk_local';
GRANT ALL PRIVILEGES ON hesk.* TO 'hesk'@'localhost';
FLUSH PRIVILEGES;
```

(Use any database name, user, and password you like; you’ll enter them in the install wizard.)

### 3. Optional: point settings to your DB before install

If you prefer not to use the install wizard first time, edit `hesk_settings.inc.php` in the project root:

- `$hesk_settings['db_host']` = `'localhost'`
- `$hesk_settings['db_name']` = your DB name (e.g. `'hesk'`)
- `$hesk_settings['db_user']` = your DB user (e.g. `'hesk'`)
- `$hesk_settings['db_pass']` = your DB password
- `$hesk_settings['hesk_url']` = `'http://localhost:8000'` (or the URL you use in step 4)
- `$hesk_settings['site_url']` = same base URL if needed

**Note:** The install wizard can create tables for you. If `hesk_settings.inc.php` already has valid DB credentials, the wizard will use them and create the schema.

### 4. Start PHP built-in server

From the project root (where `index.php` and `hesk_settings.inc.php` are):

```bash
cd /Users/danylokuryliak/Desktop/University/BA/ucu-hesk
php -S localhost:8000
```

Keep this terminal open.

### 5. Run the install wizard

1. Open in browser: **http://localhost:8000/install/**
2. Step 1: Accept the license → continue.
3. Step 2: Check that requirements pass (PHP version, extensions).
4. Step 3: Enter MySQL host (`localhost`), database name, user, password. Test connection, then run “Create database tables”.
5. Step 4: Finish. Then **delete or rename the `install/` folder** so the help desk is no longer in install mode (e.g. `mv install install.bak`).

### 6. Use the help desk

- **Customer portal (form, KB, my tickets):**  
  http://localhost:8000/  
  Click “Submit a ticket” → choose category → fill form.

- **Admin panel:**  
  http://localhost:8000/admin/  
  Log in with the admin user/password you set in the wizard (or that was in the wizard step 3). Configure categories, custom fields, and settings here.

---

## If something fails

- **“Database connection failed”**  
  Check MySQL is running, and that `hesk_settings.inc.php` (or the wizard) has the correct host, database name, user, and password.

- **Blank page or 500**  
  Enable debug: in `hesk_settings.inc.php` set `$hesk_settings['debug_mode'] = 1;` and reload. Check the browser and PHP error log for the exact error.

- **Sessions / install wizard resets**  
  PHP built-in server is single-threaded; use one browser tab for the wizard. If your PHP is configured with a custom session path, ensure it’s writable.

---

## Alternative: MAMP / XAMPP / Laravel Valet

- **MAMP / XAMPP:** Put the project in the web root (e.g. `htdocs/ucu-hesk`), ensure Apache/Nginx uses that folder as document root, and that PHP can talk to MySQL. Then open `http://localhost/ucu-hesk/install/` (or the URL your stack uses).
- **Laravel Valet:** `valet link ucu-hesk` in the project directory, then open `http://ucu-hesk.test/install/`.

Once install is done and `install/` is removed, the customer form is at the same base URL (e.g. `http://localhost:8000/` → Submit a ticket).
