# Tribe — Advanced Reference

## Glossary

| Term | Definition |
|------|------------|
| **Tribe** | PHP content/app framework — core backend and UI engine |
| **Junction** | CMS layer connecting to Tribe via API |
| **Loom** | Multi-tenant mode — one shared infrastructure hosting many Threads |
| **Thread** | A single Tribe + Junction instance inside a Loom setup |
| **Tor** | Overlay mode routing all traffic through `.onion` hidden services |
| **Docker** | Containerisation platform; `docker-compose.yml` defines all services |
| **.env** | Environment file holding secrets and config |
| **MySQL** | Relational database storing all Tribe content and data |
| **phpMyAdmin** | Web UI for browsing and managing MySQL databases |
| **Caddy** | Web server / reverse proxy — handles HTTP, routes to PHP-FPM |
| **Typesense** | Fast search engine — indexes Tribe content for full-text search |
| **FileBrowser** | Web UI for browsing and managing uploaded files |

---

## Full Folder Structure

```
applications/          # App code
  tribe/               # Tribe PHP application
  junction/            # Junction frontend (dist/)

config/                # Service configuration
  mysql/               # my.cnf, install.sql
  tribe/               # Caddyfile, supervisord.conf, watcher.sh, indexer scripts
  junction/            # Caddyfile
  dist/                # Caddyfile (static site)
  dist-php/            # Caddyfile (PHP site)
  backup.sh            # MySQL backup script
  tor/                 # Tor daemon config and helpers
    README.tor.md      # ← Tor setup guide
    docker-compose.tor.yml
    .env.tor.sample
    torrc
    tor-addresses.sh

uploads/               # All runtime data (gitignored)
  sites/
    dist/              # Static site files
    dist-php/          # PHP site files
  threads/             # Per-thread uploads (Loom mode)
  backups/             # Automated MySQL dumps
  typesense/           # Typesense data
  filebrowser/         # FileBrowser database
  tor/                 # .onion keys and hostnames

logs/                  # Runtime logs (gitignored)
  threads/             # Per-thread logs (Loom mode)

threads/               # Generated thread configs (Loom mode)
  .registry.json
  <name>/
    docker-compose.yml
    .env
    config/

docker-compose.yml     # Standard single-project setup
.env.sample            # Standard env template
```

---

## Advanced Modes

### Loom — Multi-tenant hosting

Loom lets you run hundreds of isolated Tribe + Junction instances (Threads) on one shared set of services (MySQL, Typesense, Caddy, phpMyAdmin, FileBrowser).

**To use Loom:**

```bash
# 1. In your .env, set LOOM_MODE=true and configure shared service credentials.
#    Thread ports are allocated automatically starting from THREAD_BASE_PORT.

# 2. Start shared infrastructure
docker compose --profile loom up -d

# 3. Manage Threads with loom.sh
./loom.sh create <name>    # provision and start a new Thread
./loom.sh list             # list all Threads with ports and status
./loom.sh destroy <name>   # stop containers and drop DB
```

---

### Tor — Anonymous hidden services

Tor mode layers `docker-compose.tor.yml` on top of the standard setup. All ports are removed from the host; services are reachable only via `.onion` addresses.

**To use Tor:**

```bash
# 1. Copy files to project root
mv config/tor/docker-compose.tor.yml .
mv config/tor/.env.tor.sample .

# 2. Follow the guide
# config/tor/README.tor.md
```

→ Full instructions: [config/tor/README.tor.md](config/tor/README.tor.md)

---

## License

GNU General Public License — free, copyleft.