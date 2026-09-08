#!/usr/bin/env bash
# Dumps every running mysql/mariadb container to a gzip'd, all-databases SQL file.
# Called by restic-backup.sh immediately before the restic run, so the snapshot
# always captures a fresh dump instead of a stale one. Takes the destination
# directory as its argument; it must sit inside the volume being backed up.
#   mysqldump-all.sh /mnt/postcode/backup
set -euo pipefail

DUMP_DIR="${1:-/mnt/postcode/backup}"
mkdir -p "$DUMP_DIR"

mapfile -t containers < <(docker ps --format '{{.Names}}\t{{.Image}}' \
  | awk -F'\t' 'tolower($2) ~ /(^|\/)(mysql|mariadb|percona)([:@]|$)/ {print $1}')

if [ "${#containers[@]}" -eq 0 ]; then
  echo "WARNING: no mysql/mariadb containers matched, no dumps written" >&2
fi

for container in "${containers[@]}"; do
  PASS=$(docker exec "$container" printenv MYSQL_ROOT_PASSWORD 2>/dev/null || true)
  [ -z "$PASS" ] && PASS=$(docker exec "$container" printenv MARIADB_ROOT_PASSWORD 2>/dev/null || true)

  if [ -z "$PASS" ]; then
    echo "WARNING: no root password found in $container, skipping" >&2
    continue
  fi

  TMPFILE="${DUMP_DIR}/${container}.sql.gz.tmp"
  FINAL="${DUMP_DIR}/${container}.sql.gz"

  docker exec "$container" sh -c \
    "MYSQL_PWD='${PASS}' mysqldump -uroot --all-databases --single-transaction --quick" \
    | gzip > "$TMPFILE"

  mv "$TMPFILE" "$FINAL"
done
