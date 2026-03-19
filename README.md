# Tribe

A web project management framework for building platforms, products and web interfaces using a modular approach.

Visit https://tribe-framework.org

---

## Quick Start

```bash
# 1. Configure
cp .env.sample .env
# Edit .env — set passwords, PROJECT_NAME, BARE_URL

# 2. Start
docker compose up -d
```

That's it. Tribe is running at `http://localhost:12000`.

## Default Ports

| Service      | Port    |
|--------------|---------|
| Tribe        | `12000` |
| phpMyAdmin   | `12001` |
| Junction     | `12002` |
| Dist (static)| `12003` |
| Dist (PHP)   | `12004` |
| FileBrowser  | `12005` |

All ports are configurable in `.env`.

## CLI

```bash
docker compose up -d       # start
docker compose down        # stop
docker compose logs -f     # tail logs
```

## Advanced Modes

For multi-tenant (Loom) or anonymous network (Tor) setups, see [README.ext.md](README.ext.md).