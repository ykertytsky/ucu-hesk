# HESK® Quick Install Guide

**Version:** 3.7.5 (13th February 2026)

**Navigation:** [Documentation Index](./index.md) | [Step by Step Guide](./step-by-step-guide.md) | [Changelog](./changelog.md)

---

## Contents

- [Install HESK](#install-hesk)
- [Upgrade from old HESK version](#upgrade-from-old-hesk-version)
- [Help & Support](#help--support)
- [Translate HESK](#translate-hesk-to-your-language)

---

## Install HESK

1. Upload HESK files to your server (e.g. `https://www.example.com/helpdesk`)
2. Open the **install** directory in your browser (e.g. `https://www.example.com/helpdesk/install`)
3. Click **INSTALL HESK** and follow the steps to check requirements and set up MySQL
4. Delete the **install** directory
5. Open the **admin** directory (e.g. `https://www.example.com/helpdesk/admin`)
6. The customer interface is at `https://www.example.com/helpdesk`
7. Things to do next:
   - **Profile** — set your name, email, signature, and a new password
   - **Settings** — set website URL, title, and get familiar with HESK settings
   - **Categories** — create help desk categories/departments
   - **Team** — create new staff accounts
   - **Knowledgebase** — create knowledgebase (FAQ) categories and articles
   - **Canned** — set reply templates (canned responses)
8. Support HESK development by [purchasing a license](https://www.hesk.com/buy.php)

---

## Upgrade from Old HESK Version

**Known upgrade issues:**
- HESK language will be reset to English if new text was added.
- When upgrading from very old versions, you might need to [reset your password](https://www.hesk.com/knowledgebase/?article=22).

**Upgrade steps:**

1. **BACKUP YOUR EXISTING HESK DATABASE AND FILES** — upgrades can go wrong, always have a backup
2. Upload all HESK files to your server **EXCEPT** these (do **NOT** upload these files):
   - `hesk_settings.inc.php`
   - `head.txt`
   - `header.txt`
   - `footer.txt`

   > **Note:** Did you rename your HESK's `/admin` folder? If yes, don't forget to move files from the new version's `/admin` folder into your renamed folder.

3. Open the **install** directory in your browser (e.g. `https://www.example.com/helpdesk/install`)
4. Click **UPDATE HESK** and follow instructions to update MySQL tables
5. Delete the **install** directory
6. Open the admin panel and log in. If your password doesn't work, [reset it](https://www.hesk.com/knowledgebase/?article=22)
7. Test HESK to make sure the upgrade was successful
8. If you own a HESK license, you might need to [download a new one](https://www.hesk.com/license)

---

## Help & Support

For comprehensive and up-to-date help and troubleshooting guides, visit the [HESK Knowledgebase](https://www.hesk.com/knowledgebase/).

---

## Translate HESK to Your Language

Multiple language packs are available thanks to community contributors: [HESK language packs](https://www.hesk.com/language/)

---

## More Free PHP Scripts

Get more free PHP scripts at [phpjunkyard.com](https://www.phpjunkyard.com/).

---

© Copyright [HESK.COM](https://www.hesk.com) 2005–2026. All rights reserved.
® HESK is a registered trademark of Klemen Stirn.
