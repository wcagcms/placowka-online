#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_PATH="${1:-$(pwd)}"

cd "$PROJECT_PATH"

export PATH="$HOME/.local/go1.26.5/bin:$PATH"
hash -r

echo "Go: $(go version)"

bash scripts/build-agent-secure.sh "$PROJECT_PATH"
bash scripts/build-agent-installer.sh "$PROJECT_PATH"

echo
echo "Gotowe pliki:"
ls -lah storage/app/agent-template/PlacowkaOnlineAgent*.exe
ls -lah storage/app/agent-installer/build/PlacowkaOnlineInstaller-BuildInput-1.0.6.zip
