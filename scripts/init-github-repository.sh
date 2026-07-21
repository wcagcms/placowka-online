#!/usr/bin/env bash
set -Eeuo pipefail
REMOTE="${1:-git@github.com:atrojanowski44/placowka-online.git}"
bash scripts/public-repo-audit.sh
[[ ! -d .git ]] && git init -b main
git add .
git commit -m "chore: initial public open-source release"
git remote get-url origin >/dev/null 2>&1 || git remote add origin "$REMOTE"
echo "Sprawdź commit, a następnie wykonaj: git push -u origin main"
