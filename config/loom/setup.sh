#!/bin/bash
# ══════════════════════════════════════════════════════════════════════════════
# config/loom/setup.sh
# Runs once inside the loom_setup container on first boot.
# - Ensures tribe-base has vendor/ (runs composer install)
# - Initialises FileBrowser database
# - Creates shared directory structure
# ══════════════════════════════════════════════════════════════════════════════

set -e

echo "🚀 Loom setup starting..."

# ─── Tribe-base: composer install ─────────────────────────────────────────────
TRIBE_BASE="/applications/tribe"

if [ ! -d "$TRIBE_BASE" ]; then
    echo "⚠️  Tribe base app not found at $TRIBE_BASE"
    echo "   Place the Tribe app code there and restart."
    exit 1
fi

if [ ! -d "$TRIBE_BASE/vendor" ]; then
    echo "📦 Running composer install in tribe-base..."
    cd "$TRIBE_BASE"
    composer install --no-interaction --optimize-autoloader --no-dev
    echo "✅ Composer install complete."
else
    echo "ℹ️  tribe-base/vendor already exists, skipping composer install."
fi

# ─── Shared upload directories ────────────────────────────────────────────────
mkdir -p \
    /uploads/threads \
    /uploads/backups \
    /uploads/filebrowser \
    /uploads/typesense

echo "📁 Upload directories ready."

# ─── FileBrowser ──────────────────────────────────────────────────────────────
if [ ! -f "/uploads/filebrowser/filebrowser.db" ]; then
    echo "📦 Initialising FileBrowser..."

    FILEBROWSER_VERSION="2.27.0"
    ARCH="linux-amd64"

    cd /tmp
    curl -fsSL "https://github.com/filebrowser/filebrowser/releases/download/v${FILEBROWSER_VERSION}/${ARCH}-filebrowser.tar.gz" \
        -o filebrowser.tar.gz
    tar -xzf filebrowser.tar.gz
    chmod +x filebrowser

    ./filebrowser config init \
        --database /uploads/filebrowser/filebrowser.db \
        --root /uploads/threads

    ./filebrowser users add admin "${FILEBROWSER_PASSWORD:-filepassword}" \
        --database /uploads/filebrowser/filebrowser.db \
        --perm.admin

    rm -f filebrowser filebrowser.tar.gz
    echo "✅ FileBrowser initialised."
else
    echo "ℹ️  FileBrowser database already exists, skipping."
fi

echo "✅ Loom setup complete."
