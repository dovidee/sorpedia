#!/bin/sh
# Waits for the database and builds it from data/raw on first start.
set -e
cd /var/www/app
echo "Waiting for MariaDB ..."
until php -r 'require "src/bootstrap.php"; db();' 2>/dev/null; do sleep 2; done
if ! php -r 'require "src/bootstrap.php"; exit(db()->query("SHOW TABLES LIKE \"products\"")->fetch() ? 0 : 1);'; then
  echo "Empty database, running import ..."
  php scripts/import.php
fi
# Weather and route conditions every 15 minutes (scripts/conditions.php); a failed fetch falls back to demo rows
(while true; do php scripts/conditions.php >/dev/null 2>&1; sleep 900; done) &
# Apache runs as www-data: it writes the error log and takes backups from the admin page, so it owns these folders.
# The nightly backup runs as the same user, so the admin page can list and read every file.
mkdir -p data/backups data/logs
chown -R www-data:www-data data/backups data/logs
chmod 0700 data/backups
# Database backup at start and then every 24 hours into data/backups (scripts/backup.php keeps the newest 14)
(while true; do runuser -u www-data -- php scripts/backup.php >>data/logs/backup.log 2>&1; sleep 86400; done) &
exec "$@"
