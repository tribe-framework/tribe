#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════
# loom.sh — Thread lifecycle management CLI
#
# Commands:
#   ./loom.sh create  <name>          Provision and start a new Thread
#   ./loom.sh destroy <name>          Stop, remove containers + drop DB
#   ./loom.sh list                    List all Threads with ports and status
#   ./loom.sh start   <name>          Start a stopped Thread
#   ./loom.sh stop    <name>          Stop a running Thread
#   ./loom.sh logs    <name> [svc]    Tail logs (svc: php_tribe|caddy_tribe|…)
#   ./loom.sh status  <name>          Show container status for a Thread
#
# Prerequisites:
#   - docker compose --profile loom up -d
#   - .env present in this directory (copy from .env.sample, set LOOM_MODE=true)
#   - applications/tribe/ contains the Tribe app code
#
# ══════════════════════════════════════════════════════════════════════════════

set -euo pipefail

# ─── Paths ────────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THREADS_DIR="$SCRIPT_DIR/threads"
REGISTRY="$THREADS_DIR/.registry.json"
ENV_FILE="$SCRIPT_DIR/.env"
TRIBE_BASE="$SCRIPT_DIR/applications/tribe"
# These are resolved after load_env() sources .env
LOOM_MYSQL_CONTAINER=""   # set to ${PROJECT_NAME}_mysql after env load
LOOM_NETWORK=""           # set to ${PROJECT_NAME}_network after env load
THREAD_BASE_PORT=13000   # overridable in .env

# ─── Colours ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

log()  { echo -e "${GREEN}✓${NC}  $*"; }
warn() { echo -e "${YELLOW}⚠${NC}  $*"; }
err()  { echo -e "${RED}✗${NC}  $*" >&2; }
die()  { err "$*"; exit 1; }
info() { echo -e "${CYAN}→${NC}  $*"; }

# ─── Load Loom env ────────────────────────────────────────────────────────────
load_env() {
    [[ -f "$ENV_FILE" ]] || die ".env not found. Copy .env.sample and fill in values."
    set -a; source "$ENV_FILE"; set +a
    THREAD_BASE_PORT="${THREAD_BASE_PORT:-13000}"
    DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-rootpassword}"
    PROJECT_NAME="${PROJECT_NAME:-tribe}"
    LOOM_MYSQL_CONTAINER="${PROJECT_NAME}_mysql"
    LOOM_NETWORK="${PROJECT_NAME}_network"
}

# ─── Registry helpers ─────────────────────────────────────────────────────────
registry_init() {
    mkdir -p "$THREADS_DIR"
    [[ -f "$REGISTRY" ]] || echo '{"project_name":"'$PROJECT_NAME'","threads":{},"next_port":'$THREAD_BASE_PORT'}' > "$REGISTRY"
}

registry_get() {
    local name=$1 field=$2
    python3 -c "
import json,sys
d=json.load(open('$REGISTRY'))
t=d.get('threads',{}).get('$name')
if not t: sys.exit(1)
print(t.get('$field',''))
" 2>/dev/null
}

registry_has() {
    python3 -c "
import json,sys
d=json.load(open('$REGISTRY'))
sys.exit(0 if '$1' in d.get('threads',{}) else 1)
" 2>/dev/null
}

registry_add() {
    local name=$1 tribe_port=$2 junction_port=$3 db_name=$4 db_user=$5
    python3 - <<EOF
import json
from datetime import datetime, timezone
with open('$REGISTRY') as f: d = json.load(f)
d['threads']['$name'] = {
    'tribe_port':    $tribe_port,
    'junction_port': $junction_port,
    'db_name':       '$db_name',
    'db_user':       '$db_user',
    'created_at':    datetime.now(timezone.utc).isoformat(),
}
d['next_port'] = $junction_port + 1
with open('$REGISTRY','w') as f: json.dump(d, f, indent=2)
EOF
}

registry_remove() {
    python3 - <<EOF
import json
with open('$REGISTRY') as f: d = json.load(f)
d['threads'].pop('$1', None)
with open('$REGISTRY','w') as f: json.dump(d, f, indent=2)
EOF
}

registry_next_port() {
    python3 -c "
import json
d=json.load(open('$REGISTRY'))
print(d.get('next_port', $THREAD_BASE_PORT))
"
}

