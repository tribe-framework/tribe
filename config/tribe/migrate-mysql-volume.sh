#!/usr/bin/env bash
# migrate_mysql_volume.sh
#
# Migrates an existing deployment's MySQL data from a named Docker volume
# to ./uploads/mysql so everything lives in the project folder.
#
# Usage:
#   cd /path/to/your/project
#   bash config/mysql/migrate-mysql-volume.sh
#
# Requirements:
#   - Run from the project root (same directory as docker-compose.yml and .env)
#   - Docker must be running
#   - PROJECT_NAME must be set in .env

set -euo pipefail

# ── helpers ────────────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%H:%M:%S')] $*"; }
warn() { echo "[$(date '+%H:%M:%S')] WARNING: $*" >&2; }
die()  { echo "[$(date '+%H:%M:%S')] ERROR: $*" >&2; exit 1; }

# ── load PROJECT_NAME from .env ────────────────────────────────────────────────
[[ -f .env ]] || die ".env file not found. Run this script from your project root."

PROJECT_NAME=$(grep -E '^PROJECT_NAME=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
[[ -n "$PROJECT_NAME" ]] || die "PROJECT_NAME not found in .env"

VOLUME_NAME="${PROJECT_NAME}_mysql_data"
TARGET_DIR="./uploads/mysql"

log "Project : $PROJECT_NAME"
log "Volume  : $VOLUME_NAME"
log "Target  : $TARGET_DIR"
echo

# ── pre-flight checks ──────────────────────────────────────────────────────────
docker volume inspect "$VOLUME_NAME" > /dev/null 2>&1 \
  || die "Named volume '$VOLUME_NAME' does not exist. Nothing to migrate."

if [[ -d "$TARGET_DIR" && -n "$(ls -A "$TARGET_DIR" 2>/dev/null)" ]]; then
  die "'$TARGET_DIR' already exists and is not empty. Aborting to avoid overwriting data."
fi

# ── stop mysql cleanly ─────────────────────────────────────────────────────────
log "Stopping MySQL container..."
docker compose stop mysql 2>/dev/null || warn "mysql container was not running (that's fine)."

# ── copy data from volume → bind mount ────────────────────────────────────────
log "Creating $TARGET_DIR ..."
mkdir -p "$TARGET_DIR"

log "Copying data from volume '$VOLUME_NAME' to '$TARGET_DIR' ..."
docker run --rm \
  -v "${VOLUME_NAME}:/source:ro" \
  -v "$(pwd)/uploads/mysql:/target" \
  alpine \
  sh -c "cp -a /source/. /target/ && echo 'Copy complete.'"

# ── sanity check ───────────────────────────────────────────────────────────────
FILE_COUNT=$(find "$TARGET_DIR" -maxdepth 1 | wc -l)
if [[ "$FILE_COUNT" -lt 2 ]]; then
  die "Target directory looks empty after copy — aborting before touching anything else."
fi
log "Copied $((FILE_COUNT - 1)) items into $TARGET_DIR."

# ── swap docker-compose.yml ────────────────────────────────────────────────────
log "Backing up docker-compose.yml → docker-compose.yml.bak ..."
cp docker-compose.yml docker-compose.yml.bak

log "Patching docker-compose.yml ..."

# Replace the mysql_data volume reference under the mysql service
sed -i 's|      - mysql_data:/var/lib/mysql|      - ./uploads/mysql:/var/lib/mysql|' docker-compose.yml

# Remove the mysql_data named volume block (handles both plain and name: / driver: variants)
python3 - <<'PYEOF'
import re, sys

with open("docker-compose.yml", "r") as f:
    content = f.read()

# Remove the mysql_data entry (with optional name/driver sub-keys) from the volumes section
content = re.sub(
    r'  mysql_data:\n(?:    (?:name|driver):[^\n]*\n)*',
    '',
    content
)

with open("docker-compose.yml", "w") as f:
    f.write(content)

print("docker-compose.yml patched successfully.")
PYEOF

# ── restart mysql with new config ──────────────────────────────────────────────
log "Starting MySQL with bind mount..."
docker compose up -d mysql

log "Waiting for MySQL to become healthy..."
ATTEMPTS=0
until docker compose exec mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
  ATTEMPTS=$((ATTEMPTS + 1))
  [[ $ATTEMPTS -ge 30 ]] && die "MySQL did not become healthy after 30 attempts. Check logs: docker compose logs mysql"
  sleep 3
done

log "MySQL is healthy."

# ── bring everything else back up ──────────────────────────────────────────────
log "Restarting remaining services..."
docker compose up -d

echo
log "✅ Migration complete."
log "   MySQL data is now at: $TARGET_DIR"
log "   The old named volume '$VOLUME_NAME' has NOT been deleted."
log "   Once you've verified everything works, remove it with:"
log "     docker volume rm $VOLUME_NAME"
