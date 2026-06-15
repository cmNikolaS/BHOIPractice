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
        ( cd /var/www/html && php classify_tasks.php ) || echo "Classify finished with warnings."
        ( cd /var/www/html && php import_legacy.php ) || echo "Legacy import finished with warnings."
        ( cd /var/www/html && php classify_legacy.php ) || echo "Legacy classify finished with warnings."
        ( cd /var/www/html && php import_tests.php ) || echo "Test import finished with warnings."
        ( cd /var/www/html && php pdf_to_markdown.php ) || echo "PDF->markdown finished with warnings."
    fi
fi

# Lightweight migrations for already-seeded volumes (idempotent: the ALTER
# errors harmlessly if the column already exists, and never touches values,
# so admin edits survive redeploys). Curated ratings/tags are applied once via
#   flyctl ssh console -C "php /var/www/html/classify_tasks.php"
sqlite3 "$DB_PATH" "ALTER TABLE tasks ADD COLUMN difficulty_rating INTEGER NOT NULL DEFAULT 5" 2>/dev/null || true
sqlite3 "$DB_PATH" "CREATE TABLE IF NOT EXISTS submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER, language TEXT NOT NULL, source TEXT NOT NULL, status TEXT, time_ms INTEGER, memory_kb INTEGER, ip TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE CASCADE ON DELETE SET NULL)" 2>/dev/null || true
sqlite3 "$DB_PATH" "ALTER TABLE submissions ADD COLUMN ip TEXT" 2>/dev/null || true
sqlite3 "$DB_PATH" "CREATE TABLE IF NOT EXISTS task_tests (id INTEGER PRIMARY KEY AUTOINCREMENT, task_id INTEGER NOT NULL, idx INTEGER NOT NULL, input_path TEXT NOT NULL, output_path TEXT NOT NULL, FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE CASCADE ON DELETE CASCADE)" 2>/dev/null || true

# The web server owns the data it reads/writes (uploads, sqlite WAL).
chown -R www-data:www-data "$DATA_DIR" || true

echo "Starting Apache..."
exec apache2-foreground
