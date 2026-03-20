#!/bin/bash
# ─── inotify watcher ───────────────────────────────────────────────────────────
# Watches $INDEX_FOLDER for file changes and triggers the PHP indexer.
# Uses inotifywait (inotify-tools) for efficient kernel-level file watching.

INDEX_FOLDER="${INDEX_FOLDER:-/uploads}"
INDEXER_SCRIPT="/var/www/html/config/index_files.php"
PHP_BIN="${PHP_BIN:-php}"

# Debounce: avoid hammering Typesense on bulk copy operations
DEBOUNCE_SECONDS=2

# Temp file used to pass events from the inotifywait subshell to the main loop.
# A file is used instead of an associative array because the pipe that feeds
# `while read` runs in a subshell — bash variables written inside it are not
# visible to the parent, so declare -gA / unset don't work as expected.
QUEUE_FILE=$(mktemp /tmp/watcher_queue.XXXXXX)
trap 'rm -f "$QUEUE_FILE"' EXIT

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [watcher] $*"
}

flush_pending() {
    # Deduplicate: keep only the last event per filepath (tail wins).
    declare -A seen
    while IFS='|' read -r event filepath; do
        seen["$filepath"]="$event"
    done < "$QUEUE_FILE"
    : > "$QUEUE_FILE"   # truncate atomically after reading

    for filepath in "${!seen[@]}"; do
        event="${seen[$filepath]}"
        log "Processing [$event]: $filepath"
        $PHP_BIN "$INDEXER_SCRIPT" "$filepath" &
    done
}

log "Starting inotify watcher on: $INDEX_FOLDER"
log "Debounce: ${DEBOUNCE_SECONDS}s"

# Run a full re-index on startup
log "Running initial full index..."
$PHP_BIN "$INDEXER_SCRIPT"
log "Initial index complete. Watching for changes..."

# Background reader: write every qualifying event to QUEUE_FILE.
# Runs in its own subshell so variable-visibility issues are irrelevant.
inotifywait \
    --monitor \
    --recursive \
    --format '%e|%w%f' \
    --event close_write \
    --event moved_to \
    --event moved_from \
    --event delete \
    "$INDEX_FOLDER" 2>/dev/null | while IFS='|' read -r event filepath; do

    # Skip directories, hidden files, temp files
    [[ -d "$filepath" ]]                  && continue
    [[ "$(basename "$filepath")" == .* ]] && continue
    [[ "$filepath" == *.tmp ]]            && continue
    [[ "$filepath" == *.swp ]]            && continue
    [[ "$filepath" == *~    ]]            && continue

    # Skip internal dirs
    case "$filepath" in
        */filebrowser/*|*/backups/*|*/typesense/*|*/types/*|*/sites/*|*/tribe-threads/*|*/.git/*) continue ;;
    esac

    echo "${event}|${filepath}" >> "$QUEUE_FILE"

done &

INOTIFY_PID=$!

# Main debounce loop: poll the queue file every DEBOUNCE_SECONDS.
# Only flushes when the queue is non-empty, so it stays idle at zero cost
# when nothing is happening.
while kill -0 "$INOTIFY_PID" 2>/dev/null; do
    sleep "$DEBOUNCE_SECONDS"
    if [[ -s "$QUEUE_FILE" ]]; then
        flush_pending
    fi
done

log "inotifywait exited — watcher shutting down."