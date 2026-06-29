#!/bin/bash
set -e

TRIBE_REPO="https://github.com/tribe-framework/tribe.git"
TRIBE_TMP="/tmp/tribe-upstream-$$"
TARGET="${1:-.}"   # pass your project path as arg, or run from project root

# ── Folders/files to NEVER overwrite ──────────────────────────────────────────
EXCLUDES=(
  ".env"
  "uploads/"
  "logs/"
  "config/mysql/"          # project-specific DB config
  "applications/tribe/custom"  # tribe custom folder
  "threads/"             # always ignored for use in Loom
)
# ──────────────────────────────────────────────────────────────────────────────

echo "→ Cloning latest tribe..."
git clone --depth=1 "$TRIBE_REPO" "$TRIBE_TMP"

# Build rsync exclude flags
RSYNC_EXCLUDES=()
for path in "${EXCLUDES[@]}"; do
  RSYNC_EXCLUDES+=("--exclude=$path")
done

echo "→ Syncing files into $TARGET (excluded: ${EXCLUDES[*]})..."
rsync -av --progress \
  "${RSYNC_EXCLUDES[@]}" \
  --exclude=".git/" \
  "$TRIBE_TMP/" "$TARGET/"

echo "→ Cleaning up..."
rm -rf "$TRIBE_TMP"

echo "✓ Done. Review changes with: git diff"