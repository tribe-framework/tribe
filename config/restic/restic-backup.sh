#!/usr/bin/env bash
# Installed on `server` at /usr/local/bin/restic-backup.sh
# One script, one argument, both volumes.
set -euo pipefail

VOLUME="${1:?usage: restic-backup.sh <beatroot|volume>}"

case "$VOLUME" in
  beatroot) SOURCE=/mnt/beatrootnews ; REPO=beatroot-backup ;;
  volume)   SOURCE=/mnt/volume       ; REPO=volume-backup ;;
  *) echo "unknown volume: $VOLUME" >&2 ; exit 64 ;;
esac

mountpoint -q "$SOURCE"

export RESTIC_REPOSITORY="rest:http://local:8000/${REPO}/"
export RESTIC_PASSWORD_FILE=/etc/restic/password
export RESTIC_CACHE_DIR=/var/cache/restic

restic snapshots >/dev/null 2>&1 || restic init

exec restic backup "$SOURCE" \
  --one-file-system \
  --exclude-file=/etc/restic/exclude.txt \
  --exclude-caches \
  --retry-lock 30m \
  --cleanup-cache \
  -o rest.connections=10 \
  --verbose
