#!/bin/bash
INDEX_FOLDER="${INDEX_FOLDER:-/uploads}"
INDEXER_SCRIPT="/var/www/html/config/index_files.php"
PHP_BIN="${PHP_BIN:-php}"
DEBOUNCE_SECONDS=2

QUEUE_FILE=$(mktemp /tmp/watcher_queue.XXXXXX)
SWAP_FILE=$(mktemp /tmp/watcher_swap.XXXXXX)
trap 'rm -f "$QUEUE_FILE" "$SWAP_FILE"' EXIT

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] [watcher] $*"; }

flush_pending() {
    # Atomically swap queue → swap so the writer can keep appending
    mv "$QUEUE_FILE" "$SWAP_FILE"
    touch "$QUEUE_FILE"   # recreate immediately so writer never blocks

    declare -A seen
    while IFS='|' read -r event filepath; do
        seen["$filepath"]="$event"
    done < "$SWAP_FILE"

    for filepath in "${!seen[@]}"; do
        log "Processing [${seen[$filepath]}]: $filepath"
        $PHP_BIN "$INDEXER_SCRIPT" "$filepath" &
    done
}

log "Starting inotify watcher on: $INDEX_FOLDER"
log "Debounce: ${DEBOUNCE_SECONDS}s"

# ── Initial full index only if collection is empty ──────────────────────────
# Avoids re-indexing everything on every docker restart (fixes issue 2).
# Ask Typesense how many docs are in the 'files' collection.
TYPESENSE_HOST="${TYPESENSE_HOST:-typesense}"
TYPESENSE_PORT="${TYPESENSE_PORT:-8108}"
TYPESENSE_API_KEY="${TYPESENSE_API_KEY:-xyz}"
COLLECTION_NAME="${COLLECTION_NAME:-files}"

count=$(curl -sf \
    -H "X-TYPESENSE-API-KEY: ${TYPESENSE_API_KEY}" \
    "http://${TYPESENSE_HOST}:${TYPESENSE_PORT}/collections/${COLLECTION_NAME}" \
    2>/dev/null | grep -o '"num_documents":[0-9]*' | grep -o '[0-9]*')

if [ -z "$count" ] || [ "$count" -eq 0 ]; then
    log "Collection empty or missing — running initial full index..."
    $PHP_BIN "$INDEXER_SCRIPT"
    log "Initial index complete."
else
    log "Collection has $count documents — skipping full re-index."
fi

log "Watching for changes..."

# Background inotify reader
inotifywait \
    --monitor \
    --recursive \
    --format '%e|%w%f' \
    --event close_write \
    --event moved_to \
    --event moved_from \
    --event delete \
    "$INDEX_FOLDER" 2>/dev/null | while IFS='|' read -r event filepath; do

    [[ -d "$filepath" ]]                  && continue
    [[ "$(basename "$filepath")" == .* ]] && continue
    [[ "$filepath" == *.tmp ]]            && continue
    [[ "$filepath" == *.swp ]]            && continue
    [[ "$filepath" == *~    ]]            && continue

    case "$filepath" in
        */filebrowser/*|*/backups/*|*/typesense/*|*/types/*|*/sites/*|*/.git/*) continue ;;
    esac

    echo "${event}|${filepath}" >> "$QUEUE_FILE"
done &

INOTIFY_PID=$!

while kill -0 "$INOTIFY_PID" 2>/dev/null; do
    sleep "$DEBOUNCE_SECONDS"
    if [[ -s "$QUEUE_FILE" ]]; then
        flush_pending
    fi
done

log "inotifywait exited — watcher shutting down."