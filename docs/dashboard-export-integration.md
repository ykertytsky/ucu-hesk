# Dashboard export integration

This document describes the **dashboard export** feature added to this HESK fork: pushing ticket exports to an external HTTP endpoint (for example a Next.js API route or another service) as **multipart file upload**, with optional **Bearer token** authentication and **persistent sync status** in the admin UI.

**Audience:** developers integrating a receiver service, operators configuring the help desk, and anyone reviewing or extending the PHP implementation.

---

## 1. Purpose

Standard HESK export produces XML (optionally zipped) for download in the browser. This fork adds an alternative path: the same XML payload can be **POSTed** to a configurable URL so an external **dashboard** or analytics pipeline can ingest tickets without manual download.

Typical use cases:

- Feed a separate reporting or BI application.
- Trigger downstream processing when staff explicitly syncs (bulk or single ticket).
- Keep a lightweight audit trail of last sync attempts via on-disk JSON state.

---

## 2. Configuration

### 2.1 Settings keys (`hesk_settings.inc.php`)

| Key | Type | Purpose |
|-----|------|---------|
| `dashboard_export_url` | string | Full URL of the HTTP endpoint that accepts the upload. If empty, dashboard sync is disabled everywhere in the UI. |
| `dashboard_export_token` | string | Optional secret. When non-empty, HESK sends `Authorization: Bearer <token>` on the upload request. |

Defaults in the shipped `hesk_settings.inc.php` are empty strings. Administrators normally set values via **Admin → Settings → Miscellaneous** (see below); editing `hesk_settings.inc.php` directly is still possible for deployments that prefer file-based config.

### 2.2 Admin UI

**Path:** `admin/admin_settings_misc.php` (Miscellaneous settings).

Under the **Other** section, two fields were added (labels from `language/en/text.php`):

- **Dashboard endpoint URL** — text input, posted as `s_dashboard_export_url`, max length 255.
- **Dashboard bearer token** — **password** input (masked), posted as `s_dashboard_export_token`, max length 255. Helper text explains that the token is optional and sent as a Bearer header.

**Persistence:** `admin/admin_settings_save.php` validates the URL with `hesk_validateURL()` when non-empty; an invalid URL is rejected. The token is passed through `hesk_input()`. Both keys are written into `hesk_settings.inc.php` when settings are saved.

**Demo mode:** `inc/admin_settings_demo.inc.php` overrides both keys with the generic demo placeholder so live secrets are not shown in demos.

---

## 3. Permissions and feature gates

- **Export permission:** All dashboard send actions require the staff permission **`can_export`** (same as classic XML/ZIP export).
- **URL gate:** Buttons and links that send data to the dashboard are shown or enabled only when `dashboard_export_url` is non-empty. An empty URL means “feature off” without hiding unrelated export options.

---

## 4. User-facing workflows

### 4.1 Bulk sync from the Export report (`admin/export.php`)

Staff open **Reports → Export** (`export.php`). After choosing date range, filters, history/replies options (same as for file export), they can:

1. **Export** — existing behavior: build XML/ZIP for download.
2. **Send to dashboard** — secondary submit button (`name="send_dashboard"`, `value="1"`). It runs only when the dashboard URL is configured; otherwise the button is **disabled**.

Implementation notes:

