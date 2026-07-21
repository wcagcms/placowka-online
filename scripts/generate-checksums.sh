#!/usr/bin/env bash
set -Eeuo pipefail
[[ $# -gt 0 ]] || { echo "Użycie: $0 plik..." >&2; exit 1; }
sha256sum "$@" > SHA256SUMS.txt
echo "Zapisano SHA256SUMS.txt"
