# =====================================================================
#  BHOI Arhiva — container image for Fly.io (PHP 8.2 + Apache + SQLite)
#
#  The catalog is baked into the image at build time (SQLite DB + the
#  downloaded PDFs/solutions under /seed). On first boot the entrypoint
#  copies /seed onto the persistent /data volume, so the running machine
#  starts fast and needs no network at runtime.
# =====================================================================
FROM php:8.2-apache

# System libraries + PHP extensions.
#   libonig-dev -> mbstring | sqlite3 CLI -> load schema | ca-certificates -> HTTPS
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev sqlite3 ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql mbstring
# (pdo_sqlite, curl, fileinfo, openssl are bundled/enabled in the base image.)

# Apache modules used by the app.
RUN a2enmod rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html/

# Bake the full catalog into the image: build the SQLite DB and download
# every task PDF + solution into /seed. (Requires network during build,
# which Fly's remote builder provides.)
RUN set -eux; \
    mkdir -p /seed/uploads/pdf /seed/uploads/solutions /seed/uploads/tests; \
    sqlite3 /seed/app.sqlite < schema.sqlite.sql; \
    DB_DRIVER=sqlite DB_SQLITE_PATH=/seed/app.sqlite UPLOAD_DIR=/seed/uploads \
        php import_bhoi.php; \
    DB_DRIVER=sqlite DB_SQLITE_PATH=/seed/app.sqlite UPLOAD_DIR=/seed/uploads \
        php classify_tasks.php; \
    chown -R www-data:www-data /seed

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
# Strip any CR (in case checked out with CRLF) and make executable.
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
 && chmod +x /usr/local/bin/docker-entrypoint.sh

# Runtime data lives on the mounted volume (see fly.toml [[mounts]]).
ENV DB_DRIVER=sqlite \
    DB_SQLITE_PATH=/data/app.sqlite \
    UPLOAD_DIR=/data/uploads

EXPOSE 80
CMD ["docker-entrypoint.sh"]
