#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
failed=0

fail(){ echo "BŁĄD: $1" >&2; failed=1; }

forbidden_files=$(find . -type f \( \
  -name '.env' -o -name '*.sqlite' -o -name '*.pfx' -o -name '*.p12' -o \
  -name '*.pem' -o -name '*.key' -o -name '*.cer' -o -name '*.crt' -o \
  -name '*.exe' -o -name '*.msi' -o -name '*.zip' -o -name '*.7z' -o \
  -name '*.log' -o -name 'state.json' \
\) -not -path './vendor/*' -not -path './node_modules/*' -print)

if [[ -n "$forbidden_files" ]]; then
  echo "$forbidden_files" >&2
  fail "Repozytorium zawiera zabronione pliki."
fi

if rg -n --hidden \
  --glob '!vendor/**' --glob '!node_modules/**' --glob '!composer.lock' \
  --glob '!scripts/public-repo-audit.sh' \
  '(APP_KEY=base64:|DB_PASSWORD=\S+|MAIL_PASSWORD=\S+|BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|Authorization:\s*Bearer\s+[A-Za-z0-9._-]{20,}|device_token["'"'"']?\s*[:=]\s*["'"'"'][A-Za-z0-9._-]{20,})' .; then
  fail "Wykryto prawdopodobny sekret."
fi

if rg -n --hidden --glob '!docs/**' --glob '!README.md' --glob '!scripts/public-repo-audit.sh' \
  '/home/wcag1|pp10\.glogow\.pl|redaktor@|atrojanowski44@gmail\.com' .; then
  fail "Wykryto dane środowiska lub placówki."
fi

if [[ ! -f LICENSE ]] || ! grep -q 'GNU AFFERO GENERAL PUBLIC LICENSE' LICENSE; then
  fail "Brak pełnego pliku LICENSE AGPL."
fi

if [[ ! -f storage/app/agent-template/src/main.go ]]; then
  fail "Brak źródła agenta."
fi

if [[ ! -f storage/app/agent-installer/inno/PlacowkaOnlineSetup.iss ]]; then
  fail "Brak źródła instalatora."
fi

if [[ $failed -ne 0 ]]; then
  exit 1
fi

echo "Repozytorium przeszło kontrolę publikacyjną."
