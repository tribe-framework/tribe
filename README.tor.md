# Tribe on Tor — Setup Guide

## How it works

The same `docker-compose.yml` serves both clearnet and Tor.
A second file, `docker-compose.tor.yml`, is layered on top for Tor mode.
It does two things: adds the `tor` daemon, and nulls out all host port bindings.

**Rule: never run both modes simultaneously on one project.**

```
Clearnet:  docker compose up -d
Tor:       docker compose -f docker-compose.yml -f docker-compose.tor.yml up -d
```

## File layout

```
config/
  tor/
    torrc                    ← Tor daemon config (6 hidden services)
    tor-addresses.sh         ← helper: print all .onion URLs
uploads/
  tor/
    tribe/hostname           ← .onion address (written on first boot)
    junction/hostname
    dist/hostname
    dist-php/hostname
    phpmyadmin/hostname      ← admin — Tor only, no clearnet port
    filebrowser/hostname     ← admin — Tor only, no clearnet port
docker-compose.yml           ← base (unchanged)
docker-compose.tor.yml       ← Tor overlay
tor-addresses.sh             ← helper: print all .onion URLs
```

## First-time setup

```bash
# 1. Copy env file (same as clearnet)
cp _env.sample .env
# Edit .env — DB passwords, project name, etc.

# 2. Start in Tor mode
docker compose -f docker-compose.yml -f docker-compose.tor.yml up -d

# 3. Wait for Tor to generate keys (~30s), then read addresses
bash config/tor/tor-addresses.sh --wait
```

Output looks like:
```
  tribe           http://abc123...onion
  junction        http://def456...onion
  dist            http://ghi789...onion
  dist-php        http://jkl012...onion
  phpmyadmin      http://mno345...onion
  filebrowser     http://pqr678...onion
```

## Backup your keys

`.onion` addresses are derived from the private key in each `uploads/tor/<service>/` directory.
**If you lose these keys, your addresses change permanently.**

```bash
# Backup
tar -czf tor-keys-backup.tar.gz uploads/tor/

# Restore (keys must be in place before `docker compose up`)
tar -xzf tor-keys-backup.tar.gz
```

## Security checklist

| Concern | Status |
|---------|--------|
| Admin ports (phpMyAdmin, FileBrowser) exposed on clearnet | ✗ Never in Tor mode |
| App ports exposed on host | ✗ All `ports: []` in overlay |
| Tor daemon exits/relays traffic | ✗ `ExitPolicy reject *:*`, `SocksPort 0` |
| Hidden service version | ✓ v3 (ed25519, 56-char addresses) |
| Key persistence | ✓ Stored in `uploads/tor/` |

## BARE_URL in Tor mode

Your `.onion` address is not known at startup time (it depends on the generated key).
Set `BARE_URL` in `.env` after reading your address:

```bash
BARE_URL=$(cat uploads/tor/tribe/hostname)
# Then restart php_tribe to pick up the change:
docker compose -f docker-compose.yml -f docker-compose.tor.yml restart php_tribe
```

Or set it manually in `.env`:
```
BARE_URL=youraddress.onion
```

## Analytics warning

Do **not** configure Plausible or any clearnet analytics service in Tor mode —
it deanonymises your visitors. Leave `PLAUSIBLE_AUTH` and `PLAUSIBLE_DOMAIN` blank,
or self-host Plausible as its own hidden service.

## Switching between modes

Always bring the stack down before switching:

```bash
# Clearnet → Tor
docker compose down
docker compose -f docker-compose.yml -f docker-compose.tor.yml up -d

# Tor → Clearnet
docker compose -f docker-compose.yml -f docker-compose.tor.yml down
docker compose up -d
```

Never mix — running both compose files while clearnet ports are also open
defeats the purpose entirely.

## Tor image note

The overlay uses `torproject/tor:latest`.
If you prefer a pinned version for reproducibility:

```yaml
# docker-compose.tor.yml
tor:
  image: torproject/tor:0.4.8.12
```

Pin the image tag and update it deliberately.