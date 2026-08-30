# restic-tribe

Restic-based volume backups for the `tribe` stack, pulling from `server` to
`local` over Tailscale. Companion doc: https://tribe-framework.org/backup (the
in-app manual page — copy it into the tribe app's templates directory).

## Layout

```
config/restic/
├── restic-backup.sh          # runs on `server`, does the actual restic backup
├── mysqldump-all.sh          # runs on `server`, dumps every mysql: container
├── exclude.txt               # restic exclude patterns, installed to /etc/restic/exclude.txt on `server`
├── install-server.sh         # installs restic + scripts + exclude file on `server`
├── install-local.sh          # installs rest-server + systemd units on `local`
└── systemd/
    ├── rest-server.service
    ├── restic-backup@.service
    ├── restic-backup@.timer
    ├── restic-prune@.service
    └── restic-prune@.timer
```

## Architecture

- **`server`** (source of the data): runs `mysqldump-all.sh` nightly (cron, ahead
  of the backup window) so mysql containers' data is captured as fresh dump
  zips rather than raw InnoDB files. Runs `restic backup` when triggered by
  `local` over SSH.
- **`local`** (holds the repository): owns the systemd timers, `rest-server`,
  pruning, and restores. The schedule lives here because this is the machine
  that's not guaranteed to always be on — `Persistent=true` on the timers
  means a missed run fires on next boot instead of being skipped.

Sources on `server`: `/mnt/postcode`, `/mnt/volume`.
Repositories on `local`: `/media/username/WD-Internal/restic/`.

## Setup order

1. Install Tailscale on both machines, note `local`'s `100.x.y.z` address,
   enable MagicDNS.
2. On `local`: run `install-local.sh`, edit `rest-server.service` to bind the
   correct Tailscale IP, confirm `rest-server` is up.
3. On `server`: run `install-server.sh`.
4. Put a shared key at `/etc/restic/password` (mode 644) on **both** machines.
5. Give root on `local` a passwordless SSH key into `server`
   (`ssh-copy-id root@server`).
6. Enable the timers on `local`:
   ```
   sudo systemctl enable --now restic-backup@postcode.timer
   # sudo systemctl enable --now restic-backup@volume.timer
   sudo systemctl enable --now restic-prune@postcode-backup.timer
   # sudo systemctl enable --now restic-prune@volume-backup.timer
   ```

Full day-to-day operation, troubleshooting, retention, and migration
instructions are in `backup.hbs`.