registry_list() {
    python3 - <<EOF
import json
with open('$REGISTRY') as f: d = json.load(f)
threads = d.get('threads', {})
if not threads:
    print('  (no threads)')
else:
    for name, t in sorted(threads.items()):
        print(f"  {name}  tribe={t['tribe_port']}  junction={t['junction_port']}  db={t['db_name']}  created={t['created_at'][:10]}")
EOF
}

# ─── Name validation ─────────────────────────────────────────────────────────
validate_name() {
    local name=$1
    [[ "$name" =~ ^[a-z0-9][a-z0-9-]{1,30}$ ]] || \
        die "Thread name must be 2–31 chars, lowercase letters/numbers/hyphens, start with alphanumeric. Got: '$name'"
}

# ─── Prerequisite checks ─────────────────────────────────────────────────────
check_prerequisites() {
    command -v docker  &>/dev/null || die "docker not found"
    command -v python3 &>/dev/null || die "python3 not found (needed for registry management)"

    docker network inspect "$LOOM_NETWORK" &>/dev/null || \
        die "Network '$LOOM_NETWORK' not found. Run: docker compose --profile loom up -d"

    docker ps --format '{{.Names}}' | grep -q "^${LOOM_MYSQL_CONTAINER}$" || \
        die "MySQL container '$LOOM_MYSQL_CONTAINER' is not running. Start Loom first: docker compose --profile loom up -d"

    [[ -d "$TRIBE_BASE" ]] || \
        die "Tribe base app not found at: $TRIBE_BASE\nPlace the Tribe app code there before creating threads."
}

