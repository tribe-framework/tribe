# Tribe Complete Setup Guide

Visit https://tribe-framework.org

---

## About Tribe Framework

Tribe is a web project management framework that allows us to build platforms, products, applications and web interfaces using a modular approach. It provides a coherent language for UX, content, design and code collaborations.

## Glossary

| Term | Definition |
|------|------------|
| **Tribe** | PHP content/app framework — the core backend and UI engine |
| **Junction** | CMS layer that connects to Tribe via API |
| **Loom** | Multi-tenant mode of Tribe — one shared infrastructure hosting many Threads |
| **Thread** | A single Tribe + Junction instance inside a Loom setup |
| **Docker** | Containerisation platform; `docker-compose.yml` defines all services and how they connect |
| **.env** | Environment file holding secrets and config (DB passwords, ports, project name) |
| **Tor** | Overlay mode that routes all traffic through `.onion` hidden services |
| **MySQL** | Relational database storing all Tribe content and application data |
| **phpMyAdmin** | Web UI for browsing and managing MySQL databases |
| **Caddy** | Web server / reverse proxy — handles HTTP and routes requests to PHP-FPM |
| **Typesense** | Fast search engine by typesense.org — indexes Tribe content for full-text search |
| **FileBrowser** | Web UI for browsing and managing uploaded files by filebrowser.org |

## Folder Structure

```
applications/          # App code
  tribe/               # Tribe PHP application
  junction/            # Junction frontend (dist/)
  threads/             # Per-thread Junction builds (Loom mode)

config/                # Service configuration
  mysql/               # my.cnf, install.sql
  tribe/               # Caddyfile, supervisord.conf, watcher.sh, indexer scripts
  junction/            # Caddyfile
  dist/                # Caddyfile (static site)
  dist-php/            # Caddyfile (PHP site)
  loom/                # Loom setup.sh, index_db.php
  tor/                 # torrc, tor-addresses.sh
  backup.sh            # MySQL backup script

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
  .registry.json       # Port + DB registry
  <name>/
    docker-compose.yml
    .env
    config/

docker-compose.yml          # Standard single-project setup
docker-compose.loom.yml     # Loom shared infrastructure
docker-compose.thread.yml   # Thread template (do not edit directly)
docker-compose.tor.yml      # Tor overlay

loom.sh                     # Loom CLI

.env                        # Active environment (copy from .env.sample)
.env.sample                 # Standard setup template
.env.loom.sample            # Loom setup template
.env.tor.sample             # Tor setup template
```

For full setup instructions, deployment modes (standard, Loom multi-tenant, Tor), and configuration reference — visit https://tribe-framework.org.

## License

The GNU General Public License is a free, copyleft license for software and other kinds of works.