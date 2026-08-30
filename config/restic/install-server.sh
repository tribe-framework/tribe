#!/usr/bin/env bash
# Run on `server`. Installs restic, the backup + mysqldump scripts, and the
# exclude file. Assumes Tailscale is up and `local` resolves via MagicDNS.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

sudo apt update
sudo apt install -y restic
sudo restic self-update

sudo install -m755 "$SCRIPT_DIR"/restic-backup.sh /usr/local/bin/restic-backup.sh
sudo install -m755 "$SCRIPT_DIR"/mysqldump-all.sh /usr/local/bin/mysqldump-all.sh

sudo mkdir -p /etc/restic
sudo install -m644 "$SCRIPT_DIR"/exclude.txt /etc/restic/exclude.txt

echo "NOTE: /etc/restic/password must exist (mode 644) and be identical to the"
echo "      copy on 'local' before the first backup run."
echo ""
echo "Add mysqldump-all.sh to run just before the nightly backup, e.g. via"
echo "cron on 'server' at 03:45 IST:"
echo "  45 3 * * * /usr/local/bin/mysqldump-all.sh"