# ─── Caddy config generators ─────────────────────────────────────────────────
generate_tribe_caddyfile() {
    local thread_name=$1
    cat <<CADDY
# Auto-generated by loom.sh for thread: ${thread_name}
{
    admin off
    local_certs
}

:80 {
    root * /var/www/html

    header {
        X-Frame-Options "SAMEORIGIN"
        X-XSS-Protection "1; mode=block"
        X-Content-Type-Options "nosniff"
        Referrer-Policy "no-referrer-when-downgrade"
        Content-Security-Policy "default-src * data: 'unsafe-eval' 'unsafe-inline'"
    }

    php_fastcgi loom_${thread_name}_php_tribe:9000 {
        trusted_proxies private_ranges
    }

    @hidden {
        path */.*
        not path */.well-known/*
    }
    respond @hidden 403

    @mysql_uploads {
        path /uploads/mysql/*
    }
    respond @mysql_uploads 403

    @uploads {
        path /uploads/*
    }
    route @uploads {
        try_files {path} /uploads.php{query}
    }

    @static {
        file
        path *.css *.gif *.ico *.webp *.jpeg *.jpg *.js *.png *.woff *.woff2 *.ttf *.eot
    }
    header @static {
        Cache-Control "public, max-age=31536000, immutable"
    }

    file_server
    encode {
        gzip 6
        minimum_length 1000
    }

    log {
        output file /var/log/caddy/tribe_access.log {
            roll_size 50MiB
            roll_keep 3
        }
        format json
        level INFO
    }
}
CADDY
}

generate_junction_caddyfile() {
    local thread_name=$1
    cat <<CADDY
# Auto-generated by loom.sh for thread: ${thread_name}
{
    admin off
    local_certs
}

:80 {
    root * /var/www/html

    header {
        X-Frame-Options "SAMEORIGIN"
        X-XSS-Protection "1; mode=block"
        X-Content-Type-Options "nosniff"
        Referrer-Policy "no-referrer-when-downgrade"
        Content-Security-Policy "default-src * data: 'unsafe-eval' 'unsafe-inline'"
    }

    php_fastcgi loom_${thread_name}_php_junction:9000 {
        trusted_proxies private_ranges
    }

    @hidden {
        path */.*
        not path */.well-known/*
    }
    respond @hidden 403

    @static {
        file
        path *.css *.gif *.ico *.webp *.jpeg *.jpg *.js *.png *.woff *.woff2 *.ttf *.eot
    }
    header @static {
        Cache-Control "public, max-age=31536000, immutable"
    }

    file_server
    encode {
        gzip 6
        minimum_length 1000
    }

    log {
        output file /var/log/caddy/junction_access.log {
            roll_size 50MiB
            roll_keep 3
        }
        format json
        level INFO
    }
}
CADDY
}

generate_thread_compose() {
    local name=$1 tribe_port=$2 junction_port=$3

    # Load Typesense key from env (already sourced)
    local ts_key="${TYPESENSE_API_KEY:-xyz}"
    local project="${PROJECT_NAME:-tribe}"
    local network="${project}_network"
    local ts_host="${project}_typesense"

    cat <<COMPOSE
# Auto-generated by loom.sh — do not edit manually.
# Regenerate with: loom.sh recreate-config ${name}

services:

  php_tribe:
    image: tribeframework/tribe-core:v1.2
    platform: linux/amd64
    container_name: ${project}_${name}_php_tribe
    restart: unless-stopped
    env_file:
      - .env
    environment:
      - PROJECT_NAME=${project}_${name}
      - TYPESENSE_HOST=${ts_host}
      - TYPESENSE_PORT=8108
      - TYPESENSE_API_KEY=${ts_key}
      - TYPESENSE_COLLECTION_PREFIX=${name}
      - COLLECTION_NAME=${name}_files
      - INDEX_FOLDER=/var/www/html/uploads
      - INDEX_DB_SCRIPT=/var/www/html/config/index_db.php
    volumes:
      - ../../applications/tribe:/var/www/html:ro
      - ../../uploads/threads/${name}:/var/www/html/uploads
      - ../../logs/threads/${name}:/var/log
      - ../../config/tribe/supervisord.conf:/etc/supervisor/conf.d/tribe.conf:ro
    command: >
      sh -c "
        exec supervisord -c /etc/supervisor/conf.d/tribe.conf
      "
    networks:
      - thread_network

  php_junction:
    image: tribeframework/tribe-core:v1.2
    platform: linux/amd64
    container_name: ${project}_${name}_php_junction
    restart: unless-stopped
    env_file:
      - .env
    volumes:
      - ../../applications/junction/dist:/var/www/html:ro
      - ../../uploads/threads/${name}:/var/www/html/uploads
      - ../../logs/threads/${name}:/var/log
    command: >
      sh -c "
        if [ -f composer.json ]; then composer install --no-interaction --optimize-autoloader; fi &&
        php-fpm
      "
    networks:
      - thread_network

  caddy_tribe:
    image: caddy:2-alpine
    container_name: ${project}_${name}_caddy_tribe
    restart: unless-stopped
    env_file:
      - .env
    ports:
      - "${tribe_port}:80"
    volumes:
      - ../../applications/tribe:/var/www/html:ro
      - ../../uploads/threads/${name}:/var/www/html/uploads
      - ./config/tribe/Caddyfile:/etc/caddy/Caddyfile:ro
      - ../../logs/threads/${name}:/var/log/caddy
      - tribe_caddy_data:/data
      - tribe_caddy_config:/config
    depends_on:
      - php_tribe
    networks:
      - thread_network

  caddy_junction:
    image: caddy:2-alpine
    container_name: ${project}_${name}_caddy_junction
    restart: unless-stopped
    env_file:
      - .env
    ports:
      - "${junction_port}:80"
    volumes:
      - ../../applications/junction/dist:/var/www/html:ro
      - ../../uploads/threads/${name}:/var/www/html/uploads
      - ./config/junction/Caddyfile:/etc/caddy/Caddyfile:ro
      - ../../logs/threads/${name}:/var/log/caddy
      - junction_caddy_data:/data
      - junction_caddy_config:/config
    depends_on:
      - php_junction
    networks:
      - thread_network

networks:
  thread_network:
    external: true
    name: ${network}

volumes:
  tribe_caddy_data:
    name: ${project}_${name}_tribe_caddy_data
  tribe_caddy_config:
    name: ${project}_${name}_tribe_caddy_config
  junction_caddy_data:
    name: ${project}_${name}_junction_caddy_data
  junction_caddy_config:
    name: ${project}_${name}_junction_caddy_config
COMPOSE
}

# ─── Thread .env generator ────────────────────────────────────────────────────
generate_thread_env() {
    local name=$1 db_name=$2 db_user=$3 db_pass=$4 tribe_port=$5 junction_port=$6

    local bare_url="${BARE_URL:-localhost}"
    local ts_key="${TYPESENSE_API_KEY:-xyz}"
    local contact="${CONTACT_EMAIL:-your@email.com}"
    local project="${PROJECT_NAME:-tribe}"

    cat <<ENV
# Auto-generated by loom.sh for thread: ${name}
# Edit freely — re-running loom.sh will not overwrite an existing .env.

# ─── Project ─────────────────────────────────────────────────────────────────
PROJECT_NAME=${project}_${name}

# ─── Application ─────────────────────────────────────────────────────────────
DISPLAY_ERRORS=false
DEFAULT_TIMEZONE=${DEFAULT_TIMEZONE:-Asia/Kolkata}
BARE_URL=${bare_url}
CONTACT_EMAIL=${contact}

# ─── Database ────────────────────────────────────────────────────────────────
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
DB_NAME=${db_name}
DB_USER=${db_user}
DB_PASS=${db_pass}
DB_PORT=3306
DB_HOST=${LOOM_MYSQL_CONTAINER}

# ─── Ports ───────────────────────────────────────────────────────────────────
TRIBE_PORT=${tribe_port}
JUNCTION_PORT=${junction_port}

# ─── Junction ────────────────────────────────────────────────────────────────
JUNCTION_SLUG=junction
JUNCTION_PASSWORD=password
TRIBE_API_URL=http://localhost:${tribe_port}
TRIBE_API_KEY=

# ─── Typesense ───────────────────────────────────────────────────────────────
TYPESENSE_API_KEY=${ts_key}
TYPESENSE_ENABLED=true
TYPESENSE_SHOW_PUBLIC_OBJECTS_ONLY=true
TYPESENSE_SHOW_UPLOADS_FOLDER_RESULTS=false
ENV
}

# ─── MySQL provisioning ───────────────────────────────────────────────────────
mysql_exec() {
    docker exec -i "$LOOM_MYSQL_CONTAINER" \
        mysql -u root -p"${DB_ROOT_PASSWORD}" -sN "$@" 2>/dev/null
}

provision_db() {
    local db_name=$1 db_user=$2 db_pass=$3

    info "Creating database '$db_name'..."
    mysql_exec -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || \
        die "Failed to create database '$db_name'"

    info "Creating user '$db_user'..."
    mysql_exec -e "CREATE USER IF NOT EXISTS '${db_user}'@'%' IDENTIFIED BY '${db_pass}';" || \
        die "Failed to create user '$db_user'"

    info "Granting privileges..."
    mysql_exec -e "GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'%'; FLUSH PRIVILEGES;" || \
        die "Failed to grant privileges"

    # Import schema if install.sql exists
    local schema="$SCRIPT_DIR/config/mysql/install.sql"
    if [[ -f "$schema" ]]; then
        info "Importing schema from config/mysql/install.sql..."
        docker exec -i "$LOOM_MYSQL_CONTAINER" \
            mysql -u root -p"${DB_ROOT_PASSWORD}" "$db_name" < "$schema" || \
            die "Failed to import schema"
        log "Schema imported."
    else
        warn "config/mysql/install.sql not found — skipping schema import."
    fi
}

drop_db() {
    local db_name=$1 db_user=$2
    warn "Dropping database '$db_name' and user '$db_user'..."
    mysql_exec -e "DROP DATABASE IF EXISTS \`${db_name}\`;"
    mysql_exec -e "DROP USER IF EXISTS '${db_user}'@'%'; FLUSH PRIVILEGES;"
    log "Database and user removed."
}

# ─── Random password ─────────────────────────────────────────────────────────
random_pass() {
    python3 -c "import secrets,string; print(''.join(secrets.choice(string.ascii_letters+string.digits) for _ in range(24)))"
}

# ═══════════════════════════════════════════════════════════════════════════════
#  COMMANDS
# ═══════════════════════════════════════════════════════════════════════════════

cmd_create() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh create <name>"

    load_env
    validate_name "$name"
    check_prerequisites
    registry_init

    registry_has "$name" && die "Thread '$name' already exists. Use 'loom.sh start $name' or 'loom.sh destroy $name' first."

    # ── Allocate ports ────────────────────────────────────────────────────────
    local tribe_port
    tribe_port=$(registry_next_port)
    local junction_port=$(( tribe_port + 1 ))

    echo ""
    echo -e "${BOLD}Creating Thread: ${CYAN}${name}${NC}"
    echo -e "  Tribe port:    ${tribe_port}"
    echo -e "  Junction port: ${junction_port}"
    echo ""

    # ── DB credentials ────────────────────────────────────────────────────────
    local db_name="${PROJECT_NAME}_${name//-/_}_db"
    local db_user="${PROJECT_NAME}_${name//-/_}_user"
    local db_pass
    db_pass=$(random_pass)

    # ── Directory structure ───────────────────────────────────────────────────
    local thread_dir="$THREADS_DIR/$name"
    mkdir -p \
        "${thread_dir}/config/tribe" \
        "${thread_dir}/config/junction" \
        "$SCRIPT_DIR/uploads/threads/${name}" \
        "$SCRIPT_DIR/logs/threads/${name}"

    # ── Generate configs ──────────────────────────────────────────────────────
    info "Generating Caddyfiles..."
    generate_tribe_caddyfile    "$name" > "${thread_dir}/config/tribe/Caddyfile"
    generate_junction_caddyfile "$name" > "${thread_dir}/config/junction/Caddyfile"

    info "Generating docker-compose.yml..."
    generate_thread_compose "$name" "$tribe_port" "$junction_port" > "${thread_dir}/docker-compose.yml"

    info "Generating .env..."
    # Never overwrite an existing .env (user may have customised it)
    if [[ ! -f "${thread_dir}/.env" ]]; then
        generate_thread_env "$name" "$db_name" "$db_user" "$db_pass" "$tribe_port" "$junction_port" > "${thread_dir}/.env"
    else
        warn ".env already exists — skipping (using existing credentials)."
        db_pass="(from existing .env)"
    fi

    # ── Provision MySQL ───────────────────────────────────────────────────────
    provision_db "$db_name" "$db_user" "$db_pass"

    # ── Register ──────────────────────────────────────────────────────────────
    registry_add "$name" "$tribe_port" "$junction_port" "$db_name" "$db_user"

    # ── Start containers ──────────────────────────────────────────────────────
    info "Starting Thread containers..."
    docker compose -f "${thread_dir}/docker-compose.yml" up -d

    echo ""
    log "Thread '${name}' is up!"
    echo ""
    echo -e "  ${CYAN}Tribe:${NC}    http://localhost:${tribe_port}"
    echo -e "  ${CYAN}Junction:${NC} http://localhost:${junction_port}"
    echo -e "  ${CYAN}DB:${NC}       ${db_name}  (user: ${db_user})"
    echo ""
}

cmd_destroy() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh destroy <name>"

    load_env
    registry_init
    registry_has "$name" || die "Thread '$name' not found in registry."

    local thread_dir="$THREADS_DIR/$name"
    local db_name; db_name=$(registry_get "$name" db_name)
    local db_user; db_user=$(registry_get "$name" db_user)

    echo ""
    warn "This will permanently stop and remove Thread '${name}'."
    warn "Database '${db_name}' will be DROPPED."
    warn "Upload files in uploads/threads/${name}/ will be kept."
    echo ""
    read -rp "Type the thread name to confirm: " confirm
    [[ "$confirm" == "$name" ]] || die "Aborted."

    info "Stopping containers..."
    docker compose -f "${thread_dir}/docker-compose.yml" down -v 2>/dev/null || true

    drop_db "$db_name" "$db_user"

    info "Removing thread directory..."
    rm -rf "$thread_dir"

    registry_remove "$name"

    log "Thread '${name}' destroyed."
    warn "Upload data preserved at: uploads/threads/${name}/"
    warn "Remove manually if no longer needed."
    echo ""
}

cmd_list() {
    load_env
    registry_init

    echo ""
    echo -e "${BOLD}Loom Threads${NC}"
    echo "────────────────────────────────────────────────────────────────"

    # Show thread list with live container status
    python3 - <<'PY'
import json, subprocess, sys

with open(sys.argv[1]) as f:
    data = json.load(f)

threads = data.get('threads', {})
if not threads:
    print("  (no threads provisioned yet)")
    sys.exit(0)

# Get running container names from docker
try:
    result = subprocess.run(['docker', 'ps', '--format', '{{.Names}}'],
                            capture_output=True, text=True)
    running = set(result.stdout.strip().split('\n'))
except Exception:
    running = set()

fmt = "  {:<20} {:>6}  {:>6}  {:<12}  {}"
print(fmt.format("THREAD", "TRIBE", "JXN", "STATUS", "DB"))
print("  " + "-"*68)
for name, t in sorted(threads.items()):
    prefix = data.get('project_name', 'tribe')
    expected = [f"{prefix}_{name}_php_tribe", f"{prefix}_{name}_caddy_tribe",
                f"{prefix}_{name}_php_junction", f"{prefix}_{name}_caddy_junction"]
    alive = sum(1 for c in expected if c in running)
    if alive == len(expected):
        status = "\033[0;32m● running\033[0m"
    elif alive > 0:
        status = f"\033[1;33m◑ partial ({alive}/{len(expected)})\033[0m"
    else:
        status = "\033[0;31m○ stopped\033[0m"
    print(fmt.format(name, t['tribe_port'], t['junction_port'], '', t['db_name']))
    print(f"  {'':20} {'':>6}  {'':>6}  {status}")
PY
    "$REGISTRY"

    echo ""
    local next; next=$(registry_next_port)
    echo -e "  ${CYAN}Next available port:${NC} ${next}"
    echo ""
}

cmd_start() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh start <name>"

    load_env
    registry_has "$name" || die "Thread '$name' not found."

    info "Starting thread '$name'..."
    docker compose -f "$THREADS_DIR/$name/docker-compose.yml" up -d
    log "Thread '$name' started."
}

cmd_stop() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh stop <name>"

    load_env
    registry_has "$name" || die "Thread '$name' not found."

    info "Stopping thread '$name'..."
    docker compose -f "$THREADS_DIR/$name/docker-compose.yml" stop
    log "Thread '$name' stopped."
}

cmd_logs() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh logs <name> [service]"
    local service="${2:-}"

    load_env
    registry_has "$name" || die "Thread '$name' not found."

    if [[ -n "$service" ]]; then
        docker compose -f "$THREADS_DIR/$name/docker-compose.yml" logs -f "$service"
    else
        docker compose -f "$THREADS_DIR/$name/docker-compose.yml" logs -f
    fi
}

cmd_status() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh status <name>"

    load_env
    registry_has "$name" || die "Thread '$name' not found."

    docker compose -f "$THREADS_DIR/$name/docker-compose.yml" ps
}

cmd_recreate_config() {
    local name="${1:-}"
    [[ -n "$name" ]] || die "Usage: loom.sh recreate-config <name>"

    load_env
    registry_has "$name" || die "Thread '$name' not found."

    local thread_dir="$THREADS_DIR/$name"
    local tribe_port; tribe_port=$(registry_get "$name" tribe_port)
    local junction_port; junction_port=$(registry_get "$name" junction_port)

    warn "Regenerating docker-compose.yml and Caddyfiles for '$name'."
    warn "Existing .env is preserved."

    generate_tribe_caddyfile    "$name" > "${thread_dir}/config/tribe/Caddyfile"
    generate_junction_caddyfile "$name" > "${thread_dir}/config/junction/Caddyfile"
    generate_thread_compose "$name" "$tribe_port" "$junction_port" > "${thread_dir}/docker-compose.yml"

    log "Config regenerated. Run 'loom.sh start $name' to apply."
}

usage() {
    cat <<USAGE
Usage: loom.sh <command> [args]

  create  <name>         Provision and start a new Thread
  destroy <name>         Stop containers, drop DB (uploads preserved)
  list                   List all Threads with ports and status
  start   <name>         Start a stopped Thread
  stop    <name>         Stop a running Thread
  logs    <name> [svc]   Tail logs (svc: php_tribe|caddy_tribe|php_junction|caddy_junction)
  status  <name>         Show container status
  recreate-config <name> Regenerate compose + Caddyfiles (keeps .env)

USAGE
    exit 1
}

# ─── Dispatch ─────────────────────────────────────────────────────────────────
case "${1:-}" in
    create)          cmd_create          "${2:-}" ;;
    destroy)         cmd_destroy         "${2:-}" ;;
    list)            cmd_list ;;
    start)           cmd_start           "${2:-}" ;;
    stop)            cmd_stop            "${2:-}" ;;
    logs)            cmd_logs            "${2:-}" "${3:-}" ;;
    status)          cmd_status          "${2:-}" ;;
    recreate-config) cmd_recreate_config "${2:-}" ;;
    *)               usage ;;
esac