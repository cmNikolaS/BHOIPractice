#!/bin/bash
# Initialises the persistent volume on first boot, then runs Apache.
set -e

DATA_DIR="${DATA_DIR:-/data}"
DB_PATH="${DB_SQLITE_PATH:-$DATA_DIR/app.sqlite}"
UP="${UPLOAD_DIR:-$DATA_DIR/uploads}"

mkdir -p "$UP/pdf" "$UP/solutions" "$UP/tests" "$(dirname "$DB_PATH")"

# Populate the volume the first time it is empty.
if [ ! -s "$DB_PATH" ]; then
    if [ -s /seed/app.sqlite ]; then
        echo "First boot: seeding volume from baked image data..."
        cp /seed/app.sqlite "$DB_PATH"
        cp -r /seed/uploads/. "$UP"/ 2>/dev/null || true
    else
        echo "First boot: no baked seed — importing at runtime..."
        sqlite3 "$DB_PATH" < /var/www/html/schema.sqlite.sql
        ( cd /var/www/html && php import_bhoi.php ) || echo "Import finished with warnings."
    fi
fi

# The web server owns the data it reads/writes (uploads, sqlite WAL).
chown -R www-data:www-data "$DATA_DIR" || true

echo "Starting Apache..."
exec apache2-foreground
