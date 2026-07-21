#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/../placowka-online-public}"
rm -rf "$OUT"
mkdir -p "$OUT"
rsync -a --delete \
  --exclude='.git/' --exclude='.env' --exclude='vendor/' --exclude='node_modules/' \
  --exclude='database/*.sqlite' --exclude='storage/logs/*' \
  --exclude='storage/framework/*' --exclude='storage/app/agent-packages/*' \
  --exclude='storage/app/agent-installer/build/*' --exclude='storage/app/backups/*' \
  --exclude='*.exe' --exclude='*.pfx' --exclude='*.p12' --exclude='*.pem' \
  "$ROOT/" "$OUT/"
(cd "$OUT" && bash scripts/public-repo-audit.sh)
echo "Eksport gotowy: $OUT"
