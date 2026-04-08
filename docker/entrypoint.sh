#!/bin/bash
set -e

# Patch hesk_settings.inc.php for Docker (DB host and URL)
HESK_SETTINGS="${APACHE_DOCUMENT_ROOT:-/var/www/html}/hesk_settings.inc.php"
if [ -f "$HESK_SETTINGS" ]; then
    [ -n "$HESK_DB_HOST" ] && sed -i "s|\$hesk_settings\['db_host'\]=.*|\$hesk_settings['db_host']='${HESK_DB_HOST}';|" "$HESK_SETTINGS"
    [ -n "$HESK_DB_NAME" ] && sed -i "s|\$hesk_settings\['db_name'\]=.*|\$hesk_settings['db_name']='${HESK_DB_NAME}';|" "$HESK_SETTINGS"
    [ -n "$HESK_DB_USER" ] && sed -i "s|\$hesk_settings\['db_user'\]=.*|\$hesk_settings['db_user']='${HESK_DB_USER}';|" "$HESK_SETTINGS"
    [ -n "$HESK_DB_PASS" ] && sed -i "s|\$hesk_settings\['db_pass'\]=.*|\$hesk_settings['db_pass']='${HESK_DB_PASS}';|" "$HESK_SETTINGS"
    [ -n "$HESK_URL" ]     && sed -i "s|\$hesk_settings\['hesk_url'\]=.*|\$hesk_settings['hesk_url']='${HESK_URL}';|" "$HESK_SETTINGS"
    [ -n "$HESK_URL" ]     && sed -i "s|\$hesk_settings\['site_url'\]=.*|\$hesk_settings['site_url']='${HESK_URL}';|" "$HESK_SETTINGS"
fi

# Ensure writable dirs (may be bind-mounted)
for d in cache attachments; do
    [ -d "${APACHE_DOCUMENT_ROOT:-/var/www/html}/$d" ] && chown -R www-data:www-data "${APACHE_DOCUMENT_ROOT:-/var/www/html}/$d" || true
done

exec "$@"
