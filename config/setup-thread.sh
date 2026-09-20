#!/bin/bash

set -e

echo "🚀 Setting up development environment..."

# ── Load environment variables ─────────────────────────────────────────────────
set -a
[ -f .env ] && source .env
set +a

SHARED_DB_PORT="${SHARED_DB_PORT:-3306}"
SHARED_TIKA_PORT="${SHARED_TIKA_PORT:-9998}"
MYSQL_CMD="mysql -h shared-services -P ${SHARED_DB_PORT} --protocol=TCP -u root -p${DB_ROOT_PASSWORD}"

# ── File permissions ───────────────────────────────────────────────────────────
# Run early so all subsequent directory creation inherits correct ownership.
#
# UIDs in use:
#   33   — www-data  (php-fpm inside tribeframework/tribe-core)
#   0    — root      (caddy, cronicle, centrifugo — no special perms needed)
#
# Verify with: docker exec <container> id
# If any UID differs on your host, adjust the values below.

echo "🔐 Setting file permissions..."

# Create all expected upload subdirectories up front so chowns below never fail
mkdir -p \
    /uploads/threads \
    /uploads/sites/dist \
    /uploads/sites/dist-php \
    /uploads/cronicle/data \
    /uploads/cronicle/logs \
    /uploads/cronicle/plugins \
    /uploads/centrifugo

# Default: www-data owns everything (php-fpm needs read/write across uploads)
chown -R 33:33 /uploads
chown -R 33:33 /logs

# Directories that root-based services write to — leave www-data ownership,
# root can always write regardless. No change needed for:
#   /uploads/cronicle, /uploads/centrifugo

# General permissions for www-data-owned dirs:
#   directories: 775 — owner+group rwx, others rx
#   files:       664 — owner+group rw, others r
find /uploads -type d -exec chmod 775 {} +
find /uploads -type f -exec chmod 664 {} +

echo "✅ File permissions set!"

# ── Validate shared MySQL connection ───────────────────────────────────────────
echo "⏳ Waiting for shared MySQL on shared-services:${SHARED_DB_PORT}..."
timeout=60
count=0
until mysqladmin ping -h shared-services -P "${SHARED_DB_PORT}" --protocol=TCP \
        -u root -p"${DB_ROOT_PASSWORD}" --silent 2>/dev/null; do
    sleep 2
    count=$((count + 2))
    if [ $count -ge $timeout ]; then
        echo "⚠️  Shared MySQL did not respond within ${timeout} seconds"
        echo "   Check that the adjacent stack is running and publishing ${SHARED_DB_PORT}"
        exit 1
    fi
done

echo "✅ Shared MySQL is ready!"

# ── Validate shared Tika connection ────────────────────────────────────────────
echo "⏳ Waiting for shared Tika on shared-services:${SHARED_TIKA_PORT}..."
timeout=60
count=0
until curl -fsS "http://shared-services:${SHARED_TIKA_PORT}/version" >/dev/null 2>&1; do
    sleep 2
    count=$((count + 2))
    if [ $count -ge $timeout ]; then
        echo "⚠️  Shared Tika did not respond within ${timeout} seconds"
        echo "   Check that the adjacent stack is running and publishing ${SHARED_TIKA_PORT}"
        exit 1
    fi
done

echo "✅ Shared Tika is ready!"

# ── Database setup ─────────────────────────────────────────────────────────────
echo "🔍 Checking if database ${DB_NAME} exists..."

DB_EXISTS=$($MYSQL_CMD -sN -e \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';")

if [ "$DB_EXISTS" -eq 0 ]; then
    echo "📦 Database ${DB_NAME} does not exist. Creating..."

    $MYSQL_CMD -e \
        "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || { echo "⚠️  Failed to create database ${DB_NAME}"; exit 1; }
    echo "✅ Database ${DB_NAME} created!"

    echo "👤 Creating database user ${DB_USER}..."
    $MYSQL_CMD -e \
        "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';" \
        || { echo "⚠️  Failed to create user ${DB_USER}"; exit 1; }
    echo "✅ User ${DB_USER} created!"

    echo "🔑 Granting privileges to ${DB_USER}..."
    $MYSQL_CMD -e \
        "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;"
    echo "✅ Privileges granted!"

    if [ -f "/config/mysql/install.sql" ]; then
        echo "📥 Importing database schema..."
        $MYSQL_CMD "$DB_NAME" < /config/mysql/install.sql \
            || { echo "⚠️  Failed to import schema"; exit 1; }
        echo "✅ Schema imported!"
    else
        echo "⚠️  install.sql not found, skipping schema import"
    fi

else
    echo "✅ Database ${DB_NAME} already exists, skipping creation."
fi

# ── Static site defaults ───────────────────────────────────────────────────────
if [ ! -f "/uploads/sites/dist/index.html" ]; then
    echo "📄 Creating default static site..."
    echo "Hello, world!" > /uploads/sites/dist/index.html
    chown 33:33 /uploads/sites/dist/index.html
    chmod 664 /uploads/sites/dist/index.html
    echo "✅ Default static site created!"
fi

if [ ! -f "/uploads/sites/dist-php/index.php" ]; then
    echo "📄 Creating default PHP site..."
    cat > /uploads/sites/dist-php/index.php << 'PHPEOF'
<?php
echo "Hello, PHP world @ " . time();
PHPEOF
    chown 33:33 /uploads/sites/dist-php/index.php
    chmod 664 /uploads/sites/dist-php/index.php
    echo "✅ Default PHP site created!"
fi

echo ""
echo "✅ Setup complete!"