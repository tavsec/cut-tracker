#!/bin/sh
set -e

DB_DIR=$(dirname "$DB_DATABASE")
if [ ! -d "$DB_DIR" ]; then
    mkdir -p "$DB_DIR"
fi

if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    sqlite3 "$DB_DATABASE" "PRAGMA journal_mode=WAL;"
    echo "Created SQLite database at $DB_DATABASE with WAL mode"
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chown www-data:www-data "$DB_DATABASE"

cd /var/www/html

php artisan config:cache
php artisan migrate --force
php artisan db:seed --force

exec supervisord -c /etc/supervisord.conf
