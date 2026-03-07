# HESK 3 Changelog

**Version:** 3.7.5 (13th February 2026)

**Navigation:** [Documentation Index](./index.md) | [Quick Install Guide](./quick-guide.md) | [Step by Step Guide](./step-by-step-guide.md)

---

## Changes in 3.7.5 — 13th February 2026

- fix: permission group categories were not considered when adding collaborators
- fix: administrators should always be able to edit all permission groups
- fix: saving a customized theme could inadvertently reset colors

## Changes in 3.7.4 — 8th February 2026

- fix: remove possible invalid theme `$css_variable`
- fix: preserve custom theme variables when not using patch files

## Changes in 3.7.3 — 24th January 2026

- hide plain text password display when a new user or customer is successfully created
- modified color selector to allow manual modification (typing in, deleting) of HEX color codes
- during upgrade, detect if the users table already has a column named "active" and convert it to 3.7.x
- fix: creating customers in the admin panel "submit a ticket" form not working in 3.7.x when emails are optional

## Changes in 3.7.2 — 10th January 2026

- fix: adding new users broken after upgrading from a pre-3.7.0 version if "Staff nicknames" are turned off

## Changes in 3.7.1 — 8th January 2026

- fix: issues with customer to ticket mapping when importing from 3.4.x and earlier versions
- fix: public side not working on PHP 5.6 anymore

## Changes in 3.7.0 — 4th January 2026

