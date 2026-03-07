# HESK® Step by Step Install Guide

**Version:** 3.7.5 (13th February 2026)

**Navigation:** [Documentation Index](./index.md) | [Quick Install Guide](./quick-guide.md) | [Changelog](./changelog.md)

---

## Contents

- [Install HESK](#install-hesk)
- [Upgrade from Old HESK Version](#upgrade-from-old-hesk-version)
- [Help & Troubleshooting](#help-and-troubleshooting)
- [Translate HESK](#translate-hesk-to-your-language)

---

> **Please take 5 minutes to read the installation instructions carefully and thoroughly! Doing so will ensure a proper and easy installation.**

> **Before installing HESK**, you will need to obtain your MySQL database information — **database name**, **user**, and **password**. You need to get this information from your **hosting company**; HESK cannot help you find it.

> **TIP:** Want a hassle-free help desk working in minutes? Sign up for [HESK Cloud](https://www.hesk.com/remote-help-desk.php) instead.

---

## Install HESK

1. **Connect with FTP** to the public folder of your server.

   > **TIP:** The public folder is usually called `public_html`, `www`, `site`, or `htdocs`.
   > **TIP:** Learn how to FTP files by reading this [FTP and CHMOD tutorial](https://www.phpjunkyard.com/tutorials/ftp-chmod-tutorial.php).

2. **Create a new folder** where you will install HESK. Name it anything you like, e.g. `helpdesk` or `support`.
   ```
   Example path: /public_html/helpdesk
   Corresponding URL: https://www.example.com/helpdesk
   ```

3. **Upload all HESK files** to your server. PHP files must be transferred in ASCII mode; images in BINARY mode.

   > **TIP:** Most FTP clients will automatically select the proper transfer mode.

4. **Open the install directory** in your browser:
   ```
   https://www.example.com/helpdesk/install
   ```

5. The HESK setup script will run. Click **INSTALL HESK** and follow the 4-step wizard:
   - **Step 1: License agreement** — read and confirm you agree with the HESK License.
   - **Step 2: Check setup** — the script tests your server for required settings.
   - **Step 3: Database settings** — enter your MySQL database settings. **You must get the correct MySQL information from your hosting company!**
   - **Step 4: Setup database tables** — the script installs the MySQL tables.

6. **Before closing the installation script, DELETE the `install` directory from your server!**

7. Set up your help desk by opening the **admin** folder:
   ```
   https://www.example.com/helpdesk/admin/
   ```
   > **TIP:** HESK passwords are case-sensitive (`ABCD` is not the same as `abcd`); usernames are not.

8. Click the **Settings** link in the top menu to configure your help desk.

9. Take time to review all available settings. Click **Save changes** when done.

10. **Things to do next:**
    - Click **Profile** to set your name, email, signature, and password.
    - Add **categories** (departments) on the Categories page. You cannot delete the default category, but you can rename it.
    - Create additional **staff accounts** on the Team page. You cannot delete the default Administrator, but you can change the username.
    - Create **canned responses** on the Canned page — pre-written replies to common questions.
    - Set up your **knowledgebase** (FAQ) — a well-written knowledgebase reduces support tickets significantly.

11. Customers can submit tickets and browse the knowledgebase at:
    ```
    https://www.example.com/helpdesk
    ```

12. If you have problems, see the [Help & Troubleshooting](#help-and-troubleshooting) section below.

13. Support HESK development by [purchasing a license](https://www.hesk.com/buy.php).

---

## Upgrade from Old HESK Version

> **Please take 5 minutes to read the upgrade instructions carefully!**

**Known upgrade issues:**
- HESK language will be reset to English if new text was added.
- When upgrading from very old versions, you might need to [reset your password](https://www.hesk.com/knowledgebase/?article=22).

> **TIP:** Perform the upgrade during low-traffic hours.

> Your existing HESK files are in a single directory on your server. You will be replacing most of its contents with the new HESK version files.

**Upgrade steps:**

1. **BACKUP YOUR EXISTING HESK DATABASE AND FILES** — upgrades can go wrong, always have a backup.

   > **TIP:** Most hosting companies allow you to backup files and databases from the hosting control panel.

2. Upload all new HESK files to your existing HESK directory **EXCEPT** these:
   - `hesk_settings.inc.php`
   - `head.txt`
   - `header.txt`
   - `footer.txt`

   > **TIP:** Transfer images in BINARY and all other files in ASCII mode.
   > **Note:** Did you rename your `/admin` folder? Move files from the new version's `/admin` into your renamed folder.

3. Open the **install** directory in your browser:
   ```
   https://www.example.com/helpdesk/install
   ```

4. Click **UPDATE HESK** and follow the 4-step wizard:
   - **Step 1: License agreement**
   - **Step 2: Check setup**
   - **Step 3: Database settings** — settings must be the same as your old installation
   - **Step 4: Update database tables**

5. **DELETE the `install` directory** from your server before closing the upgrade script.

6. Open the admin panel and log in. If your password doesn't work, [reset it](https://www.hesk.com/knowledgebase/?article=22).

7. Test HESK thoroughly. You may need to reconfigure Help Desk title and Custom field settings.

8. If you own a HESK license, you might need to [download a new one](https://www.hesk.com/license).

---

## Help and Troubleshooting

For comprehensive and up-to-date help and troubleshooting guides, visit the [HESK Knowledgebase](https://www.hesk.com/knowledgebase/).

---

## Translate HESK to Your Language

Multiple language packs are available: [HESK language packs](https://www.hesk.com/language/)

---

## More Free PHP Scripts

[PHP Scripts at phpjunkyard.com](https://www.phpjunkyard.com/)

---

© Copyright [HESK.COM](https://www.hesk.com) 2005–2026. All rights reserved.
® HESK is a registered trademark of Klemen Stirn.