- For the dashboard action, the code path calls `hesk_token_check()` (CSRF) and then `hesk_export_push_to_dashboard($sql, …)` with the **same SQL** as the normal export for the current filters.
- A **status banner** at the top of the page summarizes the last attempt using persistent state (see [Section 7](#7-sync-state-file)).
- On completion, success/error messages use the `dashboard_sync_*` language strings. A small inline script logs structured details to the **browser console** for debugging (`[HESK dashboard sync]`).

### 4.2 Single-ticket sync (`admin/export_ticket.php`)

From **Admin ticket view**, in the **More** menu, staff see **Send to dashboard** next to **Export** when:

- They may export (`can_export`),
- The ticket is not anonymized,
- `dashboard_export_url` is set.

Link shape:

```text
export_ticket.php?track=<TRACKID>&dashboard=1&token=<CSRF>
```

Flow:

1. `export_ticket.php` runs `hesk_export_push_to_dashboard($sql, true)` for that ticket only (`export_selected`-style single ticket).
2. Result messages are flashed via `hesk_process_messages()` and the user is redirected back to `admin_ticket.php`.
3. Console log payload for the redirect page is stored in **`$_SESSION['dashboard_sync_console_log']`** and emitted once on the next ticket page load (`admin/admin_ticket.php`), then cleared — so developers still get a console line without leaving it in session forever.

---

## 5. HTTP API contract (receiver implementation)

HESK acts as an **HTTP client**. Your endpoint must accept what HESK sends.

### 5.1 Method and body

- **Method:** `POST`
- **Body:** `multipart/form-data`
- **File field name:** **`file`** (required name for compatibility with common `request.formData().get("file")` handlers).

The file part is the **raw XML** export (same structure as the standalone XML export), with filename `<export_name>.xml` and content type `application/xml`.

**Important:** Do not manually set `Content-Type` for the whole request on the client — PHP’s cURL builds the multipart boundary. The code explicitly sends an `Expect:` header (empty value) to avoid `100-continue` issues with some proxies.

### 5.2 Optional authentication

If `dashboard_export_token` is non-empty:

```http
Authorization: Bearer <dashboard_export_token>
```

### 5.3 Other request headers

- `User-Agent: HESK-DashboardExport/<hesk_version>`
- HTTP version forced to **1.1** where supported.

### 5.4 Timeouts

| Phase | Timeout |
|-------|---------|
| Connect | 15 seconds |
| Total request | 120 seconds |

Large exports may need adequate limits on the receiver (reverse proxy, PHP, Node body limits).

### 5.5 Success and failure semantics

- **Success:** HTTP status code **in the range 200–299**. Response body is not required for success handling (but may be logged elsewhere).
- **Failure:** Any other status, or cURL-level failure (no HTTP response).

On failure, if the response body is JSON and contains an **`error`** string field, HESK appends it to the user-visible error message (`Remote: …`).

Example error payload your API might return:

```json
{ "error": "Invalid token" }
```

### 5.6 PHP prerequisites on the HESK server

The upload path requires:

- `curl` extension (`curl_init`).
- **`CURLFile`** class (PHP 5.5+), used for the multipart file part.

If either is missing, sync aborts with a clear language-string message and state is written (see below).

---

## 6. Implementation pipeline (server-side)

Main entry point:

```text
hesk_export_push_to_dashboard($sql, $export_selected, $export_history, $export_replies)
```

Defined in `inc/export_functions.inc.php`.

High-level steps:

1. If `dashboard_export_url` is empty → fail fast with “not configured” and update sync state.
2. Call **`hesk_export_build_xml_file()`** — same pipeline as classic export: builds an XML file on disk under the export cache directory.
3. If zero tickets match → treat as failure (`n2ex`-style messaging) and update state.
4. Verify cURL / `CURLFile` / file readable.
5. **POST** the XML as multipart field `file` to `dashboard_export_url`.
6. Delete the temporary XML file after the request (whether success or failure).
7. On HTTP 2xx → success; else build error string (optional JSON `error`).
8. Update **`dashboard_export_sync.json`** via `hesk_write_dashboard_sync_state()`.

Helper functions in the same file:

| Function | Role |
|----------|------|
| `hesk_get_dashboard_sync_state()` | Read JSON state from disk (returns array). |
| `hesk_write_dashboard_sync_state($state)` | Merge into existing state and write JSON. |
| `hesk_get_dashboard_sync_state_file()` | Path to the JSON file. |
| `hesk_dashboard_sync_client_log_data()` | Build a safe array for browser console logging. |
| `hesk_dashboard_sync_emit_console_script_from_data()` | Output `<script>console.log("[HESK dashboard sync]", …)</script>`. |

---

## 7. Sync state file

**Path:** `{HESK_PATH}{cache_dir}/dashboard_export_sync.json`

Typically: `cache/dashboard_export_sync.json` relative to the installation root (assuming default `cache_dir`).

**Not committed to Git** in a normal deployment — it is runtime state, created when needed. The export cache directory is prepared with `hesk_prepare_export_cache_dir()`.

### 7.1 Fields (conceptual)

Written incrementally; merges preserve unspecified keys when updating:

| Field | Meaning |
|-------|---------|
| `last_attempt_at` | Timestamp of the most recent sync attempt (success or failure). |
| `last_success_at` | Timestamp of the last **successful** HTTP 2xx sync (only set on success). |
| `last_http_code` | Last HTTP status code from the endpoint (0 if no response). |
| `last_error` | Human-readable error message for the last failure; cleared on success. |
| `last_ticket_count` | Number of tickets included in the last attempt’s XML batch. |

The Export page uses this file to show the blue **Dashboard sync status** notification (configured / never run / last success / last failure + error text).

---

## 8. Security considerations

1. **Secrets:** The bearer token lives in `hesk_settings.inc.php` on the server. Restrict file permissions and backups accordingly. The admin UI uses a **password** input so shoulder-surfing is harder; the value is still present in HTML source when editing (standard limitation — rotating the token after use on a shared machine is good practice).

2. **Transport:** Prefer **HTTPS** for `dashboard_export_url` in production so the XML and token are not sent in clear text.

3. **CSRF:** Bulk export uses `hesk_token_check()` for the dashboard submit; single-ticket links include the session token query parameter consistent with other admin actions.

4. **Authorization:** The receiving service must validate the Bearer token (if used) and must not rely on obscurity alone.

---

## 9. Troubleshooting

| Symptom | Things to check |
|---------|-----------------|
| “Dashboard sync endpoint is not configured” | Set **Dashboard endpoint URL** in Misc settings or `dashboard_export_url` in config. |
| Button disabled on Export page | Same — URL empty. |
| “requires the PHP cURL extension” | Enable `curl` in `php.ini`. |
| “requires the CURLFile class” | PHP version / build too old; upgrade to 5.5+ with curl. |
| HTTP 4xx/5xx with generic message | Check receiver logs; if API returns JSON `{ "error": "…" }`, that string is surfaced. |
| Timeouts | Reduce batch size (filters), increase server/proxy timeouts, or optimize receiver. |
| Status always “never run” | Permissions on `cache/` — state file cannot be written. |

---

## 10. Source file reference

| File | Role |
|------|------|
| `inc/export_functions.inc.php` | Core: `hesk_export_push_to_dashboard`, sync state helpers, console logging helpers. |
| `admin/export.php` | Bulk UI, status banner, `send_dashboard` handling. |
| `admin/export_ticket.php` | Single-ticket `dashboard=1` handling and redirect. |
| `admin/admin_ticket.php` | “Send to dashboard” link; one-shot console log emission from session. |
| `admin/admin_settings_misc.php` | URL and token form fields. |
| `admin/admin_settings_save.php` | Validation and persistence of both settings. |
| `hesk_settings.inc.php` | Default keys `dashboard_export_url`, `dashboard_export_token`. |
| `inc/admin_settings_demo.inc.php` | Demo overrides for URL/token. |
| `language/en/text.php` | All `dashboard_sync_*` user-visible strings. |

---

## 11. Related documentation

- [`codebase-features.md`](codebase-features.md) — high-level map of the HESK fork.
- Original HESK export behavior is unchanged for users who only use **Export** (download); dashboard sync is an additional path.

---

*Last updated to match the dashboard export implementation in this repository (UCU HESK fork).*
