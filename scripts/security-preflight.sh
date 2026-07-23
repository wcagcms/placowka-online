#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT"

echo "[1/5] Kontrola konfiguracji produkcyjnej"
php artisan placowka:security-check --strict

echo "[2/5] Kontrola składni PHP"
php scripts/php-syntax-check.php

echo "[3/5] Kontrola Composer"
composer validate --strict
composer audit --locked

echo "[4/5] Kontrola repozytorium publicznego"
bash scripts/public-repo-audit.sh

echo "[5/5] Kontrola frontend"
if [[ ! -f package-lock.json ]]; then
    echo "BŁĄD: Brak package-lock.json. Uruchom npm install --package-lock-only." >&2
    exit 1
fi
npm ci
npm audit --audit-level=high
npm run build

echo "Kontrola przedwdrożeniowa zakończona poprawnie."
