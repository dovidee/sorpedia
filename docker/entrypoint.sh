#!/bin/sh
# Venter på databasen og bygger den fra data/raw første gang.
set -e
cd /var/www/app
echo "Venter på MariaDB ..."
until php -r 'require "src/bootstrap.php"; db();' 2>/dev/null; do sleep 2; done
if ! php -r 'require "src/bootstrap.php"; exit(db()->query("SHOW TABLES LIKE \"products\"")->fetch() ? 0 : 1);'; then
  echo "Tom database, kjører import ..."
  php scripts/import.php
fi
exec "$@"
