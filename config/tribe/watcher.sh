#!/bin/bash
# ─── inotify watcher ───────────────────────────────────────────────────────────
# Watches $INDEX_FOLDER for file changes and triggers the PHP indexer.
# Uses inotifywait (inotify-tools) for efficient kernel-level file watching.

INDEX_FOLDER="${INDEX_FOLDER:-/uploads}"
INDEXER_SCRIPT="/app/index_files.php"
PHP_BIN="${PHP_BIN:-php}"

# Debounce: avoid hammering Typesense on bulk copy operations
DEBOUNCE_SECONDS=2
declare -A pending_files
last_event_time=0

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [watcher] $*"
}

flush_pending() {
    for filepath in "${!pending_files[@]}"; do
        event="${pending_files[$filepath]}"
        log "Processing [$event]: $filepath"
        $PHP_BIN "$INDEXER_SCRIPT" "$filepath" &
    done
    unset pending_files
    declare -gA pending_files
}

log "Starting inotify watcher on: $INDEX_FOLDER"
log "Debounce: ${DEBOUNCE_SECONDS}s"

# Run a full re-index on startup
log "Running initial full index..."
$PHP_BIN "$INDEXER_SCRIPT"
log "Initial index complete. Watching for changes..."

# Watch for file events — recursive, follow symlinks
inotifywait \
    --monitor \
    --recursive \
    --format '%e %w%f' \
    --event close_write \
    --event moved_to \
    --event moved_from \
    --event delete \
    "$INDEX_FOLDER" 2>/dev/null | while read -r event filepath; do

    # Skip directories, hidden files, temp files
    [[ -d "$filepath" ]]                  && continue
    [[ "$(basename "$filepath")" == .* ]] && continue
    [[ "$filepath" == *.tmp ]]            && continue
    [[ "$filepath" == *.swp ]]            && continue
    [[ "$filepath" == *~    ]]            && continue

    # Skip internal dirs
    case "$filepath" in
        */filebrowser/*|*/backups/*|*/typesense/*|*/.git/*) continue ;;
    esac

    # Queue file for debounced processing
    pending_files["$filepath"]="$event"
    last_event_time=$(date +%s)

    # Check if debounce window has passed and process queue
    while true; do
        sleep "$DEBOUNCE_SECONDS"
        now=$(date +%s)
        elapsed=$(( now - last_event_time ))
        if [[ $elapsed -ge $DEBOUNCE_SECONDS && ${#pending_files[@]} -gt 0 ]]; then
            flush_pending
            break
        fi
    done &

done