- staff permission groups for easier application of permission combinations
- option to allow staff to use nicknames instead of their real names for customer interactions
- you can now deactivate help desk users (staff/administrators) instead of deleting them
- select which columns to display in the customer (public) side ticket list
- you can now modify customer (public) side colors from the admin panel
- improved handling of customer names when customer accounts are disabled
- improved detection of replying customer when multiple customers are copied on a ticket
- mute email addresses (accept tickets, but don't send any email notifications)
- in email to ticket functions, copied contacts (To and Cc) can be included as ticket followers
- automatically order categories by name or ID
- link selected tickets in bulk
- link tickets using ticket number or tracking ID
- merge customer accounts
- download or delete selected attachments
- work-around for PHP 8.5 deprecated functions
- show customer name in ticket history log instead of "Customer"
- option to show full email addresses in the staff tickets table
- you can now force extending staff and customer sessions using an iframe trick
- added a tool for clearing HESK cache
- edit head/header/footer files from admin panel
- choose multiple categories in the "Show tickets" and "Find tickets" forms
- additional settings for browser autocomplete and remembering custom field data
- additional data included in ticket exports (number of replies, reply messages)
- fix: "Download all" ignoring files with duplicate names
- fix: processing incoming emails fails if the stream starts with a UTF-8 BOM
- fix: don't change the ticket last updated timestamp when a blank customer IP is updated
- fix: missing ticket customer data in an expired session with auto-login case
- fix: don't pass a non-string to `password_verify`
- fix: hard-coded admin path in email settings
- fix: the "Bookmarks" quick link is not colored when selected
- fix: priority drop-down selects are missing colored flag icons
- fix: SQL error when re-sending emails of an unassigned ticket without collaborators

## Changes in 3.6.4 — 17th August 2025

- fix: a public article in a private sub-category appears on the knowledgebase.php page
- fix: possible incorrect count of valid MFA backup codes shown in admin panel
- fix: PHP 8.4 deprecates implicitly nullable types (MFA module)
- fix: don't pass a null value to `setcookie()`

## Changes in 3.6.3 — 15th July 2025

- fix: knowledgebase rating not working anymore

## Changes in 3.6.2 — 13th July 2025

- fix: datepicker in admin panel not working correctly on mobile devices
- fix: permission combinations trigger a wrong ticket count in the ticket list
- fix: ignore emails from email addresses registered in HESK settings to prevent loops
- fix: the "Submit as" button is not working when "Time worked" is turned off
- fix: script execution time limit calculation ignored by a fixed value
- fix: hide the "Add a reply" link for staff with no reply permission
- fix: cannot lock tickets with "Can resolve tickets" but without "Edit ticket replies" permission
- fix: linked anonymized tickets now displayed; remove links when anonymizing
- fix: don't redirect staff to the submitted ticket if no permission to view it
- fix: wrong ticket count for users with limited permissions on manage status and priority pages
- minor UI changes

## Changes in 3.6.1 — 21st June 2025

- fix: cannot translate "Unlink" button text
- fix: hard-coded database table prefix in Manage customers
- fix: SQL error when adding a note to a ticket assigned to someone else with no collaborators
- fix: remove collaborators from tickets when their category access is revoked
- fix: linking tickets not working with some custom date formats
- fix: cannot create multiple customers with no email in admin panel
- minor UI changes

## Changes in 3.6.0 — 11th June 2025

- **[CLOUD ONLY]** Recurring tickets: automatically create predefined tickets
- multiple staff on a ticket; tickets can now have one owner and multiple collaborators
- linked tickets; link several tickets to easier track related issues
- create custom ticket priorities, manage built-in priorities
- file attachments can be added directly to emails instead of linked
- you can now select the desired IMAP mailbox when fetching emails
- auto-install additional language packs if server permissions allow
- improved ADA Compliance
- allow MFA Authenticator App registration with cURL disabled (manual code entry)
- MFA can now be forced for help desk staff and/or customers
- staff that cannot manage categories can now at least view them
- choose what to do with tickets when deleting a customer
- simplified favicon to a single `favicon.ico` file
- fix: disable ticket submission when files are being uploaded
- fix: detect Nginx `HTTP_X_FORWARDED_PROTO` https
- fix: public KB articles and categories within a private parent category should be private
- fix: cached SQL queries could cause different results order between pages
- updated TinyMCE to 7.9.1

## Changes in 3.5.3 — 8th March 2025

- fix: improper customer handling when emails are not required
- fix: time worked not updated when submitting notes in a special case
- fix: passing null instead of a string to some functions is deprecated
- updated TinyMCE to 7.7.1 for better mobile performance

## Changes in 3.5.2 — 12th January 2025

- you can now copy the customer email and IP address when viewing details from admin ticket
- when submitting a ticket from admin panel, category assign settings are used to select default owner
- added ticket subject tag for use in canned responses
- provide a way for customers to change their preferred default language
- fix: query searches by customer in replies can take a long time
- fix: SQL error when retrieving existing ticket IDs in MySQL strict mode
- fix: the "View all customers (but not manage them)" permission not showing customers
- fix: very long email IDs can be truncated when inserted into the database
- fix: allow additional characters in MySQL password
- fix: customer emails sent in default, not selected language
- fix: customer auto-login not working correctly in a specific case

## Changes in 3.5.1 — 15th November 2024

- fix: cannot delete customers from the database when customer accounts are disabled
- fix: link to the wrong `download_attachment.php` file in customer emails
- fix: "Submit as" button not working with Ticket Formatting set to Rich Text

## Changes in 3.5.0 — 4th November 2024

- HESK now supports customer accounts; they can be disabled, optional or required
- service messages can now be displayed on multiple customer-side pages
- a barcode (C128, C39, QR code) can be added when printing tickets
- when submitting a ticket or a reply, HESK can show a "Submitting, please wait" message
- copy/generate a public link to a ticket from the admin panel
- rich text editors now have anchor plugin enabled
- number of available custom fields doubled to **100**
- staff can now bookmark tickets (you only see your own bookmarks)
- staff can send a ticket email reminder to the assigned staff member
- added a "Download all" link to ticket attachments
- added IMAP option to disable GSSAPI authenticator (Kerberos error work-around)
- added a limit to the number of email recipients in a single email
- added closed-at and ticket URL to ticket exports
- added option to include ticket history log in ticket exports
- added an easy way to load an extra custom Javascript file to HESK admin

## Changes in 3.4.6 — 11th August 2024

- **Security fix:** reflected XSS vulnerability patched

---

> For the full historical changelog covering all HESK 3.x versions, refer to the original [docs/changelog.html](../changelog.html) file or visit [hesk.com](https://www.hesk.com).

---

© Copyright [HESK.COM](https://www.hesk.com) 2005–2026. All rights reserved.
® HESK is a registered trademark of Klemen Stirn.
