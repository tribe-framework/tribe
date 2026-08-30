#!/usr/bin/env bash
# Run on `local`. Installs the rest-server, backup, and prune systemd units.
# Assumes: Tailscale is up, restic is installed, /etc/restic/password exists,
# and rest-server binary is at /usr/local/bin/rest-server.
set -euo pipefail

UNIT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/systemd" && pwd)"

sudo cp "$UNIT_DIR"/rest-server.service /etc/systemd/system/
sudo cp "$UNIT_DIR"/restic-backup@.service /etc/systemd/system/
sudo cp "$UNIT_DIR"/restic-backup@.timer /etc/systemd/system/
sudo cp "$UNIT_DIR"/restic-prune@.service /etc/systemd/system/
sudo cp "$UNIT_DIR"/restic-prune@.timer /etc/systemd/system/

echo "NOTE: edit /etc/systemd/system/rest-server.service and replace 100.x.y.z"
echo "      with this machine's Tailscale IPv4 address (tailscale ip -4)."

sudo mkdir -p /media/cityowl/WD-Internal/restic

sudo systemctl daemon-reload
sudo systemctl enable --now rest-server

echo "Enable per-volume timers manually once rest-server is confirmed up, e.g.:"
echo "  sudo systemctl enable --now restic-backup@beatroot.timer"
echo "  sudo systemctl enable --now restic-backup@volume.timer"
echo "  sudo systemctl enable --now restic-prune@beatroot-backup.timer"
echo "  sudo systemctl enable --now restic-prune@volume-backup.timer"
