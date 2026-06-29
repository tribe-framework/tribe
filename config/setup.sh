#!/bin/bash

set -e

echo "🚀 Setting up development environment..."

# ── Load environment variables ─────────────────────────────────────────────────
set -a
[ -f .env ] && source .env
set +a

# ── File permissions ───────────────────────────────────────────────────────────
# Run early so all subsequent directory creation inherits correct ownership.
#
# UIDs in use:
#   33   — www-data  (php-fpm inside tribeframework/tribe-core)
#   999  — mysql     (mysql:9)
#   1000 — typesense (typesense/typesense:30.1)
#   0    — root      (caddy, filebrowser, cronicle, centrifugo — no special perms needed)
#
# Verify with: docker exec <container> id
# If any UID differs on your host, adjust the values below.

echo "🔐 Setting file permissions..."

# Create all expected upload subdirectories up front so chowns below never fail
mkdir -p \
    /uploads/mysql \
    /uploads/threads \
    /uploads/sites/dist \
    /uploads/sites/dist-php \
    /uploads/filebrowser \
    /uploads/cronicle/data \
    /uploads/cronicle/logs \
    /uploads/cronicle/plugins \
    /uploads/centrifugo

# Default: www-data owns everything (php-fpm needs read/write across uploads)
chown -R 33:33 /uploads
chown -R 33:33 /logs

# MySQL data dir — mysql user (UID 999) must own it exclusively.
# MySQL refuses to start if this dir is owned by anyone else or perms are too open.
chown -R 999:999 /uploads/mysql
chmod -R 750 /uploads/mysql

# Typesense data dir — typesense user (UID 1000)
chown -R 1000:1000 /uploads/typesense 2>/dev/null || true

# Directories that root-based services write to — leave www-data ownership,
# root can always write regardless. No change needed for:
#   /uploads/cronicle, /uploads/centrifugo, /uploads/filebrowser

# General permissions for www-data-owned dirs:
#   directories: 775 — owner+group rwx, others rx
#   files:       664 — owner+group rw, others r
find /uploads -type d \
    -not -path "/uploads/mysql*" \
    -exec chmod 775 {} +

find /uploads -type f \
    -not -path "/uploads/mysql*" \
    -exec chmod 664 {} +

echo "✅ File permissions set!"

# ── Validate database connection ───────────────────────────────────────────────
echo "🔍 Testing MySQL connection..."

if ! docker ps --format '{{.Names}}' | grep -q "^${DB_HOST}$"; then
    echo "⚠️  ${DB_HOST} container is not running"
    echo "   Please start tribe-server first: docker compose up -d"
    exit 1
fi

echo "⏳ Waiting for MySQL to be ready..."
timeout=60
count=0
until docker exec ${DB_HOST} mysqladmin ping -u root -p"${DB_ROOT_PASSWORD}" --silent 2>/dev/null; do
    sleep 2
    count=$((count + 2))
    if [ $count -ge $timeout ]; then
        echo "⚠️  MySQL did not become ready within ${timeout} seconds"
        docker logs ${DB_HOST} | tail -10
        exit 1
    fi
done

echo "✅ MySQL is ready!"

# ── Database setup ─────────────────────────────────────────────────────────────
echo "🔍 Checking if database ${DB_NAME} exists..."

DB_EXISTS=$(docker exec ${DB_HOST} mysql -u root -p"$DB_ROOT_PASSWORD" -sN -e \
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';")

if [ "$DB_EXISTS" -eq 0 ]; then
    echo "📦 Database ${DB_NAME} does not exist. Creating..."

    docker exec ${DB_HOST} mysql -u root -p"$DB_ROOT_PASSWORD" -e \
        "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || { echo "⚠️  Failed to create database ${DB_NAME}"; exit 1; }
    echo "✅ Database ${DB_NAME} created!"

    echo "👤 Creating database user ${DB_USER}..."
    docker exec ${DB_HOST} mysql -u root -p"$DB_ROOT_PASSWORD" -e \
        "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';" \
        || { echo "⚠️  Failed to create user ${DB_USER}"; exit 1; }
    echo "✅ User ${DB_USER} created!"

    echo "🔑 Granting privileges to ${DB_USER}..."
    docker exec ${DB_HOST} mysql -u root -p"$DB_ROOT_PASSWORD" -e \
        "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;"
    echo "✅ Privileges granted!"

    if [ -f "/config/mysql/install.sql" ]; then
        echo "📥 Importing database schema..."
        docker exec -i ${DB_HOST} mysql -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" \
            < /config/mysql/install.sql \
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

# ── FileBrowser initialisation ─────────────────────────────────────────────────
echo "🔧 Setting up FileBrowser..."

if [ ! -f "/uploads/filebrowser/filebrowser.db" ]; then
    echo "📥 Downloading FileBrowser binary..."
    FILEBROWSER_VERSION="2.27.0"
    cd /tmp
    curl -fsSL "https://github.com/filebrowser/filebrowser/releases/download/v${FILEBROWSER_VERSION}/linux-amd64-filebrowser.tar.gz" \
        -o filebrowser.tar.gz
    tar -xzf filebrowser.tar.gz
    chmod +x filebrowser

    echo "🔨 Initialising FileBrowser database..."
    ./filebrowser config init \
        --database /uploads/filebrowser/filebrowser.db \
        --root /uploads

    echo "👤 Adding admin user..."
    ./filebrowser users add admin "${FILEBROWSER_PASSWORD:-filepassword}" \
        --database /uploads/filebrowser/filebrowser.db \
        --perm.admin

    rm -f filebrowser filebrowser.tar.gz
    cd /workdir

    # FileBrowser runs as root so it can own this itself, but keep consistent
    chown 33:33 /uploads/filebrowser/filebrowser.db
    chmod 664 /uploads/filebrowser/filebrowser.db

    echo "✅ FileBrowser initialised!"
else
    echo "ℹ️  FileBrowser database already exists, skipping."
fi

echo ""
echo "✅ Setup complete!"