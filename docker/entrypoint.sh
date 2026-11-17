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
exec "$@"
