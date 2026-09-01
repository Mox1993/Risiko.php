#!/usr/bin/env bash
# Startet den PHP-Built-in-Server im Hintergrund, sobald der Codespace bereit ist.
# Läuft idempotent: falls der Server schon läuft, wird nichts doppelt gestartet.

set -e

PORT=8000
DOCROOT="public"
LOGFILE="/tmp/php-server.log"

if ! pgrep -f "php -S 0.0.0.0:${PORT}" > /dev/null 2>&1; then
    cd "$(dirname "$0")/.."
    nohup php -S 0.0.0.0:${PORT} -t "${DOCROOT}" > "${LOGFILE}" 2>&1 &
    disown
    echo "PHP-Server gestartet auf Port ${PORT} (Log: ${LOGFILE})"
else
    echo "PHP-Server läuft bereits auf Port ${PORT}"
fi
