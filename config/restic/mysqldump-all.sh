#!/usr/bin/env bash
# Dumps every running mysql: container to a gzip'd, all-databases SQL file.
# Intended to run nightly on `server`, just ahead of the restic backup run,
# so the restic snapshot always captures a fresh dump instead of a stale one.
set -euo pipefail

DUMP_DIR=/mnt/volume/backup
mkdir -p "$DUMP_DIR"

mapfile -t containers < <(docker ps --format '{{.Names}}\t{{.Image}}' | awk -F'\t' '$2 ~ /^mysql:/ {print $1}')

for container in "${containers[@]}"; do
  PASS=$(docker exec "$container" printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)

  if [ -z "$PASS" ]; then
    echo "WARNING: no MYSQL_ROOT_PASSWORD found in $container, skipping" >&2
    continue
  fi

  TMPFILE="${DUMP_DIR}/${container}.sql.gz.tmp"
  FINAL="${DUMP_DIR}/${container}.sql.gz"

  docker exec "$container" sh -c \
    "MYSQL_PWD='${PASS}' mysqldump -uroot --all-databases --single-transaction --quick" \
    | gzip > "$TMPFILE"

  mv "$TMPFILE" "$FINAL"
done
